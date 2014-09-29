<?php
/***************************************************************
* Mobiquo-Templates.php                                        *
* Copyright 2009 Quoord Systems Ltd. All Rights Reserved.     *
* Created by Dragooon (http://smf-media.com)                   *
****************************************************************
* This file or any content of the file should not be           *
* redistributed in any form of matter. This file is a part of  *
* Tapatalk package and should not be used and distributed      *
* in any form not approved by Quoord Systems Ltd.              *
* http://tapatalk.com | http://taptatalk.com/license.html      *
****************************************************************
* Contains XML templates for mobiquo mod                       *
***************************************************************/

if (!defined('SMF'))
    die('Hacking attempt...');

// Outputs the board tree
function outputRPCBoardTree($tree)
{
    // Start of response
    $response = '
<params>
<param>
<value>
<array>
<data>';
    // Record the actual boards
    foreach ($tree as $node)
        outputRPCBoardNode($node, $response);

    // End the response
    $response .= '
</data>
</array>
</value>
</param>
</params>';

    // Send the data
    outputRPCResponse($response);
}

// Outputs a board node
function outputRPCBoardNode($node, &$response)
{
    $response .= '
<value>
<struct>
<member>
<name>forum_id</name>
<value><string>' . $node['id'] . '</string></value>
</member>
<member>
<name>forum_name</name>
<value><base64>' . base64_encode(mobi_unescape_html($node['name'])) . '</base64></value>
</member>
<member>
<name>description</name>
<value><base64>' . base64_encode(mobi_unescape_html($node['description'])) . '</base64></value>
</member>
<member>
<name>sub_only</name>
<value><boolean>' . (empty($node['sub_only']) ? 0 : 1) . '</boolean></value>
</member>
<member>
<name>parent_id</name>
<value><string>' . (empty($node['parent']) ? '-1' : $node['parent']) . '</string></value>
</member>
<member>
<name>logo_url</name>
<value><string>' . process_url($node['icon']) . '</string></value>
</member>
<member>
<name>is_protected</name>
<value><boolean>0</boolean></value>
</member>
<member>
<name>new_post</name>
<value><boolean>' . ($node['new'] ? 1 : 0) . '</boolean></value>
</member>
<member>
<name>is_subscribed</name>
<value><boolean>' . ($node['is_notify'] ? 1 : 0) . '</boolean></value>
</member>
<member>
<name>can_subscribe</name>
<value><boolean>' . ($node['can_notify'] ? 1 : 0) . '</boolean></value>
</member>
<member>
<name>url</name>
<value><string>' . (!empty($node['redirect']) ? process_url($node['redirect']) : '') . '</string></value>
</member>';
    // Are their any children?
    if (!empty($node['children']))
    {
        $response .= '
<member>
<name>child</name>
<value><array><data>';
        foreach ($node['children'] as $child)
            outputRPCBoardNode($child, $response);
        $response .= '
</data></array></value>
</member>';
    }

    $response .= '
</struct>
</value>';
}

// Outputs the topic structure
function outputRPCTopics($topics, $board_info)
{
    // Start the response
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>prefixes</name>
<value><array><data></data></array></value>
</member>
<member>
<name>total_topic_num</name>
<value><int>' . intval($board_info['total_topic_num']) . '</int></value>
</member>
<member>
<name>unread_sticky_count</name>
<value><int>' . intval($board_info['unread_sticky_count']) . '</int></value>
</member>
<member>
<name>forum_id</name>
<value><string>' . $board_info['id_board'] . '</string></value>
</member>
<member>
<name>forum_name</name>
<value><base64>' . base64_encode(mobi_unescape_html($board_info['board_name'])) . '</base64></value>
</member>
<member>
<name>can_post</name>
<value><boolean>' . intval($board_info['can_post_new']) . '</boolean></value>
</member>
<member>
<name>topics</name>
<value><array><data>';

    // Show the actual topics
    foreach ($topics as $topic)
        $response .= topicRPCXML($topic);

    // Finish up the output..
    $response .= '
</data></array></value>
</member>
</struct>
</value>
</param>
</params>';

    // Send the response
    outputRPCResponse($response);
}

