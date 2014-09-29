<?php
defined('IN_MOBIQUO') or exit;
require_once (IPS_ROOT_PATH . 'sources/interfaces/interface_usercp.php');
require_once (IPS_ROOT_PATH . 'applications/core/modules_public/usercp/manualResolver.php');
require_once (IPS_ROOT_PATH . 'applications/core/extensions/usercpForms.php');
class mobi_usercp extends public_core_usercp_manualResolver
{
	public $tt_result_text;
	/**
	 * Main class entry point
	 *
	 * @param	object		ipsRegistry reference
	 * @return	@e void		[Outputs to screen]
	 */
	public function doExecute( ipsRegistry $registry )
	{
		$this->registry   =  $registry;
        $this->DB         =  $this->registry->DB();
        $this->settings   =& $this->registry->fetchSettings();
        $this->request    =& $this->registry->fetchRequest();
        $this->lang       =  $this->registry->getClass('class_localization');
        $this->member     =  $this->registry->member();
        $this->memberData =& $this->registry->member()->fetchMemberData();
        $this->cache      =  $this->registry->cache();
        $this->caches     =& $this->registry->cache()->fetchCaches();
		//-----------------------------------------
		// INIT
		//-----------------------------------------

		$_thisNav = array();

		//-----------------------------------------
		// Load language
		//-----------------------------------------

		$this->registry->getClass('class_localization')->loadLanguageFile( array( 'public_usercp' ) );

		//-----------------------------------------
		// Logged in?
		//-----------------------------------------

		if ( (! $this->memberData['member_id']) && (!isset($_POST['tt_token']))  )
		{
			get_error('No permission to change password/email');
			exit();
		}
		else if (isset($_POST['tt_token']))
		{
			//@todo
			$result = tt_register_verify($_POST['tt_token'], $_POST['tt_code']);
			if($result->result == false)
			{
				get_error($result->result_text);
			}
			$username = mysql_escape_string($result->email);
			$member = IPSMember::load($username, 'all', 'email');
			$this->memberData = $member;
			$this->memberData['bw_local_password_set'] = false;
			$this->memberData['members_created_remote'] = true;
			if ( (! $this->memberData['member_id']))
			{
				get_error('username is not exist');
			}
		}

		//-----------------------------------------
		// Make sure they're clean
		//-----------------------------------------

		$this->request['tab'] = IPSText::alphanumericalClean( $this->request['tab'] );
		$this->request['area'] = IPSText::alphanumericalClean( $this->request['area'] );

		//-----------------------------------------
		// Set up some basics...
		//-----------------------------------------

		$_TAB  = ( $this->request['tab'] )  ? $this->request['tab']  : 'core';
		$_AREA = ( $this->request['area'] ) ? $this->request['area'] : 'settings';
		$_DO   = ( $this->request['do'] )   ? $this->request['do']   : 'show';
		$_FUNC = ( $_DO == 'show' ) ? 'showForm' : ( $_DO == 'save' ? 'saveForm' : $_DO );
		$tabs  = array();
		$errors = array();

		//-----------------------------------------
		// Got a plug in?
		//-----------------------------------------
		
		IPSLib::loadInterface( 'interface_usercp.php' );
		
		$EXT_DIR  = IPSLib::getAppDir( $_TAB ) . '/extensions';
		if ( ! is_file($EXT_DIR . '/usercpForms.php') )
		{
			get_error("usercpForms.php is not exist");
			exit();
		}

		//-----------------------------------------
		// Cycle through applications and load
		// usercpForm extensions
		//-----------------------------------------
		foreach( IPSLib::getEnabledApplications() as $app_dir => $app_data )
		{
			$ext_dir  = IPSLib::getAppDir( $app_dir ) . '/extensions';

			// Make sure the extension exists
			if ( !is_file( $ext_dir . '/usercpForms.php' ) )
			{
				continue;
			}
			
			$__class        = IPSLib::loadLibrary( $ext_dir . '/usercpForms.php', 'usercpForms_' . $app_dir, $app_dir );
			$_usercp_module = new $__class();
			
			/* Block based on version to prevent old files showing up/causing an error */
			if( !$_usercp_module->version OR $_usercp_module->version < 32 )
			{
				continue;
			}

			$_usercp_module->makeRegistryShortcuts( $this->registry );

			if ( is_callable( array( $_usercp_module, 'init' ) ) )
			{
				$_usercp_module->init();

				/* Set default area? */
				if (  ( $_TAB == $app_dir ) AND ! isset( $_REQUEST['area'] ) )
				{
					if ( isset( $_usercp_module->defaultAreaCode ) )
					{
						$this->request['area'] = $_AREA = $_usercp_module->defaultAreaCode;
					}
				}
			}
	
			
		}

		
		//-----------------------------------------
		// Begin initilization routine for extension
		//-----------------------------------------
		//$classToLoad   = IPSLib::loadLibrary( $EXT_DIR . '/usercpForms.php', 'usercpForms_' . $_TAB, $_TAB );
		$usercp_module = new mobi_usercpForms_core();
		$usercp_module->makeRegistryShortcuts( $this->registry );
		$usercp_module->init();

		if ( ( $_DO == 'saveForm' || $_DO == 'showForm' ) AND ! is_callable( array( $usercp_module, $_FUNC ) ) )
		{
			get_error("Call saveForm function error");
			exit();
		}

		//-----------------------------------------
		// Run it...
		//-----------------------------------------
		if ( $_FUNC == 'saveForm' )
		{
			global $request_name;
			$errors = $usercp_module->saveForm( $_AREA );

			if ( is_array( $errors ) AND count( $errors ) )
			{
				foreach ($errors as $key=> $values)
				{
					get_error($values);
				}
			}
			else if ( $usercp_module->ok_message )
			{
				$this->tt_result_text = strip_tags($usercp_module->ok_message);
				return true;
			}
			else
			{
				get_error("Update password/email faile , please try again ");
			}
		}


	}
}
class mobi_usercpForms_core extends usercpForms_core {
	public function saveFormEmailPassword()
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------

