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


// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

// Bypass PHP INI memory limit!
if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < (128 * 1024 * 1024) && $current_memory_limit > 0)
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}

@set_time_limit(0);

if (!defined('IN_CONTROL_PANEL'))
{
	global $vbphrase;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################
require_once(DIR . '/includes/phpkd/vblvb/class_core.php');
require_once(DIR . '/includes/class_taggablecontent.php');

$phpkd_vblvb = new PHPKD_VBLVB($vbulletin, $vbphrase, defined('IN_CONTROL_PANEL') ? ERRTYPE_CP : ERRTYPE_SILENT);

if ($vbulletin->options['phpkd_vblvb_general_active'])
{
	if (!$vbulletin->options['phpkd_vblvb_general_checked_existingposts'])
	{
		$phpkd_vblvb->seterror('phpkd_vblvb_checked_existing');
	}

	$inex_forums = '';
	$cutoff = '';

	// Auto exclude report forums/threads & recycle bin forum from being checked: http://forum.phpkd.net/project.php?issueid=71
	$forced_inex_forums = ($vbulletin->options['phpkd_vblvb_reporting_forumid'] ? $vbulletin->options['phpkd_vblvb_reporting_forumid'] . ',' : '') . '0';


	switch ($vbulletin->options['phpkd_vblvb_general_inex_forums'])
	{
		case 1:
			$forums = @implode(',', unserialize($vbulletin->options['phpkd_vblvb_general_inex_forums_ids']));
			$inex_forums = (!empty($forums) ? 'AND thread.forumid IN (' . $vbulletin->db->escape_string($forums) . ')' : '');
			break;

		case 2:
			$forums = @implode(',', unserialize($vbulletin->options['phpkd_vblvb_general_inex_forums_ids']));
			$inex_forums = (!empty($forums) ? 'AND thread.forumid NOT IN (' . $vbulletin->db->escape_string($forums) . ')' : '');
			break;
	}

	switch ($vbulletin->options['phpkd_vblvb_general_cutoff_mode'])
	{
		case 0:
			$cutoff = (!empty($vbulletin->options['phpkd_vblvb_general_cutoff_value']) ? 'AND post.dateline > ' . (TIMENOW - (intval($vbulletin->options['phpkd_vblvb_general_cutoff_value']) * 86400)) : '');
			break;

		case 1:
			$cutoff = (!empty($vbulletin->options['phpkd_vblvb_general_cutoff_value']) ? 'AND post.dateline < UNIX_TIMESTAMP(\'' . $vbulletin->db->escape_string($vbulletin->options['phpkd_vblvb_general_cutoff_value']) . '\')' : '');
			break;

		case 2:
			$cutoff = (!empty($vbulletin->options['phpkd_vblvb_general_cutoff_value']) ? 'AND post.dateline > UNIX_TIMESTAMP(\'' . $vbulletin->db->escape_string($vbulletin->options['phpkd_vblvb_general_cutoff_value']) . '\')' : '');
			break;

		case 3:
			$cutoff_value = @explode('|', $vbulletin->options['phpkd_vblvb_general_cutoff_value']);
			$cutoff = ((!empty($vbulletin->options['phpkd_vblvb_general_cutoff_value']) && count($cutoff_value) == 2) ? 'AND post.dateline > UNIX_TIMESTAMP(\'' . $vbulletin->db->escape_string($cutoff_value[0]) . '\') && post.dateline < UNIX_TIMESTAMP(\'' . $vbulletin->db->escape_string($cutoff_value[1]) . '\')' : '');
			break;
	}

	$checked_posts = (($vbulletin->options['phpkd_vblvb_general_checked_existingposts'] == 2) ? 'AND post.postid = thread.firstpostid' : '');

	$succession = 'AND post.phpkd_vblvb_lastcheck ' . (($vbulletin->options['phpkd_vblvb_general_succession_period'] > 0) ? '< ' . (TIMENOW - ($vbulletin->options['phpkd_vblvb_general_succession_period'] * 86400)) : '= 0');

	$limit = 'LIMIT ' . (($vbulletin->options['phpkd_vblvb_general_query_limit'] > 0) ? $vbulletin->options['phpkd_vblvb_general_query_limit'] : 50);


	// Main query
	$post_query = $vbulletin->db->query_read("
		SELECT user.username, user.email, user.languageid, post.userid, post.postid, post.threadid, post.title AS posttitle, post.pagetext, post.visible AS pvisible, thread.forumid, forum.title AS forumtitle, forum.replycount, forum.replycount AS countposts, thread.title AS threadtitle, thread.open, thread.sticky, thread.firstpostid, thread.visible, thread.pollid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON (thread.forumid = forum.forumid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (post.userid = user.userid)
		WHERE 1 = 1
			" . (!empty($forced_inex_forums) ? "AND thread.forumid NOT IN (" . $forced_inex_forums . ")" : "") . "
			$inex_forums
			$cutoff
			$checked_posts
			$succession
		$limit
	");


	if ($vbulletin->db->num_rows($post_query))
	{
		$punished_links = '';
		$records = array('checked' => 0, 'dead' => 0, 'punished' => 0);
		$posts = array();
		$punished_content = array();
		$checkedposts = array();
		$taggedthreads = array();
		$deadposts = array();
		$phpkd_vblvb->logstring($vbphrase['phpkd_vblvb_log_checked_posts'] . '<ol class="smallfont">', true);

		while ($postitem = $vbulletin->db->fetch_array($post_query))
		{
			$checkedposts[] = $postitem['postid'];
			$posts[$postitem['forumid']]['forumtitle'] = $postitem['forumtitle'];
			$posts[$postitem['forumid']][$postitem['threadid']]['threadtitle'] = $postitem['threadtitle'];
			$posts[$postitem['forumid']][$postitem['threadid']][$postitem['postid']] = $postitem;
		}

		foreach ($posts AS $forumid => $forumposts)
		{
			$phpkd_vblvb->logstring('<li>' . construct_phrase($vbphrase['phpkd_vblvb_log_forum'], $vbulletin->options['bburl'] . '/forumdisplay.php?f=' . $forumid, $forumposts['forumtitle']) . '<ol class="smallfont">', true);
			unset($forumposts['forumtitle']);

			foreach ($forumposts AS $threadid => $threadposts)
			{
				// Tag Threads - Reported Status
				if ($vbulletin->options['phpkd_vblvb_tagging_status'])
				{
					$taggedthreads[$threadid][0] = $vbphrase['phpkd_vblvb_tagging_status_alive'];
				}

				$phpkd_vblvb->logstring('<li>' . construct_phrase($vbphrase['phpkd_vblvb_log_thread'], $vbulletin->options['bburl'] . '/showthread.php?t=' . $threadid, $threadposts['threadtitle']) . '<ol class="smallfont">', true);
				unset($threadposts['threadtitle']);

				foreach ($threadposts AS $postid => $post)
				{
					$phpkd_vblvb->logstring('<li>' . construct_phrase($vbphrase['phpkd_vblvb_log_post'], $vbulletin->options['bburl'] . '/showthread.php?t=' . $threadid . '&amp;p=' . $postid . '&amp;viewfull=1#post' . $postid, ($post['posttitle'] ? $post['posttitle'] : $post['threadtitle'])), true, array('postid' => $postid, 'posttitle' => ($post['posttitle'] ? $post['posttitle'] : $post['threadtitle']), 'threadid' => $threadid, 'forumid' => $forumid, 'threadtitle' => $post['threadtitle'], 'forumtitle' => $post['forumtitle'], 'userid' => $post['userid'], 'username' => $post['username'], 'email' => $post['email'], 'languageid' => $post['languageid']));

					$links = $phpkd_vblvb->getDmhandle()->fetch_urls($post['pagetext'], $postid);

					if (!empty($links['urlrecords']))
					{
						// Tag Threads - Included Hosts
						if ($vbulletin->options['phpkd_vblvb_tagging_host'])
						{
							foreach ($links['urlrecords'] as $urlrecord)
							{
								$taggedthreads[$threadid][] = $urlrecord['host'];
							}
						}
					}

					$phpkd_vblvb->logstring(construct_phrase($vbphrase['phpkd_vblvb_log_summery_post'], '#008000', '#FF0000', '#FFA500', $links['all'], $links['checked'], $links['alive'], $links['dead'], $links['down'], ($links['all'] - $links['checked'])) . '</li><br />', true, $postid);


					// Dead posts
					if ($links['checked'] > 0 && $links['dead'] > 0)
					{
						$records['dead']++;
						$critical = ($links['dead'] / $links['checked']) * 100;

						if (!$vbulletin->options['phpkd_vblvb_reporting_user_reports_mode'])
						{
							$deadposts[] = $postid;
						}

						// Critical Limit/Red Line
						if ($critical >= $vbulletin->options['phpkd_vblvb_general_critical_limit'])
						{
							$records['punished']++;
							$punished_links .= '<li><a href="' . $vbulletin->options['bburl'] . '/showthread.php?t=' . $threadid . '&amp;p=' . $postid . '&amp;viewfull=1#post' . $postid . '" target="_blank">' . ($post['posttitle'] ? $post['posttitle'] : $post['threadtitle']) . '</a></li>';
							$punished_content['threads'][$threadid] = array('forumid' => $forumid, 'threadid' => $threadid, 'open' => $post['open'], 'visible' => $post['visible'], 'sticky' => $post['sticky'], 'firstpostid' => $post['firstpostid'], 'replycount' => $post['replycount'], 'title' => $post['threadtitle'], 'pollid' => $post['pollid']);
							$punished_content['forums'][] = $forumid;
						}
					}

					$records['checked']++;
				}


				$phpkd_vblvb->logstring('</ol></li><br />', true);
			}

			$phpkd_vblvb->logstring('</ol></li>', true);
		}

		$phpkd_vblvb->logstring(construct_phrase($vbphrase['phpkd_vblvb_log_summery_all'], '#008000', '#FF0000', '#FFA500', $records['checked'], ($records['checked'] - $records['dead']), $records['dead'], $records['punished']) . '</ol><br />', true);


		if ($vbulletin->options['threadtagging'] && !empty($taggedthreads))
		{
			// Tag Threads - Reported Status
			if ($vbulletin->options['phpkd_vblvb_tagging_status'] && !empty($punished_content['threads']))
			{
				foreach ($punished_content['threads'] as $threadid => $threadarray)
				{
					$taggedthreads[$threadid][0] = $vbphrase['phpkd_vblvb_tagging_status_dead'];
				}
			}

			foreach ($taggedthreads as $taggedthreadid => $tags)
			{
				$ourtags = array_unique($tags);
				$content = vB_Taggable_Content_Item::create($vbulletin, vB_Types::instance()->getContentTypeID("vBForum_Thread"), $taggedthreadid);

				if ($content)
				{
					foreach ($ourtags as $tagid => $tag)
					{
						if (!in_array($tag, array($vbphrase['phpkd_vblvb_tagging_status_alive'], $vbphrase['phpkd_vblvb_tagging_status_dead'])))
						{
							if (!in_array($tag, array_keys($phpkd_vblvb->hosts)) || (in_array($tag, array_keys($phpkd_vblvb->hosts)) && !$phpkd_vblvb->hosts[$tag]['taggable']))
							{
								unset($ourtags[$tagid]);
							}
							else
							{
								$ourtags[$tagid] = $phpkd_vblvb->hosts[$tag]['tagtext'];
							}
						}
					}

					$content->add_tags_to_content($ourtags, array('content_limit' => $vbulletin->options['tagmaxthread']));
				}

				unset($content);
			}
		}


		// Punish Dead Posts (only those over critical limit)
		if ($records['punished'] > 0)
		{
			$phpkd_vblvb->logstring($vbphrase['phpkd_vblvb_log_punished_posts'] . '<ol class="smallfont">' . $punished_links . '</ol><br />');
			$phpkd_vblvb->getDmhandle()->punish($punished_content);
		}

		// Send User Reports
		if (!empty($deadposts))
		{
			$phpkd_vblvb->getDmhandle()->user_reports($deadposts);
			$phpkd_vblvb->updatepostlogs($deadposts, 'dead');
		}

		// Send Staff Reports
		if ($vbulletin->options['phpkd_vblvb_reporting_staff_reports_mode'] == 0 || ($vbulletin->options['phpkd_vblvb_reporting_staff_reports_mode'] == 1 && $records['checked'] > 0) || ($vbulletin->options['phpkd_vblvb_reporting_staff_reports_mode'] == 2 && $records['dead'] > 0) || ($vbulletin->options['phpkd_vblvb_reporting_staff_reports_mode'] == 3 && $records['punished'] > 0))
		{
			$phpkd_vblvb->getDmhandle()->staff_reports($punished_links, $records);
		}


		// Every thing has been finished!
		$phpkd_vblvb->commit($checkedposts);
	}
	else
	{
		$phpkd_vblvb->seterror('phpkd_vblvb_checked_nothing');
	}
	$vbulletin->db->free_result($post_query);
}
else
{
	$phpkd_vblvb->seterror('phpkd_vblvb_inactive');
}

unset($phpkd_vblvb);

log_cron_action('', $nextitem, 1);
?>