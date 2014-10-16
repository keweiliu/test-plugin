<?php
/*======================================================================*\
|| #################################################################### ||
|| # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # This file is part of the Tapatalk package and should not be used # ||
|| # and distributed for any other purpose that is not approved by    # ||
|| # Quoord Systems Ltd.                                              # ||
|| # http://www.tapatalk.com | https://tapatalk.com/license.php       # ||
|| #################################################################### ||
\*======================================================================*/
defined('IN_MOBIQUO') or exit;

function advanced_search_func()
{
    global $search_results, $return_search_count, $show_last_post, $registry;
    
    $member = $registry->member()->fetchMemberData();
    
    $items = $search_results['list'];
    $return_list = array();
    
    if($search_results['showtopic'])
    {
        foreach($items as $item)
        {
            $fid = $item['forum_id'];
            
            /* Is it read?  We don't support last_vote in search. */
            $is_read = $registry->getClass( 'classItemMarking' )->isRead( array( 'forumID' => $fid, 'itemID' => $item['tid'], 'itemLastUpdate' => $item['lastupdate'] ? $item['lastupdate'] : $item['updated'] ), 'forums' );
            $is_subscribed = is_subscribed($item['topic_id']);
            $is_closed = $item['state'] == 'closed';
            $is_sticky = $item['pinned'] == 1;
            $is_approved = $item['approved'] > 0;
            $is_deleted = $topic['approved'] == 2;

            $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = false;
            if (in_array($item['topic_archive_status'], array(0, 3)))
            {
                $permission = $member['forumsModeratorData'][ $fid ];
                
                if ($member['g_is_supmod'])
                    $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = true;
                else if ($member['is_mod'])
                {
                    $can_rename = $permission['edit_topic'];
                    $can_move = $permission['move_topic'] && $item['state'] != 'link';
                    $can_delete = $permission['delete_topic'];
                    
                    $can_stick = $is_sticky ? $permission['unpin_topic'] : $permission['pin_topic'];
                    $can_close = $is_closed ? $permission['open_topic'] : $permission['close_topic'];
                    $can_approve = $is_approved ? $registry->getClass('class_forums')->canSoftDeleteTopics( $fid )
                                                : $registry->getClass('class_forums')->can_Un_SoftDeleteTopics( $fid ); // hide
                }
                else if ($member['member_id'] == $item['starter_id'] && $member['g_edit_posts'])
                {
                    if ( $member['g_edit_cutoff'] > 0 )
                    {
                        if ( $item['start_date'] > ( IPS_UNIX_TIME_NOW - ( intval($member['g_edit_cutoff']) * 60 ) ) )
                        {
                            $can_rename = true;
                        }
                    }
                    else
                    {
                        $can_rename = true;
                    }
                }
                
                if ( ( $item['state'] != 'open' ) and ( ! $member['g_is_supmod'] AND ! $permission['edit_post'] ) )
                {
                    if ( $member['g_post_closed'] != 1 )
                    {
                        $can_rename = false;
                    }
                }
            }
            
            $return_thread = array(
                'forum_id'          => new xmlrpcval($fid, 'string'),
                'forum_name'        => new xmlrpcval(subject_clean($registry->class_forums->forum_by_id[ $fid ]['name']), 'base64'),
                'topic_id'          => new xmlrpcval($item['tid'], 'string'),
                'topic_title'       => new xmlrpcval(subject_clean($item['topic_title']), 'base64'),
                // last post time, not first match post time
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($item['last_post']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($item['last_post'], 'string'),
                // first matched post author info
                'post_author_id'    => new xmlrpcval($show_last_post ? $item['last_poster_id'] : $item['author_id'], 'string'),
                'post_author_name'  => new xmlrpcval(subject_clean($show_last_post ? $item['last_poster_name'] : $item['author_name']), 'base64'),
                'icon_url'          => new xmlrpcval($item['pp_main_photo'] , 'string'),
                'user_type'         => new xmlrpcval(check_return_user_type($item), 'base64'),
                // first match post id
                // 'post_id'           => new xmlrpcval($item['pid'], 'string'),
                'short_content'     => new xmlrpcval(get_short_content($item['preview'],0,500), 'base64'),
                
                'reply_number'      => new xmlrpcval(intval($item['posts']), 'int'),
                'view_number'       => new xmlrpcval(intval($item['views']), 'int'),
                'attachment'        => new xmlrpcval($item['topic_hasattach'], 'string'),
                'can_subscribe'     => new xmlrpcval($member['member_id'], 'boolean'),
                'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
            );
            
            if ($item['tags']['prefix'])
            {
                $return_thread['prefix']     = new xmlrpcval(subject_clean($item['tags']['prefix']), 'base64');
                $return_thread['prefix_id']  = new xmlrpcval(subject_clean($item['tags']['prefix']));
            }
            
            if ($is_subscribed) $return_thread['is_subscribed'] = new xmlrpcval(true, 'boolean');
            if (!$is_read)      $return_thread['new_post']      = new xmlrpcval(true, 'boolean');
            if ($can_close)     $return_thread['can_close']     = new xmlrpcval(true, 'boolean');
            if ($is_closed)     $return_thread['is_closed']     = new xmlrpcval(true, 'boolean');
            if ($can_delete)    $return_thread['can_delete']    = new xmlrpcval(true, 'boolean');
            if ($is_deleted)    $return_thread['is_deleted']    = new xmlrpcval(true, 'boolean');
            if ($can_stick)     $return_thread['can_stick']     = new xmlrpcval(true, 'boolean');
            if ($is_sticky)     $return_thread['is_sticky']     = new xmlrpcval(true, 'boolean');
            if ($can_move)      $return_thread['can_move']      = new xmlrpcval(true, 'boolean');
            if ($can_approve)   $return_thread['can_approve']   = new xmlrpcval(true, 'boolean');
            if ($can_rename)    $return_thread['can_rename']    = new xmlrpcval(true, 'boolean');
            
            $return_list[] = new xmlrpcval($return_thread, 'struct');
        }
    }
    else
    {
        foreach($items as $item)
        {
            $fid = $item['forum_id'];
            
            $return_post = array(
                'forum_id'          => new xmlrpcval($fid, 'string'),
                'forum_name'        => new xmlrpcval(subject_clean($registry->class_forums->forum_by_id[ $fid ]['name']), 'base64'),
                'topic_id'          => new xmlrpcval($item['tid'], 'string'),
                'topic_title'       => new xmlrpcval(subject_clean($item['topic_title']), 'base64'),
                'post_id'           => new xmlrpcval($item['pid'], 'string'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($item['post_date']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($item['post_date'], 'string'),
                'post_author_id'    => new xmlrpcval($item['author_id'], 'string'),
                'post_author_name'  => new xmlrpcval(subject_clean($item['author_name']), 'base64'),
                'icon_url'          => new xmlrpcval($item['pp_main_photo'] , 'string'),
                'user_type'         => new xmlrpcval(check_return_user_type($item), 'base64'),
                'short_content'     => new xmlrpcval(get_short_content($item['preview'],0,500), 'base64'),
                
                'is_approved'       => new xmlrpcval($item['_p_isVisible'], 'boolean'),
                'is_deleted'        => new xmlrpcval($item['_p_isDeleted'], 'boolean'),
            );
            
            $return_list[] = new xmlrpcval($return_post, 'struct');
        }
    }

    if ($return_search_count) {
        if($search_results['showtopic']) {
            return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(true, 'boolean'),
                'search_id'         => new xmlrpcval($search_results['sid'], 'string'),
                'total_topic_num'   => new xmlrpcval($search_results['count'], 'int'),
                'topics'            => new xmlrpcval($return_list, 'array'),
            ), 'struct'));
        } else {
            return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(true, 'boolean'),
                'search_id'         => new xmlrpcval($search_results['sid'], 'string'),
                'total_post_num'    => new xmlrpcval($search_results['count'], 'int'),
                'posts'             => new xmlrpcval($return_list, 'array'),
            ), 'struct'));
        }
    } else {
        return new xmlrpcresp(new xmlrpcval($return_list, 'array'));
    }
}

