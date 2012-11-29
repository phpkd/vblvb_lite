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
 * Hooks class
 *
 * @category	vB Link Verifier Bot 'Lite'
 * @package		PHPKD_VBLVB
 * @subpackage	PHPKD_VBLVB_Hooks
 * @copyright	Copyright ©2005-2012 PHP KingDom. All Rights Reserved. (http://www.phpkd.net)
 * @license		http://info.phpkd.net/en/license/free
 */
class PHPKD_VBLVB_Hooks
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
	 * @return	void
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
	}

	/*
	 * Required Initializations
	 * ~~~~~~~~~~~~~~~~~~~~~~~~~~
	 * NULL
	 *
	 * Input Parameters:
	 * ~~~~~~~~~~~~~~~~~~
	 * $do, $admin
	 *
	 * Output Parameters:
	 * ~~~~~~~~~~~~~~~~~~~
	 * $return_value
	 *
	 */
	public function can_administer($params)
	{
		// Parameters required!
		if ($this->_registry->verify_hook_params($params))
		{
			@extract($params);
			$bits = array(
				'phpkd_vblvb' => 1
			);

			foreach($do as $field)
			{
				if (isset($bits["$field"]) && ($admin['phpkd_vblvb'] & $bits["$field"]))
				{
					$return_value = true;
					return array('return_value' => $return_value);
				}
			}
		}
	}

	/*
	 * Required Initializations
	 * ~~~~~~~~~~~~~~~~~~~~~~~~~~
	 * NULL
	 *
	 * Input Parameters:
	 * ~~~~~~~~~~~~~~~~~~
	 * $admindm
	 *
	 * Output Parameters:
	 * ~~~~~~~~~~~~~~~~~~~
	 * NULL
	 *
	 */
	public function admin_permissions_process($params)
	{
		// Parameters required!
		if ($this->_registry->verify_hook_params($params))
		{
			@extract($params);
			$this->_registry->_vbulletin->input->clean_array_gpc('p', array(
				'phpkd_vblvb' => TYPE_ARRAY_INT
			));

			$perms = 0;

			foreach ($this->_registry->_vbulletin->GPC['phpkd_vblvb'] as $bit => $value)
			{
				if ($value)
				{
					$perms += $bit;
				}
			}

			$admindm->set('phpkd_vblvb', $perms);

			return true;
		}
	}

	/*
	 * Required Initializations
	 * ~~~~~~~~~~~~~~~~~~~~~~~~~~
	 * NULL
	 *
	 * Input Parameters:
	 * ~~~~~~~~~~~~~~~~~~
	 * $user
	 *
	 * Output Parameters:
	 * ~~~~~~~~~~~~~~~~~~~
	 * NULL
	 *
	 */
	public function admin_permissions_form($params)
	{
		// Parameters required!
		if ($this->_registry->verify_hook_params($params))
		{
			@extract($params);
			print_yes_no_row($this->_registry->_vbphrase['can_administer_phpkd_vblvb'], "phpkd_vblvb[1]", ($user['phpkd_vblvb'] & 1 ? 1 : 0));
			return true;
		}
	}

	/*
	 * Required Initializations
	 * ~~~~~~~~~~~~~~~~~~~~~~~~~~
	 * NULL
	 *
	 * Input Parameters:
	 * ~~~~~~~~~~~~~~~~~~
	 * (vB_DataManager_Admin) $there
	 *
	 * Output Parameters:
	 * ~~~~~~~~~~~~~~~~~~~
	 * NULL
	 *
	 */
	public function admindata_start($params)
	{
		// Parameters required!
		if ($this->_registry->verify_hook_params($params))
		{
			@extract($params);
			$there->validfields['phpkd_vblvb'] = array(TYPE_UINT, REQ_NO);
			return true;
		}
	}

	/*
	 * Required Initializations
	 * ~~~~~~~~~~~~~~~~~~~~~~~~~~
	 * NULL
	 *
	 * Input Parameters:
	 * ~~~~~~~~~~~~~~~~~~
	 * $thread
	 *
	 * Output Parameters:
	 * ~~~~~~~~~~~~~~~~~~~
	 * $phpkd_vblvb_postbit
	 *
	 */
	public function showthread_postbit_create($params)
	{
		// Parameters required!
		if ($this->_registry->verify_hook_params($params))
		{
			@extract($params);

			$phpkd_vblvb_postbit = false;
			$lookfeel_postbit = unserialize($this->_registry->_vbulletin->options['phpkd_vblvb_lookfeel_postbit_note']);

			if (!empty($lookfeel_postbit) && in_array($thread['forumid'], $lookfeel_postbit))
			{
				$phpkd_vblvb_postbit = true;
			}

			return array('phpkd_vblvb_postbit' => $phpkd_vblvb_postbit);
		}
	}

	/*
	 * Required Initializations
	 * ~~~~~~~~~~~~~~~~~~~~~~~~~~
	 * NULL
	 *
	 * Input Parameters:
	 * ~~~~~~~~~~~~~~~~~~
	 * $post, $thread, $template_hook
	 *
	 * Output Parameters:
	 * ~~~~~~~~~~~~~~~~~~~
	 * $template_hook
	 *
	 */
	public function postbit_display_complete($params)
	{
		// Parameters required!
		if ($this->_registry->verify_hook_params($params))
		{
			@extract($params);
			global $phpkd_vblvb_postbit;

			if (!$this->_registry->_vbulletin->options['phpkd_vblvb_lookfeel_postbit_note_firstpost'] || ($this->_registry->_vbulletin->options['phpkd_vblvb_lookfeel_postbit_note_firstpost'] && $post['postid'] == $thread['firstpostid']))
			{
				if (!empty($phpkd_vblvb_postbit))
				{
					if (!empty($post['phpkd_vblvb_lastcheck']))
					{
						// format date/time
						$postdate = vbdate($this->_registry->_vbulletin->options['dateformat'], $post['phpkd_vblvb_lastcheck'], true);
						$posttime = vbdate($this->_registry->_vbulletin->options['timeformat'], $post['phpkd_vblvb_lastcheck']);

						$template_hook['postbit_phpkd_vblvb'] .= construct_phrase($this->_registry->_vbphrase['phpkd_vblvb_lastcheck'], $postdate, $posttime);
					}
					else
					{
						$template_hook['postbit_phpkd_vblvb'] .= $this->_registry->_vbphrase['phpkd_vblvb_lastcheck_never'];
					}
				}
				else
				{
					$template_hook['postbit_phpkd_vblvb'] .= $this->_registry->_vbphrase['phpkd_vblvb_lastcheck_disabled'];
				}
			}

			return array('template_hook' => $template_hook);
		}
	}
}
