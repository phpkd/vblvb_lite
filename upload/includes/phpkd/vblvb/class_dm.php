<?php
/*==================================================================================*\
|| ################################################################################ ||
|| # Product Name: vB Link Verifier Bot 'Lite'                   Version: 4.2.110 # ||
|| # License Type: Free License                                                   # ||
|| # ---------------------------------------------------------------------------- # ||
|| # 																			  # ||
|| #            Copyright ©2005-2012 PHP KingDom. All Rights Reserved.            # ||
|| #       This product may be redistributed in whole or significant part.        # ||
|| # 																			  # ||
|| # ------------- "vB Link Verifier Bot 'Lite'" IS A FREE SOFTWARE ------------- # ||
|| #        http://www.phpkd.net | http://info.phpkd.net/en/license/free/         # ||
|| ################################################################################ ||
\*==================================================================================*/


// No direct access! Should be accessed throuth the core class only!!
if (!defined('VB_AREA') || !defined('PHPKD_VBLVB') || @get_class($this) != 'PHPKD_VBLVB')
{
	echo 'Prohibited Access!';
	exit;
}


/**
 * Data Manager class
 *
 * @category	vB Link Verifier Bot 'Lite'
 * @package		PHPKD_VBLVB
 * @subpackage	PHPKD_VBLVB_DM
 * @copyright	Copyright ©2005-2012 PHP KingDom. All Rights Reserved. (http://www.phpkd.net)
 * @license		http://info.phpkd.net/en/license/free
 */
class PHPKD_VBLVB_DM
{
	/**
	* The PHPKD_VBLVB registry object
	*
	* @var	PHPKD_VBLVB
	*/
	private $_registry = null;

	/**
	 * Constructor - checks that PHPKD_VBLVB registry object including vBulletin registry oject has been passed correctly.
	 *
	 * @param	PHPKD_VBLVB	Instance of the main product's data registry object - expected to have both vBulletin data registry & database object as two of its members.
	 * @return	PHPKD_VBLVB
	 */
	public function __construct(&$registry)
	{
		if (is_object($registry))
		{
			$this->_registry =& $registry;

			if (is_object($registry->_vbulletin))
			{
				if (!is_object($registry->_vbulletin->db))
				{
					trigger_error('vBulletin Database object is not an object!', E_USER_ERROR);
				}
			}
			else
			{
				trigger_error('vBulletin Registry object is not an object!', E_USER_ERROR);
			}
		}
		else
		{
			trigger_error('PHPKD_VBLVB Registry object is not an object!', E_USER_ERROR);
		}

		return $this;
	}