function login_func()
{
    global $tapatalk_controller;
    
    $allowed_file_ext = array();
    $attach_type_info = ipsRegistry::cache()->getCache('attachtypes');
    if (is_array($attach_type_info) && $attach_type_info)
    {
        foreach( $attach_type_info as $idx => $data )
        {
            if ( $data['atype_post'] )
            {
                $allowed_file_ext[] = $data['atype_extension'];
            }
        }
    }
    
    $response = array(
        'result'        => new xmlrpcval($tapatalk_controller->result, 'boolean'),
        'result_text'   => new xmlrpcval(subject_clean($tapatalk_controller->result_text), 'base64'),
    );
    
    // update push status for tapatalk user and set push type
    if ($tapatalk_controller->result)
    {
        $settings = ipsRegistry::$settings;
        $member = ipsRegistry::member()->fetchMemberData();
        
        $push_type = array();
        $table = 'tapatalk_users';
        if( $member['member_id'] && ipsRegistry::DB()->checkForTable( $table ))
        {
            $check = ipsRegistry::DB()->buildAndFetch( array( 'select' => 'userid', 'from' => $table, 'where' => 'userid=' . intval($member['member_id']) ) );
            
            if( !$check['userid'] )
            {
                $data = array('userid' => $member['member_id']);
                ipsRegistry::DB()->insert( $table, $data );
            }
            else
            {
                $data = array('updated' => date("Y-m-d H:i:s"));
                ipsRegistry::DB()->update( $table, $data, 'userid=' . intval($member['member_id']) );
            }
            
            $userPushType = array('conv', 'sub', 'like', 'quote', 'newtopic', 'tag');
            
            foreach ($userPushType as $type)
            {
                $push_type[] = new xmlrpcval(array(
                    'name'  => new xmlrpcval($type, 'string'),
                    'value' => new xmlrpcval(true, 'boolean'),
                ), 'struct');
            }
        }
        
        $groups = array_unique(array_map('intval', explode(',', $member['member_group_id'].','.$member['g_perm_id'])));
        foreach($groups as $gid)
            $group_ids[] = new xmlrpcval($gid);
        
        $max_single_upload = intval(IPSLib::getMaxPostSize());
        
        $ignored_uids = '';
        if ($ignored_users = unserialize($member['ignored_users']))
        {
            $ignored_uid_array = array();
            foreach($ignored_users as $ignored_user)
            {
                if ($ignored_user['ignore_topics'])
                    $ignored_uid_array[] = intval($ignored_user['ignore_ignore_id']);
            }
            
            if ($ignored_uid_array)
                $ignored_uids = implode(',', $ignored_uid_array);
        }
        
        $userType = check_return_user_type($member);
        $response_success = array(
            'user_id'           => new xmlrpcval($member['member_id']),
            'username'          => new xmlrpcval(subject_clean($member['members_display_name']), 'base64'),
            'login_name'        => new xmlrpcval(subject_clean($member['name']), 'base64'),
            'email'             => new xmlrpcval($member['email'], 'base64'),
            'user_type'         => new xmlrpcval($userType,'base64'),
            'usergroup_id'      => new xmlrpcval($group_ids, 'array'),
            'icon_url'          => new xmlrpcval(get_avatar($member)),
            'post_count'        => new xmlrpcval($member['posts'], 'int'),
            
            'can_pm'            => new xmlrpcval($member['members_disable_pm'] == 0 && $member['g_use_pm'], 'boolean'),
            'can_send_pm'       => new xmlrpcval($member['members_disable_pm'] == 0 && $member['g_use_pm'], 'boolean'),
            'can_search'        => new xmlrpcval($settings['allow_search'] && $member['g_use_search'], 'boolean'),
            'can_whosonline'    => new xmlrpcval($settings['allow_online_list'], 'boolean'),
            'allowed_extensions'=> new xmlrpcval(implode(',', $allowed_file_ext), 'string'),
            'max_attachment'    => new xmlrpcval(100, 'int'),
            'max_attachment_size'=>new xmlrpcval($max_single_upload, 'int'),
            'max_png_size'      => new xmlrpcval($max_single_upload, 'int'),
            'max_jpg_size'      => new xmlrpcval($max_single_upload, 'int'),
            'max_avatar_size'   => new xmlrpcval($member['photoMaxKb'], 'int'),
            'max_avatar_width'  => new xmlrpcval($member['photoMaxWidth'], 'int'),
            'max_avatar_height' => new xmlrpcval($member['photoMaxHeight'], 'int'),
            'can_upload_avatar' => new xmlrpcval(isset($settings['avatars_on']) ? $settings['avatars_on'] && $member['g_avatar_upload'] : IPSMember::canUploadPhoto($member), 'boolean'),
            'push_type'         => new xmlrpcval($push_type, 'array'),
            'post_countdown'    => new xmlrpcval($member['g_avoid_flood'] ? 0 : $settings['flood_control'], 'int'),
            
            'can_moderate'      => new xmlrpcval(($member['g_is_supmod'] || $member['access_report_center']), 'boolean'),
            'ignored_uids'      => new xmlrpcval($ignored_uids, 'string'),
        );
        
        if (isset($tapatalk_controller->is_register))
        {
            $response_success['register'] = new xmlrpcval($tapatalk_controller->is_register, 'boolean');
            if ($userType = 'banned'||$member['member_banned'] || in_array($settings['banned_group'],$group_ids))
            {
                $response_success['user_type'] = new xmlrpcval('unapproved','base64');
            }
        }
        
        $response = array_merge($response, $response_success);
    }
    else
    {
        $response['status'] = new xmlrpcval($tapatalk_controller->status, 'string');
    }
    
    return new xmlrpcresp(new xmlrpcval($response, 'struct'));
}

function register_func()
{
     global $result, $result_text;
     $response = new xmlrpcval(array(
        'result'            => new xmlrpcval($result, 'boolean'),
        'result_text'       => new xmlrpcval($result_text, 'base64'),
     ), 'struct');
     return new xmlrpcresp($response);
}

function forget_password_func()
{
     global $result , $result_text ,$verified;
     $response = new xmlrpcval(array(
        'result'            => new xmlrpcval($result, 'boolean'),
        'result_text'       => new xmlrpcval($result_text, 'base64'),
         'verified'          => new xmlrpcval($verified, 'boolean'),
     ), 'struct');
     return new xmlrpcresp($response);
}

function new_topic_func()
{
    global $result, $result_text;

    $xmlrpc_new_topic = new xmlrpcval(array(
        'result'        => new xmlrpcval($result['tid'], 'boolean'),
        'result_text'   => new xmlrpcval(isset($result_text) ? $result_text : '', 'base64'),
        'topic_id'      => new xmlrpcval($result['tid'], 'string'),
        'state'         => new xmlrpcval($result['approved'] ? 0 : 1)
    ), 'struct');

    return new xmlrpcresp($xmlrpc_new_topic);
}

function get_board_stat_func()
{
    global $board_stat;

    $result = array(
        'total_threads' => new xmlrpcval($board_stat['total_topics'], 'int'),
        'total_posts'   => new xmlrpcval($board_stat['total_posts'], 'int'),
        'total_members' => new xmlrpcval($board_stat['mem_count'], 'int'),
        //'active_members'=> new xmlrpcval($board_stat['MEMBERS'] + $board_stat['ANON'], 'int'),
        'guest_online'  => new xmlrpcval($board_stat['GUESTS'], 'int'),
        'total_online'  => new xmlrpcval($board_stat['TOTAL'], 'int'),
    );

    $response = new xmlrpcval($result, 'struct');

    return new xmlrpcresp($response);
}

