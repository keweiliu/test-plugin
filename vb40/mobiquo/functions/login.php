<?php
/*======================================================================*\
 || #################################################################### ||
 || # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
 || # This file may not be redistributed in whole or significant part. # ||
 || # This file is part of the Tapatalk package and should not be used # ||
 || # and distributed for any other purpose that is not approved by    # ||
 || # Quoord Systems Ltd.                                              # ||
 || # http://www.tapatalk.com | http://www.tapatalk.com/license.html   # ||
 || #################################################################### ||
 \*======================================================================*/

defined('IN_MOBIQUO') or exit;
require_once(CWD1.'/include/functions_logout_user.php');

$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();

require_once('./global.php');
require_once(DIR . '/includes/functions_login.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_misc.php');


function login_func($params)
{
    return mobiquo_login($params);
}

function login_mod_func($params)
{
    return mobiquo_login($params, 'modcplogin');
}

function mobiquo_login($params, $mode = null)
{
    global $vbulletin, $tt_config, $db, $backup_forceoptions;

    $decode_params = php_xmlrpc_decode($params);
    $username = mobiquo_encode($decode_params[0], 'to_local');
    $username = str_replace('&trade;', chr(153), $username);
    $password = mobiquo_encode($decode_params[1], 'to_local');

    $vbulletin->GPC['logintype'] = $mode;
    if ($username && $password)
    {
        $return = array();
        $vbulletin->GPC['username'] =$username;
        if(strlen($password) == 32){
            $vbulletin->GPC['md5password'] = $password;
            $vbulletin->GPC['md5password_utf'] = $password;
        } else {
            $vbulletin->GPC['password'] = $password;
        }

        $strikes = mobiquo_verify_strike_status($vbulletin->GPC['username']);
        if ($vbulletin->GPC['username'] == '')
        {
            return_fault(mobiquo_encode(fetch_phrase('mb_invalid_login', 'error')));
        }

        if(!$strikes)
        {
            return_fault(mobiquo_encode(fetch_phrase('mb_strikes_full', 'error')));
        }

        // make sure our user info stays as whoever we were (for example, we might be logged in via cookies already)
        $original_userinfo = $vbulletin->userinfo;

        if (!verify_authentication($vbulletin->GPC['username'], $vbulletin->GPC['password'], $vbulletin->GPC['md5password'], $vbulletin->GPC['md5password_utf'], $vbulletin->GPC['cookieuser'], true))
        {
            exec_strike_user($vbulletin->userinfo['username']);
            if ($vbulletin->options['usestrikesystem'])
            {
                $return_text = fetch_error('mb_invalid_login_striks', $strikes['strikes']);
            }
            else
            {
                $return_text= fetch_error('mb_invalid_login');
            }
            $user_exist = get_userid_by_name($vbulletin->GPC['username']);
            $return = array(
                'result'        => new xmlrpcval(false, 'boolean'),
                'result_text'   => new xmlrpcval(mobiquo_encode($return_text), 'base64')
            );

            if(!$user_exist)
                $return['status'] = new xmlrpcval(2, 'string');

            return new xmlrpcresp(new xmlrpcval($return, 'struct'));
        }
        else
        {
            exec_unstrike_user($vbulletin->GPC['username']);

            $member_groups = preg_split("/,/", $vbulletin->userinfo['membergroupids']);

            $return_group_ids = array();
            foreach($member_groups AS $id)
            {
                if($id)
                {
                    array_push($return_group_ids, new xmlrpcval($id, 'string'));
                }
            }
            array_push($return_group_ids,new xmlrpcval($vbulletin->userinfo['usergroupid'], 'string'));

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
                    $result_text = mobiquo_encode(fetch_phrase('mb_no_permission_access', 'error'));
                }
            }

            $fetch_userinfo_options = (
                FETCH_USERINFO_AVATAR | FETCH_USERINFO_LOCATION |
                FETCH_USERINFO_PROFILEPIC | FETCH_USERINFO_SIGPIC |
                FETCH_USERINFO_USERCSS | FETCH_USERINFO_ISFRIEND
            );
            $mobiquo_userinfo = mobiquo_verify_id('user', $vbulletin->userinfo['userid'], 0, 1, $fetch_userinfo_options);

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
            $max_jpg_size = $vbulletin->userinfo['attachmentpermissions']['jpeg']['permissions'] ? $vbulletin->userinfo['attachmentpermissions']['jpeg']['permissions'] : 0;
            if(empty($max_jpg_size)) $max_jpg_size = $vbulletin->userinfo['attachmentpermissions']['jpg']['permissions'] ? $vbulletin->userinfo['attachmentpermissions']['jpg']['size'] : 0;
            $max_attachment = $vbulletin->options['attachlimit'] ? $vbulletin->options['attachlimit'] : 100;
            $can_whosonline = $vbulletin->options['WOLenable'] && $permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonline'];
            $can_upload_avatar = $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'];

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
                'user_id'           => new xmlrpcval($vbulletin->userinfo['userid'], 'string'),
                'login_name'        => new xmlrpcval(mobiquo_encode($vbulletin->userinfo['username']), 'base64'),
                'display_thread_id' => new xmlrpcval(empty($force_thread_id)? '' : $force_thread_id ,'string'),
                'username'          => new xmlrpcval(mobiquo_encode($vbulletin->userinfo['username']), 'base64'),
                'email'             => new xmlrpcval(mobiquo_encode($mobiquo_userinfo['email']), 'base64'),
                'user_type'         => new xmlrpcval($user_type, 'base64'),
                'icon_url'          => new xmlrpcval(mobiquo_get_user_icon($vbulletin->userinfo['userid']), 'string'),
                'ignore_uids'       => new xmlrpcval($ignore_user_ids, 'string'),
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
            if (isset($decode_params[3]) && $decode_params[3])
                update_push();

            $return = new xmlrpcresp(new xmlrpcval($return_array, 'struct'));
        
        }
    }
    else
    {
        return_fault(mobiquo_encode(fetch_phrase('mb_invalid_login', 'error')));
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