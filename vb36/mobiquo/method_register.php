<?php
/*======================================================================*\
 || #################################################################### ||
 || # Copyright 2014 Tapatalk Inc. All Rights Reserved.                # ||
 || # This file may not be redistributed in whole or significant part. # ||
 || # This file is part of the Tapatalk package and should not be used # ||
 || # and distributed for any other purpose that is not approved by    # ||
 || # Tapatalk Inc.                                                    # ||
 || # http://tapatalk.com | http://tapatalk.com/forum_owner_license.php# ||
 || #################################################################### ||
 \*======================================================================*/
defined('CWD1') or exit;
$methodContainer = array(
    "authorize_user" => array(
        "function" => "authorize_user_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
        "docstring" => 'authorize need two parameters,the first is user name,second is password. Both are Base64',
    ),
    "login" => array(
        "function" => "login_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64)),
        "docstring" => 'authorize need two parameters,the first is user name,second is password. Both are Base64',
    ),
    "login_mod" => array(
        "function" => "login_mod_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64)),
        "docstring" => 'authorize need two parameters,the first is user name,second is password. Both are Base64',
    ),
    "logout_user" => array(
        "function" => "logout_func",
        "signature" => array( array($xmlrpcArray)),
        "docstring" => 'no need parameters for logout',
    ),
    "get_forum" => array(
        "function" => "get_forum_func",
        "signature" => array( array($xmlrpcArray)),
        "docstring" => 'no need parameters for get_forum',
    ),
    "get_forum_all" => array(
        "function" => "get_forum_all_func",
        "signature" => array( array($xmlrpcArray)),
        "docstring" => 'no need parameters for get_forum',
    ),
    "get_topic" => array(
        "function" => "get_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcString, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(int,int,int,string)',
    ),
    "get_thread" => array(
        "function" => "get_thread_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(int,int,int,string)',
    ),
    "get_thread_by_post" => array(
        "function" => "get_thread_by_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(int,int,int,string)',
    ),
    "get_thread_by_unread" => array(
        "function" => "get_thread_by_unread_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(int,int,int,string)',
    ),
    "get_user_topic" => array(
        "function" => "get_user_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64)),
        "docstring" => 'parameter should be array(int,int,int,string)',
    ),
    "get_user_reply_post" => array(
        "function" => "get_user_reply_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64)),
        "docstring" => 'parameter should be array(int,int,int,string)',
    ),
    "get_user_info" => array(
        "function" => "get_user_info_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64)),
        "docstring" =>'parameter should be array(sring)',
    ),
    "get_friend_list" => array(
        "function" => "get_friend_list_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64)),
        "docstring" =>'parameter should be array(sring)',
    ),
    "add_friend" => array(
        "function" => "add_friend_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64)),
        "docstring" =>'parameter should be array(sring)',
    ),
    "remove_friend" => array(
        "function" => "remove_friend_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64)),
        "docstring" =>'parameter should be array(sring)',
    ),
    "get_new_topic" => array(
        "function" => "get_new_topic_func",
        "signature" => array(array($xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt)),
        "docstring" => 'no need parameters for get_forum',
    ),
    "get_latest_topic" => array(
        "function" => "get_latest_topic_func",
        "signature" => array(array($xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt)),
        "docstring" => 'no need parameters for get_latest_topic',
    ),
    "get_config" => array(
        "function" => "get_config_func",
        "signature" => array( array($xmlrpcArray)),
        "docstring" => 'no need parameters for get_forum',
    ),
    "return_fault" => array(
        "function" =>  "return_fault_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcInt, $xmlrpcString)),
        'docstring' => "return_fault function ; params faultcode,faultstring ; return @fault",
    ),
    "create_topic" => array(
        "function" => "create_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcString)),
        "docstring" => 'parameter should be array(int,string,string)',
    ),
    "reply_topic" => array(
        "function" => "reply_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString),),
        "docstring" => 'parameter should be array(int,string,string)',
    ),
    "reply_post" => array(
        "function" => "reply_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray, $xmlrpcString, $xmlrpcBoolean)),
        "docstring" => 'parameter should be array(int,string,string)',
    ),
    "new_topic" => array(
        "function" => "new_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcArray, $xmlrpcString)),
        "docstring" => 'parameter should be array(int,string,string)',
    ),
    "get_subscribed_topic" => array(
        "function" => "get_subscribed_topic_func",
        "signature" => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt)),
        "docstring" => 'no parameter',
    ),
    "get_subscribed_forum" => array(
        "function" => "get_subscribed_forum_func",
        "signature" => array(array($xmlrpcStruct)),
        "docstring" => 'no parameter',
    ),
    "subscribe_topic" => array(
        "function" => "subscribe_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(int)',
    ),
    "subscribe_forum" => array(
        "function" => "subscribe_forum_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(int)',
    ),
    "unsubscribe_forum" => array(
        "function" => "unsubscribe_forum_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(int)',
    ),
    "unsubscribe_topic" => array(
        "function" => "unsubscribe_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(int)',
    ),
    "create_message" => array(
        "function" => "create_message_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcInt, $xmlrpcString)),
        "docstring" => 'parameter should be array(string,string,string)',
    ),
    "get_inbox_stat" => array(
        "function" => "get_inbox_stat_func",
        "signature" => array(array($xmlrpcStruct)),
        "docstring" => 'parameter should be array(string)',
    ),
    "get_box_info" => array(
        "function" => "get_box_info_func",
        "signature" => array(array($xmlrpcStruct)),
        "docstring" => 'parameter should be array(string)',
    ),
    "get_box" => array(
        "function" => "get_box_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(string,int)',
    ),
    "get_message" => array(
        "function" => "get_message_func",
        "signature" => array( array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBoolean)),
        "docstring" => 'parameter should be array(string,int)',
    ),
    "delete_message" => array(
        "function" => "delete_message_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString) ,
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
        "docstring" => 'parameter should be array(string,int)',
    ),
    "get_board_stat" => array(
        "function" => "get_board_stat_func",
        "signature" => array(array($xmlrpcStruct)),
        "docstring" => 'no parameter',
    ),
    "get_online_users" => array(
        "function" => "get_online_users_func",
        "signature" => array(array($xmlrpcStruct)),
        "docstring" => 'no parameter',
    ),
    'push_notify' => array(
        "function" => "push_notify_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString, $xmlrpcString)),
        "docstring" => 'parameter should be array(string,string)',
    ),
    'save_raw_post' => array(
        "function" => "save_raw_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean)),
        "docstring" => 'parameter should be array(string,base64,base64)',
    ),
    'get_raw_post' => array(
        "function" => "get_raw_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameter should be array(string)',
    ),
    'attach_image' => array(
        "function" => "attach_image_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcString, $xmlrpcString)),
        "docstring" => 'parameter should be array(string,base64,base64,string,string)',
    ),
    'remove_attach' => array(
        "function" => "remove_attach_func",
        "signature" => array( array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcString)),
        "docstring" => 'parameter should be array(string)',
    ),
    'remove_attachment' => array(
        "function" => "remove_attach_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString)),
        "docstring" => 'parameter should be array(string)',
    ),
    'search_topic' => array(
        "function" => "search_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt)),
        "docstring" => 'parameter should be array(string,base64,base64,string,string)',
    ),
    'search_post' => array(
        "function" => "search_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                            array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt)),
        "docstring" => 'parameter should be array(string,base64,base64,string,string)',
    ),
    "mark_all_as_read" => array(
        "function" => "mark_all_as_read_func",
        "signature" => array(array($xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'no need parameters for mark_all_as_read',
    ),
    "get_unread_topic" => array(
        "function" => "get_unread_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray)),
        "docstring" => 'no need parameters for get_unread_topic',
    ),
    "get_quote_post" => array(
        "function" => "get_quote_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'no need parameters for get_unread_topic',
    ),
    "mark_pm_unread" => array(
        "function" => "mark_pm_unread_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameters(string) for mark_pm_unread',
    ),
    "get_quote_pm" => array(
        "function" => "get_quote_pm_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameters(string) for mark_pm_unread',
    ),
    "get_participated_topic" => array(
        "function" => "get_participated_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcBase64)),
        "docstring" => 'parameters(string) for mark_pm_unread',
    ),
    "report_post" => array(
        "function" => "report_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
        "docstring" => 'parameters(string) for mark_pm_unread',
    ),
    "report_pm" => array(
        "function" => "report_pm_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
        "docstring" => 'parameters(string) for mark_pm_unread',
    ),
    "login_forum" => array(
        "function" => "login_forum_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
        "docstring" => 'parameters(string) for move_topic',
    ),
    "m_get_moderate_topic" => array(
        "function" => "get_moderate_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcInt),
                             array($xmlrpcArray)),
        "docstring" => 'parameters(string) for get_moderate_topic',
    ),
    "m_get_moderate_post" => array(
        "function" => "get_moderate_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcInt),
                             array($xmlrpcArray)),
        "docstring" => 'parameters(string) for get_moderate_post',
    ),
    "m_get_delete_topic" => array(
        "function" => "get_delete_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcInt),
                             array($xmlrpcArray)),
        "docstring" => 'parameters(string) for get_moderate_topic',
    ),
    "m_get_delete_post" => array(
        "function" => "get_delete_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcInt),
                             array($xmlrpcArray)),
        "docstring" => 'parameters(string) for get_moderate_post',
    ),
    "m_delete_topic" => array(
        "function" => "delete_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
        "docstring" => 'parameters(string) for delete_topic',
    ),
    "m_undelete_topic" => array(
        "function" => "undelete_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
        "docstring" => 'parameters(string) for undelete_topic',
    ),
    "m_delete_post" => array(
        "function" => "delete_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
        "docstring" => 'parameters(string) for delete_post',
    ),
    "m_undelete_post" => array(
        "function" => "undelete_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
        "docstring" => 'parameters(string) for delete_post',
    ),
//  "m_ban_user" => array(
//      "function" => "ban_user_func",
//      "signature" => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt),
//                         array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcBase64),
//                          ),
//      "docstring" => 'parameters(string) for delete_post',
//  ),
    "m_stick_topic" => array(
        "function" => "stick_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
        "docstring" => 'parameters(string) for stick_topic',
    ),
    "m_open_close_topic" => array(
        "function" => "open_close_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
        "docstring" => 'parameters(string) for stick_topic',
    ),
    "m_close_topic" => array(
        "function" => "open_close_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
        "docstring" => 'parameters(string) for stick_topic',
    ),
    "m_approve_topic" => array(
        "function" => "moderate_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
        "docstring" => 'parameters(string) for stick_topic',
    ),
    "m_approve_post" => array(
        "function" => "moderate_post_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
        "docstring" => 'parameters(string) for stick_topic',
    ),
    "m_move_topic" => array(
        "function" => "move_topic_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
        "docstring" => 'parameters(string) for move_topic',
    ),
    "get_announcement" => array(
        "function" => "get_announcement_func",
        "signature" => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString)),
        "docstring" => 'parameters(string) for get_announcement',
    ),
);