	/**
	 * Extract links from text, pass it to be verified and return report
	 *
	 * @param	string	Message text
	 * @param	int		postid
	 * @return	array	Returns array of how many (all/checked/alive/dead/down) links
	 */
	public function fetch_urls($messagetext, $postid = 0)
	{
		$this->_registry->initialize(array('hosts' => array('whereactive' => 1)));

		$regex1 = '#(^|(?<=[^_a-z0-9-=\]"\'/@]|(?<=\[b|\[i|\[u|\[color|\[highlight|\[size|\[font|\[left|\[center|\[right|\[indent|\[align|\[\*||\[code|\[php|\[html|\[quote|\[hide|\[charge\[/b|\[/i|\[/u|\[/color|\[/highlight|\[/size|\[/font|\[/left|\[/center|\[/right|\[/indent|\[/align||\[/code|\[/php|\[/html|\[/quote|\[/hide|\[/charge)\]))((http|https|ftp)://|www\.)((\[(?!/)|[^\s[^$`"{}<>])+)(?!\[/url|\[/img)(?=[,.!\')]*(\)\s|\)$|[\s[]|$))#siU';
		preg_match_all($regex1, $messagetext, $matches1);

		if (is_array($matches1) && !empty($matches1))
		{
			unset($matches1[1], $matches1[2], $matches1[3], $matches1[4], $matches1[5], $matches1[6]);
		}

		$regex2 = '#\[url=("|\'|)?(.*)\\1\](?:.*)\[/url\]|\[url\](.*)\[/url\]#siU';
		preg_match_all($regex2, $messagetext, $matches2);

		if (is_array($matches2) && !empty($matches2))
		{
			unset($matches2[0], $matches2[1]);
		}


		if ((is_array($matches1) && !empty($matches1)) && (is_array($matches2) && !empty($matches2)))
		{
			$matches = array_merge($matches1, $matches2);
		}
		else if ((is_array($matches1) && !empty($matches1)) && (!is_array($matches2) || empty($matches2)))
		{
			$matches = $matches1;
		}
		else if ((!is_array($matches1) || empty($matches1)) && (is_array($matches2) && !empty($matches2)))
		{
			$matches = $matches2;
		}
		else
		{
			$this->_registry->seterror('phpkd_vblvb_invalid_criteria', ERRTYPE_ECHO, $postid);
			return array('all' => 0, 'checked' => 0, 'alive' => 0, 'dead' => 0, 'down' => 0);
		}


		if (is_array($matches) && !empty($matches))
		{
			$actualurls = array();

			foreach ($matches as $matchvalue)
			{
				foreach ($matchvalue as $singleurl)
				{
					if (!empty($singleurl))
					{
						$actualurls[] = trim($singleurl);
					}
				}
			}
		}
		else
		{
			$this->_registry->seterror('phpkd_vblvb_invalid_criteria', ERRTYPE_ECHO, $postid);
			return array('all' => 0, 'checked' => 0, 'alive' => 0, 'dead' => 0, 'down' => 0);
		}


		if (is_array($actualurls) && !empty($actualurls))
		{
			$checked = 0;
			$counter = 0;
			$urlsreturn = array();

			foreach(array_unique($actualurls) as $url)
			{
				if ($this->_registry->_vbulletin->options['phpkd_vblvb_general_maxlinks'] > 0 && $checked >= $this->_registry->_vbulletin->options['phpkd_vblvb_general_maxlinks'])
				{
					break;
				}

				// Match URLs with active hosts
				foreach($this->_registry->hosts as $host)
				{
					if ($host['active'] && !empty($host['urlmatch']) && preg_match("#$host[urlmatch]#i", $url, $hostmatch))
					{
						if ($checked == 0)
						{
							$this->_registry->logstring('<ol>', true, $postid);
						}

						$urlsreturn[] = ($this->_registry->_vbulletin->options['phpkd_vblvb_tagging_host'] ? array('host' => $host['domain'], 'url' => $url, 'lastcheck' => TIMENOW, 'hash' => md5($url), 'status' => $this->check($url, $host['status'], $host['contentmatch'], $host['downmatch'], $host['urlsearch'], $host['urlreplace'], $userid, $postid)) : array('status' => $this->check($url, $host['status'], $host['contentmatch'], $host['downmatch'], $host['urlsearch'], $host['urlreplace'], $userid, $postid)));

						$checked++;
					}
				}

				$counter++;
			}

			if ($checked > 0)
			{
				$this->_registry->logstring('</ol>', true, $postid);
			}

			$alive = 0;
			$dead = 0;
			$down = 0;

			foreach ($urlsreturn as $urlreturn)
			{
				switch ($urlreturn['status'])
				{
					case 'alive':
						$alive++;
						break;

					case 'dead':
						$dead++;
						break;

					case 'down':
					default:
						$down++;
						break;
				}
			}

			if ($checked == 0)
			{
				$this->_registry->seterror('phpkd_vblvb_invalid_criteria', ERRTYPE_ECHO, $postid);
			}

			return array('all' => $counter, 'checked' => $checked, 'alive' => $alive, 'dead' => $dead, 'down' => $down, 'urlrecords' => ($this->_registry->_vbulletin->options['phpkd_vblvb_tagging_host'] ? $urlsreturn : false));
		}
		else
		{
			$this->_registry->seterror('phpkd_vblvb_invalid_criteria', ERRTYPE_ECHO, $postid);
			return array('all' => 0, 'checked' => 0, 'alive' => 0, 'dead' => 0, 'down' => 0);
		}
	}

