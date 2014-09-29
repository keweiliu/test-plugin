<?php
defined('IN_MOBIQUO') or exit;
require_once('./global.php');
require_once(DIR . '/includes/functions_misc.php');

function sign_in_func($xmlrpc_params)
{
    global $vbulletin, $tt_config, $backup_forceoptions, $db;

    $params = php_xmlrpc_decode($xmlrpc_params);

    $need_email_verification = true;

    $_POST['token'] = mobiquo_encode($params[0], 'to_local');
    $_POST['code'] = mobiquo_encode($params[1], 'to_local');
    $_POST['email'] = mobiquo_encode($params[2], 'to_local');
    $_POST['username'] = mobiquo_encode($params[3], 'to_local');
    $_POST['password'] = mobiquo_encode($params[4], 'to_local');
    $_POST['password_md5'] = md5($_POST['password']);
    $_POST['agree'] = 1;

    $tapatalk_directory = empty($vbulletin->options['tapatalk_directory']) ? 'mobiquo' : $vbulletin->options['tapatalk_directory'];
    include_once(DIR.'/'.$tapatalk_directory.'/include/function_push.php');

    if(!empty($_POST['token']))
        $email_response = getEmailFromScription($_POST['token'], $_POST['code'], $vbulletin->options['push_key']);

    $response_verified = $email_response['result'] && isset($email_response['email']) && !empty($email_response['email']);
    if(!$response_verified)
    {
        if(!isset($vbulletin->options['push_key']) || empty($vbulletin->options['push_key']))
            return return_fault('Sorry, this community has not yet full configured to work with Tapatalk, this feature has been disabled.');
        else if(empty($email_response))
            return return_fault('Single Sign-On feature is not setup correctly with this community. Please contact your administrator if problem persists.');
        else
            return return_fault(isset($email_response['result_text'])? $email_response['result_text'] : 'Tapatalk ID session expired, please re-login Tapatalk ID and try again, if the problem persist please tell us.');
    }
    // Sign in logic
    if(!empty($_POST['email']))
    {
        if($email_response['email'] == $_POST['email'])
        {
            $user = get_user_by_NameorEmail($_POST['email']);
            if(isset($user['userid']) && !empty($user['userid']))
            {
                return login_user($user);
            }
            else
            {
                if(!empty($_POST['username']))
                {
                    $user = get_user_by_NameorEmail($_POST['username']);
                    $username_exist = isset($user['userid']) && !empty($user['userid']);
                    
                    //gavatar? vb don't support
                    if(!$tt_config['sso_signin']) return return_fault('Application Error : social sign in is not supported currently.');

                    //formating custom fields    
                    $custom_fields = $params[5];
                    if(!empty($custom_fields))
                    {
                        foreach($custom_fields as $filed_name => $field_value)
                        {
                            if(is_array($field_value))
                            {
                                $orgnized_value = array();
                                foreach($field_value as $key => $value)
                                {
                                    $orgnized_value[] = $key;
                                }
                                $custom_fields[$filed_name] = $orgnized_value;
                            }
                            else
                            {
                                $custom_fields[$filed_name] = mobiquo_encode($field_value, 'to_local');
                            }
                            $custom_fields[$filed_name.'_set'] = 1;
                        }
                        $profilefields = $db->query_read_slave("
                    		SELECT *
                    		FROM " . TABLE_PREFIX . "profilefield
                    		WHERE editable > 0 AND required <> 0
                    		ORDER BY displayorder
                    	");
                    	while ($profilefield = $db->fetch_array($profilefields))
                    	{
                    	    $profilefieldname = "field$profilefield[profilefieldid]";
                    		if ($profilefield['type'] == 'radio' OR $profilefield['type'] == 'select')
                    		{
                    		    if(isset($custom_fields[$profilefieldname]))
                    		    {
                    		        $custom_fields[$profilefieldname] = $custom_fields[$profilefieldname][0];
                    		    }
                    		}
                    		if(isset($custom_fields[$profilefieldname]) && $profilefield['regex'])
                    		{//$profilefield['title'])
    				            if (!preg_match('#' . str_replace('#', '\#', $profilefield['regex']) . '#siU', $custom_fields[$profilefieldname]))
                                {
                    		        $profilefield['title'] = fetch_phrase($profilefieldname . '_title', 'cprofilefield');
                                    eval(standard_error(fetch_error('regexincorrect', $profilefield['title'])));
                                }
                    		}
                    		
                    	}
                        $_POST['userfield'] = $custom_fields;
                    }
                    $reg_response = register_user(false, false, $email_response['profile']);

                    if(is_array($reg_response))
                    {
                        list($user_id, $result_text) = $reg_response;
        
                        if($user_id != 0) 
                        {
                            // register succeed, try to add custom avatar
                            if(isset($email_response['profile']['avatar_url'])&& !empty($email_response['profile']['avatar_url']))
                            {
                                try
                                {
                                    $_POST['userid'] = $user_id;
                                    $_POST['avatarid'] = 0;
                                    $_POST['avatarurl'] = $email_response['profile']['avatar_url'];
                                    $_POST['resize'] = 1;
                                    $vbulletin->input->clean_array_gpc('p', array(
                                        'userid'    => TYPE_UINT,
                                        'avatarid'  => TYPE_INT,
                                        'avatarurl' => TYPE_STR,
                                        'resize'    => TYPE_BOOL,
                                    ));
                                        $useavatar = iif($vbulletin->GPC['avatarid'] == -1, 0, 1);

                                    $userinfo = fetch_userinfo($vbulletin->GPC['userid']);

                                    // init user datamanager
                                    $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
                                    $userdata->set_existing($userinfo);

                                    // custom avatar
                                    $vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);
                        
                                    require_once(DIR . '/includes/class_upload.php');
                                    require_once(DIR . '/includes/class_image.php');
                        
                                    $upload = new vB_Upload_Userpic($vbulletin);
                        
                                    $upload->data =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_CP, 'userpic');
                                    $upload->image =& vB_Image::fetch_library($vbulletin);
                                    $upload->userinfo =& $userinfo;
                        
                                    cache_permissions($userinfo, false);
                        
                                    // user's group doesn't have permission to use custom avatars
                                    if ($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'])
                                    {
                                        if ($vbulletin->GPC['resize'])
                                        {
                                            $upload->maxwidth = $userinfo['permissions']['avatarmaxwidth'];
                                            $upload->maxheight = $userinfo['permissions']['avatarmaxheight'];
                                        }
                                        if ($upload->process_upload($vbulletin->GPC['avatarurl']))
                                        {
                                            $userdata->set('avatarid', $vbulletin->GPC['avatarid']);
                                            $userdata->save();
                                        }
                                    }
                                }
                                catch(Exception $e){}
                            }
                            
                            $user = mobiquo_verify_id('user', $user_id, 0, 1);
                            return login_user($user, true); // login if registered
                        }
                        else
                        {
                            return error_status($username_exist ? '1' : '0', $result_text);
                        }
                    }
                    else
                    {
                        $result_text = (string) $reg_response;
                        return error_status($username_exist ? '1' : '0', $result_text);
                    }
                }
                else
                {
                    return error_status(2);
                }
            }
        }
        else
        {
            return error_status(3);
        }
    }
    else if(!empty($_POST['username']))
    {
        $user = get_user_by_NameorEmail($_POST['username']);

        if(isset($user['userid']) && !empty($user['userid']) && $user['email'] == $email_response['email'])
        {
            return login_user($user);
        }
        else
        {
            return error_status(3);
        }
    }
    else
    {
        return return_fault('Application Error : either email or username should provided.');
    }

}