		$_emailOne         = strtolower( trim($this->request['in_email_1']) );
		$_emailTwo         = strtolower( trim($this->request['in_email_2']) );
		$cur_pass = trim($this->request['current_pass']);
 		$new_pass = trim($this->request['new_pass_1']);
 		$chk_pass = trim($this->request['new_pass_2']);
		$isRemote = ( ! $this->memberData['bw_local_password_set'] AND $this->memberData['members_created_remote'] ) ? true : false;
		
		if ( $_emailOne or $_emailTwo )
		{
			//-----------------------------------------
			// Do not allow validating members to change
			// email when admin validation is on
			// @see	http://community.invisionpower.com/tracker/issue-19964-loophole-in-registration-procedure/
			//-----------------------------------------
			
			if( $this->memberData['member_group_id'] == $this->settings['auth_group'] AND in_array( $this->settings['reg_auth_type'], array( 'admin', 'admin_user' ) ) )
			{
				$this->registry->output->showError( $this->lang->words['admin_val_no_email_chg'], 10190 );
			}
			
			//-----------------------------------------
			// Check input
			//-----------------------------------------
	
			if( $this->memberData['g_access_cp'] )
			{
				return array( 0 => $this->lang->words['admin_emailpassword'] );
			}
	
			if ( ! $_POST['in_email_1'] OR ! $_POST['in_email_2'] )
			{
				return array( 0 => $this->lang->words['complete_entire_form'] );
			}
	
			//-----------------------------------------
			// Check password...
			//-----------------------------------------
	
			if ( ! $this->_isFBUser )
			{
				if ( $this->_checkPassword( $this->request['password'] ) === FALSE )
				{
					return array( 0 => $this->lang->words['current_pw_bad'] );
				}
			}
	
			//-----------------------------------------
			// Test email addresses
			//-----------------------------------------
	
			if ( $_emailOne != $_emailTwo )
			{
				return array( 0 => $this->lang->words['emails_no_matchy'] );
			}
	
			if ( IPSText::checkEmailAddress( $_emailOne ) !== TRUE )
			{
				return array( 0 => $this->lang->words['email_not_valid'] );
			}
	
			//-----------------------------------------
			// Is this email addy taken?
			//-----------------------------------------
	
			if ( IPSMember::checkByEmail( $_emailOne ) == TRUE )
			{
				return array( 0 => $this->lang->words['email_is_taken'] );
			}
	
			//-----------------------------------------
			// Load ban filters
			//-----------------------------------------
			$banfilters = array();
			$this->DB->build( array( 'select' => '*', 'from' => 'banfilters' ) );
			$this->DB->execute();
	
			while( $r = $this->DB->fetch() )
			{
				$banfilters[ $r['ban_type'] ][] = $r['ban_content'];
			}
	
			//-----------------------------------------
			// Check in banned list
			//-----------------------------------------
	
			if ( isset($banfilters['email']) AND is_array( $banfilters['email'] ) and count( $banfilters['email'] ) )
			{
				foreach ( $banfilters['email'] as $email )
				{
					$email = str_replace( '\*', '.*' ,  preg_quote($email, "/") );
	
					if ( preg_match( "/^{$email}$/i", $_emailOne ) )
					{
						return array( 0 => $this->lang->words['email_is_taken'] );
					}
				}
			}
	
			//-----------------------------------------
			// Load handler...
			//-----------------------------------------
	
			$classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/handlers/han_login.php', 'han_login' );
			$this->han_login   = new $classToLoad( $this->registry );
			$this->han_login->init();
	
			if ( $this->han_login->emailExistsCheck( $_emailOne ) !== FALSE )
			{
				return array( 0 => $this->lang->words['email_is_taken'] );
			}
	
			$this->han_login->changeEmail( $this->memberData['email'], $_emailOne, $this->memberData );
	
			if ( $this->han_login->return_code AND $this->han_login->return_code != 'METHOD_NOT_DEFINED' AND $this->han_login->return_code != 'SUCCESS' )
			{
			 	return array( 0 => $this->lang->words['email_is_taken'] );
			}
	
			//-----------------------------------------
			// Want a new validation? NON ADMINS ONLY
			//-----------------------------------------
	
			if ( $this->settings['reg_auth_type'] AND !$this->memberData['g_access_cp'] )
			{
				//-----------------------------------------
				// Remove any existing entries
				//-----------------------------------------
				
				$_previous	= $this->DB->buildAndFetch( array( 'select' => 'prev_email, real_group', 'from' => 'validating', 'where' => "member_id={$this->memberData['member_id']} AND email_chg=1" ) );
				
				if( $_previous['prev_email'] )
				{
					$this->DB->delete( 'validating', "member_id={$this->memberData['member_id']} AND email_chg=1" );
					
					$this->memberData['email']				= $_previous['prev_email'];
					$this->memberData['member_group_id']	= $_previous['real_group'];
				}
				
				$validate_key = md5( IPSMember::makePassword() . time() );
	
				//-----------------------------------------
				// Update the new email, but enter a validation key
				// and put the member in "awaiting authorisation"
				// and send an email..
				//-----------------------------------------
	
				$db_str = array(
								'vid'         => $validate_key,
								'member_id'   => $this->memberData['member_id'],
								'temp_group'  => $this->settings['auth_group'],
								'entry_date'  => time(),
								'coppa_user'  => 0,
								'email_chg'   => 1,
								'ip_address'  => $this->member->ip_address,
								'prev_email'  => $this->memberData['email'],
							   );
	
				if ( $this->memberData['member_group_id'] != $this->settings['auth_group'] )
				{
					$db_str['real_group'] = $this->memberData['member_group_id'];
				}
	
				$this->DB->insert( 'validating', $db_str );
				
				IPSLib::runMemberSync( 'onEmailChange', $this->memberData['member_id'], strtolower( $_emailOne ), $this->memberData['email'] );
	
				IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'member_group_id' => $this->settings['auth_group'],
																								  'email'           => $_emailOne ) ) );
	
				//-----------------------------------------
				// Update their session with the new member group
				//-----------------------------------------
	
				if ( $this->member->session_id  )
				{
					$this->member->sessionClass()->convertMemberToGuest();
				}
	
				//-----------------------------------------
				// Kill the cookies to stop auto log in
				//-----------------------------------------
	
				IPSCookie::set( 'pass_hash'  , '-1', 0 );
				IPSCookie::set( 'member_id'  , '-1', 0 );
				IPSCookie::set( 'session_id' , '-1', 0 );
	
				//-----------------------------------------
				// Dispatch the mail, and return to the activate form.
				//-----------------------------------------
	
				IPSText::getTextClass( 'email' )->getTemplate("newemail");
	
				IPSText::getTextClass( 'email' )->buildMessage( array(
													'NAME'         => $this->memberData['members_display_name'],
													'THE_LINK'     => $this->settings['base_url']."app=core&module=global&section=register&do=auto_validate&type=newemail&uid=".$this->memberData['member_id']."&aid=".$validate_key,
													'ID'           => $this->memberData['member_id'],
													'MAN_LINK'     => $this->settings['base_url']."app=core&module=global&section=register&do=07",
													'CODE'         => $validate_key,
												  ) );
	
				IPSText::getTextClass( 'email' )->subject = $this->lang->words['lp_subject'].' '.$this->settings['board_name'];
				IPSText::getTextClass( 'email' )->to      = $_emailOne;
	
				IPSText::getTextClass( 'email' )->sendMail();
				$this->ok_message = $this->lang->words['ce_auth'];
			}
			else
			{
				//-----------------------------------------
				// No authorisation needed, change email addy and return
				//-----------------------------------------
				
				IPSLib::runMemberSync( 'onEmailChange', $this->memberData['member_id'], strtolower( $_emailOne ), $this->memberData['email'] );
	
				IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'email' => $_emailOne ) ) );
	
				//-----------------------------------------
				// Add to OK message
				//-----------------------------------------
	
				$this->ok_message = $this->lang->words['ok_email_changed'];
			}
		}
		else if ( $cur_pass OR $new_pass )
		{
			if( $this->memberData['g_access_cp'] )
			{
				return array( 0 => $this->lang->words['admin_emailpassword'] );
			}
	
			if ( $isRemote === false AND ( ! $_POST['current_pass'] OR ( empty($new_pass) ) or ( empty($chk_pass) ) ) )
	 		{
				return array( 0 => $this->lang->words['complete_entire_form'] );
	 		}
	
	 		//-----------------------------------------
	 		// Do the passwords actually match?
	 		//-----------------------------------------
	
	 		if ( $new_pass != $chk_pass )
	 		{
	 			return array( 0 => $this->lang->words['passwords_not_matchy'] );
	 		}
	
	 		//-----------------------------------------
	 		// Check password...
	 		//-----------------------------------------
			
			if ( $isRemote === false )
			{
				if ( $this->_checkPassword( $cur_pass ) !== TRUE )
				{
					return array( 0 => $this->lang->words['current_pw_bad'] );
				}
			}
			else
			{
				/* This is INIT in _checkPassword */
				$classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/handlers/han_login.php', 'han_login' );
				$this->han_login   = new $classToLoad( $this->registry );
	    		$this->han_login->init();
	    	}
	
	 		//-----------------------------------------
	 		// Create new password...
	 		//-----------------------------------------
	
	 		$md5_pass = md5($new_pass);
	
	        //-----------------------------------------
	    	// han_login was loaded during check_password
	    	//-----------------------------------------
	
	    	$this->han_login->changePass( $this->memberData['email'], $md5_pass, $new_pass, $this->memberData );
	
	    	if ( $this->han_login->return_code AND $this->han_login->return_code != 'METHOD_NOT_DEFINED' AND $this->han_login->return_code != 'SUCCESS' )
	    	{
				return array( 0 => $this->lang->words['hanlogin_pw_failed'] );
	    	}
	
	 		//-----------------------------------------
	 		// Update the DB
	 		//-----------------------------------------
	
	 		IPSMember::updatePassword( $this->memberData['email'], $md5_pass );
	
	 		IPSLib::runMemberSync( 'onPassChange', $this->memberData['member_id'], $new_pass );
	
	 		//-----------------------------------------
	 		// Update members log in key...
	 		//-----------------------------------------
	
	 		$key  = IPSMember::generateAutoLoginKey();
	
			IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'member_login_key' => $key, 'bw_local_password_set' => 1 ) ) );
	
			$this->ok_message = $this->lang->words['pw_change_successful'];
		}
		
		return TRUE;
	}
}