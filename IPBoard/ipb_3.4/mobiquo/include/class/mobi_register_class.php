<?php

defined('IN_MOBIQUO') or exit;
require_once (IPS_ROOT_PATH . 'applications/core/modules_public/global/register.php');
class mobi_register extends public_core_global_register
{
	public $tt_result_text;
	public $tt_result = false;
	/**
	 * Class entry point
	 *
	 * @param	object		Registry reference
	 * @return	@e void		[Outputs to screen/redirects]
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
		/* Load language */
		$this->registry->class_localization->loadLanguageFile( array( 'public_register' ), 'core' );
		//@todo
		
    	//-----------------------------------------
    	// Meta tags
    	//-----------------------------------------
    	//$this->registry->output->addMetaTag( 'robots', 'noindex' );
		/* What to do */
		switch( $this->request['do'] )
		{
			case 'process_form':
				if( $this->settings['no_reg'] > 0 || $this->settings['tapatalk_reg_type'] == 2 )
				{
					get_error( 'registration_disabled');
				}
				
				if(($_POST['tt_token']!=='') && ($_POST['tt_code']!=='') && $this->settings['tapatalk_reg_type'] == 0)
				{
					$result = tt_register_verify($_POST['tt_token'], $_POST['tt_code']);   	
					if($result->result && !empty($result->email) && (empty($this->request['EmailAddress']) || strtolower($this->request['EmailAddress'] == strtolower($result->email))))
					{
						$this->tt_result = $result->result;
						$this->request['EmailAddress'] = $result->email;
					}
					else if(!$result->result && empty($this->request['EmailAddress']) && !empty($result->email))
					{
						$this->request['EmailAddress'] = $result->email;
					}			
				}
				
				$this->registerProcessForm();
			break;
		}
		return true;		
	}

	/**
	 * Processes the registration form
	 *
	 * @return	@e void
	 */
 	public function registerProcessForm()
 	{
		$this->_resetMember();
			
		$coppa			= ( $this->request['coppa_user'] == 1 ) ? 1 : 0;
		$in_password	= trim( $this->request['PassWord'] );
		$in_email		= strtolower( trim( $this->request['EmailAddress'] ) );
		
		/* Did we agree to the t&c? */
		if( ! $this->request['agree_tos'] )
		{
			get_error( $this->lang->words['must_agree_to_terms'] );
		}
		
		$customFields = $_POST['custom_register_fields'];
        
        if ((!isset($customFields['field_5']) || empty($customFields['field_5'])) && $this->tapatalk_profile['gender'])
        {
            $gender = $this->tapatalk_profile['gender'] == 'male' ? 'm' : ($this->tapatalk_profile['gender'] == 'female' ? 'f' : 'u');
            $customFields['field_5'] = $gender;
        }
        
        if ((!isset($customFields['field_6']) || empty($customFields['field_6'])) && $this->tapatalk_profile['location'])
        {
            $customFields['field_6'] = to_local($this->tapatalk_profile['location']);
        }
        
        if ((!isset($customFields['field_3']) || empty($customFields['field_3'])) && $this->tapatalk_profile['link'])
        {
            $customFields['field_3'] = to_local($this->tapatalk_profile['link']);
        }
        
        /* Custom profile field stuff */
        $classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/customfields/profileFields.php', 'customProfileFields' );
        $custom_fields = new $classToLoad();

        $custom_fields->initData( 'edit' );
        $custom_fields->setFromGroups( array('contact', 'profile_info') );
        $custom_fields->parseToSave( $customFields, 'register' );
		

		/* Check */
		$errors = $custom_fields->error_messages;
		if( $errors )
		{
		    $final_errors = array();
		    foreach( $errors as $eid => $error)
		    {
		        $final_errors[]="$error";
		    }
		    $final_error_msg = implode( "\n", $final_errors );
			get_error($final_error_msg);
		}
		/* Check the email address */		
		if ( ! $in_email OR strlen( $in_email ) < 6 OR !IPSText::checkEmailAddress( $in_email ) )
		{
			get_error($this->lang->words['err_invalid_email']);
		}
		
		if( trim($this->request['PassWord_Check']) != $in_password OR !$in_password )
		{
			get_error($this->lang->words['passwords_not_match']);
		}
		elseif ( strlen( $in_password ) < 3 )
		{
			get_error($this->lang->words['pass_too_short']);
		}
		elseif ( strlen( $in_password ) > 32 )
		{
			get_error($this->lang->words['pass_too_long']);
		}

		/* Check the username */
		$user_check = IPSMember::getFunction()->cleanAndCheckName( $this->request['members_display_name'], array(), 'name' );
		$disp_check = IPSMember::getFunction()->cleanAndCheckName( $this->request['members_display_name'], array(), 'members_display_name' );

		if( is_array( $user_check['errors'] ) && count( $user_check['errors'] ) )
		{
			foreach( $user_check['errors'] as $key => $error )
			{
				get_error(isset($this->lang->words[ $error ]) ? $this->lang->words[ $error ] : $error);
			}
		}
		
		/* Is this email addy taken? */
		if( IPSMember::checkByEmail( $in_email ) == TRUE )
		{
			get_error($this->lang->words['reg_error_email_taken']);
		}
		
		/* Load handler... */
    	$classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/handlers/han_login.php', 'han_login' );
    	$this->han_login =  new $classToLoad( $this->registry );
    	$this->han_login->init();
		$this->han_login->emailExistsCheck( $in_email );

		if( $this->han_login->return_code AND $this->han_login->return_code != 'METHOD_NOT_DEFINED' AND $this->han_login->return_code != 'EMAIL_NOT_IN_USE' )
		{
			get_error($this->lang->words['reg_error_email_taken']);
		}
		
		/* Are they banned [EMAIL]? */
		if ( IPSMember::isBanned( 'email', $in_email ) === TRUE )
		{
			get_error($this->lang->words['reg_error_email_ban']);
		}
		
        // don't need user validation after tapatalk id authorization
        if ($this->tt_result)
        {
            if ($this->settings['reg_auth_type'] == 'user')
                $this->settings['reg_auth_type'] = '';
            else if ($this->settings['reg_auth_type'] == 'admin_user')
                $this->settings['reg_auth_type'] = 'admin';
        }
		
		/* Build up the hashes */
		$mem_group = $this->settings['member_group'];
		
		/* Are we asking the member or admin to preview? */
		if( $this->settings['reg_auth_type'] )
		{
			$mem_group = $this->settings['auth_group'];
		}
		else if ($coppa == 1)
		{
			$mem_group = $this->settings['auth_group'];
		}
		
		// add for tapatalk register options
		if ($this->tt_result && $this->settings['reg_auth_type'] != 'admin' && isset($this->settings['tapatalk_reg_group']) && intval($this->settings['tapatalk_reg_group']))
		{
			$mem_group = intval($this->settings['tapatalk_reg_group']);
		}
		
		/* Create member */
		$member = array(
						 'name'						=> $this->request['members_display_name'],
						 'password'					=> $in_password,
						 'members_display_name'		=> $this->request['members_display_name'],
						 'email'					=> $in_email,
						 'member_group_id'			=> $mem_group,
						 'joined'					=> time(),
						 'ip_address'				=> $this->member->ip_address,
						 'time_offset'				=> $this->request['time_offset'],
						 'coppa_user'				=> $coppa,
						 'members_auto_dst'			=> intval($this->settings['time_dst_auto_correction']),
						 'allow_admin_mails'		=> intval( $this->request['allow_admin_mail'] ),
						 'language'					=> $this->member->language_id,
					   );
	
		/* Spam Service */
		$spamCode 	= 0;
		$_spamFlag	= 0;
		
		if( $this->settings['spam_service_enabled'] )
		{
			/* Query the service */
			$spamCode = IPSMember::querySpamService( $in_email );
        
			/* Action to perform */
			$action = $this->settings[ 'spam_service_action_' . $spamCode ];
        
			/* Perform Action */
			switch( $action )
			{
				/* Proceed with registration */
				case 1:
				break;
        
				/* Flag for admin approval */
				case 2:
        			$member['member_group_id'] = $this->settings['auth_group'];
					$this->settings['reg_auth_type'] = 'admin';
					$_spamFlag	= 1;
				break;
        
				/* Approve the account, but ban it */
				case 3:
        			$member['member_banned']			= 1;
					$member['bw_is_spammer']			= 1;
					$this->settings['reg_auth_type']	= '';
				break;
			}
		}
		
        // stop forum spam check for native register from app
        if (!$this->tt_result && ($this->settings['tapatalk_spam_option'] == 1 || $this->settings['tapatalk_spam_option'] == 3))
        {
            /* Get the file managemnet class */
            $classToLoad = IPSLib::loadLibrary( IPS_KERNEL_PATH . 'classFileManagement.php', 'classFileManagement' );
            $query = new $classToLoad();
            $query->timeout = ipsRegistry::$settings['spam_service_timeout'];
            
            $ip = $this->member->ip_address;
            $ipcheck = $ip ? "&ip={$ip}" : '';
            
            /* Query the service */
            $response = $query->getFileContents( "http://www.stopforumspam.com/api?f=serial&email={$in_email}".$ipcheck );
            $response = unserialize($response);
            
            if ((isset($response['email']['confidence']) && $response['email']['confidence'] > 50) ||
                (isset($response['ip']['confidence']) && $response['ip']['confidence'] > 60))
            {
                $this->registry->output->showError( 'spam_denied_account' );
            }
        }
		
		//-----------------------------------------
		// Create the account
		//-----------------------------------------

		$member	= IPSMember::create( array( 'members' => $member, 'pfields_content' => $custom_fields->out_fields ), FALSE, FALSE, FALSE );
				
		//-----------------------------------------
		// Login handler create account callback
		//-----------------------------------------
		
   		$this->han_login->createAccount( array(	'email'			=> $member['email'],
												'joined'		=> $member['joined'],
												'password'		=> $in_password,
												'ip_address'	=> $this->member->ip_address,
												'username'		=> $member['members_display_name'],
   										)		);

		//-----------------------------------------
		// We'll just ignore if this fails - it shouldn't hold up IPB anyways
		//-----------------------------------------
		
		/*if ( $han_login->return_code AND ( $han_login->return_code != 'METHOD_NOT_DEFINED' AND $han_login->return_code != 'SUCCESS' ) )
		{
			$this->registry->output->showError( 'han_login_create_failed', 2017, true );
		}*/
   		
		//-----------------------------------------
		// Validation
		//-----------------------------------------
		
		$validate_key = md5( IPSMember::makePassword() . time() );
		$time         = time();
		
		if( $coppa != 1 )
		{
			if( ( $this->settings['reg_auth_type'] == 'user' ) or ( $this->settings['reg_auth_type'] == 'admin' ) or ( $this->settings['reg_auth_type'] == 'admin_user' ) )
			{
				//-----------------------------------------
				// We want to validate all reg's via email,
				// after email verificiation has taken place,
				// we restore their previous group and remove the validate_key
				//-----------------------------------------
				
				$this->DB->insert( 'validating', array(
													  'vid'         => $validate_key,
													  'member_id'   => $member['member_id'],
													  'real_group'  => $this->settings['member_group'],
													  'temp_group'  => $this->settings['auth_group'],
													  'entry_date'  => $time,
													  'coppa_user'  => $coppa,
													  'new_reg'     => 1,
													  'ip_address'  => $member['ip_address'],
													  'spam_flag'	=> $_spamFlag,
											)       );
				
				if( $this->settings['reg_auth_type'] == 'user' OR $this->settings['reg_auth_type'] == 'admin_user' )
				{
					if($this->tt_result == false)
					{						
					
						/* Send out the email. */
						$message = array(   'THE_LINK'     => $this->settings['base_url'] . "app=core&module=global&section=register&do=auto_validate&uid=" . urlencode( $member['member_id'] ) . "&aid=" . urlencode( $validate_key ),
											'NAME'         => $member['members_display_name'],
											'MAN_LINK'     => $this->settings['base_url'] . "app=core&module=global&section=register&do=05",
											'EMAIL'        => $member['email'],
											'ID'           => $member['member_id'],
											'CODE'         => $validate_key );
					
						IPSText::getTextClass('email')->setPlainTextTemplate( IPSText::getTextClass('email')->getTemplate( "reg_validate" ) );
						IPSText::getTextClass('email')->buildPlainTextContent( $message );											
						IPSText::getTextClass('email')->buildHtmlContent( $message );
				
						IPSText::getTextClass('email')->subject = sprintf( $this->lang->words['new_registration_email'], $this->settings['board_name'] );
						IPSText::getTextClass('email')->to      = $member['email'];
						
						IPSText::getTextClass('email')->sendMail();
						//$this->output     = $this->registry->output->getTemplate('register')->showAuthorize( $member );
						//@todo
						$this->tt_result_text = sprintf($this->lang->words['auth_text'], $member['members_display_name'],$member['email']);
						$this->tt_result_text = str_replace('<br />', " ", $this->tt_result_text);
						$this->tt_result_text;
					}	
					else 
					{					
						$this->request['uid'] = urlencode( $member['member_id'] );
						$this->request['aid'] = urlencode( $validate_key );
						$this->autoValidate();
					}
				}
				else if( $this->settings['reg_auth_type'] == 'admin' )
				{
					$this->tt_result_text = $this->lang->words['preview_reg_text'];
				}
				
				/* Only send new registration email if the member wasn't banned */
				if( $this->settings['new_reg_notify'] AND ! $member['member_banned'] )
				{
					$date = $this->registry->class_localization->getDate( time(), 'LONG', 1 );
					
					IPSText::getTextClass('email')->getTemplate( 'admin_newuser' );
					
					IPSText::getTextClass('email')->buildMessage( array( 'DATE'			=> $date,
																		 'LOG_IN_NAME'  => $member['name'],
																		 'EMAIL'		=> $member['email'],
																		 'IP'			=> $member['ip_address'],
																		 'DISPLAY_NAME'	=> $member['members_display_name'] ) );
																 
					IPSText::getTextClass('email')->subject = sprintf( $this->lang->words['new_registration_email1'], $this->settings['board_name'] );
					IPSText::getTextClass('email')->to      = $this->settings['email_in'];
					IPSText::getTextClass('email')->sendMail();
				}
				
				//$this->registry->output->setTitle( $this->lang->words['reg_success'] . ' - ' . ipsRegistry::$settings['board_name'] );
				//$this->registry->output->addNavigation( $this->lang->words['nav_reg'], '' );
			}
			else
			{
				/* We don't want to preview, or get them to validate via email. */
				$stat_cache = $this->cache->getCache('stats');
				
				if( $member['members_display_name'] AND $member['member_id'] AND !$this->caches['group_cache'][ $member['member_group_id'] ]['g_hide_online_list'] )
				{
					$stat_cache['last_mem_name']		= $member['members_display_name'];
					$stat_cache['last_mem_name_seo']	= IPSText::makeSeoTitle( $member['members_display_name'] );
					$stat_cache['last_mem_id']			= $member['member_id'];
				}
				
				$stat_cache['mem_count'] += 1;
				
				$this->cache->setCache( 'stats', $stat_cache, array( 'array' => 1 ) );
				
				/* Only send new registration email if the member wasn't banned */
				if( $this->settings['new_reg_notify'] AND ! $member['member_banned'] )
				{
					$date = $this->registry->class_localization->getDate( time(), 'LONG', 1 );
					
					IPSText::getTextClass('email')->getTemplate( 'admin_newuser' );
					
					IPSText::getTextClass('email')->buildMessage( array( 'DATE'			=> $date,
																		 'LOG_IN_NAME'  => $member['name'],
																		 'EMAIL'		=> $member['email'],
																		 'IP'			=> $member['ip_address'],
																		 'DISPLAY_NAME'	=> $member['members_display_name'] ) );
												
					IPSText::getTextClass('email')->subject = sprintf( $this->lang->words['new_registration_email1'], $this->settings['board_name'] );
					IPSText::getTextClass('email')->to      = $this->settings['email_in'];
					IPSText::getTextClass('email')->sendMail();
				}

				IPSCookie::set( 'pass_hash'   , $member['member_login_key'], 1);
				IPSCookie::set( 'member_id'   , $member['member_id']       , 1);
				
				//-----------------------------------------
				// Fix up session
				//-----------------------------------------
				
				$privacy = ( $member['g_hide_online_list'] || ( empty($this->settings['disable_anonymous']) && ! empty($this->request['Privacy']) ) ) ? 1 : 0;
				
				# Update value for onCompleteAccount call
				$member['login_anonymous'] = $privacy . '&1';
				
				$this->member->sessionClass()->convertGuestToMember( array( 'member_name'	=> $member['members_display_name'],
																  			'member_id'		=> $member['member_id'],
																			'member_group'	=> $member['member_group_id'],
																			'login_type'	=> $privacy ) );
				
				IPSLib::runMemberSync( 'onCompleteAccount', $member );

				//$this->registry->output->silentRedirect( $this->settings['base_url'] . '&app=core&module=global&section=login&do=autologin&fromreg=1');
			}
		}
		else
		{
			/* This is a COPPA user, so lets tell them they registered OK and redirect to the form. */
			$this->DB->insert( 'validating', array (
												  'vid'         => $validate_key,
												  'member_id'   => $member['member_id'],
												  'real_group'  => $this->settings['member_group'],
												  'temp_group'  => $this->settings['auth_group'],
												  'entry_date'  => $time,
												  'coppa_user'  => $coppa,
												  'new_reg'     => 1,
												  'ip_address'  => $member['ip_address']
										)       );
			
			//$this->registry->output->redirectScreen( $this->lang->words['cp_success'], $this->settings['base_url'] . 'app=core&amp;module=global&amp;section=register&amp;do=12' );
		}
	}
	/*
	 * Validation completion.  This is the action hit when a user clicks a validation link from their email for
	 * lost password, email change and new registration.
	 *
	 * @return	@e void
	 */
	protected function autoValidate()
 	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$in_user_id			= intval(trim(urldecode($this->request['uid'])));
		$in_validate_key	= substr( IPSText::alphanumericalClean( urldecode( $this->request['aid'] ) ), 0, 32 );
		$in_type			= trim($this->request['type']);
		$in_type			= $in_type ? $in_type : 'reg';

		//-----------------------------------------
		// Attempt to get the profile of the requesting user
		//-----------------------------------------
		
		$member = IPSMember::load( $in_user_id, 'members' );
			
		if ( ! $member['member_id'] )
		{
			$this->_showManualForm( $in_type, 'reg_error_validate' );
			return;
		}
		
		//-----------------------------------------
		// Get validating info..
		//-----------------------------------------
		
		if ( $in_type == 'lostpass' )
		{
			$validate = $this->DB->buildAndFetch( array( 'select' => '*', 'from' => 'validating', 'where' => 'member_id=' . $in_user_id . " AND lost_pass=1" ) );
		}
		else if ( $in_type == 'newemail' )
		{
			$validate = $this->DB->buildAndFetch( array( 'select' => '*', 'from' => 'validating', 'where' => 'member_id=' . $in_user_id . " AND email_chg=1" ) );
		}
		else
		{
			$validate = $this->DB->buildAndFetch( array( 'select' => '*', 'from' => 'validating', 'where' => 'member_id=' . $in_user_id ) );
		}
		
		//-----------------------------------------
		// Checks...
		//-----------------------------------------
		
		if ( ! $validate['member_id'] )
		{
			get_error( 'no_validate_key', 10120 );
		}
		
		//-----------------------------------------
		// Changed to check if this is an admin flagged
		//	account for a spam user too
		//-----------------------------------------
		
		if ( ( $validate['new_reg'] == 1 ) && (($this->settings['reg_auth_type'] == "admin" ) || $validate['spam_flag']) ) 
		{ 
			get_error( 'validate_admin_turn', 10121 );
		} 

		if ( $validate['vid'] != $in_validate_key )
		{
			get_error( 'validation_key_invalid', 10122 );
		}
		
		//-----------------------------------------
		// Captcha (from posted form, not GET)
		//-----------------------------------------
		
		if ( $this->settings['use_captcha'] AND $this->request['uid'] )
		{
			if ( $this->registry->getClass('class_captcha')->validate( $this->request['captcha_unique_id'], $this->request['captcha_input'] ) !== TRUE )
			{
				$this->_showManualForm( $in_type, 'reg_error_anti_spam' );
				return;
			}
		}
		//-----------------------------------------
		// REGISTER VALIDATE
		//-----------------------------------------
		
		if ( $validate['new_reg'] == 1 )
		{
			if( $member['member_group_id'] == $this->settings['auth_group'] )
			{
				if ( ! $validate['real_group'] )
				{
					$validate['real_group'] = $this->settings['member_group'];
				}
				else if( !isset( $this->caches['group_cache'][ $validate['real_group'] ] ) )
				{
					$validate['real_group'] = $this->settings['member_group'];
				}
			}
			
			//-----------------------------------------
			// SELF-VERIFICATION...
			// 12.14.2009 Changed from != 'admin_user' to
			//	be more inclusive (just self-verification only)
			//-----------------------------------------
			
			if ( $this->settings['reg_auth_type'] == 'user' )
			{
				if( $member['member_group_id'] == $this->settings['auth_group'] )
				{
					IPSMember::save( $member['member_id'], array( 'members' => array( 'member_group_id' => $validate['real_group'] ) ) );
				}
				
				/* Reset newest member */
				$stat_cache	 = $this->caches['stats'];
				
				if( $member['members_display_name'] AND $member['member_id'] AND !$this->caches['group_cache'][ $validate['real_group'] ]['g_hide_online_list'] )
				{
					$stat_cache['last_mem_name']		= $member['members_display_name'];
					$stat_cache['last_mem_name_seo']	= IPSText::makeSeoTitle( $member['members_display_name'] );
					$stat_cache['last_mem_id']			= $member['member_id'];
				}

				$stat_cache['mem_count'] += 1;
				
				$this->cache->setCache( 'stats', $stat_cache, array( 'array' => 1 ) );
				
				//-----------------------------------------
				// Remove "dead" validation
				//-----------------------------------------

				$this->DB->delete( 'validating', "vid='" . $validate['vid'] . "'" );
				
				IPSLib::runMemberSync( 'onCompleteAccount', $member );
				
				//$this->registry->output->silentRedirect( $this->settings['base_url'] . '&app=core&module=global&section=login&do=autologin&fromreg=1' );
			}
			
			//-----------------------------------------
			// ADMIN-VERIFICATION...
			//-----------------------------------------
			
			else
			{
				//-----------------------------------------
				// Update DB row...
				//-----------------------------------------
				
				$this->DB->update( 'validating', array( 'user_verified' => 1 ), 'vid=\'' . $validate['vid'] . '\'' );
				
				//-----------------------------------------
				// Print message
				//-----------------------------------------
				
				//$this->registry->output->setTitle( $this->lang->words['validation_complete'] . ' - ' . ipsRegistry::$settings['board_name'] );
				
				//$this->output = $this->registry->getClass('output')->getTemplate('register')->showPreview( $member );
			}
		}
		
		//-----------------------------------------
		// LOST PASS VALIDATE
		//-----------------------------------------
		
		else if ( $validate['lost_pass'] == 1 )
		{
			//-----------------------------------------
			// INIT
			//-----------------------------------------
			
			$save_array = array();
			
			//-----------------------------------------
			// Generate a new random password
			//-----------------------------------------
			
			$new_pass = IPSMember::makePassword();
			
			//-----------------------------------------
			// Generate a new salt
			//-----------------------------------------
			
			$salt = IPSMember::generatePasswordSalt(5);
			$salt = str_replace( '\\', "\\\\", $salt );
			
			//-----------------------------------------
			// New log in key
			//-----------------------------------------
			
			$key  = IPSMember::generateAutoLoginKey();
			
			//-----------------------------------------
			// Update...
			//-----------------------------------------
			
			$save_array['members_pass_salt']		= $salt;
			$save_array['members_pass_hash']		= md5( md5($salt) . md5( $new_pass ) );
			$save_array['member_login_key']			= $key;
			$save_array['member_login_key_expire']	= $this->settings['login_key_expire'] * 60 * 60 * 24;
			
	        //-----------------------------------------
	    	// Load handler...
	    	//-----------------------------------------
	    	
	    	$classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/handlers/han_login.php', 'han_login' );
	    	$this->han_login =  new $classToLoad( $this->registry );
	    	$this->han_login->init();
	    	$this->han_login->changePass( $member['email'], md5( $new_pass ), $new_pass, $member );
	    	
	    	if ( $this->han_login->return_code != 'METHOD_NOT_DEFINED' AND $this->han_login->return_code != 'SUCCESS' )
	    	{
				$this->registry->output->showError( 'lostpass_external_fail', 2015, true );
	    	}
			
	    	IPSMember::save( $member['member_id'], array( 'members' => $save_array ) );

			//-----------------------------------------
			// Send out the email...
			//-----------------------------------------
			
			IPSText::getTextClass('email')->getTemplate("lost_pass_email_pass", $member['language']);
				
			IPSText::getTextClass('email')->buildMessage( array(
															'NAME'		=> $member['members_display_name'],
															'THE_LINK'	=> $this->settings['base_url'] . 'app=core&module=usercp&tab=core&area=email',
															'PASSWORD'	=> $new_pass,
															'LOGIN'		=> $this->settings['base_url'] . 'app=core&module=global&section=login',
															'USERNAME'	=> $member['name'],
															'EMAIL'		=> $member['email'],
															'ID'		=> $member['member_id'],
														)
													);
										
			IPSText::getTextClass('email')->subject = $this->lang->words['lp_random_pass_subject'] . ' ' . $this->settings['board_name'];
			IPSText::getTextClass('email')->to      = $member['email'];
			
			IPSText::getTextClass('email')->sendMail();

			//$this->registry->output->setTitle( $this->lang->words['validation_complete'] . ' - ' . ipsRegistry::$settings['board_name'] );
			
			//-----------------------------------------
			// Remove "dead" validation
			//-----------------------------------------
			
			$this->DB->delete( 'validating', "vid='" . $validate['vid'] . "' OR (member_id={$member['member_id']} AND lost_pass=1)" );

			//$this->output = $this->registry->getClass('output')->getTemplate('register')->showLostPassWaitRandom( $member );
		}
		
		//-----------------------------------------
		// EMAIL ADDY CHANGE
		//-----------------------------------------
		
		else if ( $validate['email_chg'] == 1 )
		{
			if ( !$validate['real_group'] )
			{
				$validate['real_group'] = $this->settings['member_group'];
			}
			else if( !isset( $this->caches['group_cache'][ $validate['real_group'] ] ) )
			{
				$validate['real_group'] = $this->settings['member_group'];
			}
			
			IPSMember::save( $member['member_id'], array( 'members' => array( 'member_group_id' => intval($validate['real_group']) ) ) );

			IPSCookie::set( "member_id", $member['member_id']		, 1 );
			IPSCookie::set( "pass_hash", $member['member_login_key'], 1 );
			
			//-----------------------------------------
			// Remove "dead" validation
			//-----------------------------------------
			
			$this->DB->delete( 'validating', "vid='" . $validate['vid'] . "' OR (member_id={$member['member_id']} AND email_chg=1)" );
			
			//$this->registry->output->silentRedirect( $this->settings['base_url'].'&app=core&module=global&section=login&do=autologin&fromemail=1' );
		}
	}
	
}