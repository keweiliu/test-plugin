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

$server_param = array(

    'login' => array(
        'function' => 'login_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean, $xmlrpcString)),
    ),
    
    'get_forum' => array(
        'function'  => 'get_forum_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcStruct,$xmlrpcBoolean),
                             array($xmlrpcStruct,$xmlrpcBoolean, $xmlrpcString)),
        'docstring' => 'no need parameters for get_forum.',
    ),

    'get_topic' => array(
        'function'  => 'get_topic_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string,int,int,string)',
    ),

    'get_thread' => array(
        'function'  => 'get_thread_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcBoolean)),
        'docstring' => 'parameter should be array(string,int,int)',
    ),
    
    'get_thread_by_post' => array(
        'function'  => 'get_thread_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean)),
        'docstring' => 'parameter should be array(string,int,int)',
    ),
    
    'get_thread_by_unread' => array(
        'function'  => 'get_thread_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean)),
        'docstring' => 'parameter should be array(string,int,int)',
    ),

    'get_raw_post' => array(
        'function'  => 'get_raw_post_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string)',
    ),

    'save_raw_post' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean, $xmlrpcArray, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBoolean, $xmlrpcArray, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => 'parameter should be array(string, base64, base64)',
    ),
    
    'search_topic' => array(
        'function'  => 'advanced_search_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString)),
        'docstring' => '',
    ),
    
    'search_post' => array(
        'function'  => 'advanced_search_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString)),
        'docstring' => '',
    ),
    
    'search' => array(
        'function' => 'advanced_search_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcStruct)),
    ),
    
    'get_unread_topic' => array(
        'function'  => 'advanced_search_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt, $xmlrpcString, $xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray)),
    ),

    'get_participated_topic' => array(
        'function'  => 'advanced_search_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt ,$xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt ,$xmlrpcString, $xmlrpcString)),
    ),
    
    'get_latest_topic' => array(
        'function'  => 'advanced_search_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt, $xmlrpcString, $xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray)),
    ),

    'get_user_topic' => array(
        'function'  => 'advanced_search_func',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
    ),

    'get_user_reply_post' => array(
        'function'  => 'advanced_search_func',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
    ),

    'get_subscribed_topic' => array(
        'function'  => 'advanced_search_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt)),
    ),

    'get_user_info' => array(
        'function'  => 'get_user_info_func',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => 'parameter should be array(sring)',
    ),

    'get_config' => array(
        'function'  => 'get_config_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for get_forum',
    ),

    'logout_user' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for logout',
    ),
    
    'new_topic' => array(
        'function'  => 'new_topic_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcString, $xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(string,byte,byte,[string],[array])',
    ),
    
    'reply_post' => array(
        'function'  => 'reply_post_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray, $xmlrpcString)),
        'docstring' => 'parameter should be array(int,string,string)',
    ),

    'subscribe_topic' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
        'docstring' => 'subscribe_topic need one parameters as topic id.',
    ),

    'unsubscribe_topic' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'unsubscribe_topic need one parameters as topic id.',
    ),

    'get_inbox_stat' => array(
        'function'  => 'get_inbox_stat_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no parameter but need login first',
    ),

    'get_board_stat' => array(
        'function'  => 'get_board_stat_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'no parameter',
    ),
    
    'mark_all_as_read' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'no parameter',
    ),

    'get_online_users' => array(
        'function'  => 'get_online_users_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'no parameter',
    ),
    
    'get_quote_post' => array(
        'function'  => 'get_quote_post_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array()',
    ),
    
    'get_quote_pm' => array(
        'function'  => 'get_quote_pm_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'parameter should be array(string)',
    ),
    
    'report_post' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => '',
    ),
    
    'login_forum' => array(
        'function'  => 'login_forum_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => 'parameter should be)',
    ),
    
    'subscribe_forum' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
        'docstring' => 'subscribe_topic need one parameters as forum id.',
    ),
    
    'unsubscribe_forum' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'unsubscribe_topic need one parameters as forum id.',
    ),
    
    'get_subscribed_forum' => array(
        'function'  => 'get_subscribed_forum_func',
        'signature' => array(array($xmlrpcArray)),
        'docstring' => 'no need parameters for get_subscribed_forum',
    ),
    
    'upload_attach' => array(
        'function'  => 'upload_attach_func',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'parameter should be',
    ),
    
    'upload_avatar' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'parameter should be',
    ),
    
    'remove_attachment' => array(
        'function'  => 'remove_attachment_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString)),
        'docstring' => 'parameter should be',
    ),
    
    'get_id_by_url' => array(
        'function'  => 'get_id_by_url_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'get_id_by_url need one parameters as url.',
    ),
    
    'like_post' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'string post_id to like',
    ),
    
    'unlike_post' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
        'docstring' => 'string post_id to unlike',
    ),
    
    'ignore_user' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
    ),
    
    'search_user' => array(
        'function'  => 'search_user',
        'signature' => array(array($xmlrpcArray, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt)),
    ),
    
    'get_contact' => array(
        'function'  => 'get_contact_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
    ),
    
    'user_sync' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray)),
    ),
    'update_signature' => array(
        'function'  => 'update_signature',
        'signature' => array(array($xmlrpcArray, $xmlrpcBase64)),
    ),
    
    
    // conversation
    //**********************************************
    // Conversation functions
    //**********************************************
    
    'get_conversations' => array(
        'function'  => 'get_conversations_func',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt)),
    ),

    'get_conversation' => array(
        'function'  => 'get_conversation_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcBoolean)),
    ),
    
    'invite_participant' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcArray, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcArray, $xmlrpcString, $xmlrpcBase64)),
    ),

    'new_conversation' => array(
        'function'  => 'new_conversation_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcArray, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcArray, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray, $xmlrpcString)),
    ),

    'reply_conversation' => array(
        'function'  => 'reply_conversation_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcArray, $xmlrpcString)),
    ),

    'get_quote_conversation' => array(
        'function'  => 'get_quote_conversation_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcString, $xmlrpcString)),
    ),

    'delete_conversation' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString),
                             array($xmlrpcArray, $xmlrpcString, $xmlrpcInt)),
    ),
    
    'mark_conversation_read' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcString)),
    ),
    
    'mark_conversation_unread' => array(
        'function'  => 'xmlresptrue',
        'signature' => array(array($xmlrpcArray, $xmlrpcString)),
    ),
    
    'get_recommended_user' => array(
        'function' => 'get_recommended_user_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt, $xmlrpcInt)),
    ),
    
    //**********************************************
    // Moderation functions
    //**********************************************
    
    'm_stick_topic' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
    ),
    
    'm_close_topic' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
    ),
    
    'm_delete_topic' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
    ),
    
    'm_undelete_topic' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
    ),
    
    'm_approve_topic' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
    ),
    
    'm_move_topic' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBoolean)),
    ),
    
    'm_rename_topic' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
    ),
    
    'm_merge_topic' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
    ),
    
    'm_merge_post' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
    ),
    
    'm_delete_post' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
    ),
    
    'm_undelete_post' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
    ),
    
    'm_approve_post' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
    ),
    
    'm_move_post' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
    ),
    
    'm_close_report' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
    ),
    
    'm_mark_as_spam' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
    ),
    
    'm_ban_user' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcBase64)),
    ),
    
    'm_get_delete_topic' => array(
        'function' => 'get_delete_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray)),
    ),
    
    'm_get_delete_post' => array(
        'function' => 'get_delete_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray)),
    ),
    
    'm_get_report_post' => array(
        'function' => 'get_report_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray)),
    ),
    
    'm_get_moderate_topic' => array(
        'function' => 'get_moderate_topic_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray)),
    ),
    
    'm_get_moderate_post' => array(
        'function' => 'get_moderate_post_func',
        'signature' => array(array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcArray)),
    ),
    
    
    //**********************************************
    // Puch related functions
    //**********************************************
    
    'update_push_status' => array(
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct, $xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64)),
    ),
    
    'get_alert' => array(
        'function' => 'get_alert_func',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt)),
    ),
    
    
    //**********************************************
    // Account related functions
    //**********************************************

    'sign_in' => array(
        'function'  => 'login_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
    ),
    
    'prefetch_account' => array(
        'function'  => 'prefetch_account_func',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64)),
    ),
    
    'register' => array (
        'function' => 'register_func',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcBase64,$xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcBase64,$xmlrpcBase64,$xmlrpcString,$xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcBase64,$xmlrpcBase64,$xmlrpcString,$xmlrpcString,$xmlrpcStruct)),
    ),
    
    'update_password' => array (
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcString ,$xmlrpcString)),
    ),
    
    'update_email' => array (
        'function' => 'xmlresptrue',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcBase64)),
    ),
    
    'forget_password' => array (
        'function' => 'forget_password_func',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64 ),
                             array($xmlrpcStruct, $xmlrpcBase64,$xmlrpcString ,$xmlrpcString)),
    )
);
