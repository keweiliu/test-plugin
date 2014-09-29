<?php
/**
 * method_defination, any method defined here would be used in the newer way
 *
 * @author Shitiz Garg
 * @copyright Copyright 2009 Quoord Systems Ltd. All Rights Reserved.
 * @license This file or any content of the file should not be
 *             redistributed in any form of matter. This file is a part of
 *             Tapatalk package and should not be used and distributed
 *             in any form not approved by Quoord Systems Ltd.
 *             http://tapatalk.com | http://taptatalk.com/license.html
 */

if (!defined('IN_MOBIQUO'))
	die('Hacking Attempt...');

$methods = array(
    'update_email' => array(
        'function' => 'mob_update_email',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64)),
    ),
    'update_password' => array(
        'function' => 'mob_update_password',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString, $xmlrpcString)),
    ),
    'forget_password' => array(
        'function' => 'mob_forget_password',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString, $xmlrpcString)),
    ),
    'prefetch_account' => array(
        'function' => 'mob_prefetch_account',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64)),
    ),
    'get_participated_forum' => array(
        'function' => 'mob_get_participated_forum',
        'signature' => array(array($xmlrpcStruct)),
        'docstring' => 'Returns the forums this user has participated in, no paramters required',
    ),
    'get_id_by_url' => array(
        'function' => 'mob_get_id_by_url',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString)),
        'docstring' => 'Returns forum ID, post ID or topic ID from an URL, first parameter should be string',
    ),
    'get_topic' => array(
        'function' => 'mob_get_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcString)),
        'docstring' => 'Returns the topics from messageindex',
    ),
    'get_latest_topic' => array(
        'function' => 'mob_get_latest_topic',
        'signature' => array(array($xmlrpcArray),
                             array($xmlrpcArray, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => 'Returns the latest topics',
    ),
    'get_unread_topic' => array(
        'function' => 'mob_get_unread_topic',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => 'Returns the unread topics',
    ),
    'get_participated_topic' => array(
        'function' => 'mob_get_participated_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString)),
        'docstring' => 'Returns the threads this user has participated in',
    ),
    'get_subscribed_topic' => array(
        'function' => 'mob_get_subscribed_topic',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcInt, $xmlrpcInt)),
        'docstring' => 'Returns the topics this user is subscribed too',
    ),
    'search_topic' => array(
        'function' => 'mob_search_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString)),
        'docstring' => 'Searches the topics',
    ),
    'search_post' => array(
        'function' => 'mob_search_post',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcInt, $xmlrpcString)),
        'docstring' => 'Searches the topics',
    ),
    'get_user_topic' => array(
        'function' => 'mob_get_user_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => 'Gets the topics this user has created',
    ),
    'get_user_reply_post' => array(
        'function' => 'mob_get_user_reply_post',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => 'Gets the topics this user has created',
    ),
    'get_user_info' => array(
        'function' => 'mob_get_user_info',
        'signature' => array(array($xmlrpcStruct),
                             array($xmlrpcStruct, $xmlrpcBase64),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => 'Profile function',
    ),
    'get_thread' => array(
        'function' => 'mob_get_thread',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcInt, $xmlrpcBoolean)),
        'docstring' => 'Returns the thread',
    ),
    'get_thread_by_post' => array(
        'function' => 'mob_get_thread_by_post',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean)),
        'docstring' => 'Returns the thread by starting from the post supplied',
    ),
    'get_thread_by_unread' => array(
        'function' => 'mob_get_thread_by_unread',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBoolean)),
        'docstring' => 'Returns the thread by starting from the first unread',
    ),
    'm_stick_topic' => array(
        'function' => 'mob_m_stick_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
        'docstring' => 'Stickies/Unstickies a topic',
    ),
    'm_close_topic' => array(
        'function' => 'mob_m_close_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt)),
        'docstring' => 'Locks/Unlocks a topic',
    ),
    'm_delete_topic' => array(
        'function' => 'mob_m_delete_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
        'docstring' => 'Removes a topic',
    ),
    'm_delete_post' => array(
        'function' => 'mob_m_delete_post',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcInt, $xmlrpcBase64)),
        'docstring' => 'Deletes a post',
    ),
    'm_move_topic' => array(
        'function' => 'mob_m_move_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
        'docstring' => 'Moves a topic',
    ),
    'm_rename_topic' => array(
        'function' => 'mob_m_rename_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcBase64)),
        'docstring' => 'Changes the subject of the first post of the topic',
    ),
    'm_move_post' => array(
        'function' => 'mob_m_move_post',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString),
                             array($xmlrpcStruct, $xmlrpcString, $xmlrpcString, $xmlrpcBase64, $xmlrpcString)),
        'docstring' => 'Splits a post or merges it',
    ),
    'm_merge_topic' => array(
        'function' => 'mob_m_merge_topic',
        'signature' => array(array($xmlrpcStruct, $xmlrpcString, $xmlrpcString)),
        'docstring' => 'Merges 2 topics',
    ),
    'm_ban_user' => array(
        'function' => 'mob_m_ban_user',
        'signature' => array(array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt),
                             array($xmlrpcStruct, $xmlrpcBase64, $xmlrpcInt, $xmlrpcBase64)),
        'docstring' => 'Bans a user',
    ),
);