function error_status($status = 0, $result_text = '')
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'status'        => new xmlrpcval($status, 'string'),
        'result_text'   => new xmlrpcval($result_text, 'base64'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function login_user($user, $new_register = false)
{
    global $vbulletin, $tt_config, $backup_forceoptions, $db;

    
    $username = $user['username'];
    $password = $user['password'];
    $vbulletin->userinfo = $user;
    $vbulletin->GPC['logintype'] = null;
    require_once(DIR . '/includes/functions_login.php');
    require_once(DIR . '/includes/functions_user.php');
    require_once(DIR . '/includes/functions_misc.php');

    if (true)
    {
        $return = array();
        $vbulletin->GPC['username'] =$username;
        if(strlen($password) == 32){
            $vbulletin->GPC['md5password'] = $password;
            $vbulletin->GPC['md5password_utf'] = $password;
        } else {
            $vbulletin->GPC['password'] = $password;
        }

        if ($vbulletin->GPC['username'] == '')
        {
            return_fault('You have entered an invalid username or password.');
        }


        // make sure our user info stays as whoever we were (for example, we might be logged in via cookies already)
        $original_userinfo = $vbulletin->userinfo;

        exec_unstrike_user($vbulletin->GPC['username']);

        $member_groups = preg_split("/,/", $vbulletin->userinfo['membergroupids']);
        $group_block = false;

        if(trim($tt_config['allowed_usergroup']) != '')
        {
            $group_block = true;
            $support_group = explode(",", $tt_config['allowed_usergroup']);

            foreach($support_group as $support_group_id)
            {
                $support_group_id = trim($support_group_id);
                if($vbulletin->userinfo['usergroupid'] == $support_group_id || in_array($support_group_id, $member_groups))
                {
                    $group_block = false;
                }
            }
        }

        $return_group_ids = array();
        foreach($member_groups AS $id)
        {
            if($id)
            {
                array_push($return_group_ids, new xmlrpcval($id, 'string'));
            }
        }
        array_push($return_group_ids,new xmlrpcval($vbulletin->userinfo['usergroupid'], 'string'));

        if($group_block)
        {
            $return_text = 'The usergroup you belong to does not have permission to login. Please contact your administrator.';
            $return = new xmlrpcresp(
                new xmlrpcval(
                    array(
                        'result'      => new xmlrpcval(false,  'boolean'),
                        'result_text' => new xmlrpcval(mobiquo_encode($return_text), 'base64'),
                    ), 'struct'
                )
            );
        }
        else
        {
            process_new_login($vbulletin->GPC['logintype'], $vbulletin->GPC['cookieuser'], $vbulletin->GPC['cssprefs']);
            $vbulletin->session->save();
            $permissions = cache_permissions($vbulletin->userinfo);
            $pmcount = $vbulletin->db->query_first("
                SELECT COUNT(pmid) AS pmtotal
                FROM " . TABLE_PREFIX . "pm AS pm
                WHERE pm.userid = '" . $vbulletin->userinfo['userid'] . "'
            ");

            $pmcount['pmtotal'] = intval($pmcount['pmtotal']);
            $show['pmmainlink'] = ($vbulletin->options['enablepms'] AND ($vbulletin->userinfo['permissions']['pmquota'] OR $pmcount['pmtotal']));
            $show['pmtracklink'] = ($vbulletin->userinfo['permissions']['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm']);
            $show['pmsendlink'] = ($vbulletin->userinfo['permissions']['pmquota']);
            if (!($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])){
                if (!($vbulletin->usergroupcache["$usergroupid"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
                {
            		$reason = $vbulletin->db->query_first_slave("
            			SELECT reason, liftdate
            			FROM " . TABLE_PREFIX . "userban
            			WHERE userid = " . $vbulletin->userinfo['userid']
            		);
            
            		// Check for a date or a perm ban
            		if ($reason['liftdate'])
            		{
            			$date = vbdate($vbulletin->options['dateformat'] . ', ' . $vbulletin->options['timeformat'], $reason['liftdate']);
            		}
            		else
            		{
            			$date = $vbphrase['never'];
            		}
            
            		if (!$reason['reason'])
            		{
            			$reason['reason'] = fetch_phrase('no_reason_specified', 'error');
            		}
                    $result_text = mobiquo_encode(fetch_error('nopermission_banned', $reason['reason'], $date));
                } else {
                    $result_text = "You do not have permission to access this forum.";
                }
            }

            $fetch_userinfo_options = (
                FETCH_USERINFO_AVATAR | FETCH_USERINFO_LOCATION |
                FETCH_USERINFO_PROFILEPIC | FETCH_USERINFO_SIGPIC |
                FETCH_USERINFO_USERCSS | FETCH_USERINFO_ISFRIEND
            );
            $mobiquo_userinfo = mobiquo_verify_id('user', $vbulletin->userinfo['userid'], 0, 1, $fetch_userinfo_options);

            //generate usergroups
            $usergroupstr = $mobiquo_userinfo['usergroupid'];
            if(isset($mobiquo_userinfo['membergroupids']) && !empty($mobiquo_userinfo['membergroupids']))
                $usergroupstr .= ','.$mobiquo_userinfo['membergroupids'];
            $usergroups = explode(',', $usergroupstr);
            //check allow usergroup
            if(isset($vbulletin->options['tp_allow_usergroup']) && !empty($vbulletin->options['tp_allow_usergroup']))
            {
                $allow_tapatalk = false;
                $allow_usergroups = explode(',', $vbulletin->options['tp_allow_usergroup']);
                foreach($usergroups as $group_id)
                {
                    if(in_array($group_id, $allow_usergroups))
                        $allow_tapatalk = true;
                }
                if(!$allow_tapatalk)
                    return return_fault('You are not allowed to login via Tapatalk, please contact your forum administrator.');
            }

            if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['passwordexpires'])
            {
                $passworddaysold = floor((TIMENOW - $mobiquo_userinfo['passworddate']) / 86400);

                if ($passworddaysold >= $vbulletin->userinfo['permissions']['passwordexpires'])
                {
                    $result_text = "Your password is ".$passworddaysold." days old, and has therefore expired.";
                }
            }

            if (isset($decode_params[2]) && $decode_params[2])
            {
                return new xmlrpcresp(new xmlrpcval(array(
                    'result'        => new xmlrpcval(true, 'boolean'),
                    'result_text'   => new xmlrpcval($result_text, 'base64'),
                ), 'struct'));
            }

            require_once(DIR . "/vb/legacy/currentuser.php");
            $current_user = new vB_Legacy_CurrentUser();
            $can_search = $current_user->hasPermission('forumpermissions', 'cansearch') && $vbulletin->options['enablesearches'];

            $max_png_size = $vbulletin->userinfo['attachmentpermissions']['png']['permissions'] ? $vbulletin->userinfo['attachmentpermissions']['png']['size'] : 0;
            $max_jpg_size = $vbulletin->userinfo['attachmentpermissions']['jpeg']['permissions'] ? $vbulletin->userinfo['attachmentpermissions']['jpeg']['size'] : 0;
            $max_attachment = $vbulletin->options['attachlimit'] ? $vbulletin->options['attachlimit'] : 100;
            $can_whosonline = $vbulletin->options['WOLenable'] && $permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonline'];
            $can_upload_avatar = $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'];
            $push_status = array();

            //fake push status
            $push_status = array();
            $supported_types = array(
                'pm'       => 'pm',
                'subscribe'=> 'sub',
                'liked'    => 'like',
                'quote'    => 'quote',
                'newtopic' => 'newtopic',
                'tag'      => 'tag',
            );
            foreach($supported_types as $support_type)
                $push_status[] = new xmlrpcval(array(
                    'name'  => new xmlrpcval($support_type, 'string'),
                    'value' => new xmlrpcval(true, 'boolean')
                ), 'struct');
            $ignore_users = get_ignore_ids($vbulletin->userinfo['userid']);
            if(!empty($ignore_users))
                $ignore_user_ids = implode(',', $ignore_users);

            //force user to read a thread mod 
            if(isset($backup_forceoptions) && !empty($backup_forceoptions))
            {
                $vbulletin->options['forcereadthread_disable_this_script'] = $backup_forceoptions == 'TAPATALK' ? '' : $backup_forceoptions;
                if (!($vbulletin->options['forcereadthread_disable_file'] != '' && in_array(substr($_SERVER['PHP_SELF'], (strrpos($_SERVER['PHP_SELF'], '/') + 1)), explode("\r\n", $vbulletin->options['forcereadthread_disable_file']))) && !($vbulletin->options['forcereadthread_disable_this_script'] != '' && in_array(THIS_SCRIPT, explode("\r\n", $vbulletin->options['forcereadthread_disable_this_script']))))
                {
                    $where_usergroups[] = "force_read_usergroups = ''";
                
                    $force_usergroupids = fetch_membergroupids_array($vbulletin->userinfo);
                
                    foreach ($force_usergroupids AS $force_usergroupid)
                    {
                        $where_usergroups[] = "force_read_usergroups LIKE '%-$force_usergroupid-%'";
                    }
                
                    $where_forums[] = "force_read_forums = ''";
                
                    if ($vbulletin->userinfo['userid'] != 0)
                    {
                        $force_thread = $db->query_first("
                            SELECT *
                            FROM " . TABLE_PREFIX . "thread AS thread
                            LEFT JOIN " . TABLE_PREFIX . "force_read_users AS force_read_users ON (thread.threadid = force_read_users.force_read_threadid AND force_read_users.force_read_userid = '".$vbulletin->userinfo['userid']."')
                            WHERE thread.force_read = '1' AND (thread.force_read_expire_date = '0' OR thread.force_read_expire_date > '".TIMENOW."') AND (". implode(' OR ', $where_usergroups) .") AND (". implode(' OR ', $where_forums) .") AND force_read_users.force_read_userid IS NULL
                            ORDER BY force_read_order ASC
                        ");
                        if ($force_thread)
                        {
                            $force_thread_active = $db->query_first("
                                SELECT *
                                FROM " . TABLE_PREFIX . "thread AS thread
                                WHERE force_read = '1' AND threadid = '$force_thread[threadid]'
                            ");
                            if ($force_thread_active)
                            {
                                $force_forumperms = fetch_permissions($force_thread['forumid']);
                                if (($force_forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) && ($force_forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
                                {
                                    $force_already = $db->query_first("
                                        SELECT *
                                        FROM " . TABLE_PREFIX . "force_read_users AS force_read_users
                                        WHERE force_read_userid = '".$vbulletin->userinfo['userid']."' AND force_read_threadid = '$force_thread[threadid]'
                                    ");
    
                                    if (!($force_already))
                                    {
                                        $db->query_write("
                                            INSERT INTO " . TABLE_PREFIX . "force_read_users
                                                (force_read_userid, force_read_threadid)
                                            VALUES
                                                ('".$vbulletin->userinfo['userid']."', '$force_thread[threadid]')
                                        ");
                                    }
                                    $force_thread_id = $force_thread['threadid'];
                                }
                            }
    
                        }
                    }
                }
            }
            $user_type = get_usertype_by_name(mobiquo_encode($vbulletin->userinfo['username']));
            $return_array = array(
                'result'            => new xmlrpcval(true, 'boolean'),
                'result_text'       => new xmlrpcval($result_text, 'base64'),
                'usergroup_id'      => new xmlrpcval($return_group_ids, 'array'),
                'register'          => new xmlrpcval($new_register, 'boolean'),
                'user_id'           => new xmlrpcval($vbulletin->userinfo['userid'], 'string'),
                'login_name'        => new xmlrpcval(mobiquo_encode($vbulletin->userinfo['username']), 'base64'),
                'display_thread_id' => new xmlrpcval(empty($force_thread_id)? '' : $force_thread_id ,'string'),
                'username'          => new xmlrpcval(mobiquo_encode($vbulletin->userinfo['username']), 'base64'),
                'email'             => new xmlrpcval(mobiquo_encode($mobiquo_userinfo['email']), 'base64'),
                'user_type'          => new xmlrpcval($user_type, 'base64'),
                'icon_url'          => new xmlrpcval(mobiquo_get_user_icon($vbulletin->userinfo['userid']), 'string'),
                'ignore_uids'      => new xmlrpcval($ignore_user_ids, 'string'),
                'post_count'        => new xmlrpcval($mobiquo_userinfo['posts'], 'int'),
                'max_attachment'    => new xmlrpcval($max_attachment, 'int'),
                'max_png_size'      => new xmlrpcval(intval($max_png_size), 'int'),
                'max_jpg_size'      => new xmlrpcval(intval($max_jpg_size), 'int'),
                'can_pm'            => new xmlrpcval($show['pmmainlink'], 'boolean'),
                'can_send_pm'       => new xmlrpcval($show['pmmainlink'] && $show['pmsendlink'], 'boolean'),
                'can_moderate'      => new xmlrpcval(can_moderate(), 'boolean'),
                'can_search'        => new xmlrpcval($can_search, 'boolean'),
                'can_whosonline'    => new xmlrpcval($can_whosonline, 'boolean'),
                'can_upload_avatar' => new xmlrpcval($can_upload_avatar, 'boolean'),
            );
            if($user_type != 'admin' && $user_type != 'mod')
            {
                $return_array['post_countdown'] =  new xmlrpcval($vbulletin->options['floodchecktime'], 'int');
            }
            if(isset($push_status) && !empty($push_status))
                $return_array['push_type'] =  new xmlrpcval($push_status, 'array');
            update_push();

            $return = new xmlrpcresp(new xmlrpcval($return_array, 'struct'));
        }
        
    }
    else
    {
        return_fault('You have entered an invalid username or password.');
    }

    return $return;
}


function getStarndardNameByTableKey($key)
{
    $starndard_key_map = array(
        'conv'     => 'conv',
        'pm'       => 'pm',
        'subscribe'=> 'sub',
        'liked'    => 'like',
        'quote'    => 'quote',
        'newtopic' => 'newtopic',
        'tag'      => 'tag',
//        'announcement'      => 'ann',
    );
    return isset($starndard_key_map[$key])? $starndard_key_map[$key]: '';
}