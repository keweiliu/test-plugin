<?php

defined('IN_MOBIQUO') or exit;

//require_once (IPS_ROOT_PATH . 'applications/core/modules_public/global/register.php');

class tapatalk_sign_in extends ipsCommand
{
    public $result = true;
    public $result_text = '';
    public $is_register = false;
    protected $_login;
    protected $tapatalk_profile;

    public function doExecute( ipsRegistry $registry )
    {
        $this->registry->class_localization->loadLanguageFile( array( 'public_register' ), 'core' );

        // verify tapatalk token and code first
        $result = tt_register_verify($this->request['tt_token'], $this->request['tt_code']);
        
        if (!$result->result || empty($result->email))
        {
            $this->registry->output->showError( $result->result_text ? $result->result_text : 'Invalid Tapatalk authentication' );
        }
        
        $this->tapatalk_profile = (array)$result->profile;

        // sign in with email or register an account
        if (isset($this->request['EmailAddress']) && !empty($this->request['EmailAddress']))
        {
            $in_email = strtolower( trim( $this->request['EmailAddress'] ) );

            if ($in_email != $result->email)
            {
                $this->registry->output->showError( 'Unmatched email with Tapatalk ID', -3 );
            }

            // email registered, login directly
            if (IPSMember::checkByEmail( $in_email ) === false)
            {
                if( $this->settings['no_reg'] > 0 || $this->settings['tapatalk_reg_type'] > 0 )
                {
                    $this->registry->output->showError( 'registration_disabled' );
                }
                
                $memberData = $this->registerProcessForm();
                $this->is_register = true;
            }
            else
                $memberData = IPSMember::load( $result->email, '', 'email' );
        }
        // sign in with username
        elseif (isset($this->request['name']) && !empty($this->request['name']))
        {
            $memberData = IPSMember::load( $this->request['name'], '', 'username' );

            if (empty($memberData) || $memberData['email'] != $result->email)
            {
                $this->registry->output->showError( 'Unmatched account with Tapatalk ID', -3 );
            }
        }
        // sign in with tapatalk id email as default
        else
        {
            $memberData = IPSMember::load( $result->email, '', 'email' );
            
            if ( empty($memberData) )
            {
                $this->registry->output->showError( 'Invalid parameters', -2 );
            }
        }
        
        $r = $this->_login()->loginWithoutCheckingCredentials( $memberData['member_id'], TRUE );
        if (is_array($r) && $r[1] == urldecode($this->request['return']))
        {
            $this->member->setMember($memberData['member_id']);
            if($memberData['member_banned'] || $memberData['member_group_id'] == $this->settings['banned_group'])
            {
                $this->registry->getClass('class_localization')->loadLanguageFile( array( 'public_error' ), 'core' );
                $this->result_text = $this->lang->words['preview_reg_text'];
            }
        }
        else
        {
            $this->result = false;
        }
    }

