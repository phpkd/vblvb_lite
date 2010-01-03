<?php
/*==================================================================================*\
|| ################################################################################ ||
|| # Product Name: PHPKD - vB Link Verifier Bot Lite             Version: 3.8.100 # ||
|| # License Type: Free License                                  $Revision$ # ||
|| # ---------------------------------------------------------------------------- # ||
|| # 																			  # ||
|| #            Copyright ©2005-2010 PHP KingDom. All Rights Reserved.            # ||
|| #     This product may be redistributed in whole or significant part under     # ||
|| #        "Creative Commons - Attribution-Noncommercial-Share Alike 3.0"        # ||
|| # 																			  # ||
|| # -------------- 'vB Link Verifier Bot Lite' IS FREE SOFTWARE ---------------- # ||
|| #        http://www.phpkd.net | http://info.phpkd.net/en/license/free          # ||
|| ################################################################################ ||
\*==================================================================================*/

//ssssf
/**
* Returns list of URLs from text
*
* @param	string	Message text
*
* @return	array
*/
function phpkd_vblvb_fetch_urls($messagetext)
{
	global $vbulletin;


	preg_match_all('#\[url=("|\'|)?(.*)\\1\](?:.*)\[/url\]|\[url\](.*)\[/url\]#siU', $messagetext, $matches);

	if (!empty($matches))
	{
		$matches = array_merge($matches[2], $matches[3]);
	}


	$hosts = array();
	if ($vbulletin->options['phpkd_vblvb_hosts_depositfiles_com'])
	{
		$hosts[] = array("depositfiles\.com\/([a-z]{2}\/)?files\/", "File Name", "@(com\/files\/)|(com\/[a-z]{2}\/files\/)@i", "com/en/files/");
	}

	if ($vbulletin->options['phpkd_vblvb_hosts_easy_share_com'])
	{
		$hosts[] = array("easy-share\.com" , "file url:");
	}

	if ($vbulletin->options['phpkd_vblvb_hosts_filefactory_com'])
	{
		$hosts[] = array("filefactory\.com\/file\/", "(download link)|(Please try again later)");
	}

	if ($vbulletin->options['phpkd_vblvb_hosts_mediafire_com'])
	{
		$hosts[] = array("mediafire\.com\/(download\.php)?\?", "You requested");
	}

	if ($vbulletin->options['phpkd_vblvb_hosts_megaupload_com'])
	{
		$hosts[] = array("megaupload\.com/([a-z]{2}\/)?\?d=", "(Filename:)|(All download slots assigned to your country)");
	}

	if ($vbulletin->options['phpkd_vblvb_hosts_netload_in'])
	{
		$hosts[] = array("netload\.in\/datei[0-9a-z]{32}\/", "download_load");
	}

	if ($vbulletin->options['phpkd_vblvb_hosts_rapidshare_com'])
	{
		$hosts[] = array("rapidshare\.com\/files\/", "(FILE DOWNLOAD|This file is larger than 200 Megabyte)");
	}

	if ($vbulletin->options['phpkd_vblvb_hosts_rapidshare_de'])
	{
		$hosts[] = array("rapidshare\.de\/files\/", "You want to download");
	}

	if ($vbulletin->options['phpkd_vblvb_hosts_sendspace_com'])
	{
		$hosts[] = array("sendspace\.com\/file\/", "The download link is located below.");
	}

	if ($vbulletin->options['phpkd_vblvb_hosts_zshare_net'])
	{
		$hosts[] = array("zshare\.net\/(download|audio|video)\/", "Last Download");
	}


	$counter = 0;
	$return = array();
	foreach($matches AS $url)
	{
		if ($vbulletin->options['phpkd_vblvb_maxlinks'] > 0 AND $counter >= $vbulletin->options['phpkd_vblvb_maxlinks'])
		{
			continue;
		}

		if (!empty($url))
		{
			// Process Available Hosts
			foreach($hosts AS $host)
			{
				if(preg_match("#$host[0]#i", $url))
				{
					$return[] = phpkd_vblvb_check(trim($url), $host[1], $host[2], $host[3]);
				}
			}

			$counter++;
		}
	}

	$log = '';
	$alive = $down = $dead = 0;
	foreach ($return AS $rtrn)
	{
		switch ($rtrn['status'])
		{
			case 'alive':
				$alive++;
				break;
			case 'down':
				$down++;
				break;
			case 'dead':
				$dead++;
				break;
		}

		$log .= $rtrn['log'];
	}

	return array('all' => $counter, 'checked' => $alive + $down + $dead, 'alive' => $alive, 'down' => $down, 'dead' => $dead, 'log' => $log);
}


