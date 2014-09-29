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

function attach_image_func()
{
    global $attach_id;

    if ($attach_id)
    {
        $xmlrpc_result = new xmlrpcval(array('attachment_id'  => new xmlrpcval($attach_id)), 'struct');
        return new xmlrpcresp($xmlrpc_result);
    }
    else
    {
        get_error('Line: '.__LINE__);
    }
}

function authorize_user_func()
{
    global $login_result;
    $response = new xmlrpcval(array('authorize_result' => new xmlrpcval($login_result, 'boolean')), 'struct');
    return new xmlrpcresp($response);
}

function login_func()
{
    global $login_result, $result_text, $max_single_upload, $settings, $member, $app_version, $push_type;

    $groups = array_unique(array_map('intval', explode(',', $member['member_group_id'].','.$member['g_perm_id'])));
    foreach($groups as $gid)
        $group_ids[] = new xmlrpcval($gid);
    
    /*
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
    */
    
    $response = new xmlrpcval(array(
        'result'            => new xmlrpcval($login_result, 'boolean'),
        'result_text'       => new xmlrpcval($result_text, 'base64'),

        'user_id'           => new xmlrpcval($member['member_id']),
        'username'          => new xmlrpcval(subject_clean($member['members_display_name']), 'base64'),
        'email'             => new xmlrpcval($member['email'], 'base64'),
        'user_type'         => new xmlrpcval(check_return_user_type($member['members_display_name']),'base64'),
        'usergroup_id'      => new xmlrpcval($group_ids, 'array'),
        'icon_url'          => new xmlrpcval(get_avatar($member)),
        'post_count'        => new xmlrpcval($member['posts'], 'int'),

        'can_pm'            => new xmlrpcval($member['members_disable_pm'] == 0 && $member['g_use_pm'], 'boolean'),
        'can_send_pm'       => new xmlrpcval($member['members_disable_pm'] == 0 && $member['g_use_pm'], 'boolean'),
        'can_search'        => new xmlrpcval($settings['allow_search'] && $member['g_use_search'], 'boolean'),
        'can_whosonline'    => new xmlrpcval($settings['allow_online_list'], 'boolean'),
        'max_attachment'    => new xmlrpcval($settings['max_images'], 'int'),
        'max_png_size'      => new xmlrpcval($max_single_upload, 'int'),
        'max_jpg_size'      => new xmlrpcval($max_single_upload, 'int'),
        'can_upload_avatar' => new xmlrpcval(isset($settings['avatars_on']) ? $settings['avatars_on'] && $member['g_avatar_upload'] : IPSMember::canUploadPhoto($member), 'boolean'),
        'push_type'         => new xmlrpcval($push_type, 'array'),

        'can_moderate'      => new xmlrpcval(version_compare($app_version, '3.2.0', '>=') && ($member['g_is_supmod'] || $member['access_report_center']), 'boolean'),
        //'ignored_uids'      => new xmlrpcval($ignored_uids, 'string'),
    ), 'struct');

    return new xmlrpcresp($response);
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
function update_password_func()
{
     global $result , $result_text;
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
function create_topic_func()
{
    global $new_topic;

    $xmlrpc_create_topic = new xmlrpcval(array(
        'result'    => new xmlrpcval($new_topic['result'], 'boolean'),
        'topic_id'  => new xmlrpcval($new_topic['topic_id'], 'string'),
        'state'     => new xmlrpcval($new_topic['state'])
    ), 'struct');

    return new xmlrpcresp($xmlrpc_create_topic);
}

function new_topic_func()
{
    global $new_topic;

    $xmlrpc_new_topic = new xmlrpcval(array(
        'result'    => new xmlrpcval($new_topic['result'], 'boolean'),
        'topic_id'  => new xmlrpcval($new_topic['topic_id'], 'string'),
        'state'     => new xmlrpcval($new_topic['state'])
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

function get_box_func()
{
    global $box_data;
    $pm_list = array();

    foreach ($box_data['list'] as $pm)
    {
        $msg_to = array();
        foreach($pm['msg_to'] as $msg_to_name) {
            $msg_to[] = new xmlrpcval(array(
                'username'      => new xmlrpcval(subject_clean($msg_to_name['name']), 'base64'),
                'user_type'     => new xmlrpcval(check_return_user_type($msg_to_name['username']),'base64'),
            ), 'struct');
        }
        $pm_list[] = new xmlrpcval(array(
            'msg_id'        => new xmlrpcval($pm['msg_id']),
            'msg_state'     => new xmlrpcval(($pm['msg_is_unread'] ? 1 : 2), 'int'),
            'sent_date'     => new xmlrpcval(mobiquo_iso8601_encode($pm['msg_date']),'dateTime.iso8601'),
            'msg_from'      => new xmlrpcval(subject_clean($pm['msg_author']), 'base64'),
            'icon_url'      => new xmlrpcval($pm['msg_sender_icon']),
            'msg_to'        => new xmlrpcval($msg_to, 'array'),
            'msg_subject'   => new xmlrpcval(subject_clean($pm['msg_subject']), 'base64'),
            'short_content' => new xmlrpcval(get_short_content($pm['msg_post']), 'base64')
        ), 'struct');
    }

    $result = new xmlrpcval(array(
        'total_message_count' => new xmlrpcval($box_data['total_msg_count'], 'int'),
        'total_unread_count'  => new xmlrpcval($box_data['total_unread_count'], 'int'),
        'list'                => new xmlrpcval($pm_list, 'array')
    ), 'struct');

    return new xmlrpcresp($result);
}


function get_message_func()
{
    global $message_data;

    $msg_to = array();
    foreach($message_data['msg_to'] as $msg_to_name) {
        $msg_to[] = new xmlrpcval(array(
            'username'      => new xmlrpcval(subject_clean($msg_to_name['name']), 'base64'),
            'user_type'     => new xmlrpcval(check_return_user_type($msg_to_name['username']),'base64'),
        ), 'struct');
    }

    $result = new xmlrpcval(array(
        'sent_date'     => new xmlrpcval(mobiquo_iso8601_encode($message_data['msg_date']),'dateTime.iso8601'),
        'msg_from'      => new xmlrpcval(subject_clean($message_data['msg_author']), 'base64'),
        'icon_url'      => new xmlrpcval($message_data['msg_sender_icon']),
        'msg_to'        => new xmlrpcval($msg_to, 'array'),
        'msg_subject'   => new xmlrpcval(subject_clean($message_data['msg_subject']), 'base64'),
        'text_body'     => new xmlrpcval(post_html_clean($message_data['msg_post']), 'base64')
    ), 'struct');

    return new xmlrpcresp($result);
}


function get_box_info_func()
{
    global $box_info;
    $box_list = array();
    foreach($box_info['box_info'] as $box)
    {
        $box_list[] = new xmlrpcval(array(
            'box_id'        => new xmlrpcval($box['box_id'], 'string'),
            'box_name'      => new xmlrpcval(to_utf8($box['box_name']), 'base64'),
            'msg_count'     => new xmlrpcval($box['msg_count'], 'int'),
            'unread_count'  => new xmlrpcval($box['unread_count'], 'int'),
            'box_type'      => new xmlrpcval($box['box_type'], 'string')
        ), 'struct');
    }

    $result = new xmlrpcval(array(
        'message_room_count' => new xmlrpcval($box_info['message_room_count'], 'int'),
        'list'               => new xmlrpcval($box_list, 'array')
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_config_func()
{
    global $mobiquo_config, $app_version, $registry, $settings, $member;

    $config_list = array('sys_version' => new xmlrpcval($app_version, 'string'));

    if (isset(ipsRegistry::$settings['tapatalk_push_key']))
    {
        $tapatalkhook = ipsRegistry::DB()->buildAndFetch( array( 'select' => '*', 'from' => 'core_hooks', 'where' => "hook_key='tapatalk'" ) );
        $config_list['hook_version'] = new xmlrpcval($tapatalkhook['hook_version_human']);
        $config_list['hook_code'] = new xmlrpcval($tapatalkhook['hook_version_long']);
        $mobiquo_config['api_key'] = ipsRegistry::$settings['tapatalk_push_key'] ? md5(ipsRegistry::$settings['tapatalk_push_key']) : '';
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
        'inbox_unread_count' => new xmlrpcval($newprvpm, 'int'),
        'subscribed_topic_unread_count' => new xmlrpcval(MbqAppEnv::$mbqReturn['subscribed_topic_unread_count'], 'int')
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_new_topic_func()
{
    global $new_topics;
    return new xmlrpcresp(new xmlrpcval($new_topics, 'array'));
}

function get_latest_topic_func()
{
    global $app_version;
    if (version_compare($app_version, '3.2.0', '>='))
        return search_func();

    global $topics, $total_recent_num;

    $response = new xmlrpcval(
        array(
            'result'            => new xmlrpcval(true, 'boolean'),
            'total_topic_num'   => new xmlrpcval($total_recent_num, 'int'),
            'topics'            => new xmlrpcval($topics, 'array'),
        ), 'struct');

    return new xmlrpcresp($response);
}

// for 3.2.0 and above unread, participated, latest topic output
function search_func()
{
    global $topics, $registry;
    $member = $registry->member()->fetchMemberData();
    $topic_list = $topics['list'];

    $list = array();
    foreach($topic_list as $topic)
    {
        $forumData = $registry->getClass('class_forums')->getForumbyId( $topic['forum_id'] );
        $short_content = isset($topic['preview']['last']) ? $topic['preview']['last']['post'] : $topic['preview']['first']['post'];

        /* Fetch last marking time for this entry */
        $lastMarked = ( $topic['_isArchived'] ) ? IPS_UNIX_TIME_NOW : $registry->classItemMarking->fetchTimeLastMarked( array( 'forumID' => $topic['_forum_id'] ? $topic['_forum_id'] : $topic['forum_id'], 'itemID' => $topic['tid'] ), 'forums');

        /* Check against it */
        if ( $topic['poll_state'] AND ( $topic['last_vote'] > $topic['topic_last_post'] ) )
        {
            $topic['_hasUnread'] = ( $lastMarked < $topic['last_vote'] ) ? true : false;
        }
        else
        {
            $topic['_hasUnread'] = ( $lastMarked < $topic['topic_last_post'] ) ? true : false;
        }

        $has_attach = $topic['topic_hasattach'] ? 1 : 0;
        $new_post = $topic['_hasUnread'] ? true : false;
        $is_subscribed = is_subscribed($topic['tid']);
        $can_subscribe = $member['member_id'] ? true : false;
        $is_closed = $topic['state'] == 'closed' ? true : false;
        $is_sticky = $topic['pinned'] == 1;
        $is_approved = $topic['approved'] > 0;

        $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = false;
        if (in_array($topic['topic_archive_status'], array(0, 3)))
        {
            $permission = $member['forumsModeratorData'][ $topic['forum_id'] ];

            if ($member['g_is_supmod'])
                $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = true;
            else if ($member['is_mod'])
            {
                $can_rename = $permission['edit_topic'];
                $can_move = $permission['move_topic'] && $topic['state'] != 'link';
                $can_delete = $permission['delete_topic'];

                $can_stick = $is_sticky ? $permission['unpin_topic'] : $permission['pin_topic'];
                $can_close = $is_closed ? $permission['open_topic'] : $permission['close_topic'];

                if (version_compare($app_version, '3.3.0', '>='))
                    $can_approve = $is_approved ? $registry->getClass('class_forums')->canSoftDeleteTopics( $topic['forum_id'] )
                                                : $registry->getClass('class_forums')->can_Un_SoftDeleteTopics( $topic['forum_id'] ); // hide
                else
                    $can_approve = $permission['topic_q']; // invisible
            }
            else if ($member['member_id'] == $topic['starter_id'] && $member['g_edit_posts'])
            {
                if ( $member['g_edit_cutoff'] > 0 )
                {
                    if ( $topic['start_date'] > ( IPS_UNIX_TIME_NOW - ( intval($member['g_edit_cutoff']) * 60 ) ) )
                    {
                        $can_rename = true;
                    }
                }
                else
                {
                    $can_rename = true;
                }
            }

            if ( ( $topic['state'] != 'open' ) and ( ! $member['g_is_supmod'] AND ! $permission['edit_post'] ) )
            {
                if ( $member['g_post_closed'] != 1 )
                {
                    $can_rename = false;
                }
            }
        }

        $xmlrpc_topic = array(
            'forum_id'          => new xmlrpcval($topic['forum_id']),
            'forum_name'        => new xmlrpcval(subject_clean($forumData['name']), 'base64'),
            'topic_id'          => new xmlrpcval($topic['tid']),
            'topic_title'       => new xmlrpcval(subject_clean($topic['topic_title']), 'base64'),
            'icon_url'          => new xmlrpcval(get_avatar($topic['last_poster_id'])),
            'post_author_id'    => new xmlrpcval(subject_clean($topic['last_poster_id']), 'string'),
            'post_author_name'  => new xmlrpcval(subject_clean($topic['last_poster_name']), 'base64'),
            'user_type'         => new xmlrpcval(check_return_user_type($topic['last_poster_name']),'base64'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($topic['topic_last_post']), 'dateTime.iso8601'),
            'timestamp'         => new xmlrpcval(intval($topic['topic_last_post']), 'string'),
            'reply_number'      => new xmlrpcval(intval($topic['topic_posts']), 'int'),
            'view_number'       => new xmlrpcval(intval($topic['views']), 'int'),
            'short_content'     => new xmlrpcval(subject_clean($short_content), 'base64'),
            'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
        );

        if ($has_attach)    $xmlrpc_topic['attachment']     = new xmlrpcval('1');
        if ($new_post)      $xmlrpc_topic['new_post']       = new xmlrpcval(true, 'boolean');
        if ($is_subscribed) $xmlrpc_topic['is_subscribed']  = new xmlrpcval(true, 'boolean');
        if ($can_subscribe) $xmlrpc_topic['can_subscribe']  = new xmlrpcval(true, 'boolean');
        if ($is_sticky)     $xmlrpc_topic['is_sticky']      = new xmlrpcval(true, 'boolean');
        if ($is_closed)     $xmlrpc_topic['is_closed']      = new xmlrpcval(true, 'boolean');

        if ($can_rename)    $xmlrpc_topic['can_rename']     = new xmlrpcval(true, 'boolean');
        if ($can_stick)     $xmlrpc_topic['can_stick']      = new xmlrpcval(true, 'boolean');
        if ($can_close)     $xmlrpc_topic['can_close']      = new xmlrpcval(true, 'boolean');
        if ($can_move)      $xmlrpc_topic['can_move']       = new xmlrpcval(true, 'boolean');
        if ($can_approve)   $xmlrpc_topic['can_approve']    = new xmlrpcval(true, 'boolean');
        if ($can_delete)    $xmlrpc_topic['can_delete']     = new xmlrpcval(true, 'boolean');

        $list[] = new xmlrpcval($xmlrpc_topic, 'struct');
    }

    $response = new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($topics['total_topic_num'], 'int'),
        'topics'          => new xmlrpcval($list, 'array'),
    ), 'struct');

    return new xmlrpcresp($response);
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

function get_raw_post_func()
{
    global $postinfo;

    $response = new xmlrpcval(
        array(
            'post_id'       => new xmlrpcval($postinfo['post_id']),
            'post_title'    => new xmlrpcval(subject_clean($postinfo['post_title']), 'base64'),
            'post_content'  => new xmlrpcval(subject_clean($postinfo['post_content']), 'base64'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}

function get_subscribed_topic_func()
{
    global $app_version;
    if (version_compare($app_version, '3.2.0', '>='))
        return get_followed_topic_func();

    global $topics;

    $response = new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($topics['total_topic_num'], 'int'),
        'topics'    => new xmlrpcval($topics['topics'], 'array'),
    ), 'struct');

    return new xmlrpcresp($response);
}

function get_followed_topic_func()
{
    global $topics, $member;

    $freq_options = array(
        'immediate'=> 1,
        'daily'    => 2,
        'weekly'   => 3,
        'offline'  => 4,
    );

    $topics_list = array();
    foreach ($topics['items'] as $topic)
    {
        $short_content = get_short_content($topic['content'], $topic['post_htmlstate']);
        $new_post = !ipsRegistry::getClass('classItemMarking')->isRead(array('forumID'=> $topic['forum_id'], 'itemID'=> $topic['tid'], 'itemLastUpdate' => $topic['last_post']),  'forums' );

        //$subscribe_mode = $topic['_followData']['like_notify_freq'];
        //$subscribe_mode = empty($subscribe_mode) || !isset($freq_options[$subscribe_mode]) ? 0 : $freq_options[$subscribe_mode];

        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($topic['forum_id'], 'string'),
            'forum_name'        => new xmlrpcval(subject_clean($topic['_followData']['like.parentTitle']), 'base64'),
            'topic_id'          => new xmlrpcval($topic['tid'], 'string'),
            'topic_title'       => new xmlrpcval(subject_clean($topic['content_title']), 'base64'),
            'reply_number'      => new xmlrpcval($topic['posts'], 'int'),
            'view_number'       => new xmlrpcval($topic['views'], 'int'),
            'short_content'     => new xmlrpcval($short_content, 'base64'),
            'icon_url'          => new xmlrpcval(get_avatar($topic['last_poster_id'])),
            'post_author_name'  => new xmlrpcval(subject_clean($topic['last_poster_name']), 'base64'),
            'user_type'         => new xmlrpcval(check_return_user_type($topic['last_poster_name']),'base64'),
            'new_post'          => new xmlrpcval($new_post, 'boolean'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($topic['last_post']), 'dateTime.iso8601'),
            'timestamp'    => new xmlrpcval(intval($topic['last_post']), 'string'),
            'is_closed'         => new xmlrpcval($topic['state'] == 'closed' ? true : false, 'boolean'),
            //'subscribe_mode'    => new xmlrpcval($subscribe_mode, 'int'),
        ), 'struct');

        $topics_list[] = $xmlrpc_topic;
    }

    $response = new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($topics['total_item_num'], 'int'),
        'topics' => new xmlrpcval($topics_list, 'array'),
    ), 'struct');

    return new xmlrpcresp($response);
}

function get_subscribed_forum_func()
{
    global $app_version;
    if (version_compare($app_version, '3.2.0', '>='))
        return get_followed_forum_func();

    global $result;

    $response = new xmlrpcval(
        array(
            'total_forums_num' => new xmlrpcval(count($result), 'int'),
            'forums'           => new xmlrpcval($result, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}

function get_followed_forum_func()
{
    global $followedItems, $member;

    require_once( IPS_ROOT_PATH . 'sources/classes/like/composite.php' );
    $like = classes_like::bootstrap('forums', 'forums');

    $freq_options = array(
        'immediate'=> 1,
        'daily'    => 2,
        'weekly'   => 3,
        'offline'  => 4,
    );

    $forums = array();
    foreach ($followedItems['items'] as $forum)
    {
        $follow_data = $like->getDataByMemberIdAndRelationshipId($forum['id'], $member['member_id']);
        $subscribe_mode = $follow_data['like_notify_freq'];
        $subscribe_mode = empty($subscribe_mode) || !isset($freq_options[$subscribe_mode]) ? 0 : $freq_options[$subscribe_mode];

        $xmlrpc_forum = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($forum['id'], 'string'),
            'forum_name'        => new xmlrpcval(subject_clean($forum['name']), 'base64'),
            'new_post'          => new xmlrpcval($forum['_has_unread'], 'boolean'),
            'is_protected'      => new xmlrpcval(isset($forum['password']) && $forum['password'] != '', 'boolean'),
            'subscribe_mode'    => new xmlrpcval($subscribe_mode, 'int'),
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

    $response = new xmlrpcval(
        array(
            'total_topic_num' => new xmlrpcval($topics['total_topic_num'], 'int'),
            'forum_id'        => new xmlrpcval($topics['forum_id']),
            'forum_name'      => new xmlrpcval(subject_clean($topics['forum_name']) , 'base64'),
            'can_post'        => new xmlrpcval($topics['can_post'] ? true : false, 'boolean'),
            'can_upload'      => new xmlrpcval($topics['can_upload'] ? true : false, 'boolean'),
            'topics'          => new xmlrpcval($topics['topics'], 'array'),
        ), 'struct');

    return new xmlrpcresp($response);
}

function get_user_info_func()
{
    global $member_info, $member, $app_version;
    $custom_fields_list = array();
    foreach ($member_info['display_text'] as $id => $data) {
        $custom_fields_list[] = new xmlrpcval(array (
            'name'  => new xmlrpcval($data['name'], 'base64'),
            'value' => new xmlrpcval($data['value'], 'base64'),
        ), 'struct');
    }


    $is_online = $member_info['_online'] ? true : false;
    $accept_pm = $member_info['g_use_pm'] && $member_info['members_disable_pm'] == 0 && IPSLib::moduleIsEnabled('messaging', 'members');
    $xmlrpc_user_info = array(
        'user_id'               => new xmlrpcval($member_info['member_id']),
        'username'              => new xmlrpcval(subject_clean($member_info['members_display_name']), 'base64'),
        'user_type'             => new xmlrpcval(check_return_user_type($member_info['members_display_name']),'base64'),
        'post_count'            => new xmlrpcval($member_info['posts'], 'int'),
        'reg_time'              => new xmlrpcval(mobiquo_iso8601_encode($member_info['joined']), 'dateTime.iso8601'),
        'reg_timestamp'         => new xmlrpcval(intval($member_info['joined']), 'string'),
        'last_activity_time'    => new xmlrpcval(mobiquo_iso8601_encode($member_info['last_activity']), 'dateTime.iso8601'),
        'timestamp'             => new xmlrpcval(intval($member_info['last_activity']), 'string'),
        'icon_url'              => new xmlrpcval($member_info['icon_url']),
        'custom_fields_list'    => new xmlrpcval($custom_fields_list, 'array'),
        'accept_pm'             => new xmlrpcval($accept_pm, 'boolean'),
    );

    if ($is_online)     $xmlrpc_user_info['is_online']      = new xmlrpcval(true, 'boolean');

    if (version_compare($app_version, '3.2.0', '>='))
    {
        $is_spam = $member_info['spamStatus'] === TRUE;
        $can_mark_spam = $member_info['spamStatus'] === FALSE && $member_info['member_id'] != $member['member_id'];

        if ($is_spam)       $xmlrpc_user_info['is_spam']        = new xmlrpcval(true, 'boolean');
        if ($can_mark_spam) $xmlrpc_user_info['can_mark_spam']  = new xmlrpcval(true, 'boolean');
        if ($is_spam)       $xmlrpc_user_info['is_ban']         = new xmlrpcval(true, 'boolean');
        if ($can_mark_spam) $xmlrpc_user_info['can_ban']        = new xmlrpcval(true, 'boolean');
    }

    return new xmlrpcresp(new xmlrpcval($xmlrpc_user_info, 'struct'));
}

function get_user_reply_post_func()
{
    global $user_posts, $app_version;

    if (version_compare($app_version, '3.3.0', '>='))
        return get_user_post_func();

    $post_list = array();
    foreach($user_posts as $post)
    {
        $short_content = get_short_content($post['content'], $post['post_htmlstate']);

        $xmlrpc_post = new xmlrpcval(array(
            'forum_id'              => new xmlrpcval($post['forum_id']),
            'forum_name'            => new xmlrpcval(subject_clean($post['forum_name']) , 'base64'),
            'topic_id'              => new xmlrpcval($post['tid']),
            'topic_title'           => new xmlrpcval(subject_clean($post['content_title']), 'base64'),
            'post_id'               => new xmlrpcval($post['pid']),
            'short_content'         => new xmlrpcval($short_content, 'base64'),
            'icon_url'              => new xmlrpcval($post['icon_url']),
            'post_time'             => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
            'timestamp'        => new xmlrpcval(intval($post['post_date']), 'string'),
            'reply_number'          => new xmlrpcval(intval($post['posts']), 'int'),
            'view_number'           => new xmlrpcval(intval($post['views']), 'int'),
            'new_post'              => new xmlrpcval($post['is_read'] ? false : true, 'boolean'),
        ), 'struct');

        $post_list[] = $xmlrpc_post;
    }

    return new xmlrpcresp(new xmlrpcval($post_list, 'array'));
}

function get_user_post_func()
{
    global $user_posts;

    $post_list = array();
    foreach($user_posts as $post)
    {
        $xmlrpc_post = new xmlrpcval(array(
            'forum_id'              => new xmlrpcval($post['forum_id']),
            'forum_name'            => new xmlrpcval(subject_clean($post['forum_name']) , 'base64'),
            'topic_id'              => new xmlrpcval($post['topic_id']),
            'topic_title'           => new xmlrpcval(subject_clean($post['title']), 'base64'),
            'post_id'               => new xmlrpcval($post['pid']),
            'short_content'         => new xmlrpcval(subject_clean($post['preview']), 'base64'),
            'icon_url'              => new xmlrpcval(get_avatar($post['author_id'])),
            'post_time'             => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
            'timestamp'        => new xmlrpcval(intval($post['post_date']), 'string'),
            'reply_number'          => new xmlrpcval(intval($post['posts']), 'int'),
            'view_number'           => new xmlrpcval(intval($post['views']), 'int'),
            'new_post'              => new xmlrpcval($post['is_read'] ? false : true, 'boolean'),
        ), 'struct');

        $post_list[] = $xmlrpc_post;
    }

    return new xmlrpcresp(new xmlrpcval($post_list, 'array'));
}

function get_user_topic_func()
{
    global $user_topics;

    $topic_list = array();
    foreach($user_topics as $thread)
    {
        $short_content = get_short_content( $thread['content'],  $thread['post_htmlstate']);

        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'              => new xmlrpcval($thread['forum_id']),
            'forum_name'            => new xmlrpcval(subject_clean($thread['forum_name']), 'base64'),
            'topic_id'              => new xmlrpcval($thread['tid']),
            'topic_title'           => new xmlrpcval(subject_clean($thread['content_title']), 'base64'),
            'icon_url'              => new xmlrpcval($thread['icon_url']),
            'last_reply_author_id'  => new xmlrpcval($thread['last_poster_id']),
            'last_reply_author_name'=> new xmlrpcval(subject_clean($thread['last_poster_name']), 'base64'),
            'last_reply_time'       => new xmlrpcval(mobiquo_iso8601_encode($thread['last_post']), 'dateTime.iso8601'),
            'reply_number'          => new xmlrpcval($thread['posts'], 'int'),
            'view_number'           => new xmlrpcval($thread['views'], 'int'),
            'new_post'              => new xmlrpcval($thread['is_read'] ? false : true, 'boolean'),
            'is_subscribed'         => new xmlrpcval($thread['issubscribed'], 'boolean'),
            'can_subscribe'         => new xmlrpcval($thread['can_subscribe'], 'boolean'),
            'short_content'         => new xmlrpcval($short_content, 'base64')
        ), 'struct');

        $topic_list[] = $xmlrpc_topic;
    }

    return new xmlrpcresp(new xmlrpcval($topic_list, 'array'));
}


function search_topic_func()
{
    global $search_topics;

    $topic_list = $search_topics['list'];
    $list = array();

    foreach($topic_list as $thread)
    {
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($thread['forum_id']),
            'forum_name'        => new xmlrpcval(subject_clean($thread['forum_name']), 'base64'),
            'topic_id'          => new xmlrpcval($thread['tid']),
            'topic_title'       => new xmlrpcval(subject_clean($thread['content_title']), 'base64'),
            'reply_number'      => new xmlrpcval($thread['posts'], 'int'),
            'view_number'       => new xmlrpcval($thread['views'], 'int'),
            'post_position'     => new xmlrpcval($thread['post_position'], 'int'),
            'short_content'     => new xmlrpcval($thread['short_content'], 'base64'),
            'icon_url'          => new xmlrpcval($thread['icon_url']),
            'post_author_name'  => new xmlrpcval(subject_clean($thread['members_display_name']), 'base64'),
            'user_type'         => new xmlrpcval(check_return_user_type($thread['members_display_name']),'base64'),
            'new_post'          => new xmlrpcval($thread['has_new'] ? false : true, 'boolean'),
            'is_subscribed'     => new xmlrpcval($thread['issubscribed'], 'boolean'),
            'can_subscribe'     => new xmlrpcval($thread['can_subscribe'], 'boolean'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($thread['post_date']), 'dateTime.iso8601'),
            'timestamp'        => new xmlrpcval(intval($thread['post_date']), 'string'),
            'is_closed'         => new xmlrpcval($thread['state'] == 'closed' ? true : false, 'boolean'),
        ), 'struct');

        $list[] = $xmlrpc_topic;
    }
    $return_arr = array(
        'total_topic_num' => new xmlrpcval($search_topics['total_topic_num'], 'int'),
        'topics'          => new xmlrpcval($list, 'array')
    );
    if(!empty($_GET['_sid']))
    {
        $return_arr['search_id'] = new xmlrpcval($_GET['_sid'], 'string');
    }
    $response = new xmlrpcval($return_arr, 'struct');

    return new xmlrpcresp($response);

}

function search_post_func()
{
    global $search_topics;
    $topic_list = $search_topics['list'];
    $list = array();

    foreach($topic_list as $thread)
    {
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($thread['forum_id']),
            'forum_name'        => new xmlrpcval(subject_clean($thread['forum_name']), 'base64'),
            'topic_id'          => new xmlrpcval($thread['tid']),
            'topic_title'       => new xmlrpcval(subject_clean($thread['content_title']), 'base64'),
            'post_id'           => new xmlrpcval($thread['pid']),
            'post_title'        => new xmlrpcval(subject_clean($thread['post_title']), 'base64'),
            'reply_number'      => new xmlrpcval($thread['posts'], 'int'),
            'view_number'       => new xmlrpcval($thread['views'], 'int'),
            'post_position'     => new xmlrpcval($thread['post_position'], 'int'),
            'short_content'     => new xmlrpcval($thread['short_content'], 'base64'),
            'icon_url'          => new xmlrpcval($thread['icon_url']),
            'post_author_name'  => new xmlrpcval(subject_clean($thread['members_display_name']), 'base64'),
            'user_type'         => new xmlrpcval(check_return_user_type($thread['members_display_name']),'base64'),
            'new_post'          => new xmlrpcval($thread['has_new'] ? false : true, 'boolean'),
            //'can_delete'        => new xmlrpcval($thread['can_delete'] ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval($thread['issubscribed'], 'boolean'),
            'can_subscribe'     => new xmlrpcval($thread['can_subscribe'], 'boolean'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($thread['post_date']), 'dateTime.iso8601'),
            'timestamp'    => new xmlrpcval(intval($thread['post_date']), 'string'),
            'is_closed'         => new xmlrpcval($thread['state'] == 'closed' ? true : false, 'boolean'),
        ), 'struct');

        $list[] = $xmlrpc_topic;
    }
    $return_arr = array(
        'total_post_num' => new xmlrpcval($search_topics['total_topic_num'], 'int'),
        'posts'          => new xmlrpcval($list, 'array')
    );
    if(!empty($_GET['_sid']))
    {
        $return_arr['search_id'] = new xmlrpcval($_GET['_sid'], 'string');
    }
    $response = new xmlrpcval($return_arr, 'struct');
    return new xmlrpcresp($response);

}

function get_unread_topic_func()
{
    global $app_version;
    if (version_compare($app_version, '3.2.0', '>='))
        return search_func();

    global $topics;
    $topic_list = $topics['list'];
    $list = array();
    foreach($topic_list as $thread)
    {
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($thread['forum_id']),
            'forum_name'        => new xmlrpcval(mobi_unescape_html(to_utf8($thread['forum_name'])), 'base64'),
            'topic_id'          => new xmlrpcval($thread['tid']),
            'topic_title'       => new xmlrpcval(subject_clean($thread['content_title']), 'base64'),
            'icon_url'          => new xmlrpcval($thread['icon_url']),
            'post_author_name'  => new xmlrpcval(mobi_unescape_html(to_utf8($thread['last_poster_name'])), 'base64'),
            'user_type'         => new xmlrpcval(check_return_user_type($thread['last_poster_name']),'base64'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($thread['last_post']), 'dateTime.iso8601'),
            'timestamp'    => new xmlrpcval(intval($thread['last_post']), 'string'),
            'reply_number'      => new xmlrpcval($thread['posts'], 'int'),
            'view_number'       => new xmlrpcval($thread['views'], 'int'),
            'new_post'          => new xmlrpcval(true, 'boolean'),
            'is_closed'         => new xmlrpcval($thread['state'] == 'closed' ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval($thread['issubscribed'], 'boolean'),
            'can_subscribe'     => new xmlrpcval($thread['can_subscribe'], 'boolean'),
            'short_content'     => new xmlrpcval($thread['short_content'], 'base64')
        ), 'struct');

        $list[] = $xmlrpc_topic;
    }

    $response = new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($topics['total_topic_num'], 'int'),
        'topics'          => new xmlrpcval($list, 'array'),
    ), 'struct');

    return new xmlrpcresp($response);
}

function reply_topic_func()
{
    global $new_reply;
    $xmlrpc_reply_topic = new xmlrpcval(array(
      'result'    => new xmlrpcval($new_reply['result'], 'boolean'),
      'post_id'   => new xmlrpcval($new_reply['post_id'], 'string'),
      'state'     => new xmlrpcval($new_reply['state'])
    ), 'struct');

    return new xmlrpcresp($xmlrpc_reply_topic);
}

function reply_post_func()
{
    global $new_reply;
    $xmlrpc_reply_topic = new xmlrpcval(array(
        'result'    => new xmlrpcval($new_reply['result'], 'boolean'),
        'post_id'   => new xmlrpcval($new_reply['post_id'], 'string'),
        'state'     => new xmlrpcval($new_reply['state'])
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

function get_quote_pm_func()
{
    global $pm_data;

    $xmlrpc_quote_post = new xmlrpcval(array(
      'msg_id'      => new xmlrpcval($pm_data['id'], 'string'),
      'msg_subject' => new xmlrpcval(subject_clean($pm_data['title']), 'base64'),
      'text_body'   => new xmlrpcval(subject_clean($pm_data['message']), 'base64'),
    ), 'struct');

    return new xmlrpcresp($xmlrpc_quote_post);
}

function save_raw_post_func()
{
    return new xmlrpcresp(new xmlrpcval(array('result' => new xmlrpcval(true, 'boolean')), 'struct'));
}

function mark_all_as_read_func()
{
    return new xmlrpcresp(new xmlrpcval(array('result' => new xmlrpcval(true, 'boolean')), 'struct'));
}

function report_post_func()
{
    return new xmlrpcresp(new xmlrpcval(array('result' => new xmlrpcval(true, 'boolean')), 'struct'));
}

function report_pm_func()
{
    return new xmlrpcresp(new xmlrpcval(array('result' => new xmlrpcval(true, 'boolean')), 'struct'));
}

function delete_message_func()
{
    global $delete_result;
    return new xmlrpcresp(new xmlrpcval(array('result' => new xmlrpcval($delete_result, 'boolean')), 'struct'));
}

function create_message_func()
{
    return new xmlrpcresp(new xmlrpcval(array('result' => new xmlrpcval(true, 'boolean')), 'struct'));
}

function xmlresptrue()
{
    global $result;

    $response = new xmlrpcval(
        array(
            'result'        => new xmlrpcval($result, 'boolean'),
            'result_text'   => new xmlrpcval('', 'base64'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}

function get_participated_topic_func()
{
    global $app_version;
    if (version_compare($app_version, '3.2.0', '>='))
        return search_func();

    global $topics;
    $topic_list = $topics['list'];
    $list = array();

    foreach($topic_list as $thread)
    {
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($thread['forum_id']),
            'forum_name'        => new xmlrpcval(mobi_unescape_html(to_utf8($thread['forum_name'])), 'base64'),
            'topic_id'          => new xmlrpcval($thread['tid']),
            'topic_title'       => new xmlrpcval(subject_clean($thread['content_title']), 'base64'),
            'reply_number'      => new xmlrpcval($thread['posts'], 'int'),
            'view_number'       => new xmlrpcval(intval($thread['views']), 'int'),
            'post_position'     => new xmlrpcval($thread['post_position'], 'int'),
            'short_content'     => new xmlrpcval($thread['short_content'], 'base64'),
            'icon_url'          => new xmlrpcval($thread['icon_url']),
            'post_author_name'  => new xmlrpcval(mobi_unescape_html(to_utf8($thread['last_poster_name'])), 'base64'),
            'user_type'         => new xmlrpcval(check_return_user_type($thread['last_poster_name']),'base64'),
            'new_post'          => new xmlrpcval($thread['has_new'] ? false : true, 'boolean'),
            //'can_delete'        => new xmlrpcval($thread['can_delete'] ? true : false, 'boolean'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($thread['post_date']), 'dateTime.iso8601'),
            'timestamp'        => new xmlrpcval(intval($thread['post_date']), 'string'),
            'is_subscribed'     => new xmlrpcval($thread['issubscribed'], 'boolean'),
            'can_subscribe'     => new xmlrpcval($thread['can_subscribe'], 'boolean'),
            'is_closed'         => new xmlrpcval($thread['state'] == 'closed' ? true : false, 'boolean'),
        ), 'struct');

        $list[] = $xmlrpc_topic;
    }

    $response = new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($topics['total_topic_num'], 'int'),
        'topics'          => new xmlrpcval($list, 'array'),
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
    global $results;

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
            'is_deleted'        => new xmlrpcval($conversation['mt_is_deleted'], 'boolean'),
        ), 'struct');
    }

    $result = new xmlrpcval(array(
        'result'                => new xmlrpcval(true, 'boolean'),
        'conversation_count'    => new xmlrpcval($results['total'], 'int'),
        'unread_count'          => new xmlrpcval($results['unread'], 'int'),
        'list'                  => new xmlrpcval($conversation_list, 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_conversation_func()
{
    global $results, $member;

    $topicData = $results['topicData'];

    $message_list = array();
    foreach($results['replyData'] as $message)
    {
        $message_list[] = new xmlrpcval(array(
            'msg_id'        => new xmlrpcval($message['msg_id'], 'string'),
            'msg_content'   => new xmlrpcval(post_html_clean($message['msg_post']), 'base64'),
            'post_time'     => new xmlrpcval(mobiquo_iso8601_encode($message['msg_date']), 'dateTime.iso8601'),
            'timestamp'        => new xmlrpcval(intval($message['msg_date']), 'string'),
            'msg_author_id' => new xmlrpcval($message['msg_author_id'], 'string'),
            'can_delete'    => new xmlrpcval($message['_canDelete'], 'boolean'),
            'can_edit'      => new xmlrpcval($message['_canEdit'], 'boolean'),
            'can_report'    => new xmlrpcval($topicData['_canReport'] && $member['member_id'] != $message['msg_author_id'], 'boolean'),

            // below two key should be moved to participants structure, not here
            'is_online'     => new xmlrpcval($results['memberData'][$message['msg_author_id']]['_online'], 'boolean'),
            'has_left'      => new xmlrpcval(!$results['memberData'][$message['msg_author_id']]['map_user_active'], 'boolean'),
        ), 'struct');
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

        'participants'      => new xmlrpcval($participants, 'struct'),
        'list'              => new xmlrpcval($message_list, 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_quote_conversation_func()
{
    global $result;

    $result = new xmlrpcval(array(
        'text_body' => new xmlrpcval($result['message'], 'base64'),
    ), 'struct');

    return new xmlrpcresp($result);
}


function get_delete_topic_func()
{
    global $result, $registry, $app_version;

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

            if (version_compare($app_version, '3.3.0', '>='))
                $can_approve = $is_approved ? $topic['permissions']['TopicSoftDelete']
                                            : $topic['permissions']['TopicSoftDeleteRestore']; // hide
            else
                $can_approve = $permission['topic_q']; // invisible
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
            'timestamp'        => new xmlrpcval(intval($topic['start_date']), 'string'),
            'short_content'     => new xmlrpcval(subject_clean($topic['preview']), 'base64'),

            'deleted_by_id'     => new xmlrpcval($result['other_data'][$topic['tid']]['member_id'], 'string'),
            'deleted_by_name'   => new xmlrpcval(subject_clean($result['other_data'][$topic['tid']]['members_display_name']), 'base64'),
            'delete_reason'     => new xmlrpcval(subject_clean($result['other_data'][$topic['tid']]['sdl_obj_reason']), 'base64'),

            'reply_number'      => new xmlrpcval($topic['posts'], 'int'),
            'view_number'       => new xmlrpcval($topic['views'], 'int'),
            'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
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
    global $result, $registry;

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
            'timestamp'    => new xmlrpcval(intval($post['post_date']), 'string'),
            'short_content'     => new xmlrpcval(subject_clean($post['preview']), 'base64'),

            'deleted_by_id'     => new xmlrpcval($result['other_data'][$post['pid']]['member_id'], 'string'),
            'deleted_by_name'   => new xmlrpcval(subject_clean($result['other_data'][$post['pid']]['members_display_name']), 'base64'),
            'delete_reason'     => new xmlrpcval(subject_clean($result['other_data'][$post['pid']]['sdl_obj_reason']), 'base64'),

            'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
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

function get_moderate_topic_func()
{
    global $result, $registry, $app_version;

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

            if (version_compare($app_version, '3.3.0', '>='))
                $can_approve = $is_approved ? $topic['permissions']['TopicSoftDelete']
                                            : $topic['permissions']['TopicSoftDeleteRestore']; // hide
            else
                $can_approve = $permission['topic_q']; // invisible
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
    global $result, $registry;

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

function reply_conversation_func()
{
    global $result;

    $result = new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
        'msg_id' => new xmlrpcval($result, 'string'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_alert_func()
{
    global $alertData;
    
    $return_array = array();
    foreach ($alertData['data'] as $data)
    {
        $xmlrpcdata = array(
            'user_id' => new xmlrpcval($data['author_id'],'string'),
            'username' => new xmlrpcval($data['author'],'base64'),
            'user_type' => new xmlrpcval(check_return_user_type($data['author'],'base64')),
            'icon_url' => new xmlrpcval($data['icon_url'],'string'),
            'message' => new xmlrpcval($data['message'],'base64'),
            'timestamp' => new xmlrpcval($data['create_time'],'string'),
            'content_type' => new xmlrpcval($data['data_type'],'string'),
            'content_id' => new xmlrpcval($data['data_id'],'string')
        );
        if(!empty($data['position']))
        {
            $xmlrpcdata['position'] = new xmlrpcval($data['position'],'int');
        }
        $return_array[] =new xmlrpcval($xmlrpcdata,'struct');
    }

    $result = new xmlrpcval(array(
        'total' => new xmlrpcval(intval($alertData['total']), 'int'),
        'items' => new xmlrpcval($return_array, 'array'),
    ), 'struct');
    
    return new xmlrpcresp($result);
}