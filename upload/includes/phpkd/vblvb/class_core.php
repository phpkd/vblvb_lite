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


if (!defined('VB_AREA'))
{
	echo 'Can not be called from outside vBulletin Framework!';
	exit;
}

define('ERRTYPE_ECHO',               10);


/**
 * Core class
 *
 * @category	vB Link Verifier Bot 'Lite'
 * @package		PHPKD_VBLVB
 * @copyright	Copyright ©2005-2012 PHP KingDom. All Rights Reserved. (http://www.phpkd.net)
 * @license		http://info.phpkd.net/en/license/free
 */
class PHPKD_VBLVB
{
	/**
	 * Array of hosts to be checked
	 *
	 * @var	array
	 */
	public $hosts;

	/**
	 * Array of thread punishment methods to be applied
	 *
	 * @var	array
	 */
	public $thread_punishs;

	/**
	 * Array of valid staff reports to be sent/posted
	 *
	 * @var	array
	 */
	public $staff_reports;

	/**
	 * Array of valid user reports to be sent
	 *
	 * @var	array
	 */
	public $user_reports;

	/**
	 * vBulletin phrases
	 *
	 * @var	array
	 */
	public $_vbphrase;

	/**
	 * The vBulletin registry object
	 *
	 * @var	vB_Registry
	 */
	public $_vbulletin = null;

	/**
	 * Array of concatenated/appended post log records as strings
	 *
	 * @var	array
	 */
	private $_postlog = array();

	/**
	 * The Initializer Object Handler
	 *
	 * @var	PHPKD_VBLVB_Init
	 */
	private $_inithandle = null;

	/**
	 * The DataManager Object Handler
	 *
	 * @var	PHPKD_VBLVB_DM
	 */
	private $_dmhandle = null;

	/**
	 * The Hooks Object Handler
	 *
	 * @var	PHPKD_VBLVB_Hooks
	 */
	private $_hookshandle;

	/**
	 * Array to store any errors encountered while processing data
	 *
	 * @var	array
	 */
	private $_errors = array();

	/**
	 * The error handler for this object
	 *
	 * @var	string
	 */
	private $_error_handler = ERRTYPE_SILENT;

	/**
	 * Callback to execute just before an error is logged.
	 *
	 * @var	callback
	 */
	private $_failure_callback = null;

	/**
	 * Constructor - checks that vBulletin registry object has been passed correctly, and initialize requirements.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its members ($this->db).
	 * @param	array		vBphrase array
	 * @param	integer		One of the ERRTYPE_x constants
	 * @return	PHPKD_VBLVB
	 */
	public function __construct(&$registry, $phrases, $errtype = ERRTYPE_SILENT)
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

		$this->_vbphrase = $phrases;
		$this->set_error_handler($errtype);
		defined('PHPKD_VBLVB') || define('PHPKD_VBLVB', true);