/**
* Returns content of the remotely fetched page
*
* @param	string	URL to be remotely fetched
* @param	string	Posted fields (string as query string, or as array)
*
* @return	string	Page Content
*/
function phpkd_vblvb_curl($url, $post = '0')
{
	global $vbulletin;
	require_once(DIR . '/includes/class_vurl.php');

	$vurl = new vB_vURL($vbulletin);
	$vurl->set_option(VURL_URL, $url);
	$vurl->set_option(VURL_USERAGENT, 'vBulletin/' . FILE_VERSION);

	if($post != '0') 
	{
		$vurl->set_option(VURL_POST, 1);
		$vurl->set_option(VURL_POSTFIELDS, $post);
	}

	$vurl->set_option(VURL_RETURNTRANSFER, 1);
	$vurl->set_option(VURL_CLOSECONNECTION, 1);
	return $vurl->exec();
}


/**
* Returns list of URLs from text
*
* @param	string	Link to be checked
* @param	string	Regex formula to be evaluated
*
* @return	array	Checked link status & report
*/
function phpkd_vblvb_check($link, $regex, $pattern = '', $replace = '')
{
	global $vbphrase;

	if(!empty($pattern)) 
	{
		$link = preg_replace($pattern, $replace, $link);
	}

	$page = phpkd_vblvb_curl($link);
	$link = htmlentities($link, ENT_QUOTES);


	if(preg_match("#$regex#i", $page)) 
	{
		$status = 'alive';
		$log = '<li>' . $vbphrase['phpkd_vblvb_log_link_active'] . "<a href=\"$link\" target=\"_blank\">$link</a></li>";
	}
	else if(preg_match("#The file you are trying to access is temporarily unavailable.#i", $page)) 
	{
		$status = 'down';
		$log = '<li>' . $vbphrase['phpkd_vblvb_log_link_down'] . "<a href=\"$link\" target=\"_blank\">$link</a></li>";
	}
	else 
	{
		$status = 'dead';
		$log = '<li>' . $vbphrase['phpkd_vblvb_log_link_dead'] . "<a href=\"$link\" target=\"_blank\">$link</a></li>";
	}


	if (defined('IN_CONTROL_PANEL'))
	{
		echo $log;
		vbflush();
	}


	return array('status' => $status, 'log' => $log);
}


