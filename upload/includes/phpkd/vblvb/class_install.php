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


if (!defined('VB_AREA') || !defined('IN_CONTROL_PANEL'))
{
	echo 'Can not be called from outside vBulletin Framework AdminCP!';
	exit;
}


/**
 * Core class
 *
 * @category	vB Link Verifier Bot 'Lite'
 * @package		PHPKD_VBLVB
 * @subpackage	PHPKD_VBLVB_Install
 * @copyright	Copyright ©2005-2012 PHP KingDom. All Rights Reserved. (http://www.phpkd.net)
 * @license		http://info.phpkd.net/en/license/free
 */
class PHPKD_VBLVB_Install
{
	/**
	 * The vBulletin registry object
	 *
	 * @var	vB_Registry
	 */
	public $_vbulletin = null;

	/**
	 * Constructor - checks that vBulletin registry object has been passed correctly, and initialize requirements.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its members ($this->db).
	 * @return	PHPKD_VBLVB_Install
	 */
	public function __construct(&$registry)
	{
		if (is_object($registry))
		{
			$this->_vbulletin =& $registry;

			if (!is_object($registry->db))
			{
				trigger_error('vBulletin Database object is not an object!', E_USER_ERROR);
			}
		}
		else
		{
			trigger_error('vBulletin Registry object is not an object!', E_USER_ERROR);
		}

		return $this;
	}

	/**
	 * Initialize installation process
	 *
	 * @param	array		Array of product's info
	 * @return	void
	 */
	public function install_init($info)
	{
		if (!file_exists(DIR . '/includes/phpkd/vblvb/class_core.php') || !file_exists(DIR . '/includes/phpkd/vblvb/class_cron.php') || !file_exists(DIR . '/includes/phpkd/vblvb/class_dm.php') || !file_exists(DIR . '/includes/phpkd/vblvb/class_hooks.php') || !file_exists(DIR . '/includes/phpkd/vblvb/class_init.php') || !file_exists(DIR . '/includes/xml/bitfield_phpkd_vblvb.xml') || !file_exists(DIR . '/includes/xml/cpnav_phpkd_vblvb.xml'))
		{
			print_cp_message('Please upload the files that came with "PHPKD - vB Link Verifier Bot" product before installing or upgrading!');
		}

		$this->_vbulletin->db->hide_errors();

		// look to see if we already have this product installed
		$existingprod = $this->_vbulletin->db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "product
				WHERE productid = '" . $this->_vbulletin->db->escape_string($info['productid']) . "'"
		);
		
		if (strpos($existingprod['title'], 'Ultimate'))
		{
			print_cp_message('"PHPKD - vB Link Verifier Bot \'Ultimate\'" edition of this product appears to be already installed! You can <strong>NOT</strong> overwrite \'Ultimate\' edition by \'Lite\' edition!!');
		}
		else if (version_compare($existingprod['version'], '4.0.138') <= 0 && strpos($existingprod['title'], 'Lite'))
		{
			print_dots_start('Un-nstalling old version...', ':', 'phpkd_vbaddon_uninstall_old');
			delete_product($info['productid']);
		}


		// ######################################################################
		// ## Debug Stuff: Begin                                               ##
		// ######################################################################

		// Import debug data in appropriate field
		$phpkdinfo['title'] = $info['title'];
		$phpkdinfo['version'] = $info['version'];
		$phpkdinfo['revision'] = trim(substr(substr('$Revision: 273 $', 10), 0, -1));
		$phpkdinfo['released'] = trim(substr(substr('$Date: 2012-11-10 18:19:46 +0200 (Sat, 10 Nov 2012) $', 6), 0, -1));
		$phpkdinfo['installdateline'] = TIMENOW;
		$phpkdinfo['author'] = trim(substr(substr('$Author: PHPKD $', 8), 0, -1));
		$phpkdinfo['vendor'] = trim(substr(substr('$Vendor: PHP KingDom $', 8), 0, -1));
		$phpkdinfo['url'] = $info['url'];
		$phpkdinfo['versioncheckurl'] = $info['versioncheckurl'];

		print_dots_start('Installing: "' . $phpkdinfo['title'] . '"<br />Version: ' . $phpkdinfo['version'] . ', Revision: ' . $phpkdinfo['revision'] . ', Released: ' . $phpkdinfo['released'] . '.<br />Thanks for choosing PHP KingDom\'s Products. If you need any help or wish to try any other products we have, just give us a visit at <a href="http://www.phpkd.net" target="_blank">www.phpkd.net</a>. You are always welcomed.<br />Please Wait...', ':', 'phpkd_vbaddon_install_info');

		if ($this->_vbulletin->options['phpkd_commercial4x_data'])
		{
			$holder = unserialize($this->_vbulletin->options['phpkd_commercial4x_data']);
			$holder[$phpkdinfo['productid']] = $phpkdinfo;
			$data = $this->_vbulletin->db->escape_string(serialize($holder));
			$this->_vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "setting
				SET value = '$data'
				WHERE varname = 'phpkd_commercial4x_data'
			");
		}
		else
		{
			$holder[$phpkdinfo['productid']] = $phpkdinfo;
			$data = $this->_vbulletin->db->escape_string(serialize($holder));

			$this->_vbulletin->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "setting
					(varname, grouptitle, value, defaultvalue, datatype, optioncode, displayorder, advanced, volatile, validationcode, blacklist, product)
				VALUES
					('phpkd_commercial4x_data', 'version', '$data', '', 'free', '', '4444', '0', '1', '', '0', 'phpkd_framework')
			");

			$this->_vbulletin->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES
					('-1', 'vbsettings', 'setting_phpkd_commercial4x_data_title', 'PHP KingDom (PHPKD) Commercial Products\' Data (4.x) [Sensitive]', 'phpkd_framework', '" . $this->_vbulletin->db->escape_string($this->_vbulletin->userinfo['username']) . "', " . TIMENOW . ", '4.x'),
					('-1', 'vbsettings', 'setting_phpkd_commercial4x_data_desc', 'PHP KingDom (PHPKD) Commercial Products\' Data used for debugging purposes. <strong>[Sensitive Data, DON\'T ALTER]</strong>.', 'phpkd_framework', '" . $this->_vbulletin->db->escape_string($this->_vbulletin->userinfo['username']) . "', " . TIMENOW . ", '4.x')
			");
		}