	/**
	 * Verify if the supplied link is (alive/dead/down) & return it's status
	 *
	 * @param	string	Link to be checked
	 * @param	string	Per Link Regex formula to be evaluated
	 * @param	string	Regex search patern to be applied on the supplied link -if required-
	 * @param	string	Regex replace patern to be applied on the supplied link -if required-
	 * @param	int		userid
	 * @param	int		postid
	 * @return	array	Checked link status & report
	 */
	public function check($url, $hoststatus, $contentmatch, $downmatch, $urlsearch, $urlreplace, $userid = 0, $postid = 0)
	{
		// Just keep the original URL as it is for the logging purposes. See ( http://forum.phpkd.net/project.php?issueid=65 )
		$oriurl = $url;

		if ($hoststatus == 'alive')
		{
			if (!empty($urlsearch) && preg_match("#$urlsearch#i", $url))
			{
				$url = preg_replace("#$urlsearch#i", $urlreplace, $url);
			}

			$page = $this->vurl($url);

			if (isset($page['headers']['http-response']['statuscode']) AND $page['headers']['http-response']['statuscode'] == 200)
			{
				if (!empty($contentmatch) && preg_match("#$contentmatch#i", $page['body']))
				{
					$status = 'alive';
					$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_alive'], '#008000', $oriurl);
				}
				else if (!empty($downmatch) && preg_match("#$downmatch#i", $page['body']))
				{
					$status = 'down';
					$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_down'], '#FFA500', $oriurl);
				}
				else if (preg_match("#http-equiv=[\'|\"]refresh[\'|\"].*content=[\'|\"][0-9]+;url=[\'|\"]?([0-9a-z_=:/.\?%]+)[\'|\"]?[\'|\"].* />#i", $page['body'], $matches))
				{
					$page = $this->vurl($matches[1]);

					if (isset($page['headers']['http-response']['statuscode']) AND $page['headers']['http-response']['statuscode'] == 200)
					{
						if (!empty($contentmatch) && preg_match("#$contentmatch#i", $page['body']))
						{
							$status = 'alive';
							$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_alive'], '#008000', $oriurl);
						}
						else if (!empty($downmatch) && preg_match("#$downmatch#i", $page['body']))
						{
							$status = 'down';
							$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_down'], '#FFA500', $oriurl);
						}
						else
						{
							$status = 'dead';
							$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_dead'], '#FF0000', $oriurl);
						}
					}
					else
					{
						$status = 'dead';
						$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_dead'], '#FF0000', $oriurl);
					}
				}
				else
				{
					$status = 'dead';
					$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_dead'], '#FF0000', $oriurl);
				}
			}
			else
			{
				$status = 'dead';
				$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_dead'], '#FF0000', $oriurl);
			}
		}
		else if ($hoststatus == 'dead')
		{
			$status = 'dead';
			$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_dead'], '#FF0000', $oriurl);
		}
		else
		{
			$status = 'down';
			$log = construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_link_down'], '#FFA500', $oriurl);
		}


		$this->_registry->logstring($log, true, $postid);

		return $status;
	}

