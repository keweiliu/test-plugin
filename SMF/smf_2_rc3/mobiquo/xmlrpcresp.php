<?php

defined('IN_MOBIQUO') or exit;

function get_config_func()
{
    global $mobiquo_config;
    
    $config_list = array(
        'version'    => new xmlrpcval($mobiquo_config['version'], 'string'),
        'is_open'    => new xmlrpcval($mobiquo_config['is_open'] ? true : false, 'boolean'),
        'guest_okay' => new xmlrpcval($mobiquo_config['guest_okay'] ? true : false, 'boolean'),
        'reg_url'    => new xmlrpcval($mobiquo_config['reg_url'], 'string'),
        'api_level'      => new xmlrpcval($mobiquo_config['api_level'], 'string'),
        'disable_search' => new xmlrpcval($mobiquo_config['disable_search'], 'string'),
        'disable_latest' => new xmlrpcval($mobiquo_config['disable_latest'], 'string'),
        'disable_pm'     => new xmlrpcval($mobiquo_config['disable_pm'], 'string'),
        'disable_bbcode' => new xmlrpcval($mobiquo_config['disable_bbcode'], 'string'),
        'report_post'    => new xmlrpcval($mobiquo_config['report_post'], 'string'),
        'mark_forum'     => new xmlrpcval($mobiquo_config['mark_forum'], 'string'),
        'goto_unread'    => new xmlrpcval($mobiquo_config['goto_unread'], 'string'),
        'goto_post'      => new xmlrpcval($mobiquo_config['goto_post'], 'string'),
    );

    $response = new xmlrpcval($config_list, 'struct');

    return new xmlrpcresp($response);
}

function get_forum_func()
{
    global $context;

    $response = new xmlrpcval($context['forum_tree'], 'array');

    return new xmlrpcresp($response);
}

function authorize_user_func()
{
    global $context;
    
    $login_status = ($context['user']['is_guest'] || (isset($context['disable_login_hashing']) && $context['disable_login_hashing'])) ? false : true;
    
    $response = new xmlrpcval(array('authorize_result' => new xmlrpcval($login_status, 'boolean')), 'struct');

    return new xmlrpcresp($response);
}

function login_func()
{
    global $context, $user_info, $modSettings;
    
    $user_info['permissions'] = array();
    loadPermissions();

    $pm_read = !$user_info['is_guest'] && allowedTo('pm_read');
    $pm_send = !$user_info['is_guest'] && allowedTo('pm_send');
    
    $login_status = ($context['user']['is_guest'] || (isset($context['disable_login_hashing']) && $context['disable_login_hashing'])) ? false : true;
    $result_text = (!$login_status && isset($context['login_errors'][0])) ? $context['login_errors'][0] : '';
    
    $usergroup_id = array();
    foreach ($user_info['groups'] as $group_id)
        $usergroup_id[] = new xmlrpcval($group_id);
    
    if (!$modSettings['attachmentSizeLimit']) $modSettings['attachmentSizeLimit'] = 5120;
    if (!$modSettings['attachmentNumPerPostLimit']) $modSettings['attachmentNumPerPostLimit'] = 10;
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($login_status, 'boolean'),
        'result_text'   => new xmlrpcval($result_text, 'base64'),
        'can_pm'        => new xmlrpcval($pm_read, 'boolean'),
        'can_send_pm'   => new xmlrpcval($pm_send, 'boolean'),
        'usergroup_id'  => new xmlrpcval($usergroup_id, 'array'),
        'max_attachment'=> new xmlrpcval($modSettings['attachmentNumPerPostLimit'], 'int'),
        'max_png_size'  => new xmlrpcval($modSettings['attachmentSizeLimit']*1024, 'int'),
        'max_jpg_size'  => new xmlrpcval($modSettings['attachmentSizeLimit']*1024, 'int'),
        'can_upload_avatar' => new xmlrpcval(allowedTo('profile_upload_avatar'), 'boolean'),
    ), 'struct');

    return new xmlrpcresp($response);
}

function login_user_func()
{
    global $context;
    
    $login_status = ($context['user']['is_guest'] || $context['disable_login_hashing']) ? 0 : 1;
    
    $response = new xmlrpcval(array('result' => new xmlrpcval($login_status, 'int')), 'struct');

    return new xmlrpcresp($response);
}