function get_config_func()
{
    global $mobiquo_config, $registry, $settings;
    
    $member = $registry->member()->fetchMemberData();
    $app_version = trim(ipsRegistry::$applications['forums']['app_version']);
    $temp_array = explode(' ', $app_version, 2);
    $app_version = $temp_array[0];
    
    $config_list = array('sys_version' => new xmlrpcval($app_version, 'string'));
    
    if (isset(ipsRegistry::$settings['tapatalk_push_key']))
    {
        $tapatalkhook = ipsRegistry::DB()->buildAndFetch( array( 'select' => '*', 'from' => 'core_hooks', 'where' => "hook_key='tapatalk'" ) );
        $config_list['hook_version'] = new xmlrpcval($tapatalkhook['hook_version_human']);
        $config_list['hook_code'] = new xmlrpcval($tapatalkhook['hook_version_long']);
        $mobiquo_config['api_key'] = ipsRegistry::$settings['tapatalk_push_key'] ? md5(ipsRegistry::$settings['tapatalk_push_key']) : '';
        
        if (isset($tapatalkhook['hook_enabled']))
        {
            if (!$tapatalkhook['hook_enabled'])
            {
                $mobiquo_config['is_open'] = 0;
                $config_list['result_text'] = new xmlrpcval('Tapatalk hook was disabled in this forum');
            }
            else if ($tapatalkhook['hook_version_long'] != $mobiquo_config['long_version'])
            {
                $config_list['result_text'] = new xmlrpcval("Tapatalk hook file 'tapatalk.xml' was not installed or updated. This may affect some features from working correctly in this forum. Please inform the forum admin to complete/fix the Tapatalk installation.", 'base64');
            }
        }
    }
    else
    {
        $mobiquo_config['is_open'] = 0;
        $config_list['result_text'] = new xmlrpcval('Tapatalk plugin hook file was not installed.');
    }
    
    foreach($mobiquo_config as $key => $value)
    {
        if (in_array($key, array('is_open', 'guest_okay'))) {
            $config_list[$key] = new xmlrpcval($value, 'boolean');
        } else {
            $config_list[$key] = new xmlrpcval(is_array($value) ? serialize($value) : $value, 'string');
        }
    }
    
    if (!$member['member_id'] && $settings['allow_search'] && $member['g_use_search'])
        $config_list['guest_search'] = new xmlrpcval('1', 'string');
    
    if (!$member['member_id'] && $settings['allow_online_list'])
        $config_list['guest_whosonline'] = new xmlrpcval('1', 'string');
        
    if ($settings['board_offline']) {
        $mbqExttTempRow = $registry->DB()->buildAndFetch( array( 'select' => '*', 'from' => 'core_sys_conf_settings', 'where' => "conf_key='offline_msg'" ) );
        $mbqExttTempString = post_html_clean($mbqExttTempRow['conf_value']);
        $mbqExttTempString = preg_replace('/\[.*?\]/i', '', $mbqExttTempString);
        $config_list['result_text'] = new xmlrpcval($mbqExttTempString, 'base64');
    }
    
    if ($settings['show_totals'])
    {
        $stats = $registry->cache()->getCache('stats');
        if (is_array($stats))
        {
            $config_list['stats'] = new xmlrpcval(array(
                'topic' => new xmlrpcval($stats['total_topics'], 'int'),
                'post'  => new xmlrpcval($stats['total_replies'] + $stats['total_topics'], 'int'),
                'user'  => new xmlrpcval($stats['mem_count'], 'int'),
            ), 'struct');
        }
    }
    
    if(isset(ipsRegistry::$settings['tapatalk_dis_ads']))
    {
        $config_list['ads_disabled_group'] = new xmlrpcval(ipsRegistry::$settings['tapatalk_dis_ads'], 'string');
    }
    else
    {
        $config_list['ads_disabled_group'] = new xmlrpcval('0', 'string');
    }
    
    $config_list['guest_group_id'] = new xmlrpcval($settings['guest_group'], 'string');
    $response = new xmlrpcval($config_list, 'struct');

    return new xmlrpcresp($response);
}

function get_forum_func()
{
    global $forum_tree;
    
    $response = new xmlrpcval($forum_tree, 'array');
    return new xmlrpcresp($response);
}

function get_inbox_stat_func()
{
    global $newprvpm;

    $result = new xmlrpcval(array(
        'inbox_unread_count' => new xmlrpcval(intval($newprvpm), 'int'),
        'subscribed_topic_unread_count' => new xmlrpcval(intval(MbqAppEnv::$mbqReturn['subscribed_topic_unread_count']), 'int')
    ), 'struct');

    return new xmlrpcresp($result);
}


function get_online_users_func()
{
    global $online_users;
    $result = array(
        'member_count' => new xmlrpcval($online_users['member_count'], 'int'),
        'guest_count'  => new xmlrpcval($online_users['guest_count'], 'int'),
        'list'         => new xmlrpcval($online_users['list'], 'array')
    );

    $response = new xmlrpcval($result, 'struct');

    return new xmlrpcresp($response);
}

function get_recommended_user_func()
{
    global $tapatalk_controller;
    
    $list = array();
    foreach($tapatalk_controller->recommendUsers as $result)
    {
        $list[] = new xmlrpcval(array (
            'user_id'   => new xmlrpcval($result['member_id']),
            'username'  => new xmlrpcval(subject_clean($result['members_display_name']), 'base64'),
            'icon_url'  => new xmlrpcval(get_avatar($result)),
            'enc_email' => new xmlrpcval(base64_encode($result['encrypt_email'])),
            'score'     => new xmlrpcval($result['score'], 'int'),
        ), 'struct');
    }
    
    $result = array(
        'total' => new xmlrpcval($tapatalk_controller->total, 'int'),
        'list'  => new xmlrpcval($list, 'array')
    );

    return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}

function get_contact_func()
{
    global $tapatalk_controller;
    
    $member = $tapatalk_controller->contact;
    
    if(isset($member['allow_admin_mails']) && $member['allow_admin_mails'])
    {
        $result = array(
            'result'        => new xmlrpcval(true, 'boolean'),
            'user_id'       => new xmlrpcval($member['member_id']),
            'display_name'  => new xmlrpcval(subject_clean($member['members_display_name']), 'base64'),
            'enc_email'     => new xmlrpcval(base64_encode($member['encrypt_email'])),
        );
    }
    else
    {
        $result = array(
            'result' => new xmlrpcval(false, 'boolean'),
            'status' => new xmlrpcval($member ? 1 : 0),
        );
    }
    
    return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}