	/**
	 * Returns content of the remotely fetched page
	 *
	 * @param	string	URL to be remotely fetched
	 * @param	string	Posted fields (string as query string, or as array)
	 * @return	string	Page Content
	 */
	public function vurl($url, $post = null)
	{
		require_once(DIR . '/includes/class_vurl.php');

		$vurl = new vB_vURL($this->_registry->_vbulletin);
		$vurl->set_option(VURL_URL, $url);
		$vurl->set_option(VURL_USERAGENT, 'vBulletin/' . FILE_VERSION);
		$vurl->set_option(VURL_FOLLOWLOCATION, 1);
		$vurl->set_option(VURL_MAXREDIRS, $this->_registry->_vbulletin->options['phpkd_vblvb_general_vurl_maxredirs']);
		$vurl->set_option(VURL_TIMEOUT, $this->_registry->_vbulletin->options['phpkd_vblvb_general_vurl_timeout']);
		$vurl->set_option(VURL_MAXSIZE, $this->_registry->_vbulletin->options['phpkd_vblvb_general_vurl_maxsize']);
		$vurl->set_option(VURL_DIEONMAXSIZE, false);

		if ($post !== null)
		{
			$vurl->set_option(VURL_POST, 1);
			$vurl->set_option(VURL_POSTFIELDS, $post);
		}

		$vurl->set_option(VURL_HEADER, 1);
		$vurl->set_option(VURL_RETURNTRANSFER, 1);
		$vurl->set_option(VURL_CLOSECONNECTION, 1);

		return $vurl->exec();
	}