function get_topic_func()
{
    global $context, $board_info, $user_profile, $settings, $scripturl, $modSettings, $mode, $subscribed_tids;
    
    $users = array();
    foreach($context['topics'] as $topic)
    {
        $users[] = $topic['first_post']['member']['id'];
    }
    
    loadMemberData(array_unique($users));
    
    $topic_list = array();
    foreach($context['topics'] as $topic) {
        $avatar = '';
        if (!empty($topic['first_post']['member']['id'])) {
            $profile = $user_profile[$topic['first_post']['member']['id']];
            
            if (!empty($settings['show_user_images']) && empty($profile['options']['show_no_avatars']))
            {
                $avatar = $profile['avatar'] == '' ? ($profile['id_attach'] > 0 ? (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $profile['filename']) : '') : (stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $modSettings['avatar_url'] . '/' . $profile['avatar']);
            }
        }
        
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($board_info['id'], 'string'),
            'topic_id'          => new xmlrpcval($topic['id'], 'string'),
            'topic_title'       => new xmlrpcval(basic_clean($topic['subject']), 'base64'),
            'topic_author_id'   => new xmlrpcval($topic['first_post']['member']['id'], 'string'),
            'topic_author_name' => new xmlrpcval(basic_clean($topic['first_post']['member']['username']), 'base64'),
    'topic_author_display_name' => new xmlrpcval(basic_clean($topic['first_post']['member']['name']), 'base64'),
            'last_reply_time'   => new xmlrpcval($topic['last_post']['time'],'dateTime.iso8601'),
            'reply_number'      => new xmlrpcval($topic['replies'], 'int'),
            'view_number'       => new xmlrpcval($topic['views'], 'int'),
            'short_content'     => new xmlrpcval(basic_clean($topic['first_post']['preview']), 'base64'),
            'icon_url'          => new xmlrpcval($avatar, 'string'),
            
            'new_post'          => new xmlrpcval($topic['new']                  ? true : false, 'boolean'),
            'can_subscribe'     => new xmlrpcval($context['can_mark_notify']    ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval(in_array($topic['id'], $subscribed_tids) ? true : false, 'boolean'),
//            'can_delete'        => new xmlrpcval(isset($topic['quick_mod']['remove']) && $topic['quick_mod']['remove'] ? true : false, 'boolean'),
//            'can_close'         => new xmlrpcval(isset($topic['quick_mod']['lock']) && $topic['quick_mod']['lock']     ? true : false, 'boolean'),
            'is_closed'         => new xmlrpcval($topic['is_locked']            ? true : false, 'boolean'),
//            'can_stick'         => new xmlrpcval(isset($topic['quick_mod']['sticky']) && $topic['quick_mod']['sticky'] ? true : false, 'boolean'),
            'is_stick'          => new xmlrpcval($topic['is_sticky']            ? true : false, 'boolean'),
//            'can_move'          => new xmlrpcval(isset($topic['quick_mod']['move']) && $topic['quick_mod']['move']     ? true : false, 'boolean'),
            'attachment'        => new xmlrpcval($topic['first_post']['icon'] == 'clip' ? 1 : 0, 'string'),
        ), 'struct');

        $topic_list[] = $xmlrpc_topic;
    }
    
    $response = new xmlrpcval(
        array(
            'total_topic_num' => new xmlrpcval($mode == 'TOP' ? $board_info['sticky_num'] : $board_info['total_topics'], 'int'),
            'unread_sticky_count'   => new xmlrpcval($board_info['unread_sticky_num'], 'int'),
            'forum_id'        => new xmlrpcval($board_info['id'], 'string'),
            'forum_name'      => new xmlrpcval(basic_clean($board_info['name']), 'base64'),
            'topics'          => new xmlrpcval($topic_list, 'array'),
            'can_post'        => new xmlrpcval($context['can_post_new'] ? true : false, 'boolean'),
            'can_upload'      => new xmlrpcval($context['can_post_attachment'] ? true : false, 'boolean'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}

function get_thread_func()
{
    global $context, $settings, $options, $txt, $smcFunc, $scripturl, $modSettings, $user_profile;
    
    $rpc_post_list = array();
    $post_place = 0;
    
    while ($message = get_post_detail()) {
        $attachments = array();
        
        if(!empty($message['attachment'])) {

            foreach($message['attachment'] as $attachment)
            {             
                $xmlrpc_attachment = new xmlrpcval(array(
                    'filename'      => new xmlrpcval(basic_clean($attachment['name']), 'base64'),
                    'filesize'      => new xmlrpcval($attachment['byte_size'], 'int'),
                    'content_type'  => new xmlrpcval($attachment['is_image'] ? 'image' : 'others'),
                    'thumbnail_url' => new xmlrpcval($attachment['thumbnail']['has_thumb'] ? $attachment['thumbnail']['href'] : ''),
                    'url'           => new xmlrpcval($attachment['href'])
                ), 'struct');
                $attachments[] = $xmlrpc_attachment;
            }
        }
        
        $avatar = '';
        if (!empty($settings['show_user_images']) && empty($options['show_no_avatars']) && !empty($message['member']['avatar']['image']))
        {
            $avatar = $message['member']['avatar']['href'];
        }

        $xmlrpc_post = new xmlrpcval(array(
            'topic_id'          => new xmlrpcval($context['current_topic'], 'string'),
            'post_id'           => new xmlrpcval($message['id']),
            'post_title'        => new xmlrpcval(basic_clean($message['subject']), 'base64'),
            'post_content'      => new xmlrpcval(post_html_clean($message['body']), 'base64'),
            'post_author_id'    => new xmlrpcval($message['member']['id']),
            'post_author_name'  => new xmlrpcval(basic_clean($message['member']['username']), 'base64'),
    'post_author_display_name'  => new xmlrpcval(basic_clean($message['member']['name']), 'base64'),
            'icon_url'          => new xmlrpcval($avatar),
            'post_time'         => new xmlrpcval($message['time'], 'dateTime.iso8601'),
            'attachments'       => new xmlrpcval($attachments, 'array'),
            
            'is_online'         => new xmlrpcval($message['member']['online']['is_online'] ? true : false, 'boolean'),
//            'can_edit'          => new xmlrpcval($message['can_modify'], 'boolean'),
//            'can_delete'        => new xmlrpcval($message['can_remove'], 'boolean'),
            'allow_smilies'     => new xmlrpcval($message['smileys_enabled'] ? true : false, 'boolean'),
            
//            'is_buddy'          => new xmlrpcval($message['member']['is_buddy'] ? true : false, 'boolean'),
//            'is_reverse_buddy'  => new xmlrpcval($message['member']['is_reverse_buddy'] ? true : false, 'boolean'),
//            'can_view_profile'  => new xmlrpcval($message['member']['can_view_profile'] ? true : false, 'boolean'),
//            'approved'          => new xmlrpcval($message['approved'] ? true : false, 'boolean'),
//            'first_new'         => new xmlrpcval($message['first_new'] ? true : false, 'boolean'),
//            'is_ignored'        => new xmlrpcval($message['is_ignored'] ? true : false, 'boolean'),
//            'can_approve'       => new xmlrpcval($message['can_approve'] ? true : false, 'boolean'),
//            'can_unapprove'     => new xmlrpcval($message['can_unapprove'] ? true : false, 'boolean'),
            
        ), 'struct');
        
        $rpc_post_list[] = $xmlrpc_post;
    }
    
    $context['num_allowed_attachments'] = empty($modSettings['attachmentNumPerPostLimit']) ? 50 : $modSettings['attachmentNumPerPostLimit'];
    $context['can_post_attachment'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments'))) && $context['num_allowed_attachments'] > 0;
    
    return new xmlrpcresp(
        new xmlrpcval(array(
                'total_post_num' => new xmlrpcval($context['total_visible_posts'], 'int'),
                'forum_id'       => new xmlrpcval($context['current_board'], 'string'),
                'forum_name'     => new xmlrpcval(basic_clean($context['forum_name']), 'base64'),
                'topic_id'       => new xmlrpcval($context['current_topic'], 'string'),
                'topic_title'    => new xmlrpcval(basic_clean($context['subject']), 'base64'),
                'can_subscribe'  => new xmlrpcval($context['can_mark_notify']  ? true : false, 'boolean'),
                'issubscribed'   => new xmlrpcval($context['is_marked_notify'] ? true : false, 'boolean'),
                'is_subscribed'  => new xmlrpcval($context['is_marked_notify'] ? true : false, 'boolean'),
//                'can_stick'      => new xmlrpcval($context['can_sticky'] ? true : false, 'boolean'),
                'can_reply'      => new xmlrpcval($context['can_reply'] ? true : false, 'boolean'),
//                'can_delete'     => new xmlrpcval($context['can_delete'] ? true : false, 'boolean'),
                'can_upload'     => new xmlrpcval($context['can_post_attachment'] ? true : false, 'boolean'),
//                'can_close'      => new xmlrpcval($context['can_lock'] ? true : false, 'boolean'),
                'is_closed'      => new xmlrpcval($context['is_locked'] ? true : false, 'boolean'),
                'position'       => new xmlrpcval($context['new_position'], 'int'),
                
                'posts'          => new xmlrpcval($rpc_post_list, 'array'),
                
//                'is_sticky'             => new xmlrpcval($context['is_sticky'] ? true : false, 'boolean'),
//                'is_very_hot'           => new xmlrpcval($context['is_very_hot'] ? true : false, 'boolean'),
//                'is_hot'                => new xmlrpcval($context['is_hot'] ? true : false, 'boolean'),
//                'is_approved'           => new xmlrpcval($context['is_approved'] ? true : false, 'boolean'),
//                'is_poll'               => new xmlrpcval($context['is_marked_notify'] ? true : false, 'boolean'),
//                'can_approve'           => new xmlrpcval($context['can_approve'] ? true : false, 'boolean'),
//                'can_ban'               => new xmlrpcval($context['can_ban'] ? true : false, 'boolean'),
//                'can_merge'             => new xmlrpcval($context['can_merge'] ? true : false, 'boolean'),
//                'can_split'             => new xmlrpcval($context['can_split'] ? true : false, 'boolean'),
//                'can_mark_notify'       => new xmlrpcval($context['can_mark_notify'] ? true : false, 'boolean'),
//                'can_send_topic'        => new xmlrpcval($context['can_send_topic'] ? true : false, 'boolean'),
//                'can_send_pm'           => new xmlrpcval($context['can_send_pm'] ? true : false, 'boolean'),
//                'can_report_moderator'  => new xmlrpcval($context['can_report_moderator'] ? true : false, 'boolean'),
//                'can_moderate_forum'    => new xmlrpcval($context['can_moderate_forum'] ? true : false, 'boolean'),
//                'can_issue_warning'     => new xmlrpcval($context['can_issue_warning'] ? true : false, 'boolean'),
//                'can_restore_topic'     => new xmlrpcval($context['can_restore_topic'] ? true : false, 'boolean'),
//                'can_restore_msg'       => new xmlrpcval($context['can_restore_msg'] ? true : false, 'boolean'),
//                'can_move'              => new xmlrpcval($context['can_move'] ? true : false, 'boolean'),
//                'can_add_poll'          => new xmlrpcval($context['can_add_poll'] ? true : false, 'boolean'),
//                'can_remove_poll'       => new xmlrpcval($context['can_remove_poll'] ? true : false, 'boolean'),
//                'can_reply_unapproved'  => new xmlrpcval($context['can_reply_unapproved'] ? true : false, 'boolean'),
//                'can_reply_approved'    => new xmlrpcval($context['can_reply_approved'] ? true : false, 'boolean'),
//                'can_mark_unread'       => new xmlrpcval($context['can_mark_unread'] ? true : false, 'boolean'),
//                'can_remove_post'       => new xmlrpcval($context['can_remove_post'] ? true : false, 'boolean'),
            ), 'struct'));
}

function get_board_stat_func()
{
    global $context, $modSettings;
    
    $board_stat = array(
        'total_threads' => new xmlrpcval($modSettings['totalTopics'], 'int'),
        'total_posts'   => new xmlrpcval($modSettings['totalMessages'], 'int'),
        'total_members' => new xmlrpcval($modSettings['totalMembers'], 'int'),
        'guest_online'  => new xmlrpcval($context['num_guests'], 'int'),
        'total_online'  => new xmlrpcval($context['num_guests'] + $context['num_users_online'], 'int'),
        
        //'num_buddies'      => new xmlrpcval($context['num_buddies'], 'int'),
        //'num_users_hidden' => new xmlrpcval($context['num_users_hidden'], 'int'),
    );

    $response = new xmlrpcval($board_stat, 'struct');

    return new xmlrpcresp($response);
}

function get_online_users_func()
{
    global $context, $user_profile, $settings, $scripturl, $modSettings;
    
    $users = array();
    foreach($context['members'] as $user)
    {
        $users[] = $user['id'];
    }
    
    loadMemberData($users);    
    
    $user_list = array();
    foreach($context['members'] as $user)
    {
        $profile = $user_profile[$user['id']];
        $avatar = '';
        if (!empty($settings['show_user_images']) && empty($profile['options']['show_no_avatars']))
        {
            $avatar = $profile['avatar'] == '' ? ($profile['id_attach'] > 0 ? (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $profile['filename']) : '') : (stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $modSettings['avatar_url'] . '/' . $profile['avatar']);
        }
        
        $user_list[] = new xmlrpcval(array(
            'user_name'     => new xmlrpcval($user['username'], 'base64'),
            'display_name'  => new xmlrpcval(basic_clean($user['name']), 'base64'),
            'display_text'  => new xmlrpcval(basic_clean($user['action']), 'base64'),
            'icon_url'      => new xmlrpcval($avatar)
        ), 'struct');
    }
    
    action_get_board_stat();

    $online_users = array(
        'member_count' => new xmlrpcval($context['num_users_online'], 'int'),
        'guest_count'  => new xmlrpcval($context['num_guests'], 'int'),
        'list'         => new xmlrpcval($user_list, 'array')
    );

    $response = new xmlrpcval($online_users, 'struct');

    return new xmlrpcresp($response);
}

function get_user_info_func()
{
    global $context, $txt, $modSettings;
    
    $custom_fields = array();
    
    if (!empty($context['member']['group']) || !empty($context['member']['post_group'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['position'], 'base64'),
            'value' => new xmlrpcval(!empty($context['member']['group']) ? $context['member']['group'] : $context['member']['post_group'], 'base64')
        ), 'struct');
    }
    
    if ($context['member']['show_email'] == 'yes' || $context['member']['show_email'] == 'yes_permission_override') {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['email'], 'base64'),
            'value' => new xmlrpcval($context['member']['email'], 'base64')
        ), 'struct');
    }
    
    if ($context['member']['website']['url'] != '' && !isset($context['disabled_fields']['website'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['website_title'], 'base64'),
            'value' => new xmlrpcval($context['member']['website']['url'], 'base64')
        ), 'struct');
    }
    
    if (!isset($context['disabled_fields']['icq']) && !empty($context['member']['icq']['link'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['icq'], 'base64'),
            'value' => new xmlrpcval($context['member']['icq']['name'], 'base64')
        ), 'struct');
    }
    
    if (!isset($context['disabled_fields']['msn']) && !empty($context['member']['msn']['link'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['msn'], 'base64'),
            'value' => new xmlrpcval($context['member']['msn']['name'], 'base64')
        ), 'struct');
    }
    
    if (!isset($context['disabled_fields']['aim']) && !empty($context['member']['aim']['link'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['aim'], 'base64'),
            'value' => new xmlrpcval($context['member']['aim']['name'], 'base64')
        ), 'struct');
    }
    
    if (!isset($context['disabled_fields']['yim']) && !empty($context['member']['yim']['link'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['yim'], 'base64'),
            'value' => new xmlrpcval($context['member']['yim']['name'], 'base64')
        ), 'struct');
    }
    
    if (!empty($modSettings['titlesEnable']) && !empty($context['member']['title'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['custom_title'], 'base64'),
            'value' => new xmlrpcval(basic_clean($context['member']['title']), 'base64')
        ), 'struct');
    }
    
    if ($modSettings['karmaMode'] == '1') {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($modSettings['karmaLabel'], 'base64'),
            'value' => new xmlrpcval($context['member']['karma']['good'] - $context['member']['karma']['bad'], 'base64')
        ), 'struct');
    } elseif ($modSettings['karmaMode'] == '2') {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($modSettings['karmaLabel'], 'base64'),
            'value' => new xmlrpcval('+'.$context['member']['karma']['good'].'/-'.$context['member']['karma']['bad'], 'base64')
        ), 'struct');
    }
    
    if (!isset($context['disabled_fields']['gender']) && !empty($context['member']['gender']['name'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['gender'], 'base64'),
            'value' => new xmlrpcval($context['member']['gender']['name'], 'base64')
        ), 'struct');
    }
    
    if ($context['member']['age']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['age'], 'base64'),
            'value' => new xmlrpcval($context['member']['age'], 'base64')
        ), 'struct');
    }
    
    if (!isset($context['disabled_fields']['location']) && !empty($context['member']['location'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['location'], 'base64'),
            'value' => new xmlrpcval(basic_clean($context['member']['location']), 'base64')
        ), 'struct');
    }
    
    if ($context['can_view_warning'] && $context['member']['warning']) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['profile_warning_level'], 'base64'),
            'value' => new xmlrpcval($context['member']['warning'].'%', 'base64')
        ), 'struct');
    }
    
    if ($context['can_see_ip']) {
        if (!empty($context['member']['ip'])) {
            $custom_fields[] = new xmlrpcval(array(
                'name'  => new xmlrpcval($txt['ip'], 'base64'),
                'value' => new xmlrpcval($context['member']['ip'], 'base64')
            ), 'struct');
        }
        
        if (empty($modSettings['disableHostnameLookup']) && !empty($context['member']['ip'])) {
            $custom_fields[] = new xmlrpcval(array(
                'name'  => new xmlrpcval($txt['hostname'], 'base64'),
                'value' => new xmlrpcval($context['member']['hostname'], 'base64')
            ), 'struct');
        }
    }
    
    if (!empty($modSettings['userLanguage']) && !empty($context['member']['language'])) {
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($txt['language'], 'base64'),
            'value' => new xmlrpcval($context['member']['language'], 'base64')
        ), 'struct');
    }
    
    $xmlrpc_user_info = new xmlrpcval(array(
        'post_count'            => new xmlrpcval(!isset($context['disabled_fields']['posts']) ? $context['member']['posts'] : '', 'int'),
        'reg_time'              => new xmlrpcval($context['member']['registered'], 'dateTime.iso8601'),
        'last_activity_time'    => new xmlrpcval($context['member']['last_login'], 'dateTime.iso8601'),
        'icon_url'              => new xmlrpcval($context['member']['avatar']['href']),
        'display_name'          => new xmlrpcval(basic_clean($context['member']['name']), 'base64'),
        'display_text'          => new xmlrpcval(basic_clean($context['member']['blurb']), 'base64'),
        'is_online'             => new xmlrpcval($context['member']['online']['is_online'] ? true : false, 'boolean'),
//        'can_ban'               => new xmlrpcval(!empty($context['menu_data_1']['sections']['profile_action']['areas']['banuser']) ? true : false, 'boolean'),
        'can_upload'            => new xmlrpcval(allowedTo('profile_upload_avatar') && $context['user']['is_owner'] ? true : false, 'boolean'),
        'can_send_pm'           => new xmlrpcval($context['can_send_pm'] ? true : false, 'boolean'),
        'accept_pm'             => new xmlrpcval(allowedTo('pm_send') ? true : false, 'boolean'),
        'is_buddy'              => new xmlrpcval($context['member']['is_buddy'] ? true : false, 'boolean'),
//        'can_have_buddy'        => new xmlrpcval(!empty($context['can_have_buddy']) && !$context['user']['is_owner'] ? true : false, 'boolean'),
        'custom_fields_list'    => new xmlrpcval($custom_fields, 'array'),
    ), 'struct');

    return new xmlrpcresp($xmlrpc_user_info);
}