function get_raw_post_func()
{
    global $postinfo;
    
    $attachments = array();
    if (isset($postinfo['attachments']) && count($postinfo['attachments'])) {
        foreach($postinfo['attachments'] as $attach)
        {
            $xmlrpc_attachment = new xmlrpcval(array(
                'attachment_id' => new xmlrpcval($attach['attach_id'], 'string'),
                'filename'      => new xmlrpcval($attach['filename'], 'base64'),
                'filesize'      => new xmlrpcval($attach['filesize'], 'int'),
                'content_type'  => new xmlrpcval($attach['content_type'], 'string'),
                'url'           => new xmlrpcval(url_encode($attach['url'])),
                'thumbnail_url' => new xmlrpcval(url_encode($attach['thumbnail_url'])),
            ), 'struct');
            $attachments[] = $xmlrpc_attachment;
        }
    }
    
    $response = new xmlrpcval(array(
        'post_id'       => new xmlrpcval($postinfo['post_id']),
        'post_title'    => new xmlrpcval(subject_clean($postinfo['post_title']), 'base64'),
        'post_content'  => new xmlrpcval(subject_clean($postinfo['post_content'], 0), 'base64'),
        'show_reason'   => new xmlrpcval($postinfo['show_reason'], 'boolean'),
        'edit_reason'   => new xmlrpcval(subject_clean($postinfo['edit_reason']), 'base64'),
        'group_id'      => new xmlrpcval($postinfo['group_id'], 'string'),
        'attachments'   => new xmlrpcval($attachments, 'array'),
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function get_subscribed_forum_func()
{
    global $followedItems, $registry;
    
    require_once( IPS_ROOT_PATH . 'sources/classes/like/composite.php' );
    $like = classes_like::bootstrap('forums', 'forums');
    
    $freq_options = array(
        'immediate'=> 1,
        'daily'    => 2,
        'weekly'   => 3,
        'offline'  => 4,
    );
    
    $forums = array();
    $member = $registry->member()->fetchMemberData();
    foreach ($followedItems['items'] as $forum)
    {
        $follow_data = $like->getDataByMemberIdAndRelationshipId($forum['id'], $member['member_id']);
        //$subscribe_mode = $follow_data['like_notify_freq'];
        //$subscribe_mode = empty($subscribe_mode) || !isset($freq_options[$subscribe_mode]) ? 0 : $freq_options[$subscribe_mode];
        
        $xmlrpc_forum = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($forum['id'], 'string'),
            'forum_name'        => new xmlrpcval(subject_clean($forum['name']), 'base64'),
            'new_post'          => new xmlrpcval($forum['_has_unread'], 'boolean'),
            'is_protected'      => new xmlrpcval(isset($forum['password']) && $forum['password'] != '', 'boolean'),
            //'subscribe_mode'    => new xmlrpcval($subscribe_mode, 'int'),
        ), 'struct');
        
        $forums[] = $xmlrpc_forum;
    }
    
    $response = new xmlrpcval(
        array(
            'total_forums_num' => new xmlrpcval($followedItems['total_item_num'], 'int'),
            'forums'           => new xmlrpcval($forums, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}

function get_thread_func()
{
    global $topic_thread;
    
    $responsexmlrpc = new xmlrpcval($topic_thread, 'struct');
    
    return new xmlrpcresp($responsexmlrpc);
}

function get_topic_func()
{
    global $topics;
    
    $response = array(
        'total_topic_num' => new xmlrpcval($topics['total_topic_num'], 'int'),
        'forum_id'        => new xmlrpcval($topics['forum_id']),
        'forum_name'      => new xmlrpcval(subject_clean($topics['forum_name']) , 'base64'),
        'can_post'        => new xmlrpcval($topics['can_post'] ? true : false, 'boolean'),
        'can_upload'      => new xmlrpcval($topics['can_upload'] ? true : false, 'boolean'),
    );
    
    if (isset($topics['require_prefix']))
    {
        $prefixes = array();
        foreach($topics['prefixes'] as $prefix)
        {
            $prefixes[] = new xmlrpcval(array(
                'prefix_id' => new xmlrpcval(subject_clean($prefix)),
                'prefix_display_name' => new xmlrpcval(subject_clean($prefix), 'base64'),
            ), 'struct');
        }
        
        $response['require_prefix'] = new xmlrpcval($topics['require_prefix'], 'boolean');
        $response['prefixes'] = new xmlrpcval($prefixes, 'array');
    }
    
    $response['topics'] = new xmlrpcval($topics['topics'], 'array');
    
    return new xmlrpcresp(new xmlrpcval($response, 'struct'));
}

function prefetch_account_func()
{
    global $profile, $required_custom_fields;
    
    if ($profile)
    {
        $xmlrpc_user_info = array(
            'result'                => new xmlrpcval($profile['member_id'] ? true : false, 'boolean'),
            'user_id'               => new xmlrpcval($profile['member_id']),
            'login_name'            => new xmlrpcval(subject_clean($profile['name']), 'base64'),
            'display_name'          => new xmlrpcval(subject_clean($profile['members_display_name']),'base64'),
            'avatar'                => new xmlrpcval(get_avatar($profile)),
        );
    }
    else
    {
        $xmlrpc_user_info['result'] = new xmlrpcval(false, 'boolean');
    }
    
    if ($required_custom_fields)
    {
        $xmlrpc_user_info['custom_register_fields'] = new xmlrpcval($required_custom_fields, 'array');
    }
    
    return new xmlrpcresp(new xmlrpcval($xmlrpc_user_info, 'struct'));
}

function search_user()
{
    global $tapatalk_controller;
    
    $list = array();
    foreach($tapatalk_controller->results as $result)
    {
        $list[] = new xmlrpcval(array (
            'user_id'  => new xmlrpcval($result['member_id']),
            'username' => new xmlrpcval(subject_clean($result['members_display_name']), 'base64'),
            'icon_url' => new xmlrpcval(get_avatar($result)),
        ), 'struct');
    }
    
    $result = array(
        'total' => new xmlrpcval($tapatalk_controller->total, 'int'),
        'list'  => new xmlrpcval($list, 'array')
    );

    return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}

function get_user_info_func()
{
    global $profile;
    
    $current_member = ipsRegistry::member()->fetchMemberData();
    
    $custom_fields_list = array();
    foreach ($profile['custom_fields_list'] as $data) {
        $custom_fields_list[] = new xmlrpcval(array (
            'name'  => new xmlrpcval(subject_clean($data['name']), 'base64'),
            'value' => new xmlrpcval(subject_clean($data['value']), 'base64'),
        ), 'struct');
    }
    $custom_fields_list[] = new xmlrpcval(array (
        'name'  => new xmlrpcval('Warning Points', 'base64'),
        'value' => new xmlrpcval($profile['warn_level'] , 'base64'),
    ), 'struct');
    
    $accept_pm = $profile['g_use_pm'] && $profile['members_disable_pm'] == 0 && IPSLib::moduleIsEnabled( 'messaging', 'members' );
    $xmlrpc_user_info = array(
        'user_id'               => new xmlrpcval($profile['member_id']),
        'username'              => new xmlrpcval(subject_clean($profile['members_display_name']), 'base64'),
        'user_type'             => new xmlrpcval(check_return_user_type($profile['members_display_name']),'base64'),
        'post_count'            => new xmlrpcval($profile['posts'], 'int'),
        'reg_time'              => new xmlrpcval(mobiquo_iso8601_encode($profile['joined']), 'dateTime.iso8601'),
        'reg_timestamp'         => new xmlrpcval(intval($profile['joined']), 'string'),
        'last_activity_time'    => new xmlrpcval(mobiquo_iso8601_encode($profile['last_activity']), 'dateTime.iso8601'),
        'timestamp'             => new xmlrpcval(intval($profile['last_activity']), 'string'),
        'is_online'             => new xmlrpcval($profile['_online'], 'boolean'),
        'display_text'          => new xmlrpcval(subject_clean($profile['pp_about_me'] ? $profile['pp_about_me'] : $profile['signature']), 'base64'),
        'current_action'        => new xmlrpcval(subject_clean($profile['online_extra']), 'base64'),
        'icon_url'              => new xmlrpcval(get_avatar($profile)),
        'custom_fields_list'    => new xmlrpcval($custom_fields_list, 'array'),
        'accept_pm'             => new xmlrpcval($accept_pm, 'boolean'),
    );
    
    $is_spam = $profile['spamStatus'] === TRUE;
    $can_mark_spam = $profile['spamStatus'] === FALSE && $profile['member_id'] != $current_member['member_id'];
        
    if ($is_spam)       $xmlrpc_user_info['is_spam']        = new xmlrpcval(true, 'boolean');
    if ($can_mark_spam) $xmlrpc_user_info['can_mark_spam']  = new xmlrpcval(true, 'boolean');
    if ($is_spam)       $xmlrpc_user_info['is_ban']         = new xmlrpcval(true, 'boolean');
    if ($can_mark_spam) $xmlrpc_user_info['can_ban']        = new xmlrpcval(true, 'boolean');
    
    return new xmlrpcresp(new xmlrpcval($xmlrpc_user_info, 'struct'));
}

function reply_post_func()
{
    global $result, $result_text;
    
    $xmlrpc_reply_topic = new xmlrpcval(array(
        'result'        => new xmlrpcval($result['pid'], 'boolean'),
        'result_text'   => new xmlrpcval(isset($result_text) ? $result_text : '', 'base64'),
        'post_id'       => new xmlrpcval($result['pid'], 'string'),
        'state'         => new xmlrpcval($result['queued'])
    ), 'struct');

    return new xmlrpcresp($xmlrpc_reply_topic);
}

function get_quote_post_func()
{
    global $quote_post;
    $xmlrpc_quote_post = new xmlrpcval(array(
        'post_id'       => new xmlrpcval($quote_post['post_id'], 'string'),
        'post_title'    => new xmlrpcval(subject_clean($quote_post['post_title']), 'base64'),
        'post_content'  => new xmlrpcval(subject_clean($quote_post['post_content']), 'base64'),
    ), 'struct');

    return new xmlrpcresp($xmlrpc_quote_post);
}

function xmlresptrue()
{
    global $result, $result_text, $tapatalk_controller;
    
    if (empty($result) && $tapatalk_controller)
        $result = $tapatalk_controller->result;
    
    if (empty($result_text) && $tapatalk_controller)
        $result_text = $tapatalk_controller->result_text;
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(subject_clean($result_text), 'base64'),
    ), 'struct');

    return new xmlrpcresp($response);
}

function login_forum_func()
{
    global $login_status;
    
    $response = new xmlrpcval(
        array(
            'result'        => new xmlrpcval($login_status, 'boolean'),
            'result_text'   => new xmlrpcval($login_status ? '' : 'Password is wrong', 'base64'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}


function upload_attach_func()
{
    global $attach_id;
    
    $xmlrpc_result = new xmlrpcval(array(
        'attachment_id' => new xmlrpcval($attach_id),
        'group_id'      => new xmlrpcval($_GET["attach_post_key"]),
        'result'        => new xmlrpcval(empty($attach_id) ? false : true, 'boolean'),
    ), 'struct');
    
    return new xmlrpcresp($xmlrpc_result);
}

function remove_attachment_func()
{
    global $removed;
    
    $xmlrpc_result = new xmlrpcval(array(
        'result'        => new xmlrpcval($removed, 'boolean'),
        'group_id'      => new xmlrpcval($_GET["attach_post_key"]),
    ), 'struct');
    
    return new xmlrpcresp($xmlrpc_result);
}

function get_conversations_func()
{
    global $tapatalk_controller;
    
    $results = $tapatalk_controller->result;
    
    foreach($results['data'] as $conversation)
    {
        $recipients = $conversation['_invitedMemberData'];
        $recipients[] = $conversation['_starterMemberData'];
        $recipients[] = $conversation['_toMemberData'];
        $participants = array();
        foreach($recipients as $recipient)
        {
            if (isset($recipient['member_id']))
            {
                $participants[$recipient['member_id']] = new xmlrpcval(array(
                    'username'  => new xmlrpcval(subject_clean($recipient['members_display_name']), 'base64'),
                    'user_type' => new xmlrpcval(check_return_user_type($recipient['members_display_name']),'base64'),
                    'icon_url'  => new xmlrpcval($recipient['pp_main_photo'], 'string'),
                ), 'struct');
            }
        }
        
        $conversation_list[] = new xmlrpcval(array(
            'conv_id'           => new xmlrpcval($conversation['mt_id'], 'string'),
            'reply_count'       => new xmlrpcval($conversation['mt_replies'], 'string'),    // need change back to int when app side was ready
            'participant_count' => new xmlrpcval(count($participants), 'int'),
            'start_user_id'     => new xmlrpcval($conversation['mt_starter_id'], 'string'),
            'start_conv_time'   => new xmlrpcval(mobiquo_iso8601_encode($conversation['mt_start_time']), 'dateTime.iso8601'),
            'start_timestamp'   => new xmlrpcval(intval($conversation['mt_start_time']), 'string'),
            'last_user_id'      => new xmlrpcval($conversation['_lastMsgAuthor']['member_id'], 'string'),
            'last_conv_time'    => new xmlrpcval(mobiquo_iso8601_encode($conversation['mt_last_post_time']), 'dateTime.iso8601'),
            'timestamp'         => new xmlrpcval(intval($conversation['mt_last_post_time']), 'string'),
            'conv_subject'      => new xmlrpcval(subject_clean($conversation['mt_title']), 'base64'),
            'participants'      => new xmlrpcval($participants, 'struct'),
            'new_post'          => new xmlrpcval($conversation['map_has_unread'], 'boolean'),
            'unread_num'        => new xmlrpcval(intval($conversation['unread_num']), 'int'),
            'is_deleted'        => new xmlrpcval($conversation['mt_is_deleted'], 'boolean'),
        ), 'struct');
    }
    
    $result = new xmlrpcval(array(
        'result'                => new xmlrpcval(true, 'boolean'),
        'conversation_count'    => new xmlrpcval($results['total'], 'int'),
        'unread_count'          => new xmlrpcval($results['unread'], 'int'),
        'can_upload'            => new xmlrpcval($results['can_upload'], 'boolean'),
        'list'                  => new xmlrpcval($conversation_list, 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_conversation_func()
{
    global $tapatalk_controller, $registry;
    
    $results = $tapatalk_controller->result;
    $topicData = $results['topicData'];
    $member = $registry->member()->fetchMemberData();
    
    $message_list = array();
    foreach($results['replyData'] as $message)
    {
        $message_arr = array(
            'msg_id'        => new xmlrpcval($message['msg_id'], 'string'),
            'msg_content'   => new xmlrpcval(post_html_clean($message['msg_post']), 'base64'),
            'post_time'     => new xmlrpcval(mobiquo_iso8601_encode($message['msg_date']), 'dateTime.iso8601'),
            'timestamp'     => new xmlrpcval(intval($message['msg_date']), 'string'),
            'msg_author_id' => new xmlrpcval($message['msg_author_id'], 'string'),
            'can_delete'    => new xmlrpcval($message['_canDelete'], 'boolean'),
            'can_edit'      => new xmlrpcval($message['_canEdit'], 'boolean'),
            'can_report'    => new xmlrpcval($topicData['_canReport'] && $member['member_id'] != $message['msg_author_id'], 'boolean'),
            
            // below two key should be moved to participants structure, not here
            'is_online'     => new xmlrpcval($results['memberData'][$message['msg_author_id']]['_online'], 'boolean'),
            'has_left'      => new xmlrpcval(!$results['memberData'][$message['msg_author_id']]['map_user_active'], 'boolean'),
        );
        
        if (isset($message['attachs']) && count($message['attachs']))
        {
            $attachments = array ();
            foreach($message['attachs'] as $attach)
            {
                $xmlrpc_attachment = new xmlrpcval(array(
                    'content_type'  => new xmlrpcval($attach['attach_is_image'] ? 'image' : $attach['attach_ext']),
                    'thumbnail_url' => new xmlrpcval(isset($attach['attach_thumb_url']) ? url_encode($attach['attach_thumb_url']) : ''),
                    'url'           => new xmlrpcval(url_encode($attach['attach_url'])),
                    'filename'      => new xmlrpcval(subject_clean($attach['attach_file']), 'base64'),
                    'filesize'      => new xmlrpcval(intval($attach['attach_filesize']), 'int'),
                ), 'struct');
                $attachments[] = $xmlrpc_attachment;
            }
            
            $message_arr['attachments'] = new xmlrpcval($attachments, 'array');
        }
        
        $message_list[] = new xmlrpcval($message_arr, 'struct');
    }
    
    $participants = array();
    foreach($results['memberData'] as $recipient)
    {
        if (isset($recipient['member_id']))
        {
            $participants[$recipient['member_id']] = new xmlrpcval(array(
                'username'  => new xmlrpcval(subject_clean($recipient['members_display_name']), 'base64'),
                'user_type' => new xmlrpcval(check_return_user_type($recipient['members_display_name']),'base64'),
                'icon_url'  => new xmlrpcval($recipient['pp_main_photo'], 'string'),
                'is_online' => new xmlrpcval($recipient['_online'], 'boolean'),
                'has_left'  => new xmlrpcval(!$recipient['map_user_active'], 'boolean'),
            ), 'struct');
        }
    }
    
    $g_max_mass_pm = $results['memberData'][$member['member_id']]['g_max_mass_pm'];
    $can_invite = $g_max_mass_pm == 0 || $g_max_mass_pm - count( $participants ) > 0;
    
    $result = new xmlrpcval(array(
        'conv_id'           => new xmlrpcval($topicData['mt_id'], 'string'),
        'conv_title'        => new xmlrpcval(subject_clean($topicData['mt_title']), 'base64'),
        'participant_count' => new xmlrpcval(count($participants), 'int'),
        'total_message_num' => new xmlrpcval($topicData['mt_replies'] + 1, 'int'),
        'can_invite'        => new xmlrpcval($can_invite, 'boolean'),
        'can_reply'         => new xmlrpcval($topicData['_canReply'], 'boolean'),
        'is_deleted'        => new xmlrpcval($topicData['mt_is_deleted'], 'boolean'),
        'can_upload'        => new xmlrpcval($topicData['can_upload'], 'boolean'),
        'participants'      => new xmlrpcval($participants, 'struct'),
        'list'              => new xmlrpcval($message_list, 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_quote_conversation_func()
{
    global $tapatalk_controller;
    
    $result = $tapatalk_controller->result;
    
    $result = new xmlrpcval(array(
        'text_body' => new xmlrpcval(subject_clean($result['message']), 'base64'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_delete_topic_func()
{
    global $tapatalk_controller, $registry;
    
    $result = $tapatalk_controller->result;
    $member = $registry->member()->fetchMemberData();
    
    foreach($result['topics'] as $topic)
    {
        $has_attach = $topic['topic_hasattach'] ? 1 : 0;
        $new_post = $topic['_hasUnread'] ? true : false;
        $is_subscribed = is_subscribed($topic['tid']);
        $can_subscribe = $member['member_id'] ? true : false;
        $is_closed = $topic['state'] == 'closed' ? true : false;
        $is_sticky = $topic['pinned'] == 1;
        $is_approved = $topic['approved'] > 0;
        $is_deleted = $topic['approved'] == 2;
        
        if (!in_array($topic['topic_archive_status'], array(0, 3)))
            $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = false;
        else if ($member['g_is_supmod'])
            $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = true;
        else if ($member['is_mod'])
        {
            $permission = $member['forumsModeratorData'][ $topic['forum_id'] ];
            
            $can_rename = $permission['edit_topic'];
            $can_move = $permission['move_topic'] && $topic['state'] != 'link';
            $can_delete = $permission['delete_topic'];
            
            $can_stick = $is_sticky ? $permission['unpin_topic'] : $permission['pin_topic'];
            $can_close = $is_closed ? $permission['open_topic'] : $permission['close_topic'];
            
            $can_approve = $is_approved ? $topic['permissions']['TopicSoftDelete']
                                        : $topic['permissions']['TopicSoftDeleteRestore']; // hide
        }
        
        $xmlrpc_topic = array(
            'forum_id'          => new xmlrpcval($topic['forum_id'], 'string'),
            'forum_name'        => new xmlrpcval(subject_clean($topic['forum']['name']), 'base64'), //wztmdf:will be $topic['forum_name'] in get_user_topic method
            'topic_id'          => new xmlrpcval($topic['tid'], 'string'),
            'topic_title'       => new xmlrpcval(subject_clean($topic['title']), 'base64'),
            'topic_author_id'   => new xmlrpcval($topic['starter_id'], 'string'),
            'topic_author_name' => new xmlrpcval(subject_clean($topic['starter_name']), 'base64'),
            'icon_url'          => new xmlrpcval(get_avatar($topic['starter_id']) , 'string'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($topic['start_date']), 'dateTime.iso8601'),
            'timestamp'        => new xmlrpcval(intval($topic['start_date']), 'string'),
            'short_content'     => new xmlrpcval(subject_clean($topic['preview']), 'base64'),
            
            'reply_number'      => new xmlrpcval($topic['posts'], 'int'),
            'view_number'       => new xmlrpcval($topic['views'], 'int'),
            'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
            'is_ban'            => new xmlrpcval($topic['is_ban'], 'boolean'),
            'can_ban'           => new xmlrpcval($topic['can_ban'], 'boolean'),
        );
        
        if ($has_attach)    $xmlrpc_topic['attachment']     = new xmlrpcval('1');
        if ($new_post)      $xmlrpc_topic['new_post']       = new xmlrpcval(true, 'boolean');
        if ($is_subscribed) $xmlrpc_topic['is_subscribed']  = new xmlrpcval(true, 'boolean');
        if ($can_subscribe) $xmlrpc_topic['can_subscribe']  = new xmlrpcval(true, 'boolean');
        if ($is_sticky)     $xmlrpc_topic['is_sticky']      = new xmlrpcval(true, 'boolean');
        if ($is_closed)     $xmlrpc_topic['is_closed']      = new xmlrpcval(true, 'boolean');
        if ($is_deleted)    $xmlrpc_topic['is_deleted']     = new xmlrpcval(true, 'boolean');
        
        if ($can_rename)    $xmlrpc_topic['can_rename']     = new xmlrpcval(true, 'boolean');
        if ($can_stick)     $xmlrpc_topic['can_stick']      = new xmlrpcval(true, 'boolean');
        if ($can_close)     $xmlrpc_topic['can_close']      = new xmlrpcval(true, 'boolean');
        if ($can_move)      $xmlrpc_topic['can_move']       = new xmlrpcval(true, 'boolean');
        if ($can_approve)   $xmlrpc_topic['can_approve']    = new xmlrpcval(true, 'boolean');
        if ($can_delete)    $xmlrpc_topic['can_delete']     = new xmlrpcval(true, 'boolean');
        
        $return_array[] = new xmlrpcval($xmlrpc_topic, 'struct');
    }
    
    return new xmlrpcresp(new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($result['total'], 'int'),
        'topics' => new xmlrpcval($return_array, 'array'),
    ), 'struct'));
}

function get_delete_post_func()
{
    global $tapatalk_controller;
    
    $result = $tapatalk_controller->result;
    
    foreach($result['posts'] as $data)
    {
        $post = $data['post'];
        
        $is_approved = $post['queued'] != 1 && $post['queued'] != 2;
        $can_approve = $is_approved ? $post['_softDelete'] : $post['_softDeleteRestore'];
        $is_deleted = $post['queued'] == 3;
        $can_delete = $post['_can_delete'] === true;

        $return_post = array(
            'forum_id'          => new xmlrpcval($post['forum_id'], 'string'),
            'forum_name'        => new xmlrpcval(subject_clean($post['forum_name']), 'base64'),
            'topic_id'          => new xmlrpcval($post['tid'], 'string'),
            'topic_title'       => new xmlrpcval(subject_clean($post['title']), 'base64'),
            'post_id'           => new xmlrpcval($post['pid'], 'string'),
            'post_title'        => new xmlrpcval(subject_clean($post['title']), 'base64'),
            
            'post_author_id'    => new xmlrpcval($post['author_id'], 'string'),
            'post_author_name'  => new xmlrpcval(subject_clean($post['author_name']), 'base64'),
            'user_type'         => new xmlrpcval(check_return_user_type($post['author_name']),'base64'),
            'icon_url'          => new xmlrpcval(get_avatar($post['author_id']), 'string'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
            'timestamp'         => new xmlrpcval(intval($post['post_date']), 'string'),
            'short_content'     => new xmlrpcval(subject_clean($post['preview']), 'base64'),
            'post_content'      => new xmlrpcval(post_html_clean($post['post_content']), 'base64'),
            'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
            'is_ban'            => new xmlrpcval($post['is_ban'], 'boolean'),
            'can_ban'           => new xmlrpcval($post['can_ban'], 'boolean'),
        );
        
        if ($can_approve)   $return_post['can_approve'] = new xmlrpcval(true, 'boolean');
        if ($is_deleted)    $return_post['is_deleted']  = new xmlrpcval(true, 'boolean');
        if ($can_delete)    $return_post['can_delete']  = new xmlrpcval(true, 'boolean');
        
        $return_array[] = new xmlrpcval($return_post, 'struct');
    }
    
    return new xmlrpcresp(new xmlrpcval(array(
        'total_post_num' => new xmlrpcval($result['total'], 'int'),
        'posts' => new xmlrpcval($return_array, 'array'),
    ), 'struct'));
}

function get_report_post_func()
{
    global $totalReports, $reports;
    $member = ipsRegistry::member()->fetchMemberData();
    
    $return_array = array();
    if ($totalReports && is_array($reports))
    {
        foreach($reports as $report)
        {
            $post = $report['post'];
            $is_approved = $post['queued'] != 1 && $post['queued'] != 2;
            $is_deleted = $post['queued'] == 3;
            
            if (!$report['Topic_sponsor'])
            {
                if ($member['g_is_supmod'])
                {
                    $can_approve = $can_delete = true;
                }
                else if ($member['is_mod'])
                {
                    $fid = $post['forum_id'];
                    $can_delete  = $is_deleted  ? $registry->getClass('class_forums')->canSoftDeletePosts( $fid , $post )
                                                : $registry->getClass('class_forums')->can_Un_SoftDeletePosts( $fid , $post); // hide
                    $can_approve = $is_approved ? $registry->getClass('class_forums')->canSoftDeletePosts( $fid , $post )
                                                : $registry->getClass('class_forums')->can_Un_SoftDeletePosts( $fid , $post); // hide
                }
            }
            else 
            {
                $can_approve = $can_delete = false;
            }
            
            $_reason = end($report['reason']);
            $str = $_reason['report'];
            
            $array_reg = array(
                array('reg' => '/(.*?)<\/blockquote\>/si','replace'=>''),
                array('reg' => '/\<p\>(.*?)\<\/p\>/si','replace'=>''),
            );
            foreach ($array_reg as $arr)
            {
                $str = preg_replace($arr['reg'], $arr['replace'], $str);
            }  
            
            $return_post = array(
                'forum_id'          => new xmlrpcval($report['exdat1'], 'string'),
                'forum_name'        => new xmlrpcval(subject_clean($report['section']['title']), 'base64'),
                'topic_id'          => new xmlrpcval($post['topic_id'], 'string'),
                'topic_title'       => new xmlrpcval(subject_clean($post['topic_title']), 'base64'),
                'post_id'           => new xmlrpcval($post['pid'], 'string'),
                'post_title'        => new xmlrpcval(subject_clean($post['title']), 'base64'),
                
                'post_author_id'    => new xmlrpcval($post['author_id'], 'string'),
                'post_author_name'  => new xmlrpcval(subject_clean($post['author_name']), 'base64'),
                'user_type'         => new xmlrpcval(check_return_user_type($post['author_name']),'base64'),
                'icon_url'          => new xmlrpcval(get_avatar($post['author_id']), 'string'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval(intval($post['post_date']), 'string'),
                'short_content'     => new xmlrpcval(subject_clean($post['post']), 'base64'),
                
                'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
                'can_approve'       => new xmlrpcval($can_approve, 'boolean'),
                'is_deleted'        => new xmlrpcval($is_deleted, 'boolean'),
                'can_delete'        => new xmlrpcval($can_delete, 'boolean'),
                'report_id'         => new xmlrpcval($report['id'], 'string'),
                'reported_by_id'    => new xmlrpcval($report['updated_by'], 'string'),
                'reported_by_name'  => new xmlrpcval(subject_clean($report['n_updated_by']), 'base64'),
                'report_reason'     => new xmlrpcval(post_html_clean($str), 'base64'),
                'is_ban'            => new xmlrpcval($report['is_ban'], 'boolean'),
                'can_ban'           => new xmlrpcval($report['can_ban'], 'boolean'),
            );
            
            $return_array[] = new xmlrpcval($return_post, 'struct');
        }
    }
    
    return new xmlrpcresp(new xmlrpcval(array(
        'total_report_num' => new xmlrpcval(intval($totalReports), 'int'),
        'reports' => new xmlrpcval($return_array, 'array'),
    ), 'struct'));
}

function get_moderate_topic_func()
{
    global $tapatalk_controller, $registry;
    
    $result = $tapatalk_controller->result;
    $member = $registry->member()->fetchMemberData();
    
    foreach($result['topics'] as $topic)
    {
        $has_attach = $topic['topic_hasattach'] ? 1 : 0;
        $new_post = $topic['_hasUnread'] ? true : false;
        $is_subscribed = is_subscribed($topic['tid']);
        $can_subscribe = $member['member_id'] ? true : false;
        $is_closed = $topic['state'] == 'closed' ? true : false;
        $is_sticky = $topic['pinned'] == 1;
        $is_approved = $topic['approved'] > 0;
        $is_deleted = $topic['approved'] == 2;
        
        if (!in_array($topic['topic_archive_status'], array(0, 3)))
            $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = false;
        else if ($member['g_is_supmod'])
            $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = true;
        else if ($member['is_mod'])
        {
            $permission = $member['forumsModeratorData'][ $topic['forum_id'] ];
            
            $can_rename = $permission['edit_topic'];
            $can_move = $permission['move_topic'] && $topic['state'] != 'link';
            $can_delete = $permission['delete_topic'];
            
            $can_stick = $is_sticky ? $permission['unpin_topic'] : $permission['pin_topic'];
            $can_close = $is_closed ? $permission['open_topic'] : $permission['close_topic'];
            
            $can_approve = $is_approved ? $topic['permissions']['TopicSoftDelete']
                                        : $topic['permissions']['TopicSoftDeleteRestore']; // hide
        }
        
        $xmlrpc_topic = array(
            'forum_id'          => new xmlrpcval($topic['forum_id'], 'string'),
            'forum_name'        => new xmlrpcval(subject_clean($topic['forum']['name']), 'base64'),
            'topic_id'          => new xmlrpcval($topic['tid'], 'string'),
            'topic_title'       => new xmlrpcval(subject_clean($topic['title']), 'base64'),
            'topic_author_id'   => new xmlrpcval($topic['starter_id'], 'string'),
            'topic_author_name' => new xmlrpcval(subject_clean($topic['starter_name']), 'base64'),
            'icon_url'          => new xmlrpcval(get_avatar($topic['starter_id']) , 'string'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($topic['start_date']), 'dateTime.iso8601'),
            'timestamp'    => new xmlrpcval(intval($topic['start_date']), 'string'),
            'short_content'     => new xmlrpcval(subject_clean($topic['preview']), 'base64'),
            
            'reply_number'      => new xmlrpcval($topic['posts'], 'int'),
            'view_number'       => new xmlrpcval($topic['views'], 'int'),
            'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
            'is_ban'            => new xmlrpcval($topic['is_ban'], 'boolean'),
            'can_ban'           => new xmlrpcval($topic['can_ban'], 'boolean'),
        );
        
        if ($has_attach)    $xmlrpc_topic['attachment']     = new xmlrpcval('1');
        if ($new_post)      $xmlrpc_topic['new_post']       = new xmlrpcval(true, 'boolean');
        if ($is_subscribed) $xmlrpc_topic['is_subscribed']  = new xmlrpcval(true, 'boolean');
        if ($can_subscribe) $xmlrpc_topic['can_subscribe']  = new xmlrpcval(true, 'boolean');
        if ($is_sticky)     $xmlrpc_topic['is_sticky']      = new xmlrpcval(true, 'boolean');
        if ($is_closed)     $xmlrpc_topic['is_closed']      = new xmlrpcval(true, 'boolean');
        if ($is_deleted)    $xmlrpc_topic['is_deleted']     = new xmlrpcval(true, 'boolean');
        
        if ($can_rename)    $xmlrpc_topic['can_rename']     = new xmlrpcval(true, 'boolean');
        if ($can_stick)     $xmlrpc_topic['can_stick']      = new xmlrpcval(true, 'boolean');
        if ($can_close)     $xmlrpc_topic['can_close']      = new xmlrpcval(true, 'boolean');
        if ($can_move)      $xmlrpc_topic['can_move']       = new xmlrpcval(true, 'boolean');
        if ($can_approve)   $xmlrpc_topic['can_approve']    = new xmlrpcval(true, 'boolean');
        if ($can_delete)    $xmlrpc_topic['can_delete']     = new xmlrpcval(true, 'boolean');
        
        $return_array[] = new xmlrpcval($xmlrpc_topic, 'struct');
    }
    
    return new xmlrpcresp(new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($result['total'], 'int'),
        'topics' => new xmlrpcval($return_array, 'array'),
    ), 'struct'));
}

function get_moderate_post_func()
{
    global $tapatalk_controller;
    
    $result = $tapatalk_controller->result;
    
    foreach($result['posts'] as $data)
    {
        $post = $data['post'];
        
        $is_approved = $post['queued'] != 1 && $post['queued'] != 2;
        $can_approve = $is_approved ? $post['_softDelete'] : $post['_softDeleteRestore'];
        $is_deleted = $post['queued'] == 3;
        $can_delete = $post['_can_delete'] === true;
        
        $return_post = array(
            'forum_id'          => new xmlrpcval($post['forum_id'], 'string'),
            'forum_name'        => new xmlrpcval(subject_clean($post['forum_name']), 'base64'),
            'topic_id'          => new xmlrpcval($post['tid'], 'string'),
            'topic_title'       => new xmlrpcval(subject_clean($post['title']), 'base64'),
            'post_id'           => new xmlrpcval($post['pid'], 'string'),
            'post_title'        => new xmlrpcval(subject_clean($post['title']), 'base64'),
            
            'post_author_id'    => new xmlrpcval($post['author_id'], 'string'),
            'post_author_name'  => new xmlrpcval(subject_clean($post['author_name']), 'base64'),
            'user_type'         => new xmlrpcval(check_return_user_type($post['author_name']),'base64'),
            'icon_url'          => new xmlrpcval(get_avatar($post['author_id']), 'string'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
            'timestamp'        => new xmlrpcval(intval($post['post_date']), 'string'),
            'short_content'     => new xmlrpcval(subject_clean($post['preview']), 'base64'),
            
            'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
            'is_ban'            => new xmlrpcval($post['is_ban'], 'boolean'),
            'can_ban'           => new xmlrpcval($post['can_ban'], 'boolean'),
        );
        
        if ($can_approve)   $return_post['can_approve'] = new xmlrpcval(true, 'boolean');
        if ($is_deleted)    $return_post['is_deleted']  = new xmlrpcval(true, 'boolean');
        if ($can_delete)    $return_post['can_delete']  = new xmlrpcval(true, 'boolean');
        
        $return_array[] = new xmlrpcval($return_post, 'struct');
    }
    
    return new xmlrpcresp(new xmlrpcval(array(
        'total_post_num' => new xmlrpcval($result['total'], 'int'),
        'posts' => new xmlrpcval($return_array, 'array'),
    ), 'struct'));
}

function update_signature()
{
    global $signature;
        
    $result = new xmlrpcval(array(
        'result'    => new xmlrpcval(true, 'boolean'),
        'signature' => new xmlrpcval($signature, 'base64'),
    ), 'struct');
    
    return new xmlrpcresp($result);
}

function new_conversation_func()
{
    global $result;
    
    if ($result === true) return xmlresptrue();
    
    $result = new xmlrpcval(array(
        'result'  => new xmlrpcval(true, 'boolean'),
        'conv_id' => new xmlrpcval($result, 'string'),
    ), 'struct');
    
    return new xmlrpcresp($result);
}

function origin_new_conversation_func()
{
    global $tapatalk_controller;
    
    $result = $tapatalk_controller->result;
    
    if ($result === true) return xmlresptrue();
    
    $result = new xmlrpcval(array(
        'result'  => new xmlrpcval(true, 'boolean'),
        'conv_id' => new xmlrpcval($result, 'string'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function reply_conversation_func()
{
    global $result;
                    
    if ($result === true) return xmlresptrue();
    
    $result = new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
        'msg_id' => new xmlrpcval($result, 'string'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function origin_reply_conversation_func()
{
    global $tapatalk_controller;
    
    $result = $tapatalk_controller->result;
    
    $result = new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
        'msg_id' => new xmlrpcval($result, 'string'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_alert_func()
{
    global $tapatalk_controller;
    
    $total_alerts = $tapatalk_controller->total;
    $alertData = $tapatalk_controller->data;
    
    $return_array = array();
    foreach ($alertData as $data)
    {
        $xmlrpcdata = array(
            'user_id'       => new xmlrpcval($data['author_id'], 'string'),
            'username'      => new xmlrpcval($data['author'], 'base64'),
            'user_type'     => new xmlrpcval(check_return_user_type($data['author'], 'base64')),
            'icon_url'      => new xmlrpcval($data['icon_url'], 'string'),
            'message'       => new xmlrpcval($data['message'], 'base64'),
            'timestamp'     => new xmlrpcval($data['create_time'], 'string'),
            'content_type'  => new xmlrpcval($data['data_type'], 'string'),
            'content_id'    => new xmlrpcval($data['data_id'], 'string'),
        );
        
        if (in_array($data['data_type'], array('sub', 'quote', 'tag', 'like')))
        {
            $xmlrpcdata['topic_id'] = new xmlrpcval($data['sub_id'], 'string');
        }
        
        if(!empty($data['position']))
        {
            $xmlrpcdata['position'] = new xmlrpcval($data['position'],'int');
        }
        
        $return_array[] =new xmlrpcval($xmlrpcdata, 'struct');
    }
    
    $result = new xmlrpcval(array(
        'total' => new xmlrpcval(intval($total_alerts), 'int'),
        'items' => new xmlrpcval($return_array, 'array'),
    ), 'struct');
    
    return new xmlrpcresp($result);
}