		build_options();
		print_dots_stop();

		// ######################################################################
		// ## Debug Stuff: End                                                 ##
		// ######################################################################

		$this->_vbulletin->db->show_errors();
	}

	/**
	 * Initialize uninstallation
	 *
	 * @return	void
	 */
	public function uninstall_init()
	{
		$this->_vbulletin->db->hide_errors();

		// ######################################################################
		// ## Debug Stuff: Begin                                               ##
		// ######################################################################

		if ($this->_vbulletin->options['phpkd_commercial4x_data'])
		{
			$holder = unserialize($this->_vbulletin->options['phpkd_commercial4x_data']);

			if ($holder[$this->_vbulletin->db->escape_string($this->_vbulletin->GPC['productid'])])
			{
				$phpkdinfo = $holder[$this->_vbulletin->db->escape_string($this->_vbulletin->GPC['productid'])];
				print_dots_start('Un-installing: "' . $phpkdinfo['title'] . '"<br />Version: ' . $phpkdinfo['version'] . ', Revision: ' . $phpkdinfo['revision'] . ', Released: ' . $phpkdinfo['released'] . '.<br />We are sad to see you un-installing "' . $phpkdinfo['title'] . '". Please if there is any thing we can do to keep you using this software product, just tell us at <a href="http://www.phpkd.net" target="_blank">www.phpkd.net</a>.<br />Please Wait...', ':', 'phpkd_vbaddon_uninstall_info');
				print_dots_stop();
				unset($holder[$this->_vbulletin->db->escape_string($this->_vbulletin->GPC['productid'])]);
			}

			if (is_array($holder) && !empty($holder))
			{
				$data = $this->_vbulletin->db->escape_string(serialize($holder));
				$this->_vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "setting SET
					value = '$data'
					WHERE varname = 'phpkd_commercial4x_data'
				");
			}
			else
			{
				// delete phrases
				$this->_vbulletin->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "phrase
					WHERE languageid IN (-1, 0) AND
						fieldname = 'vbsettings' AND
						varname IN ('setting_phpkd_commercial4x_data_title', 'setting_phpkd_commercial4x_data_desc')
				");

				// delete setting
				$this->_vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "setting WHERE varname = 'phpkd_commercial4x_data'");
			}
		}

		build_options();

		// ######################################################################
		// ## Debug Stuff: End                                                 ##
		// ######################################################################

		$this->_vbulletin->db->show_errors();
	}

	/**
	 * Install v4.2.110
	 *
	 * @return	void
	 */
	public function install_42110()
	{
		$this->_vbulletin->db->hide_errors();

		print_dots_start('Installing v4.2.110 updates...', ':', 'phpkd_vbaddon_install_42110');

		require_once(DIR . '/includes/class_dbalter.php');
		$db_alter = new vB_Database_Alter_MySQL($this->_vbulletin->db);

		// Add permission field to the administrator table
		if ($db_alter->fetch_table_info('administrator'))
		{
			$db_alter->add_field(array(
					'name'       => 'phpkd_vblvb',
					'type'       => 'int',
					'attributes' => 'unsigned',
					'default'    => 0
			));
		}

		if ($db_alter->fetch_table_info('thread'))
		{
			$db_alter->add_field(array(
					'name'       => 'phpkd_vblvb_lastpunish',
					'type'       => 'mediumtext',
					'default'    => '',
			));
		}

		if ($db_alter->fetch_table_info('post'))
		{
			$db_alter->add_field(array(
				'name'       => 'phpkd_vblvb_lastcheck',
				'type'       => 'int',
				'attributes' => 'unsigned',
				'default'    => '0',
			));
		}

		print_dots_stop();

		$this->_vbulletin->db->show_errors();
	}

	/**
	 * Uninstall v4.2.110
	 *
	 * @return	void
	 */
	public function uninstall_42110()
	{
		$this->_vbulletin->db->hide_errors();

		print_dots_start('Un-installing v4.2.110 updates...', ':', 'phpkd_vbaddon_uninstall_42110');

		require_once(DIR . '/includes/class_dbalter.php');
		$db_alter = new vB_Database_Alter_MySQL($this->_vbulletin->db);

		if ($db_alter->fetch_table_info('administrator'))
		{
			$db_alter->drop_field('phpkd_vblvb');
		}

		if ($db_alter->fetch_table_info('thread'))
		{
			$db_alter->drop_field('phpkd_vblvb_lastpunish');
		}

		if ($db_alter->fetch_table_info('post'))
		{
			$db_alter->drop_field('phpkd_vblvb_lastcheck');
		}

		print_dots_stop();

		$this->_vbulletin->db->show_errors();
	}
}