		return $this;
	}

	/**
	 * Initialize requirements
	 *
	 * @param	array		Array of data requirements to be initialized
	 * @return	PHPKD_VBLVB
	 */
	public function initialize($initparams)
	{
		// Initialized params SHOULD be passed as an array!
		if (is_array($initparams) && !empty($initparams))
		{
			$initmethods = get_class_methods($this->getInithandle());

			foreach ($initparams as $method => $params)
			{
				if (in_array($method, $initmethods))
				{
					if ($this->$method !== null)
					{
						continue;
					}

					$this->$method = $this->getInithandle()->$method($params);
				}
			}
		}

		return $this;
	}

	/**
	 * Initiate PHPKD_VBLVB_Init
	 *
	 * @return	void
	 */
	private function setInithandle()
	{
		if (!class_exists('PHPKD_VBLVB_Init'))
		{
			if (file_exists(DIR . '/includes/phpkd/vblvb/class_init.php'))
			{
				require_once(DIR . '/includes/phpkd/vblvb/class_init.php');
			}
			else
			{
				$this->seterror(array('phpkd_vblvb_initialization_failed_file', 'class_init.php'));
			}
		}

		$this->_inithandle = new PHPKD_VBLVB_Init($this);
	}

	/**
	 * Return PHPKD_VBLVB_Init object
	 *
	 * @return	PHPKD_VBLVB_Init
	 */
	private function getInithandle()
	{
		if ($this->_inithandle == null)
		{
			$this->setInithandle();
		}

		return $this->_inithandle;
	}

	/**
	 * Initiate PHPKD_VBLVB_DM
	 *
	 * @return	void
	 */
	private function setDmhandle()
	{
		if (!class_exists('PHPKD_VBLVB_DM'))
		{
			if (file_exists(DIR . '/includes/phpkd/vblvb/class_dm.php'))
			{
				require_once(DIR . '/includes/phpkd/vblvb/class_dm.php');
			}
			else
			{
				$this->seterror(array('phpkd_vblvb_initialization_failed_file', 'class_dm.php'));
			}
		}

		$this->_dmhandle = new PHPKD_VBLVB_DM($this);
	}

	/**
	 * Return PHPKD_VBLVB_DM object
	 *
	 * @return	PHPKD_VBLVB_DM
	 */
	public function getDmhandle()
	{
		if ($this->_dmhandle == null)
		{
			$this->setDmhandle();
		}

		return $this->_dmhandle;
	}

	/**
	 * Initiate PHPKD_VBLVB_Hooks
	 *
	 * @return	void
	 */
	private function setHookshandle()
	{
		if (!class_exists('PHPKD_VBLVB_Hooks'))
		{
			if (file_exists(DIR . '/includes/phpkd/vblvb/class_hooks.php'))
			{
				require_once(DIR . '/includes/phpkd/vblvb/class_hooks.php');
			}
			else
			{
				$this->seterror(array('phpkd_vblvb_initialization_failed_file', 'class_hooks.php'));
			}
		}

		$this->_hookshandle = new PHPKD_VBLVB_Hooks($this);
	}

	/**
	 * Return PHPKD_VBLVB_Hooks object
	 *
	 * @return	PHPKD_VBLVB_Hooks
	 */
	private function getHookshandle()
	{
		if ($this->_hookshandle == null)
		{
			$this->setHookshandle();
		}

		return $this->_hookshandle;
	}

	/**
	 * Verify hook parameters
	 *
	 * @param	array	Input parameters
	 * @return	boolean	Returns true if valid, false if not
	 */
	public function verify_hook_params($params)
	{
		if (is_array($params) && count($params) > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Fetch requested hook
	 *
	 * @param	string	Hook name
	 * @param	array	Optional parameters used within the requested hook's code
	 * @return	mixed	Return boolean if there's nothing to get back, otherwise return array
	 */
	public function fetch_hook($hookname, $params = array())
	{
		$hooksmethods = get_class_methods($this->getHookshandle());

		if (in_array($hookname, $hooksmethods))
		{
			return $this->getHookshandle()->$hookname($params);
		}
	}

	/**
	 * Process log records
	 *
	 * @param	string	Log record to be appended
	 * @param	boolean	Whether or not to echo this log record to the display output
	 * @param	mixed	Array of required post info: postid, userid, username, email, languageid |[OR]| just postid in case other post info already passed earlier
	 * @return	void
	 */
	public function logstring($content, $echo = true, $post = 0)
	{
		if ($echo && defined('IN_CONTROL_PANEL'))
		{
			echo $content;
			vbflush();
		}

		if (!empty($post))
		{
			if (is_int($post))
			{
				$this->_postlog[$post]['logrecord'] .= $content;
			}
			else if (is_array($post))
			{
				$this->_postlog[$post['postid']]['posttitle']   = $post['posttitle'];
				$this->_postlog[$post['postid']]['threadid']    = $post['threadid'];
				$this->_postlog[$post['postid']]['threadtitle'] = $post['threadtitle'];
				$this->_postlog[$post['postid']]['forumid']     = $post['forumid'];
				$this->_postlog[$post['postid']]['forumtitle']  = $post['forumtitle'];
				$this->_postlog[$post['postid']]['userid']      = $post['userid'];
				$this->_postlog[$post['postid']]['username']    = $post['username'];
				$this->_postlog[$post['postid']]['email']       = $post['email'];
				$this->_postlog[$post['postid']]['languageid']  = $post['languageid'];
				$this->_postlog[$post['postid']]['logrecord']  .= $content;
			}
		}
	}

	/**
	 * Update punished posts
	 *
	 * @param	array	Array of updated post IDs
	 * @param	string	Update type, either 'dead' or 'punished'
	 * @return	void
	 */
	public function updatepostlogs($itemarray, $type)
	{
		foreach ($itemarray as $postid)
		{
			$this->_postlog[$postid][$type] = true;
		}
	}

	/**
	 * Return post log records
	 *
	 * @param	int	Optional postid
	 * @return	array
	 */
	public function getPostlog($postid = 0)
	{
		if (!empty($postid))
		{
			return $this->_postlog[$postid];
		}
		else
		{
			return $this->_postlog;
		}
	}

	/**
	 * Commit log records & update checked posts timestamp
	 *
	 * @param	array	Array of checked post IDs
	 * @return	void
	 */
	public function commit($postids = array())
	{
		if (!empty($postids))
		{
			// Finished, now update post last check time
			$this->_vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "post AS post
				SET post.phpkd_vblvb_lastcheck = " . TIMENOW . "
				WHERE postid IN(" . implode(',', $postids) . ")
			");
		}
	}

	/**
	 * Sets the error handler for the object
	 *
	 * @param	string	Error type
	 * @return	boolean
	 */
	private function set_error_handler($errtype = ERRTYPE_SILENT)
	{
		switch ($errtype)
		{
			case ERRTYPE_ECHO:
			case ERRTYPE_ARRAY:
			case ERRTYPE_STANDARD:
			case ERRTYPE_CP:
			case ERRTYPE_SILENT:
				$this->_error_handler = $errtype;
				break;
			default:
				$this->_error_handler = ERRTYPE_SILENT;
				break;
		}
	}

	/**
	 * Sets the function to call on an error.
	 *
	 * @param	callback	A valid callback (either a function name, or specially formed array)
	 * @return	void
	 */
	private function set_failure_callback($callback)
	{
		$this->_failure_callback = $callback;
	}

	/**
	 * Set error
	 *
	 * @param	string	Error message
	 * @param	int		Error type
	 * @param	int		postid
	 * @return	void
	 */
	public function seterror($error, $errortype = null, $postid = 0)
	{
		$this->set_error_handler($errortype ? $errortype : (defined('IN_CONTROL_PANEL') ? ERRTYPE_CP : ERRTYPE_SILENT));
		$this->error($error, $postid);

		if ($this->_error_handler != ERRTYPE_ECHO)
		{
			exit();
		}
	}

	/**
	 * Shows an error message and halts execution - use this in the same way as print_stop_message();
	 *
	 * @param	string	Phrase name for error message
	 * @param	int		postid
	 * @return	void
	 */
	public function error($errorphrase, $postid = 0)
	{
		$args = func_get_args();

		if (is_array($errorphrase))
		{
			$error = fetch_error($errorphrase);
		}
		else
		{
			$error = call_user_func_array('fetch_error', $args);
		}

		$this->_errors[] = $error;

		if ($this->_failure_callback && is_callable($this->_failure_callback))
		{
			call_user_func_array($this->_failure_callback, array(&$this, $errorphrase));
		}

		switch ($this->_error_handler)
		{
			case ERRTYPE_ECHO:
				$this->logstring('<br />' . $error . '<br />', true, $postid);
				break;

			case ERRTYPE_ARRAY:
			case ERRTYPE_SILENT:
				// do nothing
				break;

			case ERRTYPE_STANDARD:
				eval(standard_error($error));
				break;

			case ERRTYPE_CP:
				print_cp_message($error);
				break;
		}
	}
}