function get_user_reply_post_func()
{
    global $context;
    
    $post_list = array();
    foreach($context['posts'] as $post)
    {
        $topic_info = get_topic_info($post['board']['id'], $post['topic']);
        
        $xmlrpc_post = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($post['board']['id']),
            'forum_name'        => new xmlrpcval(basic_clean($post['board']['name']), 'base64'),
            'topic_id'          => new xmlrpcval($post['topic']),
            'topic_title'       => new xmlrpcval(basic_clean($topic_info['first_subject']), 'base64'),
            'post_id'           => new xmlrpcval($post['id']),
            'post_title'        => new xmlrpcval(basic_clean($post['subject']), 'base64'),
            'short_content'     => new xmlrpcval(basic_clean($post['body'], 100), 'base64'),
            'icon_url'          => new xmlrpcval($topic_info['first_poster_avatar']),
            'post_time'         => new xmlrpcval($post['time'], 'dateTime.iso8601'),
            'reply_number'      => new xmlrpcval($topic_info['num_replies'], 'int'),
            'view_number'       => new xmlrpcval($topic_info['num_views'], 'int'),
            'new_post'          => new xmlrpcval($topic_info['new'] ? true : false, 'boolean'),
            
//            'can_move'          => new xmlrpcval(allowedTo('move_any') || ($context['user']['is_owner'] && allowedTo('move_own')), 'boolean'),
//            'can_approve'       => new xmlrpcval(allowedTo('approve_posts') ? true : false, 'boolean'),
            'is_approved'       => new xmlrpcval($post['approved'] ? true : false, 'boolean'),
//            'can_delete'        => new xmlrpcval($post['can_delete'] && $post['delete_possible'] ? true : false, 'boolean'),
            'can_reply'         => new xmlrpcval($post['can_reply'] ? true : false, 'boolean'),
            'can_subscribe'     => new xmlrpcval($post['can_mark_notify'] ? true : false, 'boolean'),
        ), 'struct');

        $post_list[] = $xmlrpc_post;
    }

    return new xmlrpcresp(new xmlrpcval($post_list, 'array'));
}