// Outputs the new topics
function outputRPCNewTopics($topics)
{
    $response = '
<params>
<param>
<value>
<array><data>';

    foreach ($topics as $topic)
        $response .= topicRPCXML($topic);

    $response .= '
</data></array>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

// Generates the RPC XML for a topic
function topicRPCXML($topic)
{
    global $user_info;

    $response = '
<value><struct>
<member>
<name>forum_id</name>
<value><string>' . (isset($topic['board']['id']) ? $topic['board']['id'] : $topic['board']) . '</string></value>
</member>';

    // For new_topic
    if (isset($topic['board_name']) || isset($topic['board']['name']))
        $response .= '
<member>
<name>forum_name</name>
<value><base64>' . base64_encode(mobi_unescape_html(isset($topic['board_name']) ? $topic['board_name'] : $topic['board']['name'])) . '</base64></value>
</member>';

    $response .= '
<member>
<name>topic_id</name>
<value><string>' . $topic['id'] . '</string></value>
</member>
<member>
<name>topic_title</name>
<value><base64>' . base64_encode(mobi_unescape_html(isset($topic['title']) ? $topic['title'] : $topic['subject'])) . '</base64></value>
</member>';

    if (isset($topic['post_id']))
        $response .= '
<member>
<name>post_id</name>
<value><string>' . $topic['post_id'] . '</string></value>
</member>
<member>
<name>post_title</name>
<value><base64>' . base64_encode(mobi_unescape_html($topic['post_title'])) . '</base64></value>
</member>';

    $response .= '
<member>
<name>post_author_id</name>
<value><string>' . (isset($topic['poster']['id']) ? $topic['poster']['id'] : $topic['last_post']['member']['id']) . '</string></value>
</member>
<member>
<name>' . (isset($topic['last_poster_name']) ? 'last_reply_author_name' : (isset($topic['board_name']) || isset($topic['board']['name']) ? 'post_' : 'topic_') . 'author_name') . '</name>
<value><base64>' . base64_encode(processUsername(isset($topic['last_poster_name']) ? $topic['last_poster_name'] : (isset($topic['poster']['username']) ? $topic['poster']['username'] : $topic['last_post']['member']['name']))) . '</base64></value>
</member>';
    if (isset($topic['last_poster_username']))
        $response .= '
<member>
<name>last_reply_author_display_name</name>
<value><base64>' . base64_encode(processUsername($topic['last_poster_username'])) . '</base64></value>
</member>';

    if (isset($topic['poster']['name']))
        $response .= '
<member>
<name>topic_author_display_name</name>
<value><base64>' . base64_encode(processUsername($topic['poster']['name'])) . '</base64></value>
</member>';

    if (isset($topic['poster']['post_name']))
        $response .= '
<member>
<name>post_author_display_name</name>
<value><base64>' . base64_encode(processUsername($topic['poster']['post_name'])) . '</base64></value>
</member>';

    $response .= '
<member>
<name>is_subscribed</name>
<value><boolean>' . (empty($topic['is_marked_notify']) ? 0 : 1) . '</boolean></value>
</member>
<member>
<name>can_subscribe</name>
<value><boolean>' . (allowedTo('mark_any_notify') && !$user_info['is_guest']) . '</boolean></value>
</member>
<member>
<name>is_closed</name>
<value><boolean>' . (empty($topic['is_locked']) ? 0 : 1) . '</boolean></value>
</member>
<member>
<name>is_sticky</name>
<value><boolean>' . (empty($topic['is_sticky']) ? 0 : 1) . '</boolean></value>
</member>
<member>
<name>icon_url</name>
<value><string>' . process_url(isset($topic['poster']['avatar']) ? $topic['poster']['avatar'] : $topic['last_post']['member']['avatar']) . '</string></value>
</member>';

    if (isset($topic['last_msg_time']))
        $response .= '
<member>
<name>last_reply_time</name>
<value><dateTime.iso8601>' . $topic['last_msg_time'] . '</dateTime.iso8601></value>
</member>';

    if (isset($topic['post_time']))
        $response .= '
<member>
<name>post_time</name>
<value><dateTime.iso8601>' . $topic['post_time'] . '</dateTime.iso8601></value>
</member>';

    if (isset($topic['last_post']['timestamp']))
        $response .= '
<member>
<name>post_time</name>
<value><dateTime.iso8601>' . mobiquo_time($topic['last_post']['timestamp']) . '</dateTime.iso8601></value>
</member>';

    $response .= '
<member>
<name>reply_number</name>
<value><int>' . intval($topic['replies']) . '</int></value>
</member>
<member>
<name>new_post</name>
<value><boolean>' . (empty($topic['is_new']) && empty($topic['new_from']) ? 0 : 1) . '</boolean></value>
</member>';

    // This does not exist in new_topic
    if (isset($topic['views']))
        $response .= '
<member>
<name>view_number</name>
<value><int>' . intval($topic['views']) . '</int></value>
</member>';

    $response .= '
<member>
<name>short_content</name>
<value><base64>' . base64_encode(isset($topic['short_msg']) ? $topic['short_msg'] : processShortContent($topic['last_post']['preview'])) . '</base64></value>
</member>
</struct></value>';

    return $response;
}

// Outputs the authorize result
function outputRPCAuthorizeResult($result)
{
    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>authorize_result</name>
<value><boolean>' . (empty($result) ? 0 : 1) . '</boolean></value>
</member>
</struct>
</value>
</param>
</params>');
}

// Outputs the posts template
function outputRPCPosts()
{
    global $context;

    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>total_post_num</name>
<value><int>' . intval($context['numReplies']+1) . '</int></value>
</member>
<member>
<name>forum_id</name>
<value><string>' . $context['board_id'] . '</string></value>
</member>
<member>
<name>forum_name</name>
<value><base64>' . base64_encode(mobi_unescape_html($context['board_name'])) . '</base64></value>
</member>
<member>
<name>topic_id</name>
<value><string>' . $context['topic_id'] . '</string></value>
</member>
<member>
<name>topic_title</name>
<value><base64>' . base64_encode(mobi_unescape_html($context['subject'])) . '</base64></value>
</member>
<member>
<name>is_subscribed</name>
<value><boolean>' . (empty($context['is_marked_notify']) ? 0 : 1) . '</boolean></value>
</member>
<member>
<name>can_subscribe</name>
<value><boolean>' . (empty($context['can_mark_notify']) ? 0 : 1) . '</boolean></value>
</member>
<member>
<name>is_closed</name>
<value><boolean>' . (empty($context['locked']) ? 0 : 1) . '</boolean></value>
</member>
<member>
<name>can_reply</name>
<value><boolean>' . (empty($context['can_reply']) ? 0 : 1) . '</boolean></value>
</member>
<member>
<name>position</name>
<value><int>' . intval($context['new_position']) . '</int></value>
</member>
<member>
<name>posts</name>
<value>
<array><data>';
    // Get the posts
    foreach ($context['posts'] as $post)
    {
        $response .= '
<value><struct>
<member>
<name>topic_id</name>
<value><string>' . $post['topic'] . '</string></value>
</member>
<member>
<name>post_id</name>
<value><string>' . $post['id'] . '</string></value>
</member>
<member>
<name>post_title</name>
<value><base64>' . base64_encode(mobi_unescape_html($post['subject'])) . '</base64></value>
</member>
<member>
<name>post_content</name>
<value><base64>' . base64_encode(mobi_unescape_body_html($post['body'])) . '</base64></value>
</member>
<member>
<name>post_author_name</name>
<value><base64>' . base64_encode(processUsername($post['poster']['username'])) . '</base64></value>
</member>
<member>
<name>post_author_display_name</name>
<value><base64>' . base64_encode(processUsername($post['poster']['name'])) . '</base64></value>
</member>
<member>
<name>can_edit</name>
<value><boolean>' . (!empty($post['can_edit']) ? 1 : 0) . '</boolean></value>
</member>
<member>
<name>allow_smilies</name>
<value><boolean>' . ($post['allow_smilies'] ? 1 : 0) . '</boolean></value>
</member>
<member>
<name>is_online</name>
<value><boolean>' . (!empty($post['poster']['is_online']) ? 1 : 0) . '</boolean></value>
</member>
<member>
<name>icon_url</name>
<value><string>' . process_url($post['poster']['avatar']) . '</string></value>
</member>
<member>
<name>post_time</name>
<value><dateTime.iso8601>' . $post['time'] . '</dateTime.iso8601></value>
</member>
<member>
<name>attachments</name>
<value>
<array><data>';
        foreach ($post['attachments'] as $attachment)
            $response .= '
<value><struct>
<member>
<name>content_type</name>
<value><string>' . ($attachment['is_image'] ? 'image' : 'others') . '</string></value>
</member>
<member>
<name>url</name>
<value><string>' . process_url($attachment['href']) . '</string></value>
</member>
<member>
<name>thumbnail_url</name>
<value><string>' . process_url($attachment['thumbnail']) . '</string></value>
</member>
</struct></value>';
        $response .= '
</data></array>
</value>
</member>
</struct></value>';
    }
    $response .= '
</data></array>
</value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

// Return's the user's information
function outputRPCUserInfo($user_data)
{
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>post_count</name>
<value><int>' . intval($user_data['posts']) . '</int></value>
</member>
<member>
<name>reg_time</name>
<value><dateTime.iso8601>' . mobiquo_time($user_data['registered_timestamp']) . '</dateTime.iso8601></value>
</member>
<member>
<name>display_name</name>
<value><base64>' . base64_encode(processUsername($user_data['name'])) . '</base64></value>
</member>
<member>
<name>last_activity_time</name>
<value><dateTime.iso8601>' . mobiquo_time($user_data['last_login_timestamp']) . '</dateTime.iso8601></value>
</member>
<member>
<name>is_online</name>
<value><boolean>' . (!empty($user_data['online']['is_online']) ? 1 : 0) . '</boolean></value>
</member>
<member>
<name>accept_pm</name>
<value><boolean>' . (allowedTo('pm_send') ? 1 : 0) . '</boolean></value>
</member>
<member>
<name>icon_url</name>
<value><string>' . process_url($user_data['avatar']['href']) . '</string></value>
</member>
<member>
<name>custom_fields_list</name>
<value>
<array>
<data>';
    foreach($user_data['custom_fields_list'] as $key => $value)
        $response .= '
<value>
<struct>
<member>
<name>name</name>
<value><base64>' . base64_encode(mobi_unescape_html($key)) . '</base64></value>
</member>
<member>
<name>value</name>
<value><base64>' . base64_encode(mobi_unescape_html($value)) . '</base64></value>
</member>
</struct>
</value>';
    $response .= '
</data>
</array>
</value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

// Outputs the boxes information
function outputRPCBoxInfo($boxes, $total_boxes)
{
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>message_room_count</name>
<value><int>' . intval($total_boxes) . '</int></value>
</member>
<member>
<name>list</name>
<value>
<array><data>';
    foreach ($boxes as $box)
        $response .= '
<value><struct>
<member>
<name>box_id</name>
<value><string>' . $box['id'] . '</string></value>
</member>
<member>
<name>box_name</name>
<value><base64>' . base64_encode($box['name']) . '</base64></value>
</member>
<member>
<name>msg_count</name>
<value><int>' . intval($box['msg_count']) . '</int></value>
</member>
<member>
<name>unread_count</name>
<value><int>' . intval($box['unread_count']) . '</int></value>
</member>
<member>
<name>box_type</name>
<value><string>' . $box['box_type'] . '</string></value>
</member>
</struct></value>';
    $response .= '
</data></array>
</value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

// Outputs one particular box
function outputRPCBox($pms, $total, $unread)
{
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>total_message_count</name>
<value><int>' . intval($total) . '</int></value>
</member>
<member>
<name>total_unread_count</name>
<value><int>' . intval($unread) . '</int></value>
</member>
<member>
<name>list</name>
<value>
<array><data>';
    foreach ($pms as $pm)
    {
        $response .= '
<value><struct>
<member>
<name>msg_id</name>
<value><string>' . $pm['id'] . '</string></value>
</member>
<member>
<name>msg_state</name>
<value><int>' . ($pm['is_unread'] ? 1 : ($pm['is_replied'] ? 3 : 2)) . '</int></value>
</member>
<member>
<name>sent_date</name>
<value><dateTime.iso8601>' . $pm['time'] . '</dateTime.iso8601></value>
</member>
<member>
<name>msg_from</name>
<value><base64>' . base64_encode(processUsername($pm['from_username'])) . '</base64></value>
</member>
<member>
<name>msg_from_display_name</name>
<value><base64>' . base64_encode(processUsername($pm['from_name'])) . '</base64></value>
</member>
<member>
<name>icon_url</name>
<value><string>'. process_url($pm['icon_url']) .'</string></value>
</member>
<member>
<name>is_online</name>
<value><boolean>' . ($pm['is_online'] ? '1' : '0') . '</boolean></value>
</member>
<member>
<name>msg_to</name>
<value>
<array><data>';
        foreach ($pm['recipients'] as $rec)
            $response .= '
<value><struct>
<member>
<name>username</name>
<value><base64>' . base64_encode(processUsername($rec['username'])) . '</base64></value>
</member>
<member>
<name>display_name</name>
<value><base64>' . base64_encode(processUsername($rec['name'])) . '</base64></value>
</member>
</struct></value>';
        $response .= '
</data></array>
</value>
</member>
<member>
<name>msg_subject</name>
<value><base64>' . base64_encode($pm['subject']) . '</base64></value>
</member>
<member>
<name>short_content</name>
<value><base64>' . base64_encode($pm['body']) . '</base64></value>
</member>
</struct></value>';
    }
    $response .= '
</data></array>
</value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

// Outputs a single PM
function outputRPCPM($pm)
{
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>msg_from</name>
<value><base64>' . base64_encode(processUsername($pm['from_username'])) . '</base64></value>
</member>
<member>
<name>msg_from_display_name</name>
<value><base64>' . base64_encode(processUsername($pm['from_name'])) . '</base64></value>
</member>
<member>
<name>msg_to</name>
<value>
<array><data>';
    foreach ($pm['recipients'] as $rec)
        $response .= '
<value><struct>
<member>
<name>username</name>
<value><base64>' . base64_encode(processUsername($rec['username'])) . '</base64></value>
</member>
<member>
<name>display_name</name>
<value><base64>' . base64_encode(processUsername($rec['name'])) . '</base64></value>
</member>
</struct></value>';
    $response .= '
</data></array>
</value>
</member>
<member>
<name>sent_date</name>
<value><dateTime.iso8601>' . $pm['time'] . '</dateTime.iso8601></value>
</member>
<member>
<name>msg_subject</name>
<value><base64>' . base64_encode($pm['subject']) . '</base64></value>
</member>
<member>
<name>text_body</name>
<value><base64>' . base64_encode($pm['body']) . '</base64></value>
</member>
<member>
<name>icon_url</name>
<value><string>' . process_url($pm['icon_url']) . '</string></value>
</member>
<member>
<name>is_online</name>
<value><boolean>' . ($pm['is_online'] ? '1' : '0') . '</boolean></value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

// Outputs the reult(a bool)...
function outputRPCResult($result, $result_text = '')
{
    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>result</name>
<value><boolean>' . ($result ? '1' : '0') . '</boolean></value>
</member>
<member>
<name>result_text</name>
<value><base64>' . base64_encode(strip_tags($result_text)) . '</base64></value>
</member>
</struct>
</value>
</param>
</params>'
    );
}

function outputRPCLogin($result = false, $result_text = '')
{
    global $user_info, $register;
    
    $can_moderate = (allowedTo('make_sticky') || allowedTo('remove_any') || allowedTo('lock_any')) && $_SERVER['HTTP_MOBIQUO_ID'] == 4;
    $pm_read = allowedTo('pm_read');
    $pm_send = allowedTo('pm_send');
    $can_whosonline = allowedTo('who_view');
    $can_search = allowedTo('search_posts');
    
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>result</name>
<value><boolean>' . ($result ? '1' : '0') . '</boolean></value>
</member>
<member>
<name>result_text</name>
<value><base64>' . base64_encode($result_text) . '</base64></value>
</member>' . ($register ? '
<member>
<name>register</name>
<value><boolean>1</boolean></value>
</member>' : '') . '
<member>
<name>user_id</name>
<value><string>' . $user_info['ID_MEMBER'] . '</string></value>
</member>
<member>
<name>username</name>
<value><base64>' . base64_encode(mobiquo_encode($user_info['realName'])) . '</base64></value>
</member>
<member>
<name>login_name</name>
<value><base64>' . base64_encode(mobiquo_encode($user_info['memberName'])) . '</base64></value>
</member>
<member>
<name>email</name>
<value><base64>' . base64_encode(mobiquo_encode($user_info['emailAddress'])) . '</base64></value>
</member>
<member>
<name>post_count</name>
<value><int>' . intval($user_info['posts']) . '</int></value>
</member>
<member>
<name>icon_url</name>
<value><string>' . get_avatar($user_info) . '</string></value>
</member>' . ($can_moderate ? '
<member>
<name>can_moderate</name>
<value><boolean>1</boolean></value>
</member>' : '') . '
<member>
<name>can_pm</name>
<value><boolean>' . ($pm_read ? '1' : '0') . '</boolean></value>
</member>
<member>
<name>can_send_pm</name>
<value><boolean>' . ($pm_send ? '1' : '0') . '</boolean></value>
</member>
<member>
<name>can_whosonline</name>
<value><boolean>' . ($can_whosonline ? '1' : '0') . '</boolean></value>
</member>
<member>
<name>can_search</name>
<value><boolean>' . ($can_search ? '1' : '0') . '</boolean></value>
</member>
<member>
<name>usergroup_id</name>
<value>
<array>
<data>';
    foreach($user_info['groups'] as $group_id)
        $response .= '
<value><string>' . intval($group_id) . '</string></value>';
    $response .= '
</data>
</array>
</value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

// Outputs a new topic notification
function outputRPCNewTopic($id_topic, $state, $is_post)
{
    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>result</name>
<value><boolean>1</boolean></value>
</member>
<member>
<name>' . ($is_post ? 'post_' : 'topic_') . 'id</name>
<value><string>' . $id_topic . '</string></value>
</member>
<member>
<name>state</name>
<value><int>' . ($state == 2 ? '1' : '0') . '</int></value>
</member>
</struct>
</value>
</param>
</params>'
    );
}

// Outputs the subscribed topics
function outputRPCSubscribedTopics($topics, $count, $search_id = '')
{
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>total_topic_num</name>
<value><int>' . intval($count) . '</int></value>
</member>';

    if ($search_id)
        $response .= '
<member>
<name>search_id</name>
<value><string>' . $search_id . '</string></value>
</member>';

    $response .= '
<member>
<name>topics</name>
<value>
<array><data>';
    foreach ($topics as $topic)
        $response .= topicRPCXML($topic);
    $response .= '
</data></array>
</value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

// Outputs the current online users
function outputRPCOnline($online)
{
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>member_count</name>
<value><int>' . count($online['users_online']) . '</int></value>
</member>
<member>
<name>guest_count</name>
<value><int>' . intval($online['num_guests']) . '</int></value>
</member>
<member>
<name>list</name>
<value>
<array><data>';
    foreach ($online['users_online'] as $user)
        $response .= '
<value><struct>
<member>
<name>user_name</name>
<value><base64>' . base64_encode(processUsername($user['username'])) . '</base64></value>
</member>
<member>
<name>display_name</name>
<value><base64>' . base64_encode(processUsername($user['name'])) . '</base64></value>
</member>
<member>
<name>icon_url</name>
<value><string>' . process_url($user['avatar']) . '</string></value>
</member>
<member>
<name>display_text</name>
<value><base64>' . base64_encode(mobi_unescape_html($user['action'])) . '</base64></value>
</member>
</struct></value>';
    $response .= '
</data></array>
</value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

function outputRPCSubscribedBoards($boards)
{
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>total_forums_num</name>
<value><int>' . count($boards) . '</int></value>
</member>
<member>
<name>forums</name>
<value>
<array><data>';
    foreach ($boards as $board)
        $response .= '
<value><struct>
<member>
<name>forum_id</name>
<value><string>' . $board['id'] . '</string></value>
</member>
<member>
<name>forum_name</name>
<value><base64>' . base64_encode(mobi_unescape_html($board['name'])) . '</base64></value>
</member>
<member>
<name>icon_url</name>
<value><string>' . process_url($board['icon']) . '</string></value>
</member>
<member>
<name>is_protected</name>
<value><boolean>0</boolean></value>
</member>
<member>
<name>sub_only</name>
<value><boolean>0</boolean></value>
</member>
<member>
<name>new_post</name>
<value><boolean>' . (empty($board['new']) ? '0' : '1') . '</boolean></value>
</member>
</struct></value>';
    $response .= '
</data></array>
</value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}
