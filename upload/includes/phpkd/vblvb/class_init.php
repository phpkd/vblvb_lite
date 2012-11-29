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
 * Init class
 *
 * @category	vB Link Verifier Bot 'Lite'
 * @package		PHPKD_VBLVB
 * @subpackage	PHPKD_VBLVB_Init
 * @copyright	Copyright ©2005-2012 PHP KingDom. All Rights Reserved. (http://www.phpkd.net)
 * @license		http://info.phpkd.net/en/license/free
 */
class PHPKD_VBLVB_Init
{
	/**
	 * The PHPKD_VBLVB registry object
	 *
	 * @var	PHPKD_VBLVB
	 */
	private $_registry = null;

	/**
	 * Bitfields
	 *
	 * @var	array
	 */
	private $_bitfields;

	/**
	 * Constructor - checks that PHPKD_VBLVB registry object including vBulletin registry oject has been passed correctly.
	 *
	 * @param	PHPKD_VBLVB	Instance of the main product's data registry object - expected to have both vBulletin data registry & database object as two of its members.
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


		if (!class_exists('vB_Bitfield_Builder'))
		{
			require_once(DIR . '/includes/class_bitfield_builder.php');
		}

		$this->_bitfields = vB_Bitfield_Builder::fetch(DIR . '/includes/xml/bitfield_phpkd_vblvb.xml', true, true);
	}

	/**
	 * Get active hosts
	 *
	 * @return	array	Array of all active hosts
	 */
	public function hosts()
	{
		return array(
			'2shared.com' => array('domain' => '2shared.com', 'active' => 1, 'status' => 'alive', 'urlmatch' => '2shared\.com/(zip|music|image|file|video|audio|photo|document|complete)/[0-9a-z]+', 'contentmatch' => 'fileinfo', 'urlsearch' => '', 'urlreplace' => '', 'downmatch' => '', 'taggable' => 1, 'tagtext' => '2shared.com'),
			'4shared.com' => array('domain' => '4shared.com', 'active' => 1, 'status' => 'alive', 'urlmatch' => '4shared\.com/(zip|music|image|file|video|audio|photo|document|get)/[0-9a-z]+', 'contentmatch' => 'fileMeta', 'urlsearch' => '', 'urlreplace' => '', 'downmatch' => '', 'taggable' => 1, 'tagtext' => '4shared.com'),
			'depositfiles.com' => array('domain' => 'depositfiles.com', 'active' => 1, 'status' => 'alive', 'urlmatch' => 'depositfiles\.com/([a-z]{2}/)?files/[0-9a-z]+', 'contentmatch' => '(gateway_result|show_gold_offer)', 'urlsearch' => 'com/([a-z]{2}/)?files/', 'urlreplace' => 'com/en/files/', 'downmatch' => '', 'taggable' => 1, 'tagtext' => 'depositfiles.com'),
			'filefactory.com' => array('domain' => 'filefactory.com', 'active' => 1, 'status' => 'alive', 'urlmatch' => 'filefactory\.com/file/[0-9a-z]+', 'contentmatch' => 'downloadFileData', 'urlsearch' => '', 'urlreplace' => '', 'downmatch' => 'Retry Download', 'taggable' => 1, 'tagtext' => 'filefactory.com'),
			'gigasize.com' => array('domain' => 'gigasize.com', 'active' => 1, 'status' => 'alive', 'urlmatch' => 'gigasize\.com/get(\.php\?d=|/)[0-9a-z]+', 'contentmatch' => 'fileInfo', 'urlsearch' => '', 'urlreplace' => '', 'downmatch' => '', 'taggable' => 1, 'tagtext' => 'gigasize.com'),
			'jumbofiles.com' => array('domain' => 'jumbofiles.com', 'active' => 1, 'status' => 'alive', 'urlmatch' => 'jumbofiles\.com/[0-9a-z]+', 'contentmatch' => '(download1|download2)', 'urlsearch' => '', 'urlreplace' => '', 'downmatch' => '', 'taggable' => 1, 'tagtext' => 'jumbofiles.com'),
			'letitbit.net' => array('domain' => 'letitbit.net', 'active' => 1, 'status' => 'alive', 'urlmatch' => 'letitbit\.net/download/[0-9a-z]+', 'contentmatch' => 'file-info-size', 'urlsearch' => '', 'urlreplace' => '', 'downmatch' => '', 'taggable' => 1, 'tagtext' => 'letitbit.net'),
			'mediafire.com' => array('domain' => 'mediafire.com', 'active' => 1, 'status' => 'alive', 'urlmatch' => 'mediafire\.com/((download\.php)?\?|file/)[0-9a-z]+', 'contentmatch' => 'download_file_title', 'urlsearch' => '(download\.php\?|file/)', 'urlreplace' => '?', 'downmatch' => '', 'taggable' => 1, 'tagtext' => 'mediafire.com'),
			'megashares.com' => array('domain' => 'megashares.com', 'active' => 1, 'status' => 'alive', 'urlmatch' => 'megashares\.com/(dl/|index\.php\?d[0-9]+=)[0-9a-z]...', 'contentmatch' => '(desc_col|file_info)', 'urlsearch' => '', 'urlreplace' => '', 'downmatch' => '', 'taggable' => 1, 'tagtext' => 'megashares.com'),
			'netload.in' => array('domain' => 'netload.in', 'active' => 1, 'status' => 'alive', 'urlmatch' => 'netload\.in/datei[0-9a-z]+', 'contentmatch' => '(dl_first_file_download|Download_Limit|password-protected)', 'urlsearch' => '&lang=[a-zA-Z]{2}', 'urlreplace' => '&lang=en', 'downmatch' => '', 'taggable' => 1, 'tagtext' => 'netload.in'),
		);
	}

	/**
	 * Get applied thread punishments
	 *
	 * @return	array	Array of all applied thread punishments
	 */
	public function thread_punishs()
	{
		$thread_punishs = array();

		foreach ($this->_bitfields['phpkd_vblvb']['thread_punishs'] as $key => $value)
		{
			if ($this->_registry->_vbulletin->options['phpkd_vblvb_punishment_thread_punishs'] & $value)
			{
				$thread_punishs[] = $key;
			}
		}

		return $thread_punishs;
	}

	/**
	 * Get staff reporting methods
	 *
	 * @return	array	Array of all staff reporting methods
	 */
	public function staff_reports()
	{
		$staff_reports = array();

		foreach ($this->_bitfields['phpkd_vblvb']['staff_reports'] as $key => $value)
		{
			if ($this->_registry->_vbulletin->options['phpkd_vblvb_reporting_staff_reports'] & $value)
			{
				$staff_reports[] = $key;
			}
		}

		return $staff_reports;
	}

	/**
	 * Get user reporting methods
	 *
	 * @return	array	Array of all user reporting methods
	 */
	public function user_reports()
	{
		$user_reports = array();

		foreach ($this->_bitfields['phpkd_vblvb']['user_reports'] as $key => $value)
		{
			if ($this->_registry->_vbulletin->options['phpkd_vblvb_reporting_user_reports'] & $value)
			{
				$user_reports[] = $key;
			}
		}

		return $user_reports;
	}
}
