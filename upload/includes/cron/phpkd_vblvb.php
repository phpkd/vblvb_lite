<?php
/*==================================================================================*\
|| ################################################################################ ||
|| # Product Name: PHPKD - vB Link Verifier Bot Lite             Version: 4.0.131 # ||
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


// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

require_once(DIR . '/includes/functions_phpkd_vblvb.php');

$log = '';
if ($vbulletin->options['phpkd_vblvb_active'])
{
	require_once(DIR . '/includes/functions_phpkd_vblvb.php');

	if (!defined('IN_CONTROL_PANEL'))
	{
		global $vbphrase;
	}


	if (!$vbulletin->options['phpkd_vblvb_checked_existingposts'])
	{
		if (defined('IN_CONTROL_PANEL'))
		{
			print_stop_message('phpkd_vblvb_existing_notchecked');
		}
		else
		{
			phpkd_vblvb_cron_kill($vbphrase['phpkd_vblvb_existing_notchecked'], $nextitem);
		}
	}


	// Required Initialization
	if (!$vbulletin->options['phpkd_vblvb_hosts_depositfiles_com'] AND !$vbulletin->options['phpkd_vblvb_hosts_easy_share_com'] AND !$vbulletin->options['phpkd_vblvb_hosts_filefactory_com'] AND !$vbulletin->options['phpkd_vblvb_hosts_mediafire_com'] AND !$vbulletin->options['phpkd_vblvb_hosts_megaupload_com'] AND !$vbulletin->options['phpkd_vblvb_hosts_netload_in'] AND !$vbulletin->options['phpkd_vblvb_hosts_rapidshare_com'] AND !$vbulletin->options['phpkd_vblvb_hosts_rapidshare_de'] AND !$vbulletin->options['phpkd_vblvb_hosts_sendspace_com'] AND !$vbulletin->options['phpkd_vblvb_hosts_zshare_net'])
	{
		if (defined('IN_CONTROL_PANEL'))
		{
			print_stop_message('phpkd_vblvb_invalid_hosts');
		}
		else
		{
			phpkd_vblvb_cron_kill($vbphrase['phpkd_vblvb_invalid_hosts'], $nextitem);
		}
	}


	switch ($vbulletin->options['phpkd_vblvb_cutoff_mode'])
	{
		case 0:
			$cutoff = (($vbulletin->options['phpkd_vblvb_cutoff_value'] > 0) ? 'AND post.dateline > UNIX_TIMESTAMP(\'' . $vbulletin->db->escape_string(trim($vbulletin->options['phpkd_vblvb_cutoff_value'])) . '\')' : '');
			break;
		case 1:
			$cutoff = (($vbulletin->options['phpkd_vblvb_cutoff_value'] > 0) ? 'AND post.dateline > ' . (TIMENOW - (intval(trim($vbulletin->options['phpkd_vblvb_cutoff_value'])) * 86400)) : '');
			break;
	}


	$inex_forums = '';
	switch ($vbulletin->options['phpkd_vblvb_inex_forums'])
	{
		case 1:
			$inex_forums = (($vbulletin->options['phpkd_vblvb_inex_forums_ids'] != '' AND strlen($vbulletin->options['phpkd_vblvb_inex_forums_ids']) > 0) ? 'AND thread.forumid IN (' . $vbulletin->db->escape_string($vbulletin->options['phpkd_vblvb_inex_forums_ids']) . ')' : '');
			break;
		case 2:
			$inex_forums = (($vbulletin->options['phpkd_vblvb_inex_forums_ids'] != '' AND strlen($vbulletin->options['phpkd_vblvb_inex_forums_ids']) > 0) ? 'AND thread.forumid NOT IN (' . $vbulletin->db->escape_string($vbulletin->options['phpkd_vblvb_inex_forums_ids']) . ')' : '');
			break;
	}


	$posts = $vbulletin->db->query_read("
		SELECT user.username, post.userid, post.postid, post.threadid, post.title, post.pagetext, thread.forumid, thread.title AS threadtitle
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (post.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		Where thread.open = 1
			AND thread.visible = 1
			AND post.visible = 1
			$cutoff
			$inex_forums
			" . (($vbulletin->options['phpkd_vblvb_checked_existingposts'] == 2) ? 'AND post.postid = thread.firstpostid' : '') . "
			" . (($vbulletin->options['phpkd_vblvb_succession_period'] > 0) ? 'AND post.phpkd_vblvb_lastcheck < ' . (TIMENOW - ($vbulletin->options['phpkd_vblvb_succession_period'] * 86400)) : '') . "
			" . (($vbulletin->options['phpkd_vblvb_limit'] > 0) ? 'LIMIT ' . $vbulletin->options['phpkd_vblvb_limit'] : '')
	);


	if ($vbulletin->db->num_rows($posts))
	{
		$log .= '<ol class="smallfont">';
		if (defined('IN_CONTROL_PANEL'))
		{
			echo '<ol class="smallfont">';
			vbflush();
		}


		$logpunished = '';
		$punished = array();
		while ($post = $vbulletin->db->fetch_array($posts))
		{
			$log .= '<li><a href="' . $vbulletin->options['bburl'] . '/showthread.php?p=' . intval($post['postid']) . '" target="_blank">' . ($post['title'] ? $post['title'] : $post['threadtitle']) . '</a>';
			if (defined('IN_CONTROL_PANEL'))
			{
				echo '<li><a href="' . $vbulletin->options['bburl'] . '/showthread.php?p=' . intval($post['postid']) . '" target="_blank">' . ($post['title'] ? $post['title'] : $post['threadtitle']) . '</a>';
				vbflush();
			}

			$links = phpkd_vblvb_fetch_urls($post['pagetext']);

			$links['ignored'] = $links['all'] - ($links['alive'] + $links['dead'] + $links['down']);
			$log .= $links['log'] . "\n" . construct_phrase($vbphrase['phpkd_vblvb_log_summery'], $links['all'], $links['checked'], $links['alive'], $links['dead'], $links['down'], $links['ignored']) . '</li>';
			if (defined('IN_CONTROL_PANEL'))
			{
				echo (($links['all'] == 0) ? $links['log'] : '') . construct_phrase($vbphrase['phpkd_vblvb_log_summery'], $links['all'], $links['checked'], $links['alive'], $links['dead'], $links['down'], $links['ignored']) . '</li>';
				vbflush();
			}


			// Critical Limit/Red Line
			if ($links['checked'] > 0 AND $links['dead'] > 0)
			{
				$critical = ($links['dead'] / $links['checked']) * 100;
				if ($critical > $vbulletin->options['phpkd_vblvb_critical'])
				{
					$logpunished .= '<li><a href="' . $vbulletin->options['bburl'] . '/showpost.php?p=' . intval($post['postid']) . '" target="_blank">' . ($post['title'] ? $post['title'] : $post['threadtitle']) . '</a></li>';
					$punished[$post['userid']][$post['postid']] = array('threadid' => $post['threadid'], 'forumid' => $post['forumid'], 'username' => $post['username'], 'title' => $post['title'], 'threadtitle' => $post['threadtitle']);
				}
			}


			// Finished, now update 'post.phpkd_vblvb_lastcheck'
			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "post
				SET phpkd_vblvb_lastcheck = " . TIMENOW . "
				WHERE postid = $post[postid]
			");
		}
	}
	else
	{
		$log .= $vbphrase['phpkd_vblvb_nothing_checked'];
		if (defined('IN_CONTROL_PANEL'))
		{
			print_stop_message('phpkd_vblvb_nothing_checked');
			vbflush();
		}
	}
	$vbulletin->db->free_result($posts);


	$log .= '</ol><br />';
	if (defined('IN_CONTROL_PANEL'))
	{
		echo '</ol><br />';
		vbflush();
	}

	if (is_array($punished) AND count($punished) > 0)
	{
		$log .= $vbphrase['phpkd_vblvb_log_punished_posts'] . '<ol class="smallfont">' . $logpunished . '</ol><br />';
		if (defined('IN_CONTROL_PANEL'))
		{
			echo $vbphrase['phpkd_vblvb_log_punished_posts'] . '<ol class="smallfont">' . $logpunished . '</ol><br />';
			vbflush();
		}

		// Punish Dead Posts
		phpkd_vblvb_punish($punished);

		// Send User Reports
		phpkd_vblvb_rprtu($punished);
	}

	// Send Staff Reports
	phpkd_vblvb_rprts($log);


	log_cron_action($log, $nextitem, 1);
}


/*============================================================================*\
|| ########################################################################### ||
|| # Version: 4.0.131
|| # $Revision$
|| # Released: $Date$
|| ########################################################################### ||
\*============================================================================*/
?>