<?php
/*==================================================================================*\
|| ################################################################################ ||
|| # Product Name: PHPKD - vB Link Verifier Bot Lite             Version: 3.8.100 # ||
|| # License Type: Free License                                  $Revision: 124 $ # ||
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


if ($vbulletin->options['phpkd_vblvb_active'])
{
	switch ($vbulletin->options['phpkd_vblvb_cutoff_mode'])
	{
		case 0:
			$cutoff = (($vbulletin->options['phpkd_vblvb_cutoff_value'] > 0) ? 'AND post.dateline > UNIX_TIMESTAMP(\'' . $vbulletin->db->escape_string($vbulletin->options['phpkd_vblvb_cutoff_value']) . '\')' : '');
			break;
		case 1:
			$cutoff = (($vbulletin->options['phpkd_vblvb_cutoff_value'] > 0) ? 'AND post.dateline > ' . TIMENOW - ($vbulletin->options['phpkd_vblvb_cutoff_value'] * 86400) : '');
			break;
	}


	$posts = $vbulletin->db->query_read("
		SELECT user.username, user.usergroupid, user.email, user.languageid, post.userid, post.postid, post.threadid, post.dateline, post.title, post.pagetext, thread.forumid, thread.title AS threadtitle
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (post.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		Where thread.open = 1
			AND thread.visible = 1
			AND post.visible = 1
			$cutoff
			" . (($vbulletin->options['phpkd_vblvb_checked_posts'] == 2) ? 'AND post.postid = thread.firstpostid' : '')
			. (($vbulletin->options['phpkd_vblvb_succession_period'] > 0) ? 'AND post.phpkd_vblvb_lastcheck < ' . (TIMENOW - (86400 * $vbulletin->options['phpkd_vblvb_succession_period'])) : '')
		. (($vbulletin->options['phpkd_vblvb_limit'] > 0) ? 'LIMIT ' . intval($vbulletin->options['phpkd_vblvb_limit']) : '')
	);


	$log = $vbphrase['phpkd_vblvb_log_scan_report'] . '<ol class="smallfont">';
	if (defined('IN_CONTROL_PANEL'))
	{
		echo '<ol class="smallfont">';
		vbflush();
	}


	$logpunished = '';
	$punished = array();
	while ($post = $vbulletin->db->fetch_array($posts))
	{
		$log .= '<li><a href="' . $vbulletin->options['bburl'] . '/showthread.php?p=' . intval($post['postid']) . '" target="_blank">' . ($post['title'] ? $post['title'] : $post['threadtitle']) . '</a><ol>';
		if (defined('IN_CONTROL_PANEL'))
		{
			echo '<li><a href="' . $vbulletin->options['bburl'] . '/showthread.php?p=' . intval($post['postid']) . '" target="_blank">' . ($post['title'] ? $post['title'] : $post['threadtitle']) . '</a><ol>';
			vbflush();
		}

		$links = phpkd_vblvb_fetch_urls($post['pagetext']);

		$links['ignored'] = $links['all'] - ($links['alive'] + $links['down'] + $links['dead']);
		$log .= $links['log'] . "</ol>" . construct_phrase($vbphrase['phpkd_vblvb_log_scan_summery'], $links['all'], $links['checked'], $links['alive'], $links['down'], $links['dead'], $links['ignored']) . '</li>';
		if (defined('IN_CONTROL_PANEL'))
		{
			echo '</ol>' . construct_phrase($vbphrase['phpkd_vblvb_log_scan_summery'], $links['all'], $links['checked'], $links['alive'], $links['down'], $links['dead'], $links['ignored']) . '</li>';
			vbflush();
		}


		// Critical Limit/Red Line
		if ($links['checked'] > 0 AND $links['dead'] > 0)
		{
			$critical = ($links['dead'] / $links['checked']) * 100;
			if ($critical > $vbulletin->options['phpkd_vblvb_critical'])
			{
				$logpunished .= '<li><a href="' . $vbulletin->options['bburl'] . '/showpost.php?p=' . intval($post['postid']) . '" target="_blank">' . ($post['title'] ? $post['title'] : $post['threadtitle']) . '</a></li>';
				$punished[$post['userid']][$post['postid']] = $post;
			}
		}


		// Finished, now update 'post.phpkd_vblvb_lastcheck'
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "post
			SET phpkd_vblvb_lastcheck = " . TIMENOW . "
			WHERE postid = $post[postid]
		");
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
		// Punish Dead Posts
		phpkd_vblvb_punish($punished);
		$log .= $vbphrase['phpkd_vblvb_log_punished_posts'] . '<ol class="smallfont">' . $logpunished . '</ol>';

		// Send User Reports
		phpkd_vblvb_rprtu($punished);

		// Send Staff Reports
		phpkd_vblvb_rprts($log);
	}

	log_cron_action($log, $nextitem, 1);
}


/*============================================================================*\
|| ########################################################################### ||
|| # Version: 3.8.100
|| # $Revision: 124 $
|| # Released: $Date: 2008-07-22 07:23:25 +0300 (Tue, 22 Jul 2008) $
|| ########################################################################### ||
\*============================================================================*/
?>