/**
* Staff Reports
*
* @param	string	Lik Verifier Bot Report
*
* @return	void
*/
function phpkd_vblvb_rprts($log)
{
	global $vbulletin, $vbphrase;

	$mods = array();
	if ($moderators = $vbulletin->db->query_read_slave("
		SELECT DISTINCT user.email, user.languageid, user.userid, user.username
		FROM " . TABLE_PREFIX . "moderator AS moderator
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
		WHERE moderator.permissions & " . ($vbulletin->bf_misc_moderatorpermissions['canbanusers']) . "
			AND moderator.forumid <> -1
	"))
	{
		while ($moderator = $vbulletin->db->fetch_array($moderators))
		{
			$mods["$moderator[userid]"] = $moderator;
		}
	}

	if (empty($mods) OR $vbulletin->options['phpkd_vblvb_rprts_messaging'] == 1)
	{
		$moderators = $vbulletin->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.username, user.userid
			FROM " . TABLE_PREFIX . "usergroup AS usergroup
			INNER JOIN " . TABLE_PREFIX . "user AS user ON
				(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
			WHERE usergroup.adminpermissions > 0
				AND (usergroup.adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ")
				" . (!empty($mods) ? "AND userid NOT IN (" . implode(',', array_keys($mods)) . ")" : "") . "
		");

		if ($moderators)
		{
			while ($moderator = $vbulletin->db->fetch_array($moderators))
			{
				$mods["$moderator[userid]"] = $moderator;
			}
		}
	}


	if ($vbulletin->options['phpkd_vblvb_reporter'] AND $rpuserinfo = fetch_userinfo($vbulletin->options['phpkd_vblvb_reporter']) AND is_array($mods) AND count($mods) > 0)
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$formatedlog = convert_wysiwyg_html_to_bbcode($log);

		$datenow = vbdate($vbulletin->options['dateformat'], TIMENOW);
		$timenow = vbdate($vbulletin->options['timeformat'], TIMENOW);


		// Staff Reports: Send Private Messages
		if ($vbulletin->options['phpkd_vblvb_rprts_pm'])
		{
			foreach ($mods AS $mod)
			{
				if (!empty($mod['username']))
				{
					cache_permissions($rpuserinfo, false);

					// create the DM to do error checking and insert the new PM
					$pmdm =& datamanager_init('PM', $vbulletin, ERRTYPE_SILENT);
					$pmdm->set_info('is_automated', true);
					$pmdm->set('fromuserid', $rpuserinfo['userid']);
					$pmdm->set('fromusername', $rpuserinfo['username']);
					$pmdm->set_info('receipt', false);
					$pmdm->set_info('savecopy', false);
					$pmdm->set('title', construct_phrase($vbphrase['phpkd_vblvb_rprts_title'], $datenow, $timenow));
					$pmdm->set('message', construct_phrase($vbphrase['phpkd_vblvb_rprts_message'], $formatedlog));
					$pmdm->set_recipients(unhtmlspecialchars($mod['username']), $rpuserinfo['permissions']);
					$pmdm->set('dateline', TIMENOW);
					$pmdm->set('allowsmilie', true);

					$pmdm->pre_save();
					if (empty($pmdm->errors))
					{
						$pmdm->save();
					}
					unset($pmdm);
				}
			}
		}


		// Staff Reports: Post New Thread
		if ($vbulletin->options['phpkd_vblvb_rprts_thread'])
		{
			if ($vbulletin->options['phpkd_vblvb_report_fid'] > 0 AND $rpforuminfo = fetch_foruminfo($vbulletin->options['phpkd_vblvb_report_fid']))
			{
				$threadman =& datamanager_init('Thread_FirstPost', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_info('forum', $rpforuminfo);
				$threadman->set_info('is_automated', true);
				$threadman->set_info('skip_moderator_email', true);
				$threadman->set_info('mark_thread_read', true);
				$threadman->set_info('parseurl', true);
				$threadman->set('allowsmilie', true);
				$threadman->set('userid', $rpuserinfo['userid']);
				$threadman->setr_info('user', $rpuserinfo);
				$threadman->set('title', construct_phrase($vbphrase['phpkd_vblvb_rprts_title'], $datenow, $timenow));
				$threadman->set('pagetext', construct_phrase($vbphrase['phpkd_vblvb_rprts_message'], $formatedlog));
				$threadman->set('forumid', $rpforuminfo['forumid']);
				$threadman->set('visible', 1);

				// not posting as the current user, IP won't make sense
				$threadman->set('ipaddress', '');

				if ($rpthreadid = $threadman->save())
				{
					$threadman->set_info('skip_moderator_email', false);
					$threadman->email_moderators(array('newthreademail', 'newpostemail'));

					// check the permission of the posting user
					$userperms = fetch_permissions($rpforuminfo['forumid'], $rpuserinfo['userid'], $rpuserinfo);
					if (($userperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($userperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) AND $rpuserinfo['autosubscribe'] != -1)
					{
						$vbulletin->db->query_write("
							INSERT IGNORE INTO " . TABLE_PREFIX . "subscribethread
								(userid, threadid, emailupdate, folderid, canview)
							VALUES
								(" . $rpuserinfo['userid'] . ", $rpthreadid, $rpuserinfo[autosubscribe], 0, 1)
						");
					}
				}

				unset($threadman);
			}
		}
	}
}


/**
* Notify punished posts' authors
*
* @param	string	Lik Verifier Bot Report
*
* @return	void
*/
function phpkd_vblvb_rprtu($punished)
{
	global $vbulletin, $vbphrase;

	if ($vbulletin->options['phpkd_vblvb_reporter'] AND $rpuserinfo = fetch_userinfo($vbulletin->options['phpkd_vblvb_reporter']))
	{
		$datenow = vbdate($vbulletin->options['dateformat'], TIMENOW);
		$timenow = vbdate($vbulletin->options['timeformat'], TIMENOW);

		// Staff Reports: Send Private Messages
		if ($vbulletin->options['phpkd_vblvb_rprtu_pm'])
		{
			foreach ($punished AS $userid => $user)
			{
				$formatedlog = '[LIST=1]';
				foreach ($user AS $postid => $post)
				{
					if (!$user['username'])
					{
						$user['username'] = $post['username'];
					}

					$formatedlog .= '[*][url=' . $vbulletin->options['bburl'] . '/showpost.php?p=' . $post['postid'] . ']' . ($post['title'] ? $post['title'] : $post['threadtitle']) . '[/url]';
				}
				$formatedlog .= '[/LIST]';

				if (!empty($user['username']))
				{
					cache_permissions($rpuserinfo, false);

					// create the DM to do error checking and insert the new PM
					$pmdm =& datamanager_init('PM', $vbulletin, ERRTYPE_SILENT);
					$pmdm->set_info('is_automated', true);
					$pmdm->set('fromuserid', $rpuserinfo['userid']);
					$pmdm->set('fromusername', $rpuserinfo['username']);
					$pmdm->set_info('receipt', false);
					$pmdm->set_info('savecopy', false);
					$pmdm->set('title', construct_phrase($vbphrase['phpkd_vblvb_rprtu_title'], $datenow, $timenow));
					$pmdm->set('message', construct_phrase($vbphrase['phpkd_vblvb_rprtu_message'], $user['username'], $formatedlog, $vbulletin->options['bburl'] . '/' . $vbulletin->options['contactuslink'], $vbulletin->options['bbtitle']));
					$pmdm->set_recipients(unhtmlspecialchars($user['username']), $rpuserinfo['permissions']);
					$pmdm->set('dateline', TIMENOW);
					$pmdm->set('allowsmilie', true);

					$pmdm->pre_save();
					if (empty($pmdm->errors))
					{
						$pmdm->save();
					}
					unset($pmdm);
				}
			}
		}
	}
}


/**
* Punish bad posts
*
* @param	array	To be punished posts
*
* @return	void
*/
function phpkd_vblvb_punish($punished)
{
	global $vbulletin;
	require_once(DIR . '/includes/functions_databuild.php');

	$puuserinfo = fetch_userinfo($vbulletin->options['phpkd_vblvb_reporter']);

	$phpkd_vblvb_punish_move = FALSE;
	if ($vbulletin->options['phpkd_vblvb_punish_fid'] > 0 AND $vbulletin->options['phpkd_vblvb_punish_move'])
	{
		$destforumid = verify_id('forum', $vbulletin->options['phpkd_vblvb_punish_fid']);
		$destforuminfo = fetch_foruminfo($destforumid);
		if ($destforuminfo['cancontainthreads'] AND !$destforuminfo['link'])
		{
			$phpkd_vblvb_punish_move = TRUE;
		}
	}

	foreach ($punished AS $userid => $user)
	{
		foreach ($user AS $postid => $post)
		{
			$puthreadinfo = fetch_threadinfo($post['threadid']);
			$puforuminfo = fetch_foruminfo($post['forumid']);


			if ($phpkd_vblvb_punish_move)
			{
				// check to see if this thread is being returned to a forum it's already been in
				// if a redirect exists already in the destination forum, remove it
				if ($checkprevious = $vbulletin->db->query_first_slave("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE forumid = $destforuminfo[forumid] AND open = 10"))
				{
					$old_redirect =& datamanager_init('Thread', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
					$old_redirect->set_existing($checkprevious);
					$old_redirect->delete(false, true, NULL, false);
					unset($old_redirect);
				}

				// check to see if this thread is being moved to the same forum it's already in but allow copying to the same forum
				if ($destforuminfo['forumid'] == $post['forumid'])
				{
					continue;
				}

				// update forumid/notes and unstick to prevent abuse
				$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
				$threadman->set_info('skip_moderator_log', true);
				$threadman->set_existing($puthreadinfo);
				$threadman->set('title', $puthreadinfo['title'], true, false);
				$threadman->set('forumid', $destforuminfo['forumid']);
				$threadman->save();
				unset($threadman);

				// kill the cache for the old thread
				delete_post_cache_threads(array($puthreadinfo['threadid']));

				// Update Post Count if we move from a counting forum to a non counting or vice-versa..
				// Source Dest  Visible Thread    Hidden Thread
				// Yes    Yes   ~           	  ~
				// Yes    No    -visible          ~
				// No     Yes   +visible          ~
				// No     No    ~                 ~
				if ($puthreadinfo['visible'] AND (($puforuminfo['countposts'] AND !$destforuminfo['countposts']) OR (!$puforuminfo['countposts'] AND $destforuminfo['countposts'])))
				{
					$posts = $vbulletin->db->query_read_slave("
						SELECT userid
						FROM " . TABLE_PREFIX . "post
						WHERE threadid = $puthreadinfo[threadid]
							AND	userid > 0
							AND visible = 1
					");
					$userbyuserid = array();
					while ($post = $vbulletin->db->fetch_array($posts))
					{
						if (!isset($userbyuserid["$post[userid]"]))
						{
							$userbyuserid["$post[userid]"] = 1;
						}
						else
						{
							$userbyuserid["$post[userid]"]++;
						}
					}

					if (!empty($userbyuserid))
					{
						$userbypostcount = array();
						foreach ($userbyuserid AS $postuserid => $postcount)
						{
							$alluserids .= ",$postuserid";
							$userbypostcount["$postcount"] .= ",$postuserid";
						}

						foreach ($userbypostcount AS $postcount => $userids)
						{
							$casesql .= " WHEN userid IN (0$userids) THEN $postcount";
						}

						$operator = ($destforuminfo['countposts'] ? '+' : '-');

						$vbulletin->db->query_write("
							UPDATE " . TABLE_PREFIX . "user
							SET posts = CAST(posts AS SIGNED) $operator
								CASE
									$casesql
									ELSE 0
								END
							WHERE userid IN (0$alluserids)
						");
					}

					unset($userbyuserid, $userbypostcount, $operator);
				}

				build_forum_counters($puthreadinfo['forumid']);
				if ($puthreadinfo['forumid'] != $destforuminfo['forumid'])
				{
					build_forum_counters($destforuminfo['forumid']);
				}

				// Update canview status of thread subscriptions
				update_subscriptions(array('threadids' => array($puthreadinfo['threadid'])));
			}


			unset($puthreadinfo, $puforuminfo);
		}
	}
}


/*============================================================================*\
|| ########################################################################### ||
|| # Version: 3.8.100
|| # $Revision$
|| # Released: $Date$
|| ########################################################################### ||
\*============================================================================*/
?>