	/**
	 * Staff Reports
	 *
	 * @param	string	Concatenated string of all punished posts
	 * @param	array	Array of checked/dead/punished post counts
	 * @return	boolean	True on success
	 */
	public function staff_reports($punished_links, $records)
	{
		$this->_registry->initialize(array('staff_reports' => array()));

		if (!empty($this->_registry->staff_reports) && $this->_registry->_vbulletin->options['phpkd_vblvb_reporting_reporter'] && ($reporter = fetch_userinfo($this->_registry->_vbulletin->options['phpkd_vblvb_reporting_reporter'])) && ($mods = $this->fetch_staff()) && ($postlogs = $this->_registry->getPostlog()))
		{
			if (intval(SIMPLE_VERSION) >= '414')
			{
				require_once(DIR . '/includes/class_wysiwygparser.php');
				$html_parser = new vB_WysiwygHtmlParser($this->_registry->_vbulletin);
			}
			else
			{
				require_once(DIR . '/includes/functions_wysiwyg.php');
			}

			$logstring = '';

			$posts = array();
			$logstring .= $this->_registry->_vbphrase['phpkd_vblvb_log_checked_posts'] . '<ol class="smallfont">';

			foreach ($postlogs as $postitemid => $postitem)
			{
				$posts[$postitem['forumid']]['forumtitle'] = $postitem['forumtitle'];
				$posts[$postitem['forumid']][$postitem['threadid']]['threadtitle'] = $postitem['threadtitle'];
				$posts[$postitem['forumid']][$postitem['threadid']][$postitem['postid']] = $postitem;
			}

			foreach ($posts AS $forumid => $forumposts)
			{
				$logstring .= '<li>' . construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_forum'], $this->_registry->_vbulletin->options['bburl'] . '/forumdisplay.php?f=' . $forumid, $forumposts['forumtitle']) . '<ol class="smallfont">';
				unset($forumposts['forumtitle']);

				foreach ($forumposts AS $threadid => $threadposts)
				{
					$logstring .= '<li>' . construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_thread'], $this->_registry->_vbulletin->options['bburl'] . '/showthread.php?t=' . $threadid, $threadposts['threadtitle']) . '<ol class="smallfont">';
					unset($threadposts['threadtitle']);

					foreach ($threadposts AS $postid => $post)
					{
						$logstring .= $post['logrecord'];
					}

					$logstring .= '</ol></li><br />';
				}

				$logstring .= '</ol></li>';
			}

			$logstring .= construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_log_summery_all'], '#008000', '#FF0000', '#FFA500', $records['checked'], ($records['checked'] - $records['dead']), $records['dead'], $records['punished']) . '</ol><br />';

			if (!empty($punished_links))
			{
				$logstring .= $this->_registry->_vbphrase['phpkd_vblvb_log_punished_posts'] . '<ol class="smallfont">' . $punished_links . '</ol><br />';
			}


			cache_permissions($reporter, false);

			if (intval(SIMPLE_VERSION) >= '414')
			{
				$formatedlog = $html_parser->parse_wysiwyg_html_to_bbcode($logstring, false, true);
			}
			else
			{
				$formatedlog = convert_wysiwyg_html_to_bbcode($logstring, false, true);
			}

			$datenow = vbdate($this->_registry->_vbulletin->options['dateformat'], TIMENOW);
			$timenow = vbdate($this->_registry->_vbulletin->options['timeformat'], TIMENOW);


			foreach ($this->_registry->staff_reports as $staff_report)
			{
				switch ($staff_report)
				{
					// Staff Reports: Private Messages
					case 'pm':
						if (is_array($mods) && !empty($mods))
						{
							foreach ($mods as $mod)
							{
								if (!empty($mod['username']))
								{
									$email_langid = ($mod['languageid'] > 0 ? $mod['languageid'] : $this->_registry->_vbulletin->options['languageid']);
									eval(fetch_email_phrases('phpkd_vblvb_staff_reports', $email_langid));

									// create the DM to do error checking and insert the new PM
									$pmdm =& datamanager_init('PM', $this->_registry->_vbulletin, ERRTYPE_SILENT);
									$pmdm->set_info('is_automated', true);
									$pmdm->set('fromuserid', $reporter['userid']);
									$pmdm->set('fromusername', $reporter['username']);
									$pmdm->set_info('receipt', false);
									$pmdm->set_info('savecopy', false);
									$pmdm->set('title', $subject);
									$pmdm->set('message', $message);
									$pmdm->set_recipients(unhtmlspecialchars($mod['username']), $reporter['permissions']);
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
						break;

					// Staff Reports: New Thread
					case 'thread':
						if ($this->_registry->_vbulletin->options['phpkd_vblvb_reporting_forumid'] > 0 && ($reportforum = fetch_foruminfo($this->_registry->_vbulletin->options['phpkd_vblvb_reporting_forumid'])))
						{
							// Start: Required for 'mark_thread_read', fix the following bug: http://forum.phpkd.net/project.php?issueid=76
							if (!$db)
							{
								global $db;
								$db = $this->_registry->_vbulletin->db;
							}
							// End: Required for 'mark_thread_read', fix the following bug: http://forum.phpkd.net/project.php?issueid=76

							eval(fetch_email_phrases('phpkd_vblvb_staff_reports', $this->_registry->_vbulletin->options['languageid']));

							$threadman =& datamanager_init('Thread_FirstPost', $this->_registry->_vbulletin, ERRTYPE_SILENT, 'threadpost');
							$threadman->set_info('forum', $reportforum);
							$threadman->set_info('is_automated', true);
							$threadman->set_info('skip_moderator_email', true);
							$threadman->set_info('mark_thread_read', true);
							$threadman->set_info('parseurl', true);
							$threadman->set('allowsmilie', true);
							$threadman->set('userid', $reporter['userid']);
							$threadman->setr_info('user', $reporter);
							$threadman->set('title', $subject);
							$threadman->set('pagetext', $message);
							$threadman->set('forumid', $reportforum['forumid']);
							$threadman->set('visible', 1);

							// not posting as the current user, IP won't make sense
							$threadman->set('ipaddress', '');

							if ($rpthreadid = $threadman->save())
							{
								$threadman->set_info('skip_moderator_email', false);
								$threadman->email_moderators(array('newthreademail', 'newpostemail'));

								// check the permission of the posting user
								$userperms = fetch_permissions($reportforum['forumid'], $reporter['userid'], $reporter);

								if (($userperms & $this->_registry->_vbulletin->bf_ugp_forumpermissions['canview']) && ($userperms & $this->_registry->_vbulletin->bf_ugp_forumpermissions['canviewthreads']) && $reporter['autosubscribe'] != -1)
								{
									$this->_registry->_vbulletin->db->query_write("
										INSERT IGNORE INTO " . TABLE_PREFIX . "subscribethread
											(userid, threadid, emailupdate, folderid, canview)
										VALUES
											(" . $reporter['userid'] . ", $rpthreadid, $reporter[autosubscribe], 0, 1)
									");
								}
							}

							unset($threadman);
						}
						break;
				}
			}

			// It's OK! Return true for success
			return true;
		}
	}

	/**
	 * User Reports
	 *
	 * @param	array	Array of posts to send user reports for their authors!
	 * @return	boolean	True on success
	 */
	public function user_reports($postids)
	{
		$this->_registry->initialize(array('user_reports' => array()));

		if (!empty($this->_registry->user_reports) && $this->_registry->_vbulletin->options['phpkd_vblvb_reporting_reporter'] && ($reporter = fetch_userinfo($this->_registry->_vbulletin->options['phpkd_vblvb_reporting_reporter'])))
		{
			require_once(DIR . '/includes/class_bbcode_alt.php');

			if (intval(SIMPLE_VERSION) >= '414')
			{
				require_once(DIR . '/includes/class_wysiwygparser.php');
				$html_parser = new vB_WysiwygHtmlParser($this->_registry->_vbulletin);
			}
			else
			{
				require_once(DIR . '/includes/functions_wysiwyg.php');
			}

			cache_permissions($reporter, false);
			$datenow = vbdate($this->_registry->_vbulletin->options['dateformat'], TIMENOW);
			$timenow = vbdate($this->_registry->_vbulletin->options['timeformat'], TIMENOW);
			$plaintext_parser = new vB_BbCodeParser_PlainText($this->_registry->_vbulletin, fetch_tag_list());
			$contactuslink = $this->_registry->_vbulletin->options['bburl'] . '/' . $this->_registry->_vbulletin->options['contactuslink'];
			$bbtitle = $this->_registry->_vbulletin->options['bbtitle'];


			foreach ($postids as $postid)
			{
				$postlog = $this->_registry->getPostlog($postid);

				if (intval(SIMPLE_VERSION) >= '414')
				{
					$formatedlog = $html_parser->parse_wysiwyg_html_to_bbcode($postlog['logrecord'], false, true);
				}
				else
				{
					$formatedlog = convert_wysiwyg_html_to_bbcode($postlog['logrecord'], false, true);
				}

				foreach ($this->_registry->user_reports as $user_report)
				{
					switch ($user_report)
					{
						// User Reports: Private Messages
						case 'pm':
							$email_langid = ($postlog['languageid'] > 0 ? $postlog['languageid'] : $this->_registry->_vbulletin->options['languageid']);
							eval(fetch_email_phrases('phpkd_vblvb_user_reports', $email_langid));

							// create the DM to do error checking and insert the new PM
							$pmdm =& datamanager_init('PM', $this->_registry->_vbulletin, ERRTYPE_SILENT);
							$pmdm->set_info('is_automated', true);
							$pmdm->set('fromuserid', $reporter['userid']);
							$pmdm->set('fromusername', $reporter['username']);
							$pmdm->set_info('receipt', false);
							$pmdm->set_info('savecopy', false);
							$pmdm->set('title', $subject);
							$pmdm->set('message', $message);
							$pmdm->set_recipients(unhtmlspecialchars($postlog['username']), $reporter['permissions']);
							$pmdm->set('dateline', TIMENOW);
							$pmdm->set('allowsmilie', true);

							$pmdm->pre_save();
							if (empty($pmdm->errors))
							{
								$pmdm->save();
							}
							unset($pmdm);
							break;
					}
				}
			}

			unset($plaintext_parser);

			// It's OK! Return true for success
			return true;
		}
	}

	/**
	 * Punish dead posts/threads
	 *
	 * @param	array	Posts/Threads to be punished
	 * @return	void
	 */
	public function punish($punished_content)
	{
		$logpunish = array();
		$this->_registry->initialize(array('thread_punishs' => array()));

		require_once(DIR . '/includes/functions_log_error.php');
		require_once(DIR . '/includes/functions_databuild.php');


		// Punish whole threads
		if (!empty($this->_registry->thread_punishs) && !empty($punished_content['threads']))
		{
			$countingthreads = array();
			$modrecords = array();
			$reporter = fetch_userinfo($this->_registry->_vbulletin->options['phpkd_vblvb_reporting_reporter']);

			foreach ($punished_content['threads'] AS $threadid => $thread)
			{
				foreach ($this->_registry->thread_punishs as $punishment)
				{
					switch ($punishment)
					{
						case 'moderate':
							if ($thread['visible'] == 1)
							{
								$logpunish['threads'][$threadid]['moderate'] = TIMENOW;
							}
							break;

						case 'delete':
							if ($thread['visible'])
							{
								$logpunish['threads'][$threadid]['delete'] = TIMENOW;
							}
							break;
					}
				}

				if ($reporter['userid'])
				{
					$modlog[] = array(
						'userid'   => $reporter['userid'],
						'forumid'  => $punished_content['threads']["$threadid"]['forumid'],
						'threadid' => $threadid,
					);
				}
			}

			if ($reporter['userid'])
			{
				$delinfo = array(
					'userid'          => $reporter['userid'],
					'username'        => $reporter['username'],
					'reason'          => $this->_registry->_vbphrase['phpkd_vblvb_punish_reason'],
					'keepattachments' => 1
				);
			}

			foreach ($this->_registry->thread_punishs as $punishment)
			{
				switch ($punishment)
				{
					case 'moderate':
						// Set threads hidden
						$this->_registry->_vbulletin->db->query_write("
							UPDATE " . TABLE_PREFIX . "thread
							SET visible = 0
							WHERE threadid IN(" . implode(',', array_keys($punished_content['threads'])) . ")
						");

						// Set thread redirects hidden
						$this->_registry->_vbulletin->db->query_write("
							UPDATE " . TABLE_PREFIX . "thread
							SET visible = 0
							WHERE open = 10 && pollid IN(" . implode(',', array_keys($punished_content['threads'])) . ")
						");

						foreach ($punished_content['threads'] as $threadid => $thread)
						{
							// this thread is visible AND in a counting forum
							if ($thread['visible'] && $thread['replycount'])
							{
								$countingthreads[] = $threadid;
							}

							$modrecords[] = "($threadid, 'thread', " . TIMENOW . ")";
						}

						if (!empty($countingthreads))
						{
							// Update post count for visible posts
							$userbyuserid = array();
							$posts = $this->_registry->_vbulletin->db->query_read("
								SELECT userid
								FROM " . TABLE_PREFIX . "post
								WHERE threadid IN(" . implode(',', $countingthreads) . ")
									AND visible = 1
									AND userid > 0
							");
							while ($post = $this->_registry->_vbulletin->db->fetch_array($posts))
							{
								if (!isset($userbyuserid["$post[userid]"]))
								{
									$userbyuserid["$post[userid]"] = -1;
								}
								else
								{
									$userbyuserid["$post[userid]"]--;
								}
							}

							if (!empty($userbyuserid))
							{
								$userbypostcount = array();
								$alluserids = '';

								foreach ($userbyuserid AS $postuserid => $postcount)
								{
									$alluserids .= ",$postuserid";
									$userbypostcount["$postcount"] .= ",$postuserid";
								}

								foreach($userbypostcount AS $postcount => $userids)
								{
									$casesql .= " WHEN userid IN (0$userids) THEN $postcount\n";
								}

								$this->_registry->_vbulletin->db->query_write("
									UPDATE " . TABLE_PREFIX . "user
									SET posts = CAST(posts AS SIGNED) +
									CASE
										$casesql
										ELSE 0
									END
									WHERE userid IN (0$alluserids)
								");
							}
						}

						if (!empty($modrecords))
						{
							// Insert Moderation Records
							$this->_registry->_vbulletin->db->query_write("
								REPLACE INTO " . TABLE_PREFIX . "moderation
								(primaryid, type, dateline)
								VALUES
								" . implode(',', $modrecords) . "
							");
						}

						// Clean out deletionlog
						$this->_registry->_vbulletin->db->query_write("
							DELETE FROM " . TABLE_PREFIX . "deletionlog
							WHERE primaryid IN(" . implode(',', array_keys($punished_content['threads'])) . ")
								AND type = 'thread'
						");

						foreach ($punished_content['forums'] as $forumid)
						{
							build_forum_counters($forumid);
						}

						if (!empty($modlog))
						{
							log_moderator_action($modlog, 'unapproved_thread');
						}
						break;

					case 'delete':
						foreach ($punished_content['threads'] as $threadid => $thread)
						{
							$replycount = $this->_registry->_vbulletin->forumcache["$thread[forumid]"]['options'] & $this->_registry->_vbulletin->bf_misc_forumoptions['countposts'];

							if ($thread['visible'] == 2)
							{
								// Thread is already soft deleted
								continue;
							}

							$threadman =& datamanager_init('Thread', $this->_registry->_vbulletin, ERRTYPE_SILENT, 'threadpost');
							$threadman->set_existing($thread);

							// Redirect
							if ($thread['open'] == 10)
							{
								$threadman->delete(false, true, ($delinfo ? $delinfo : null), ($delinfo ? true : false));
							}
							else
							{
								$threadman->delete($replycount, false, ($delinfo ? $delinfo : null), ($delinfo ? true : false));
							}

							unset($threadman);
						}

						foreach ($punished_content['forums'] as $forumid)
						{
							build_forum_counters($forumid);
						}
						break;
				}
			}
		}


		if (!empty($logpunish))
		{
			$update_lastpunish_thread = '';
			$update_lastpunish_post = '';

			foreach ($logpunish as $typeid => $type)
			{
				switch ($typeid)
				{
					case 'threads':
						foreach ($type as $threadid => $thread)
						{
							$update_lastpunish_thread .= ' WHEN ' . $threadid . ' THEN \'' . serialize($thread) . '\'';
						}
						break;
				}
			}
		}


		// Record punishment actions in details (for future use when editing)
		if (!empty($logpunish['threads']))
		{
			$this->_registry->_vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "thread SET
				phpkd_vblvb_lastpunish = CASE threadid
				$update_lastpunish_thread ELSE phpkd_vblvb_lastpunish END
				WHERE threadid IN(" . implode(',', array_keys($punished_content['threads'])) . ")
			");
		}
	}

	/**
	 * Get Staff Members
	 *
	 * @return	array	Staff members to be notified
	 */
	public function fetch_staff()
	{
		$mods = array();

		if ($moderators = $this->_registry->_vbulletin->db->query_read("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "moderator AS moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			WHERE moderator.permissions & " . ($this->_registry->_vbulletin->bf_misc_moderatorpermissions['canbanusers']) . "
				AND moderator.forumid <> -1
		"))
		{
			while ($moderator = $this->_registry->_vbulletin->db->fetch_array($moderators))
			{
				$mods["$moderator[userid]"] = $moderator;
			}
		}

		if (empty($mods) || $this->_registry->_vbulletin->options['phpkd_vblvb_reporting_staff_reports_messaging'] == 1)
		{
			$moderators = $this->_registry->_vbulletin->db->query_read("
				SELECT DISTINCT user.email, user.languageid, user.username, user.userid
				FROM " . TABLE_PREFIX . "usergroup AS usergroup
				INNER JOIN " . TABLE_PREFIX . "user AS user ON
					(user.usergroupid = usergroup.usergroupid || FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
				WHERE usergroup.adminpermissions > 0
					AND (usergroup.adminpermissions & " . $this->_registry->_vbulletin->bf_ugp_adminpermissions['ismoderator'] . ")
					" . (!empty($mods) ? "AND userid NOT IN (" . implode(',', array_keys($mods)) . ")" : "") . "
			");

			if ($moderators)
			{
				while ($moderator = $this->_registry->_vbulletin->db->fetch_array($moderators))
				{
					$mods["$moderator[userid]"] = $moderator;
				}
			}
		}

		return $mods;
	}
}