function get_user_topic_func()
{
    global $context, $modSettings;
    
    $topic_list = array();
    foreach($context['posts'] as $topic)
    {
        $topic_info = get_topic_info($topic['board']['id'], $topic['topic']);
        
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($topic['board']['id']),
            'forum_name'        => new xmlrpcval(basic_clean($topic['board']['name']), 'base64'),
            'topic_id'          => new xmlrpcval($topic['topic']),
            'topic_title'       => new xmlrpcval(basic_clean($topic['subject']), 'base64'),
 'last_reply_author_name'       => new xmlrpcval($topic_info['last_member_name'], 'base64'),
'last_reply_author_display_name'=> new xmlrpcval(basic_clean($topic_info['last_display_name']), 'base64'),
            'short_content'     => new xmlrpcval(basic_clean($topic_info['first_body']), 'base64'),
            'icon_url'          => new xmlrpcval($topic_info['last_poster_avatar']),
            'last_reply_time'   => new xmlrpcval($topic_info['last_poster_time'], 'dateTime.iso8601'),
            'reply_number'      => new xmlrpcval($topic_info['num_replies'], 'int'),
            'view_number'       => new xmlrpcval($topic_info['num_views'], 'int'),
            'new_post'          => new xmlrpcval($topic_info['new'] ? true : false, 'boolean'),
            
            'can_subscribe'     => new xmlrpcval($topic_info['can_mark_notify']  ? true : false, 'boolean'),
            'issubscribed'      => new xmlrpcval($topic_info['is_marked_notify'] ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval($topic_info['is_marked_notify'] ? true : false, 'boolean'),
//            'can_delete'        => new xmlrpcval($topic_info['can_remove']  ? true : false, 'boolean'),
//            'can_close'         => new xmlrpcval($topic_info['can_lock']    ? true : false, 'boolean'),
            'is_closed'         => new xmlrpcval($topic_info['is_locked']   ? true : false, 'boolean'),
//            'can_stick'         => new xmlrpcval($topic_info['can_sticky']  ? true : false, 'boolean'),
            'is_stick'          => new xmlrpcval($topic_info['is_sticky']   ? true : false, 'boolean'),
//            'can_move'          => new xmlrpcval($topic_info['can_move']    ? true : false, 'boolean'),
//            'can_approve'       => new xmlrpcval($topic_info['can_approve'] ? true : false, 'boolean'),
            'is_approved'       => new xmlrpcval($topic_info['is_approved'] ? true : false, 'boolean'),
        ), 'struct');

        $topic_list[] = $xmlrpc_topic;
    }
    
    return new xmlrpcresp(new xmlrpcval($topic_list, 'array'));
}