    public function registerProcessForm()
    {
        $this->_resetMember();

        $form_errors    = array();
        $coppa          = ( $this->request['coppa_user'] == 1 ) ? 1 : 0;
        $in_password    = trim( $this->request['PassWord'] );
        $in_email       = strtolower( trim( $this->request['EmailAddress'] ) );

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
        if( $custom_fields->error_messages )
        {
            $form_errors['general'] = $custom_fields->error_messages;
        }

        /* Check the email address */
        if ( ! $in_email OR strlen( $in_email ) < 6 OR !IPSText::checkEmailAddress( $in_email ) )
        {
            $form_errors['email'][$this->lang->words['err_invalid_email']] = $this->lang->words['err_invalid_email'];
        }

        if( trim($this->request['PassWord_Check']) != $in_password OR !$in_password )
        {
            $form_errors['password'][$this->lang->words['passwords_not_match']] = $this->lang->words['passwords_not_match'];
        }

        /* Check the username */
        $user_name_status = $this->request['name'] ? 0 : -2;
        $user_check = IPSMember::getFunction()->cleanAndCheckName( $this->request['name'], array(), 'name' );

        if( is_array( $user_check['errors'] ) && count( $user_check['errors'] ) )
        {
            foreach( $user_check['errors'] as $key => $error )
            {
                if ($error == 'reg_error_username_taken') $user_name_status = -1;
                $form_errors['dname'][ $error ] = isset($this->lang->words[ $error ]) ? $this->lang->words[ $error ] : $error;
            }
        }
        
        /* check display name from tapatalk profile */
        if ($this->tapatalk_profile['name'])
        {
            $display_name = trim($this->tapatalk_profile['name']);
            if ($display_name != $this->request['name'])
            {
                $user_check = IPSMember::getFunction()->cleanAndCheckName( $display_name, array(), 'members_display_name' );
                if( !is_array( $user_check['errors'] ) || empty( $user_check['errors'] ) )
                    $this->request['members_display_name'] = $display_name;
            }
        }

        /* Is this email addy taken? */
        if( IPSMember::checkByEmail( $in_email ) == TRUE )
        {
            $form_errors['email'][$this->lang->words['reg_error_email_taken']] = $this->lang->words['reg_error_email_taken'];
        }

        $this->_login()->emailExistsCheck( $in_email );
        if( $this->_login()->return_code AND $this->_login()->return_code != 'METHOD_NOT_DEFINED' AND $this->_login()->return_code != 'EMAIL_NOT_IN_USE' )
        {
            $form_errors['email'][$this->lang->words['reg_error_email_taken']] = $this->lang->words['reg_error_email_taken'];
        }

        /* Are they banned [EMAIL]? */
        if ( IPSMember::isBanned( 'email', $in_email ) === TRUE )
        {
            $form_errors['email'][$this->lang->words['reg_error_email_ban']] = $this->lang->words['reg_error_email_ban'];
        }

        /* CHECK 2: Any errors ? */
        if ( count( $form_errors ) )
        {
            @header('SSO-Error: '.serialize($form_errors));
            $final_errors = array();
            
            foreach($form_errors as $section => $details)
            {
                if (is_array($details))
                {
                    foreach($details as $key => $errormsg)
                    {
                        $final_errors[] = "$errormsg";
                    }
                }
                else
                    $final_errors[] = "$details";
            }
            
            $final_error_msg = implode( "\n", $final_errors );
            
            $this->registry->output->showError( $final_error_msg, $user_name_status );
        }

        // don't need user validation after tapatalk id authorization
        if ($this->settings['reg_auth_type'] == 'user')
            $this->settings['reg_auth_type'] = '';
        else if ($this->settings['reg_auth_type'] == 'admin_user')
            $this->settings['reg_auth_type'] = 'admin';

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
        if ($this->settings['reg_auth_type'] != 'admin' && isset($this->settings['tapatalk_reg_group']) && intval($this->settings['tapatalk_reg_group']))
        {
            $mem_group = intval($this->settings['tapatalk_reg_group']);
        }

        /* Create member */
        $member = array(
                         'name'                     => $this->request['name'],
                         'password'                 => $in_password,
                         'members_display_name'     => $this->request['members_display_name'],
                         'email'                    => $in_email,
                         'member_group_id'          => $mem_group,
                         'joined'                   => time(),
                         'ip_address'               => $this->member->ip_address,
                         'time_offset'              => $this->request['time_offset'],
                         'coppa_user'               => $coppa,
                         'members_auto_dst'         => intval($this->settings['time_dst_auto_correction']),
                         'allow_admin_mails'        => intval( $this->request['allow_admin_mail'] ),
                         'language'                 => $this->member->language_id,
                       );
        
        // sync tapatalk profile
        if ($this->tapatalk_profile['birthday'])
        {
            $birthday = array_map('intval', explode('-', $this->tapatalk_profile['birthday']));
            if (count($birthday) == 3)
            {
                $member['bday_day'] = $birthday[2];
                $member['bday_month'] = $birthday[1];
                $member['bday_year'] = $birthday[0];
            }
        }
        
        $extendedProfile = array();
        if ($this->tapatalk_profile['description'] || $this->tapatalk_profile['signature'])
        {
            $extendedProfile = array(
                'pp_about_me' => to_local($this->tapatalk_profile['description']),
                'signature'   => to_local($this->tapatalk_profile['signature']),
            );
        }
        
        
        /* Spam Service */
        $spamCode   = 0;
        $_spamFlag  = 0;

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
                    $_spamFlag  = 1;
                break;

                /* Approve the account, but ban it */
                case 3:
                    $member['member_banned']            = 1;
                    $member['bw_is_spammer']            = 1;
                    $this->settings['reg_auth_type']    = '';
                break;

                /* Deny registration */
                case 4:
                    @header('Spam-Code: '.$spamCode);
                    @header('Spam-action: '.$action);
                    $this->registry->output->showError( 'spam_denied_account', '100x001', FALSE, '', 200 );
                break;
            }
        }

        //-----------------------------------------
        // Create the account
        //-----------------------------------------

        $member = IPSMember::create( array( 'members' => $member ), FALSE, FALSE, FALSE );
        IPSMember::save( $member['member_id'], array('customFields' => $custom_fields->out_fields, 'extendedProfile' => $extendedProfile));
        if ($this->tapatalk_profile['avatar_url'])
        {
            $this->_importAvatar($member, $this->tapatalk_profile['avatar_url']);
        }
        
        //-----------------------------------------
        // Login handler create account callback
        //-----------------------------------------

        $this->_login()->createAccount( array( 'member_id'              => $member['member_id'],
                                                'email'                 => $member['email'],
                                                'joined'                => $member['joined'],
                                                'password'              => $in_password,
                                                'ip_address'            => $this->member->ip_address,
                                                'username'              => $member['members_display_name'],
                                                'name'                  => $member['name'],
                                                'members_display_name'  => $member['members_display_name'],
                                        )       );

        //-----------------------------------------
        // Validation
        //-----------------------------------------

        $validate_key = md5( IPSMember::makePassword() . time() );
        $time         = time();

        if( $coppa != 1 )
        {
            if( $this->settings['reg_auth_type'] == 'admin' )
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
                                                      'spam_flag'   => $_spamFlag,
                                            )       );

                $this->output = $this->registry->output->getTemplate('register')->showPreview( $member );
                
                /* Only send new registration email if the member wasn't banned */
                if( $this->settings['new_reg_notify'] AND ! $member['member_banned'] )
                {
                    $date = $this->registry->class_localization->getDate( time(), 'LONG', 1 );

                    IPSText::getTextClass('email')->getTemplate( 'admin_newuser' );

                    IPSText::getTextClass('email')->buildMessage( array( 'DATE'         => $date,
                                                                         'LOG_IN_NAME'  => $member['name'],
                                                                         'EMAIL'        => $member['email'],
                                                                         'IP'           => $member['ip_address'],
                                                                         'DISPLAY_NAME' => $member['members_display_name'] ) );

                    IPSText::getTextClass('email')->subject = sprintf( $this->lang->words['new_registration_email1'], $this->settings['board_name'] );
                    IPSText::getTextClass('email')->to      = $this->settings['email_in'];
                    IPSText::getTextClass('email')->sendMail();
                }
            }
            else
            {
                /* We don't want to preview, or get them to validate via email. */
                $stat_cache = $this->cache->getCache('stats');

                if( $member['members_display_name'] AND $member['member_id'] AND !$this->caches['group_cache'][ $member['member_group_id'] ]['g_hide_online_list'] )
                {
                    $stat_cache['last_mem_name']        = $member['members_display_name'];
                    $stat_cache['last_mem_name_seo']    = IPSText::makeSeoTitle( $member['members_display_name'] );
                    $stat_cache['last_mem_id']          = $member['member_id'];
                }

                $stat_cache['mem_count'] += 1;

                $this->cache->setCache( 'stats', $stat_cache, array( 'array' => 1 ) );

                /* Only send new registration email if the member wasn't banned */
                if( $this->settings['new_reg_notify'] AND ! $member['member_banned'] )
                {
                    $date = $this->registry->class_localization->getDate( time(), 'LONG', 1 );

                    IPSText::getTextClass('email')->getTemplate( 'admin_newuser' );

                    IPSText::getTextClass('email')->buildMessage( array( 'DATE'         => $date,
                                                                         'LOG_IN_NAME'  => $member['name'],
                                                                         'EMAIL'        => $member['email'],
                                                                         'IP'           => $member['ip_address'],
                                                                         'DISPLAY_NAME' => $member['members_display_name'] ) );

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

                $this->member->sessionClass()->convertGuestToMember( array( 'member_name'   => $member['members_display_name'],
                                                                            'member_id'     => $member['member_id'],
                                                                            'member_group'  => $member['member_group_id'],
                                                                            'login_type'    => $privacy ) );

                IPSLib::runMemberSync( 'onCompleteAccount', $member );
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
        }
        
        return $member;
    }
    
    
    protected function _resetMember()
    {
        if( $this->memberData['member_id'] )
        {
            //-----------------------------------------
            // Set some cookies
            //-----------------------------------------
            
            IPSCookie::set( "member_id" , "0"  );
            IPSCookie::set( "pass_hash" , "0"  );
            
            if ( is_array($_COOKIE) )
            {
                foreach( $_COOKIE as $cookie => $value)
                {
                    if ( stripos( $cookie, $this->settings['cookie_id']."ipbforum" ) !== false )
                    {
                        IPSCookie::set( str_replace( $this->settings['cookie_id'], "", $cookie ) , '-', -1 );
                    }
                }
            }
        
            //-----------------------------------------
            // Logout callbacks...
            //-----------------------------------------
            
            $this->_login()->logoutCallback( $this->memberData );
            
            //-----------------------------------------
            // Do it..
            //-----------------------------------------
            
            $this->member->sessionClass()->convertMemberToGuest();
        
            $privacy = intval( IPSMember::isLoggedInAnon($this->memberData) );
            
            IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'login_anonymous' => "{$privacy}&0", 'last_activity' => IPS_UNIX_TIME_NOW ) ) );
        }
    }
    
    protected function _login()
    {
        if ( ! is_object( $this->_login ) )
        {
            $classToLoad  = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/handlers/han_login.php', 'han_login' );
            $this->_login = new $classToLoad( $this->registry );
            $this->_login->init();
        }

        return $this->_login;
    }
    
    protected function _importAvatar($member, $url)
    {
        $classToLoad  = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/member/photo.php', 'classes_member_photo' );
        $this->photo = new $classToLoad( $this->registry );
        try
        {
            $url = str_replace('https://', 'http://', $url);
            $photo = $this->photo->save( $member, 'url', '', trim($url) );
        }
        catch( Exception $error ) {}
    }
}