function get_unread_topic_func()
{
    global $context, $user_info, $mode;
    
    $topic_list = array();
    foreach($context['topics'] as $topic) 
    {
        $topic_info = get_topic_info($topic['board']['id'], $topic['id']);

        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($topic['board']['id'], 'string'),
            'forum_name'        => new xmlrpcval(basic_clean($topic['board']['name']), 'base64'),
            'topic_id'          => new xmlrpcval($topic['id'], 'string'),
            'topic_title'       => new xmlrpcval(basic_clean($topic['subject']), 'base64'),
            'post_author_name'  => new xmlrpcval(basic_clean($topic['last_post']['member']['name']), 'base64'),
    'post_author_display_name'  => new xmlrpcval(basic_clean($topic['last_post']['member']['name']), 'base64'),
            'post_time'         => new xmlrpcval($topic['last_post']['time'],'dateTime.iso8601'),
            'reply_number'      => new xmlrpcval($topic['replies'], 'int'),
            'view_number'       => new xmlrpcval($topic['views'], 'int'),
            'short_content'     => new xmlrpcval(basic_clean($topic_info['last_body']), 'base64'),
            'icon_url'          => new xmlrpcval($topic_info['last_poster_avatar']),
            'new_post'          => new xmlrpcval(true, 'boolean'),
            
            'can_subscribe'     => new xmlrpcval($topic_info['can_mark_notify']     ? true : false, 'boolean'),
            'issubscribed'      => new xmlrpcval($topic_info['is_marked_notify']    ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval($topic_info['is_marked_notify']    ? true : false, 'boolean'),
//            'can_delete'        => new xmlrpcval($topic_info['can_remove']  ? true : false, 'boolean'),
//            'can_close'         => new xmlrpcval($topic_info['can_lock']    ? true : false, 'boolean'),
            'is_closed'         => new xmlrpcval($topic['is_locked']        ? true : false, 'boolean'),
//            'can_stick'         => new xmlrpcval($topic_info['can_sticky']  ? true : false, 'boolean'),
            'is_stick'          => new xmlrpcval($topic['is_sticky']        ? true : false, 'boolean'),
//            'can_move'          => new xmlrpcval($topic_info['can_move']    ? true : false, 'boolean'),
//            'can_approve'       => new xmlrpcval($topic_info['can_approve'] ? true : false, 'boolean'),
            'is_approved'       => new xmlrpcval($topic_info['is_approved'] ? true : false, 'boolean'),
        ), 'struct');

        $topic_list[] = $xmlrpc_topic;
    }
    
    $total_unread_topic_num = isset($context['num_topics']) ? $context['num_topics'] : 0;
    
    $response = new xmlrpcval(
        array(
            'total_topic_num' => new xmlrpcval($total_unread_topic_num, 'int'),
            'topics'          => new xmlrpcval($topic_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}

function get_new_topic_func()
{
    global $context;
    
    $topic_list = array();
    foreach ($context['posts'] as $topic)
    {
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($topic['board']['id']),
            'forum_name'        => new xmlrpcval(basic_clean($topic['board']['name']), 'base64'),
            'topic_id'          => new xmlrpcval($topic['topic']),
            'topic_title'       => new xmlrpcval(basic_clean($topic['subject']), 'base64'),
            'reply_number'      => new xmlrpcval($topic['replies'], 'int'),
            'view_number'       => new xmlrpcval($topic['views'], 'int'),
            'short_content'     => new xmlrpcval(basic_clean($topic['preview']), 'base64'),
            'post_author_id'    => new xmlrpcval($topic['poster']['id']),
            'post_author_name'  => new xmlrpcval(basic_clean($topic['poster']['name']), 'base64'),
    'post_author_display_name'  => new xmlrpcval(basic_clean($topic['poster']['name']), 'base64'),
            'new_post'          => new xmlrpcval($topic['is_new'] ? true : false, 'boolean'),
            'post_time'         => new xmlrpcval($topic['time'], 'dateTime.iso8601'),
            'icon_url'          => new xmlrpcval($topic['poster']['avatar']),
            
            'can_subscribe'     => new xmlrpcval($topic['can_mark_notify']  ? true : false, 'boolean'),
            'issubscribed'      => new xmlrpcval($topic['is_marked_notify'] ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval($topic['is_marked_notify'] ? true : false, 'boolean'),
//            'can_delete'        => new xmlrpcval($topic['can_remove']  ? true : false, 'boolean'),
//            'can_close'         => new xmlrpcval($topic['can_lock']    ? true : false, 'boolean'),
            'is_closed'         => new xmlrpcval($topic['is_locked']   ? true : false, 'boolean'),
//            'can_stick'         => new xmlrpcval($topic['can_sticky']  ? true : false, 'boolean'),
            'is_stick'          => new xmlrpcval($topic['is_sticky']   ? true : false, 'boolean'),
//            'can_move'          => new xmlrpcval($topic['can_move']    ? true : false, 'boolean'),
//            'can_approve'       => new xmlrpcval($topic['can_approve'] ? true : false, 'boolean'),
            'is_approved'       => new xmlrpcval($topic['is_approved'] ? true : false, 'boolean'),
        ), 'struct');

        $topic_list[] = $xmlrpc_topic;
    }

    return new xmlrpcresp(new xmlrpcval($topic_list, 'array'));
}

function get_subscribed_forum_func()
{
    global $context;
    
    $board_list = array();
    foreach($context['boards'] as $board) 
    {
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'      => new xmlrpcval($board['id'], 'string'),
            'forum_name'    => new xmlrpcval(basic_clean($board['name']), 'base64'),
            'icon_url'      => new xmlrpcval($board['logo']),
            'new_post'      => new xmlrpcval($board['new'] ? true : false, 'boolean'),
        ), 'struct');

        $board_list[] = $xmlrpc_topic;
    }
    
    $response = new xmlrpcval(
        array(
            'total_forums_num' => new xmlrpcval(count($context['boards']), 'int'),
            'forums' => new xmlrpcval($board_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}

function get_subscribed_topic_func()
{
    global $context;

    $topic_list = array();
    foreach ($context['topics'] as $topic)
    {
        $topic_info = get_topic_info($topic['id_board'], $topic['id_topic']);
        
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($topic['id_board']),
            'forum_name'        => new xmlrpcval(basic_clean($topic['board_name']), 'base64'),
            'topic_id'          => new xmlrpcval($topic_info['id_topic']),
            'topic_title'       => new xmlrpcval(basic_clean($topic_info['first_subject']), 'base64'),
            'reply_number'      => new xmlrpcval($topic_info['num_replies'], 'int'),
            'view_number'       => new xmlrpcval($topic_info['num_views'], 'int'),
            'short_content'     => new xmlrpcval(basic_clean($topic_info['last_body']), 'base64'),
            'post_author_name'  => new xmlrpcval(basic_clean($topic_info['first_member_name']), 'base64'),
    'post_author_display_name'  => new xmlrpcval(basic_clean($topic_info['first_display_name']), 'base64'),
            'new_post'          => new xmlrpcval($topic_info['new'] ? true : false, 'boolean'),
            'post_time'         => new xmlrpcval($topic_info['last_poster_time'], 'dateTime.iso8601'),
            'icon_url'          => new xmlrpcval($topic_info['last_poster_avatar']),
            
            'can_subscribe'     => new xmlrpcval($topic_info['can_mark_notify']  ? true : false, 'boolean'),
            'issubscribed'      => new xmlrpcval($topic_info['is_marked_notify'] ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval($topic_info['is_marked_notify'] ? true : false, 'boolean'),
//            'can_delete'        => new xmlrpcval($topic_info['can_remove']  ? true : false, 'boolean'),
//            'can_close'         => new xmlrpcval($topic_info['can_lock']    ? true : false, 'boolean'),
            'is_closed'         => new xmlrpcval($topic_info['is_locked']   ? true : false, 'boolean'),
//            'can_stick'         => new xmlrpcval($topic_info['can_sticky']  ? true : false, 'boolean'),
            'is_stick'          => new xmlrpcval($topic_info['is_sticky']   ? true : false, 'boolean'),
//            'can_move'          => new xmlrpcval($topic_info['can_move']    ? true : false, 'boolean'),
//            'can_approve'       => new xmlrpcval($topic_info['can_approve'] ? true : false, 'boolean'),
            'is_approved'       => new xmlrpcval($topic_info['is_approved'] ? true : false, 'boolean'),
        ), 'struct');

        $topic_list[] = $xmlrpc_topic;
    }

    $response = new xmlrpcval(
        array(
            'total_topic_num' => new xmlrpcval($context['topic_num'], 'int'),
            'topics'          => new xmlrpcval($topic_list, 'array'),
        ),
        'struct'
    );

    return new xmlrpcresp($response);
}

function create_topic_func()
{
    global $new_topic_id, $is_approved;

    $xmlrpc_create_topic = new xmlrpcval(array(
        'result'    => new xmlrpcval($new_topic_id ? true : false, 'boolean'),
        'topic_id'  => new xmlrpcval($new_topic_id),
        'state'     => new xmlrpcval($is_approved ? 0 : 1, 'int'),
    ), 'struct');

    return new xmlrpcresp($xmlrpc_create_topic);
}

function reply_topic_func()
{
    global $new_post_id, $becomesApproved;

    $xmlrpc_reply_topic = new xmlrpcval(array(
        'result'    => new xmlrpcval($new_post_id ? true : false, 'boolean'),
        'post_id'   => new xmlrpcval($new_post_id),
        'state'     => new xmlrpcval($becomesApproved ? 0 : 1, 'int'),
    ), 'struct');

    return new xmlrpcresp($xmlrpc_reply_topic);
}

function get_raw_post_func()
{
    global $context;
    
    $response = new xmlrpcval(
        array(
            'post_id'       => new xmlrpcval($_GET['msg']),
            'post_title'    => new xmlrpcval(basic_clean($context['subject']), 'base64'),
            'post_content'  => new xmlrpcval(basic_clean($context['message']), 'base64'),
        ),
        'struct'
    );
    
    return new xmlrpcresp($response);
}

function save_raw_post_func()
{
    global $becomesApproved;
    
    return new xmlrpcresp(
        new xmlrpcval(array(
            'result' => new xmlrpcval(true, 'boolean'),
            'state'  => new xmlrpcval($becomesApproved ? 0 : 1, 'int'),
        ), 'struct'));
}

function get_quote_post_func()
{
    global $context;
    
    $response = new xmlrpcval(
        array(
            'post_id'       => new xmlrpcval($_GET['quote']),
            'post_title'    => new xmlrpcval(basic_clean($context['subject']), 'base64'),
            'post_content'  => new xmlrpcval(basic_clean($context['message']), 'base64'),
        ),
        'struct'
    );
    
    return new xmlrpcresp($response);
}

function get_quote_pm_func()
{
    global $context;
    
    $response = new xmlrpcval(
        array(
            'msg_id'        => new xmlrpcval($_GET['pmsg']),
            'msg_subject'   => new xmlrpcval(basic_clean($context['subject']), 'base64'),
            'text_body'     => new xmlrpcval(basic_clean($context['message']), 'base64'),
        ),
        'struct'
    );
    
    return new xmlrpcresp($response);
}

function get_inbox_stat_func()
{
    global $user_info;
    
    $result = new xmlrpcval(array(
        'inbox_unread_count' => new xmlrpcval($user_info['unread_messages'], 'int')
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_box_info_func()
{
    global $context;
    
    $box_list = array();
    foreach($context['boxes'] as $box)
    {
        $box_list[] = new xmlrpcval(array(
            'box_id'        => new xmlrpcval($box['id'], 'string'),
            'box_name'      => new xmlrpcval(basic_clean($box['name']), 'base64'),
            'msg_count'     => new xmlrpcval($box['msg_count'], 'int'),
            'unread_count'  => new xmlrpcval($box['unread_count'], 'int'),
            'box_type'      => new xmlrpcval($box['box_type'], 'string')
        ), 'struct');
    }

    $result = new xmlrpcval(array(
        'message_room_count' => new xmlrpcval($context['message_remain'], 'int'),
        'list'               => new xmlrpcval($box_list, 'array')
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_box_func()
{
    global $context;
    
    $pm_list = array();
    foreach ($context['messages'] as $pm)
    {
        $msg_to = array();
        foreach ($pm['recipients']['to'] as $rec_user) {
            $msg_to[] = new xmlrpcval(array('username' => new xmlrpcval(basic_clean($rec_user), 'base64')), 'struct');
        }
        foreach ($pm['recipients']['bcc'] as $rec_user) {
            $msg_to[] = new xmlrpcval(array('username' => new xmlrpcval(basic_clean($rec_user), 'base64')), 'struct');
        }
        
        $pm_list[] = new xmlrpcval(array(
            'msg_id'        => new xmlrpcval($pm['id']),
            'msg_state'     => new xmlrpcval($pm['is_unread'] ? 1 : ($pm['is_replied_to'] ? 3 : 2), 'int'),
            'sent_date'     => new xmlrpcval($pm['time'],'dateTime.iso8601'),
            'msg_from'      => new xmlrpcval(basic_clean($pm['msg_from']), 'base64'),
            'icon_url'      => new xmlrpcval($pm['member']['avatar']['href']),
            'msg_to'        => new xmlrpcval($msg_to, 'array'),
            'msg_subject'   => new xmlrpcval(basic_clean($pm['subject']), 'base64'),
            'short_content' => new xmlrpcval(basic_clean($pm['body'], 100), 'base64'),
            'is_online'     => new xmlrpcval($pm['member']['online']['is_online'] ? true : false, 'boolean'),
        ), 'struct');
    }

    $result = new xmlrpcval(array(
        'total_message_count' => new xmlrpcval($context['pmnum'], 'int'),
        'total_unread_count'  => new xmlrpcval($context['unread_messages'], 'int'),
        'list'                => new xmlrpcval($pm_list, 'array')
    ), 'struct');

    return new xmlrpcresp($result);
}

function delete_message_func()
{
    return new xmlrpcresp(new xmlrpcval(array('result' => new xmlrpcval(true, 'boolean')), 'struct'));
}

function get_message_func()
{
    global $context;
    
    $result = new xmlrpcval(array(
        'msg_from'      => new xmlrpcval(basic_clean($context['pm']['username']), 'base64'),
'msg_from_display_name' => new xmlrpcval(basic_clean($context['pm']['name']), 'base64'),
        'msg_to'        => new xmlrpcval($context['pm']['recipients'], 'array'),
        'icon_url'      => new xmlrpcval($context['pm']['member']['avatar']['href']),
        'sent_date'     => new xmlrpcval($context['pm']['time'],'dateTime.iso8601'),
        'msg_subject'   => new xmlrpcval(basic_clean($context['pm']['subject']), 'base64'),
        'text_body'     => new xmlrpcval(post_html_clean($context['pm']['body']), 'base64'),
        'is_online'     => new xmlrpcval($context['pm']['member']['online']['is_online'] ? true : false, 'boolean'),
        'allow_smilies' => new xmlrpcval(true, 'boolean'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function attach_image_func()
{
    global $context;
    
    if (isset($context['attachids'][0]))
    {
        $associate_id = implode('.', $context['attachids']);
        $xmlrpc_result = new xmlrpcval(array('attachment_id'  => new xmlrpcval($associate_id)), 'struct');
        return new xmlrpcresp($xmlrpc_result);
    }
    else
    {
        get_error('Add attachment failed!');
    }
}

function search_topic_func()
{
    global $context;
    
    $topic_list = array();
    while ($topic = $context['get_topics']())
    {
        $topic_info = get_topic_info($topic['board']['id'], $topic['id']);
        
        $xmlrpc_topic = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($topic['board']['id']),
            'forum_name'        => new xmlrpcval(basic_clean($topic['board']['name']), 'base64'),
            'topic_id'          => new xmlrpcval($topic['id']),
            'topic_title'       => new xmlrpcval(basic_clean($topic['matches'][0]['subject']), 'base64'),
       'post_author_name'       => new xmlrpcval($topic['matches'][0]['member']['username'], 'base64'),
    'post_author_display_name'  => new xmlrpcval(basic_clean($topic['matches'][0]['member']['name']), 'base64'),
            'short_content'     => new xmlrpcval(basic_clean($topic['matches'][0]['body']), 'base64'),
            'icon_url'          => new xmlrpcval($topic['matches'][0]['member']['avatar']['href']),
            'post_time'         => new xmlrpcval($topic['matches'][0]['time'], 'dateTime.iso8601'),
            'reply_number'      => new xmlrpcval($topic_info['num_replies'], 'int'),
            'view_number'       => new xmlrpcval($topic_info['num_views'], 'int'),
            'new_post'          => new xmlrpcval($topic_info['new'] ? true : false, 'boolean'),
            
            'can_subscribe'     => new xmlrpcval($topic_info['can_mark_notify']  ? true : false, 'boolean'),
            'issubscribed'      => new xmlrpcval($topic_info['is_marked_notify'] ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval($topic_info['is_marked_notify'] ? true : false, 'boolean'),
//            'can_delete'        => new xmlrpcval($topic_info['can_remove']  ? true : false, 'boolean'),
//            'can_close'         => new xmlrpcval($topic_info['can_lock']    ? true : false, 'boolean'),
            'is_closed'         => new xmlrpcval($topic_info['is_locked']   ? true : false, 'boolean'),
//            'can_stick'         => new xmlrpcval($topic_info['can_sticky']  ? true : false, 'boolean'),
            'is_stick'          => new xmlrpcval($topic_info['is_sticky']   ? true : false, 'boolean'),
//            'can_move'          => new xmlrpcval($topic_info['can_move']    ? true : false, 'boolean'),
//            'can_approve'       => new xmlrpcval($topic_info['can_approve'] ? true : false, 'boolean'),
            'is_approved'       => new xmlrpcval($topic_info['is_approved'] ? true : false, 'boolean'),
        ), 'struct');

        $topic_list[] = $xmlrpc_topic;
    }
    
    $result = new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($context['num_results'], 'int'),
        'topics'          => new xmlrpcval($topic_list, 'array')
    ), 'struct');
    
    return new xmlrpcresp($result);
}

function search_post_func()
{
    global $context;
    
    $post_list = array();
    while ($topic = $context['get_topics']())
    {
        $topic_info = get_topic_info($topic['board']['id'], $topic['id']);
        
        $xmlrpc_post = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($topic['board']['id']),
            'forum_name'        => new xmlrpcval(basic_clean($topic['board']['name']), 'base64'),
            'topic_id'          => new xmlrpcval($topic['id']),
            'topic_title'       => new xmlrpcval(basic_clean($topic['first_post']['subject']), 'base64'),
            'post_id'           => new xmlrpcval($topic['matches'][0]['id'], 'string'),
            'post_title'        => new xmlrpcval(basic_clean($topic['matches'][0]['subject']), 'base64'),
       'post_author_name'       => new xmlrpcval($topic['matches'][0]['member']['username'], 'base64'),
    'post_author_display_name'  => new xmlrpcval(basic_clean($topic['matches'][0]['member']['name']), 'base64'),
            'short_content'     => new xmlrpcval(basic_clean($topic['matches'][0]['body']), 'base64'),
            'icon_url'          => new xmlrpcval($topic['matches'][0]['member']['avatar']['href']),
            'post_time'         => new xmlrpcval($topic['matches'][0]['time'], 'dateTime.iso8601'),
            'reply_number'      => new xmlrpcval($topic_info['num_replies'], 'int'),
            'view_number'       => new xmlrpcval($topic_info['num_views'], 'int'),
            'new_post'          => new xmlrpcval($topic_info['new'] ? true : false, 'boolean'),
            
            'can_subscribe'     => new xmlrpcval($topic_info['can_mark_notify']  ? true : false, 'boolean'),
            'issubscribed'      => new xmlrpcval($topic_info['is_marked_notify'] ? true : false, 'boolean'),
            'is_subscribed'     => new xmlrpcval($topic_info['is_marked_notify'] ? true : false, 'boolean'),
//            'can_delete'        => new xmlrpcval($topic_info['can_remove']  ? true : false, 'boolean'),
//            'can_close'         => new xmlrpcval($topic_info['can_lock']    ? true : false, 'boolean'),
            'is_closed'         => new xmlrpcval($topic_info['is_locked']   ? true : false, 'boolean'),
//            'can_stick'         => new xmlrpcval($topic_info['can_sticky']  ? true : false, 'boolean'),
            'is_stick'          => new xmlrpcval($topic_info['is_sticky']   ? true : false, 'boolean'),
//            'can_move'          => new xmlrpcval($topic_info['can_move']    ? true : false, 'boolean'),
//            'can_approve'       => new xmlrpcval($topic_info['can_approve'] ? true : false, 'boolean'),
            'is_approved'       => new xmlrpcval($topic_info['is_approved'] ? true : false, 'boolean'),
        ), 'struct');

        $post_list[] = $xmlrpc_post;
    }
    
    $result = new xmlrpcval(array(
        'total_post_num' => new xmlrpcval($context['num_results'], 'int'),
        'posts'          => new xmlrpcval($post_list, 'array')
    ), 'struct');
    
    return new xmlrpcresp($result);
}

function xmlresptrue()
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval('', 'base64')
    ), 'struct');
    
    return new xmlrpcresp($result);
}

function upload_attach_func()
{
    global $attachIDs, $group_id;
    
    if (!empty($attachIDs)) $group_id[$attachIDs[0]] = isset($attachIDs[1]) ? $attachIDs[1] : '';
    
    $xmlrpc_result = new xmlrpcval(array(
        'attachment_id' => new xmlrpcval(implode('.', $attachIDs)),
        'group_id'      => new xmlrpcval(serialize($group_id)),
        'result'        => new xmlrpcval(empty($attachIDs) ? false : true, 'boolean'),
    ), 'struct');
    
    return new xmlrpcresp($xmlrpc_result);
}

function remove_attachment_func()
{
    global $group_id;
    
    $xmlrpc_result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'group_id'      => new xmlrpcval(serialize($group_id)),
    ), 'struct');
    
    return new xmlrpcresp($xmlrpc_result);
}
