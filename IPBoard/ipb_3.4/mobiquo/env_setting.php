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

@register_shutdown_function('xmlrpc_shutdown');

if (isset($_SERVER['HTTP_APP_VAR'] ) && $_SERVER['HTTP_APP_VAR'])
    @header('App-Var: '.$_SERVER['HTTP_APP_VAR']);

mobi_parse_requrest();

if (!$request_name && isset($_POST['method_name'])) $request_name = $_POST['method_name'];
// display mobiquo interface for directly request
if(empty($request_name)) require 'web.php';

if (in_array($request_name, array('get_config'))) define('IPS_ENFORCE_ACCESS', true);

$result = true;
$tapatalk_handle = '';

// for search
$return_search_count = true;
$show_last_post = true;

switch ($request_name) {
    case 'search':
        $tapatalk_handle = 'search';
        $show_last_post = false;
        $search_filter = $request_params[0];
        
        // default settings
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'search';
        
        // request filters
        list($_GET['st'], $search_per_page) = process_perpage($search_filter['page'], $search_filter['perpage']);
        $_POST['search_term'] = isset($search_filter['keywords']) ? str_replace(' ', '+', $search_filter['keywords']) : '';
        
        if (isset($search_filter['searchid']) && $search_filter['searchid'])
        {
            $_GET['sid'] = $search_filter['searchid'];
        }
        else if (isset($search_filter['threadid']))
        {
            $_GET['fromMainBar'] = 1;
            $_POST['search_app'] = 'forums:topic:'.$search_filter['threadid'];
        }
        else
        {
            $_GET['section'] = 'search';
            $_GET['fromsearch'] = 1;
            $_POST['search_app'] = 'forums';
            //$_POST['andor_type'] = 'and';
            //$_POST['search_tags'] = '';
            $_POST['submit'] = 'Search Now';
            $_POST['search_content'] = (isset($search_filter['titleonly']) && $search_filter['titleonly']) ? 'titles' : 'both';
            $_POST['search_author'] = isset($search_filter['searchuser']) ? $search_filter['searchuser'] : '';
            $_POST['search_authorid'] = isset($search_filter['userid']) ? $search_filter['userid'] : '';
            $_POST['search_date_start'] = (isset($search_filter['searchtime']) && is_numeric($search_filter['searchtime'])) ? $search_filter['searchtime'].' seconds ago' : '';
            $_POST['search_date_end'] = '';
            $_POST['search_app_filters'] = array(
                'forums' => array(
                    'forums' => isset($search_filter['forumid']) ? array($search_filter['forumid']) : (
                                isset($search_filter['only_in']) ? $search_filter['only_in'] : array()),
                    'forums_exclude' => isset($search_filter['not_in']) ? $search_filter['not_in'] : array(),
                    'noPreview' => (isset($search_filter['showposts']) && $search_filter['showposts']) ? 0 : 1,
                    'pCount' => 0,
                    'pViews' => 0,
                    'sortKey' => date,
                    'sortDir' => 0,
                ),
            );
        }
        break;
    case 'get_unread_topic':
        $tapatalk_handle = 'search';
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'viewNewContent';
        list($_GET['st'], $search_per_page) = process_page($request_params[0], $request_params[1]);
        
        if (isset($request_params[2]) && $request_params[2])
        {
            $_GET['sid'] = $request_params[2];
        }
        else
        {
            $_GET['search_app'] = 'forums';
            $_GET['period'] = 'unread';
            $_GET['userMode'] = '';
            $_GET['followedItemsOnly'] = 0;
            
            $_POST['search_app_filters'] = array(
                'forums' => array(
                    'forums' => (isset($request_params[3]['only_in']) && is_array($request_params[3]['only_in'])) ? $request_params[3]['only_in'] : array(),
                    'forums_exclude' => (isset($request_params[3]['not_in']) && is_array($request_params[3]['not_in'])) ? $request_params[3]['not_in'] : array(),
                ),
            );
        }
        break;
    case 'get_participated_topic':
        $tapatalk_handle = 'search';
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'user_activity';
        list($_GET['st'], $search_per_page) = process_page($request_params[1], $request_params[2]);
        
        if (isset($request_params[3]) && $request_params[3])
        {
            $_GET['sid'] = $request_params[3];
        }
        else
        {
            $_GET['search_app'] = 'forums';
            $_GET['userMode'] = 'all';
            $_GET['search_app_filters'] = array('forums' => array('sortKey' => date, 'sortDir' => 0));
            $_GET['search_author'] = isset($request_params[0]) ? $request_params[0] : '';
            $_GET['mid'] = isset($request_params[4]) ? intval($request_params[4]) : 0;
            $_GET['return_mine'] = 1;
        }
        break;
    case 'get_latest_topic':
        $tapatalk_handle = 'search';
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'viewNewContent';
        list($_GET['st'], $search_per_page) = process_page($request_params[0], $request_params[1]);
        
        if (isset($request_params[2]) && $request_params[2])
        {
            $_GET['sid'] = $request_params[2];
        }
        else
        {
            $_GET['search_app'] = 'forums';
            $_GET['period'] = 'month';
            $_GET['userMode'] = '';
            $_GET['followedItemsOnly'] = 0;
            
            $_POST['search_app_filters'] = array(
                'forums' => array(
                    'forums' => (isset($request_params[3]['only_in']) && is_array($request_params[3]['only_in'])) ? $request_params[3]['only_in'] : array(),
                    'forums_exclude' => (isset($request_params[3]['not_in']) && is_array($request_params[3]['not_in'])) ? $request_params[3]['not_in'] : array(),
                ),
            );
        }
        break;
    case 'get_user_topic':
        $tapatalk_handle = 'search';
        $return_search_count = false;
        $search_per_page = 50;
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'search';
        $_GET['st'] = 0;
        $_GET['section'] = 'search';
        $_GET['fromsearch'] = 1;
        $_POST['search_app'] = 'forums';
        $_POST['submit'] = 'Search Now';
        $_POST['search_content'] = 'titles';
        $_POST['search_author'] = isset($request_params[0]) ? $request_params[0] : '';
        $_POST['search_authorid'] = isset($request_params[1]) ? intval($request_params[1]) : 0;
        $_POST['return_mine'] = 1;
        $_POST['search_app_filters'] = array('forums' => array('noPreview' => 1));
        break;
    case 'get_user_reply_post':
        $tapatalk_handle = 'search';
        $return_search_count = false;
        $search_per_page = 50;
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'search';
        $_GET['st'] = 0;
        $_GET['section'] = 'search';
        $_GET['fromsearch'] = 1;
        $_POST['search_app'] = 'forums';
        $_POST['submit'] = 'Search Now';
        $_POST['search_content'] = 'both';
        $_POST['search_author'] = isset($request_params[0]) ? $request_params[0] : '';
        $_POST['search_authorid'] = isset($request_params[1]) ? intval($request_params[1]) : 0;
        $_POST['return_mine'] = 1;
        $_POST['search_app_filters'] = array('forums' => array('noPreview' => 0));
        break;
    case 'get_subscribed_topic':
        $tapatalk_handle = 'search';
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'viewNewContent';
        list($_GET['st'], $search_per_page) = process_page($request_params[0], $request_params[1]);
        
        $_GET['search_app'] = 'forums';
        $_GET['period'] = 'year';
        $_GET['userMode'] = '';
        $_GET['followedItemsOnly'] = 1;
        break;
    case 'search_topic':
        $show_last_post = false;
        $tapatalk_handle = 'search';
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'search';
        list($_GET['st'], $search_per_page) = process_page($request_params[1], $request_params[2]);
        
        if (isset($request_params[3]) && $request_params[3])
        {
            $_GET['sid'] = $request_params[3];
        }
        else
        {
            $_GET['section'] = 'search';
            $_GET['fromsearch'] = 1;
            $_POST['search_app'] = 'forums';
            $_POST['submit'] = 'Search Now';
            $_POST['search_term'] = $request_params[0];
            $_POST['search_app_filters'] = array('forums' => array('noPreview' => 1));
        }
        break;
    case 'search_post':
        $tapatalk_handle = 'search';
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'search';
        list($_GET['st'], $search_per_page) = process_page($request_params[1], $request_params[2]);
        
        if (isset($request_params[3]) && $request_params[3])
        {
            $_GET['sid'] = $request_params[3];
        }
        else
        {
            $_GET['section'] = 'search';
            $_GET['fromsearch'] = 1;
            $_POST['search_app'] = 'forums';
            $_POST['submit'] = 'Search Now';
            $_POST['search_term'] = $request_params[0];
            $_POST['search_app_filters'] = array('forums' => array('noPreview' => 0));
        }
        break;
    case 'login':
        $tapatalk_handle = 'login';
        $_GET['app'] = 'core';
        $_GET['module'] = 'global';
        $_GET['section'] = 'login';
        $_GET['do'] = 'process';
        $_POST['ips_username'] = $request_params[0];
        $_POST['ips_password'] = $request_params[1];
        $_POST['rememberMe'] = 1;
        $_POST['anonymous'] = isset($request_params[2]) ? $request_params[2] : false;
        break;
    case 'logout_user':
        $_GET['app'] = 'core';
        $_GET['module'] = 'global';
        $_GET['section'] = 'login';
        $_GET['do'] = 'logout';
        $tapatalk_handle = 'login';
        break;
    case 'sign_in':
        $_POST['app'] = 'core';
        $_POST['module'] = 'global';
        $_POST['section'] = 'register';
        $_POST['tt_token'] = $request_params[0];
        $_POST['tt_code'] = $request_params[1];
        $_POST['return'] = 'https://tapatalk.com';
        if (isset($request_params[2])) $_POST['EmailAddress'] = $request_params[2];
        if (isset($request_params[3])) $_POST['name'] = $request_params[3];
        if (isset($request_params[3])) $_POST['members_display_name'] = $request_params[3];
        if (isset($request_params[4])) $_POST['PassWord'] = $request_params[4];
        if (isset($request_params[4])) $_POST['PassWord_Check'] = $request_params[4];
        if (isset($request_params[5]))
        {
            $_POST['custom_register_fields'] = $request_params[5];
        }
        
        $tapatalk_handle = 'sign_in';
        break;
    case 'prefetch_account':
        if (!defined('IPS_ENFORCE_ACCESS')) define('IPS_ENFORCE_ACCESS', true);
        $_GET['app'] = 'members';
        $_GET['module'] = 'profile';
        $_GET['section'] = 'view';
        $_POST['email'] = isset($request_params[0]) ? $request_params[0] : '';
        $tapatalk_handle = 'system';
        break;
    case 'register':
        $_GET['app'] = 'core';
        $_GET['module'] = 'global';
        $_GET['section'] = 'register';
        $_POST['app'] = 'core';
        $_POST['module'] = 'global';
        $_POST['section'] = 'register';
        $_POST['agree_to_terms'] = 1;
        $_POST['do'] = 'process_form';
        $_POST['nexus_pass'] = 1;
        $_POST['time_offset'] = 8;
        $_POST['dst'] = 0;
        $_POST['members_display_name'] = $request_params[0];
        $_POST['EmailAddress'] = $request_params[2];
        $_POST['PassWord'] = $request_params[1];
        $_POST['PassWord_Check'] = $request_params[1];
        $_POST['allow_admin_mail'] = 1;
        $_POST['agree_tos'] = 1;
        if(count($request_params) >= 5)
        {
            $_POST['tt_token'] = $request_params[3];
            $_POST['tt_code'] = $request_params[4];
        }
        if (isset($request_params[5]))
        {
            $_POST['custom_register_fields'] = $request_params[5];
        }
        break;
    case 'update_password':
        $tapatalk_handle = 'usercp';
        $_GET['app'] = 'core';
        $_GET['area'] = 'email';
        $_GET['module'] = 'usercp';
        $_GET['tab'] = 'core';
        $_POST['do'] = 'save';
        $_POST['submitForum'] = 'Save changes';
        
        if ($params_num == 2)
        {
            $_POST['current_pass'] = $request_params[0];
            $_POST['new_pass_1'] = $request_params[1];
            $_POST['new_pass_2'] = $request_params[1];
        }
        elseif ($params_num == 3)
        {
            $_POST['current_pass'] = true;
            $_POST['new_pass_1'] = $request_params[0] ;
            $_POST['new_pass_2'] = $request_params[0] ;
            $_POST['tt_token'] = $request_params[1];
            $_POST['tt_code'] = $request_params[2];
        }
        break;
    case 'update_email':
        $tapatalk_handle = 'usercp';
        $_GET['app'] = 'core';
        $_GET['module'] = 'usercp';
        $_GET['tab']= 'core';
        $_GET['area'] = 'email';
        $_POST['do'] = 'save';
        $_POST['in_email_1'] = $request_params[1];
        $_POST['in_email_2'] = $request_params[1];
        $_POST['password'] = $request_params[0];
        $_POST['submitForum'] = 'Save changes';
        break;
        
    case 'forget_password':
        $_GET['app'] = 'core';
        $_GET['module'] = 'global';
        $_GET['section'] = 'lostpass';
        $_POST['do'] = '11';
        $_POST['member_name'] = $request_params[0];
        if(count($request_params) == 3)
        {
            $_POST['tt_token'] = $request_params[1];
            $_POST['tt_code'] = $request_params[2];
        }
        break;
    case 'login_forum':
        if ($params_num == 2) {
            $_GET['f'] = $request_params[0];
            $_POST['L'] = 1;
            $_POST['f_password'] = $request_params[1];
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'new_topic':
        if ($params_num >= 3)
        {
            $tapatalk_handle = 'system';
            $_POST['app']               = 'forums';
            $_POST['module']            = 'post';
            $_POST['section']           = 'post';
            $_POST['do']                = 'new_post_do';
            $_POST['isRte']             = 1;
            $_POST['noCKEditor']        = 0;
            $_POST['f']                 = $request_params[0];
            $_POST['TopicTitle']        = $request_params[1];
            $_POST['Post']              = $request_params[2];
            $_POST['ipsTags']           = isset($request_params[3]) ? $request_params[3] : '';
            $_POST['attach_post_key']   = isset($request_params[5]) ? $request_params[5] : 0;
        }
        else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_board_stat': break;
    case 'get_config': break;
    case 'get_forum':
        $_POST['/index'] = '';
        isset($request_params[0]) && $_POST['sub_desc'] = $request_params[0];
        isset($request_params[1]) && $_POST['parent_id'] = $request_params[1];
        break;
    case 'get_inbox_stat':
        $_GET['app'] = 'core';
        $_GET['module'] = 'search';
        $_GET['do'] = 'viewNewContent';
        $_GET['search_app'] = 'forums';
        $_GET['search_app_filters[forums][searchInKey]'] = '';
        //$_GET['change'] = 1;
        $_GET['period'] = 'unread';
        $_GET['userMode'] = '';
        $_GET['followedItemsOnly'] = 1;
        break;
    case 'get_online_users':
        $_GET['app'] = 'members';
        $_GET['module'] = 'online';
        $_GET['do'] = 'listall';
        $_GET['sort_order'] = 'desc';
        break;
    case 'get_thread':
        if ($params_num >= 1) {
            if (!defined('IPS_ENFORCE_ACCESS')) define('IPS_ENFORCE_ACCESS', true);
            $topic_id = $request_params[0];
            $return_html  = isset($request_params[3]) ? $request_params[3] : false;

            if (preg_match('/^ann_/', $topic_id))
            {
                $_GET['announce_id'] = intval(str_replace('ann_', '', $topic_id));
            }
            else
            {
                // for 3.3.0
                $_GET['request_method'] = 'get';
                $_SERVER['REQUEST_URI'] = '/topic/' .$topic_id . '-mobiquo/';

                $_GET['showtopic']  = $topic_id;
                $_GET['app']        = 'forums';
                $_GET['module']     = 'forums';
                $_GET['section']    = 'topics';
                $_GET['t']          = $topic_id;
                list($_GET['st'], $_GET['post_per_page']) = process_page($request_params[1], $request_params[2]);
            }

        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_thread_by_post':
        if ($params_num >= 1) {
            if (!defined('IPS_ENFORCE_ACCESS')) define('IPS_ENFORCE_ACCESS', true);
            $_GET['request_method'] = 'get';
            $_GET['app']        = 'forums';
            $_GET['module']     = 'forums';
            $_GET['section']    = 'topics';
            $_GET['view']       = 'findpost';
            $_GET['p']          = intval($request_params[0]);
            $_GET['post_per_page'] = isset($request_params[1]) ? intval($request_params[1]) : 20;
            $return_html = isset($request_params[2]) ? $request_params[2] : false;
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_thread_by_unread':
        if ($params_num >= 1) {
            if (!defined('IPS_ENFORCE_ACCESS')) define('IPS_ENFORCE_ACCESS', true);
            $topic_id = $request_params[0];
            $posts_per_request  = isset($request_params[1]) ? intval($request_params[1]) : 20;
            $return_html  = isset($request_params[2]) ? $request_params[2] : false;
            if (preg_match('/^ann_/', $topic_id))
            {
                $_GET['announce_id'] = intval(str_replace('ann_', '', $topic_id));
            }
            else
            {
                $_GET['request_method'] = 'get';
                $_SERVER['REQUEST_URI'] = '/topic/' .$topic_id . '-mobiquo/';
                //showtopic
                $_GET['showtopic']  = $topic_id;
                $_GET['app']        = 'forums';
                $_GET['module']     = 'forums';
                $_GET['section']    = 'topics';
                $_GET['t']          = $topic_id;
                $_GET['view']       = 'getnewpost';
    
                $_POST['t']         = $topic_id;
                $_POST['view']       = 'getnewpost';
                $_GET['post_per_page'] = $posts_per_request;
            }
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_topic':
        if ($params_num >= 1) {
            if (!defined('IPS_ENFORCE_ACCESS')) define('IPS_ENFORCE_ACCESS', true);
            $forum_id = $request_params[0];
            $start_num = isset($request_params[1]) ? $request_params[1] : '0';
            $end_num = isset($request_params[2]) ? $request_params[2] : '19';
            $mode = isset($request_params[3]) && $request_params[3] ? strtolower($request_params[3]) : 'normal';
            $_GET['f'] = $forum_id;
            $_GET['request_method'] = 'post';
            $_GET['showforum'] = $forum_id;
            $_GET['app'] = 'forums';
            $_GET['module'] = 'forums';
            $_GET['section'] = 'forums';
            $_SERVER['REQUEST_URI'] = '/forum/'. $forum_id .'-mobiquo';
            if ($start_num > $end_num) {
                get_error('Line: '.__LINE__);
            } elseif ($end_num - $start_num >= 50) {
                $end_num = $start_num + 49;
            }

            // for 3.3.0
            list($_GET['st'], $_GET['perpage']) = process_page($request_params[1], $request_params[2]);
            $_GET['topicfilter'] = $mode;

        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_user_info':
        $tapatalk_handle = 'system';
        if ($params_num <= 2) {
            if (isset($request_params[1]) && !empty($request_params[1]))
                $_GET['id'] = intval($request_params[1]);
            elseif (isset($request_params[0]))
                $_GET['user_name']  = $request_params[0];

            $_GET['app'] = 'members';
            $_GET['module'] = 'profile';
            $_GET['section'] = 'view';
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'mark_all_as_read':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'forums';
        $_GET['section'] = 'markasread';
        if ($params_num == 0) {
            $_GET['marktype'] = 'all';
        } elseif ($params_num == 1) {
            $_GET['marktype'] = 'forum';
            $_GET['forumid'] = $request_params[0];
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'reply_post':
        if ($params_num >= 4)
        {
            $tapatalk_handle = 'system';
            $_POST['app']               = 'forums';
            $_POST['module']            = 'post';
            $_POST['section']           = 'post';
            $_POST['do']                = 'reply_post_do';
            $_POST['isRte']             = 1;
            $_POST['noCKEditor']        = 0;
            $_POST['f']                 = $request_params[0];
            $_POST['t']                 = $request_params[1];
            $_POST['Post']              = $request_params[3];
            $_POST['attach_post_key']   = isset($request_params[5]) ? $request_params[5] : 0;
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_quote_post':
        if ($params_num == 1) {
            $tapatalk_handle = 'topics_ajax';
            $_GET['app'] = 'forums';
            $_GET['module'] = 'ajax';
            $_GET['section']= 'topics';
            $_GET['do'] = 'mqquote';
            $_POST['pids'] = implode(',', array_filter(array_unique(array_map('intval', explode('-', $request_params[0])))));
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_raw_post':
        if ($params_num == 1) {
            $tapatalk_handle = 'topics_ajax';
            $_GET['app'] = 'forums';
            $_GET['module'] = 'ajax';
            $_GET['section']= 'topics';
            $_GET['do'] = 'editBoxShow';
            $_GET['rteStatus'] = 'rte';
            $_GET['p'] = $request_params[0];
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'save_raw_post':
        if ($params_num > 2) {
            $tapatalk_handle = 'system';
            $_POST['app']               = 'forums';
            $_POST['module']            = 'post';
            $_POST['section']           = 'post';
            $_POST['do']                = 'edit_post_do';
            $_POST['isRte']             = 1;
            $_POST['noCKEditor']        = 0;
            $_POST['p']                 = $request_params[0];
            $_POST['Post']              = $request_params[2];
            $_POST['attach_post_key']   = isset($request_params[5]) ? $request_params[5] : 0;
            $_POST['post_edit_reason']  = isset($request_params[6]) ? $request_params[6] : '';
            $_POST['add_edit']          = isset($request_params[6]) && !empty($request_params[6]);
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'subscribe_topic':
        if ($params_num <= 2)
        {
            $_POST['app'] = 'core';
            $_POST['module'] = 'usercp';
            $_POST['tab'] = 'forums';
            $_POST['area'] = 'watch';
            $_POST['watch'] = 'topic';
            $_POST['do'] = 'saveWatch';
            $_POST['tid'] = $request_params[0];

            $freq_option = '';
            if (isset($request_params[1]))
            {
                $freq_index = intval($request_params[1]);
                $freq_options = array(
                    0 => '',
                    1 => 'immediate',
                    2 => 'daily',
                    3 => 'weekly',
                    4 => 'offline',
                );

                $freq_option = isset($freq_options[$freq_index]) ? $freq_options[$freq_index] : '';
            }

        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'unsubscribe_topic':
        if ($params_num == 1) {
            $_POST['app'] = 'core';
            $_POST['module'] = 'usercp';
            $_POST['tab'] = 'forums';
            $_POST['area'] = 'updateWatchTopics';
            $_POST['do'] = 'saveIt';
            $_POST['topicIDs'] = array( $request_params[0] => 1);
            $_POST['trackchoice'] = 'unsubscribe';
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
   case 'report_post':
        $_GET['app'] = 'core';
        $_GET['module'] = 'reports';
        $_GET['rcom'] = 'post';
        $_GET['message'] = isset($request_params[1]) && trim($request_params[1]) ? $request_params[1] : 'Spam - Report from Tapatalk';
        $_GET['send'] = '1';
        $_GET['post_id'] = $request_params[0];
        break;
    case 'subscribe_forum':
        $_POST['fid'] = $request_params[0];

        $freq_option = '';
        if (isset($request_params[1]))
        {
            $freq_index = intval($request_params[1]);
            $freq_options = array(
                0 => '',
                1 => 'immediate',
                2 => 'daily',
                3 => 'weekly',
                4 => 'offline',
            );

            $freq_option = isset($freq_options[$freq_index]) ? $freq_options[$freq_index] : '';
        }

        $_POST['st'] = 0;
        $_POST['emailtype'] = 'delayed';

        $_GET['app'] = 'core';
        $_GET['module'] = 'usercp';
        $_GET['tab'] = 'forums';
        $_GET['area'] = 'watch';
        $_GET['watch'] = 'forum';
        $_GET['do'] = 'saveWatch';
        break;
    case 'unsubscribe_forum':
        $_POST['fid'] = $request_params[0];
        $_POST['st'] = 0;
        $_POST['emailtype'] = 'delayed';

        $_GET['app'] = 'core';
        $_GET['module'] = 'usercp';
        $_GET['tab'] = 'forums';
        $_GET['area'] = 'updateWatchForums';
        $_GET['do'] = 'saveIt';
        $_GET['forumIDs'] = array($request_params[0] => 1);
        $_GET['trackchoice'] = 'unsubscribe';
        break;
    case 'get_subscribed_forum':
        $_GET['app'] = 'core';
        $_GET['module'] = 'usercp';
        $_GET['tab'] = 'forums';
        $_GET['area'] = 'forumsubs';

        // for 3.2.0
        $_GET['do'] = 'followed';
        $_GET['search_app'] = 'forums';
        $_GET['contentType'] = 'forums';
        break;
    case 'like_post':
        $_GET['app'] = 'core';
        $_GET['module'] = 'ajax';
        $_GET['section'] = 'reputation';
        $_GET['do'] = 'add_rating';
        $_GET['app_rate'] = 'forums';
        $_GET['type'] = 'pid';
        $_GET['type_id'] = $request_params[0];
        $_GET['rating'] = 1;
        break;
    case 'unlike_post':
        $_GET['app'] = 'core';
        $_GET['module'] = 'ajax';
        $_GET['section'] = 'reputation';
        $_GET['do'] = 'add_rating';
        $_GET['app_rate'] = 'forums';
        $_GET['type'] = 'pid';
        $_GET['type_id'] = $request_params[0];
        $_GET['rating'] = -1;
        break;

    case 'upload_avatar':
        $_GET['app'] = 'core';
        $_GET['module'] = 'usercp';
        $_GET['tab'] = 'members';
        $_GET['area'] = 'avatar';
        $_REQUEST['area'] = 'avatar';
        $_POST['do'] = 'save';
        $_POST['submit'] = 'Save Changes';
        if (isset($_FILES['upload']))
        {
            $_FILES['upload_avatar'] = $_FILES['upload'];
            $_FILES['upload_photo'] = $_FILES['upload'];
        }
        $server_data = '<?xml version="1.0"?><methodCall><methodName>upload_avatar</methodName><params></params></methodCall>';
        break;
    case 'upload_attach':
        $_GET['app'] = 'core';
        $_GET['module'] = 'attach';
        $_GET['section'] = 'attach';
        $_GET['do'] = 'attachUploadiFrame';
        if (trim($_POST['type']) == "pm")
        {
            $_GET['attach_rel_module'] = 'msg';
        }
        else
        {
            $_GET['attach_rel_module'] = 'post';
            $_GET['forum_id'] =  $_POST['forum_id'];
        }
        $_GET['attach_rel_id'] = '0';
        $_GET['attach_post_key'] = empty($_POST['group_id']) ? md5(microtime()) : $_POST['group_id'];
        
        $_GET['fetch_all'] = '1';
        if (isset($_FILES['attachment']['name'])){
             $_FILES['FILE_UPLOAD'] = array(
                 'name' => $_FILES['attachment']['name'][0],
                 'type' => $_FILES['attachment']['type'][0],
                 'tmp_name' => $_FILES['attachment']['tmp_name'][0],
                 'error' => $_FILES['attachment']['error'][0],
                 'size' => $_FILES['attachment']['size'][0],
             );
        }

        $server_data = '<?xml version="1.0"?><methodCall><methodName>upload_attach</methodName><params></params></methodCall>';
        break;
    case 'remove_attachment':
        $_GET['app'] = 'core';
        $_GET['module'] = 'attach';
        $_GET['section'] = 'attach';
        $_GET['do'] = 'attach_upload_remove';
        if(intval($request_params[1]) !== 0)
            $_GET['attach_rel_module'] = 'post';
        else 
            $_GET['attach_rel_module'] = 'msg';
        $_GET['attach_rel_id'] = isset($request_params[3]) ? $request_params[3] : 0;
        $_GET['attach_post_key'] = $request_params[2];
        $_GET['forum_id'] = $request_params[1];
        $_GET['attach_id'] = $request_params[0];
        break;
    case 'get_alert':
        $tapatalk_handle = 'alert';
        list($_GET['st'], $_GET['perpage']) = process_perpage($request_params[0], $request_params[1]);
        break;
    case 'ignore_user':
        $tapatalk_handle = 'usercp';
        $_GET['app'] = 'core';
        $_GET['module'] = 'usercp';
        $_GET['tab']= 'core';
        if (!isset($request_params[1]) || intval($request_params[1]))
        {
            $_GET['area'] = 'ignoredusers';
            $_POST['do'] = 'save';
            $_POST['ignore_topics'] = 1;
            $_POST['ignore_signatures'] = 1;
            $_POST['ignore_messages'] = 1;
            $_POST['submitForm'] = 'Save Changes';
            $_POST['uid'] = intval($request_params[0]);
        }
        else
        {
            $_GET['area'] = 'removeIgnoredUser';
            $_GET['do'] = 'saveIt';
            $_GET['id'] = intval($request_params[0]);
        }
        break;
    case 'search_user':
        $tapatalk_handle = 'search_user';
        $_GET['app'] = 'core';
        $_GET['module'] = 'ajax';
        $_GET['section'] = 'findnames';
        $_GET['do'] = 'get-member-names';
        $_GET['name'] = $request_params[0];
        list($_GET['st'], $_GET['perpage']) = process_perpage($request_params[1], $request_params[2]);
        break;
    case 'get_recommended_user':
        $tapatalk_handle = 'recommend';
        $_GET['app'] = 'members';
        list($_GET['st'], $_GET['perpage']) = process_perpage($request_params[0], $request_params[1]);
        $_GET['mode'] = isset($request_params[2]) ? $request_params[2] : 1;
        break;
    case 'get_contact':
        $tapatalk_handle = 'recommend';
        $_GET['app'] = 'members';
        $_GET['do'] = 'contact';
        $_GET['uid'] = intval($request_params[0]);
        break;
    case 'user_sync':
        $tapatalk_handle = 'recommend';
        $_GET['app'] = 'members';
        $_GET['do'] = 'user_sync';
        break;
    case 'update_signature':
        $tapatalk_handle = 'system';
        $_GET['app'] = 'core';
        $_GET['module'] = 'usercp';
        $_GET['tab'] = 'core';
        $_GET['area'] = 'signature';
        $_POST['do'] = 'save';
        $_POST['isRte'] = 1;
        $_POST['noSmilies'] = 0;
        $_POST['Post'] = $request_params[0];
        $_POST['submitForm'] = 'Save Changes';
        break;
    // conversation part
    case 'get_conversations':
        $tapatalk_handle = 'conversation';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'inbox';
        $_GET['folderID'] = 'myconvo';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        break;
    case 'get_conversation':
        $tapatalk_handle = 'conversation';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'showConversation';
        $_GET['topicID'] = $request_params[0];
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[1], $request_params[2]);
        $return_html  = isset($request_params[3]) ? $request_params[3] : false;
        break;
    case 'origin_reply_conversation':
        $tapatalk_handle = 'conversation_send';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'send';
        $_GET['do'] = 'sendReply';
        $_GET['topicID'] = $request_params[0];

        $_POST['fast_reply_used'] = 1;
        $_POST['enableemo'] = 'yes';
        $_POST['enablesig'] = 'yes';
        $_POST['submit'] = 'Post';
        $_POST['msgContent'] = $request_params[1];
        break;
    case 'reply_conversation':
        $tapatalk_handle = 'system';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'send';
        $_GET['do'] = 'sendReply';
        $_POST['isRte']     = 1;
        $_POST['noCKEditor']= 0;
        $_POST['topicID'] = $request_params[0];
        $_POST['msgID'] = $request_params[0];
        $_POST['msgContent'] = $request_params[1];
        $_POST['postKey']   = isset($request_params[4]) ? $request_params[4] : 0;
        break;
    case 'origin_new_conversation':
        $tapatalk_handle = 'conversation_send';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'send';
        $_GET['do'] = 'send';

        $_POST['entered_name'] = array_shift($request_params[0]);
        $_POST['inviteUsers'] = empty($request_params[0]) ? '' : implode(', ', $request_params[0]);
        $_POST['sendType'] = 'invite';
        $_POST['msg_title'] = $request_params[1];
        $_POST['Post'] = $request_params[2];
        $_POST['dosubmit'] = 'Send Message';
        break;
    case 'new_conversation':
        $tapatalk_handle = 'system';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'send';
        $_GET['do'] = 'send';
        
        $_POST['entered_name'] = array_shift($request_params[0]);
        $_POST['inviteUsers'] = empty($request_params[0]) ? '' : implode(', ', $request_params[0]);
        $_POST['sendType'] = 'invite';
        $_POST['msg_title'] = $request_params[1];
        $_POST['isRte']     = 1;
        $_POST['noCKEditor']= 0;
        $_POST['noSmilies'] = 0;
        $_POST['Post'] = $request_params[2];
        $_POST['postKey']   = isset($request_params[4]) ? $request_params[4] : 0;
        $_POST['dosubmit'] = 'Send Message';
        break;
    case 'invite_participant':
        $tapatalk_handle = 'conversation';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'addParticipants';

        $_POST['inviteNames'] = $request_params[0];
        $_POST['topicID'] = $request_params[1];
        break;
    case 'get_quote_conversation':
        $tapatalk_handle = 'conversation_send';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'send';
        $_GET['do'] = 'replyForm';
        $_GET['topicID'] = $request_params[0];
        $_GET['msgID'] = $request_params[1];
        break;
    case 'delete_conversation':
        $tapatalk_handle = 'conversation';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'deleteConversation';
        $_GET['topicID'] = $request_params[0];
        break;
    case 'mark_conversation_read':
        $tapatalk_handle = 'conversation';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'multiFile';
        $_GET['cFolderID'] = 'myconvo';
        $_GET['method'] = 'markread';
        $_POST['method'] = 'markread';
        $_POST['msgid'] = array();
        $mark_all_read = !isset($request_params[0]);
        if (isset($request_params[0]))
        {
            foreach(explode(',', $request_params[0]) as $topicID)
            {
                if ($topicID = intval($topicID))
                {
                    $_POST['msgid'][$topicID] = 'on';
                }
            }
        }
        break;
    case 'mark_conversation_unread':
        $tapatalk_handle = 'conversation';
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'multiFile';
        $_GET['cFolderID'] = 'myconvo';
        $_GET['method'] = 'markunread';
        $_POST['msgid'] = array();
        foreach(explode(',', $request_params[0]) as $topicID)
        {
            if ($topicID = intval($topicID))
            {
                $_POST['msgid'][$topicID] = 'on';
            }
        }
        break;

    // moderation part
    case 'm_stick_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $_GET['do'] = $request_params[1] == 2 ? '16' : '15';
        $tapatalk_handle = 'moderate';
        break;
    case 'm_close_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $_GET['do'] = $request_params[1] == 2 ? '00' : '01';
        $tapatalk_handle = 'moderate';
        break;
    case 'm_delete_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $_GET['do'] = $request_params[1] == 2 ? '09' : '08';
        $tapatalk_handle = 'moderate';
        break;
    case 'm_undelete_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $_GET['do'] = 'topic_restore';
        $tapatalk_handle = 'moderate';
        break;
    case 'm_approve_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $tapatalk_handle = 'moderate';

        if ($request_params[1] == 2)
        {
            $_POST['do'] = 'topicchoice';
            $_POST['selectedtids'] = array($request_params[0]);
            $_POST['tact'] = 'sdelete';
            $_POST['deleteReason'] = '';
        }
        else
        {
            $_GET['do'] = 'sundelete';
        }
        break;
    case 'm_move_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $_POST['do'] = 'topicchoice';
        $_POST['tact'] = 'domove';
        $_POST['selectedtids'] = array_map('intval', explode(',', $request_params[0]));
        $_POST['df'] = $request_params[1];
        $_POST['leave'] = isset($request_params[2]) && $request_params[2] ? 'y' : 0;
        $tapatalk_handle = 'moderate';
        break;
    case 'm_rename_topic':
        $tapatalk_handle = 'topics_ajax';
        $_GET['app'] = 'forums';
        $_GET['module'] = 'ajax';
        $_GET['section'] = 'topics';
        $_GET['do'] = 'saveTopicTitle';
        $_GET['tid'] = $request_params[0];
        $_POST['name'] = $request_params[1];
        break;
    case 'm_merge_topic':
        $_GET['t'] = $request_params[0];
        $_POST['app'] = 'forums';
        $_POST['module'] = 'moderate';
        $_POST['section'] = 'moderate';
        $_POST['do'] = 'topicchoice';
        $_POST['tact'] = 'merge';
        $_POST['selectedtids'] = $request_params[0].','.$request_params[1];
        $tapatalk_handle = 'moderate';
        break;
    case 'm_delete_post':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['p'] = $request_params[0];
        $_GET['pid'] = array($request_params[0]);
        $_GET['do'] = $request_params[1] == 2 ? 'p_hdelete' : '04';
        $tapatalk_handle = 'moderate';
        break;
    case 'm_undelete_post':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['p'] = $request_params[0];
        $_GET['pid'] = array($request_params[0]);
        $_GET['do'] = 'p_hrestore';
        $tapatalk_handle = 'moderate';
        break;
    case 'm_approve_post':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['do'] = 'postchoice';
        $_GET['p'] = $request_params[0];
        $_GET['pid'] = $request_params[0];
        $_GET['selectedpids'] = array($request_params[0]);
        $tapatalk_handle = 'moderate';

        if ($request_params[1] == 2)
        {
            $_GET['tact'] = 'sdelete';
            $_POST['deleteReason'] = '';
        }
        else
        {
            $_GET['tact'] = 'sundelete';
        }
        break;
    case 'm_move_post':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_POST['topic_url'] = $request_params[1];
        $_POST['selectedpids'] = array_map('intval', explode(',', $request_params[0]));
        $_POST['p'] = $_POST['selectedpids'][0];
        $_POST['do'] = 'postchoice';
        $_POST['tact'] = 'move';
        $_POST['checked'] = 1;
        $_POST['submit'] = 'Move Posts';
        $tapatalk_handle = 'moderate';
        break;
    case 'm_merge_post':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_POST['p'] = $request_params[1];
        $_POST['selectedpids'] = array_map('intval', array_unique(explode(',', $request_params[0].','.$request_params[1])));
        $_POST['do'] = 'postchoice';
        $_POST['tact'] = 'merge';
        $_POST['checked'] = 1;
        $_POST['postdate'] = $request_params[1];
        $tapatalk_handle = 'moderate';
        break;
    case 'm_close_report':
        $_GET['app'] = 'core';
        $_GET['module'] = 'reports';
        $_GET['section'] = 'reports';
        $_GET['newstatus'] = 3;
        $_GET['report_ids'] = array(intval($request_params[0]));
        $_GET['do'] = 'process';
        $tapatalk_handle = 'system';
        break;
    case 'm_mark_as_spam':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['member_id'] = $request_params[0];
        $_GET['do'] = 'setAsSpammer';
        $tapatalk_handle = 'modcp';
        break;
    case 'm_ban_user':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['member_name'] = $request_params[0];
        $_GET['do'] = 'setAsSpammer';
        $tapatalk_handle = 'modcp';
        break;
    case 'm_get_delete_topic':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['fromapp'] = 'forums';
        $_GET['tab'] = 'deletedtopics';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        $tapatalk_handle = 'modcp';
        break;
    case 'm_get_delete_post':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['fromapp'] = 'forums';
        $_GET['tab'] = 'deletedposts';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        $tapatalk_handle = 'modcp';
        break;
    case 'm_get_report_post':
        $_GET['app'] = 'core';
        $_GET['module'] = 'reports';
        $_GET['do'] = 'index';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        $tapatalk_handle = 'system';
        break;
    case 'm_get_moderate_topic':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['fromapp'] = 'forums';
        $_GET['tab'] = 'unapprovedtopics';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        $tapatalk_handle = 'modcp';
        break;
    case 'm_get_moderate_post':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['fromapp'] = 'forums';
        $_GET['tab'] = 'unapprovedposts';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        $tapatalk_handle = 'modcp';
        break;
}

$function_file_name = $tapatalk_handle ? $tapatalk_handle : $request_name;


function process_page($start_num, $end)
{
    $start = intval($start_num);
    $end = intval($end);
    $start = empty($start) ? 0 : max($start, 0);
    $end = (empty($end) || $end < $start) ? ($start + 19) : max($end, $start);
    if ($end - $start >= 50) {
        $end = $start + 49;
    }
    $limit = $end - $start + 1;
    $page = intval($start/$limit) + 1;

    return array($start, $limit, $page);
}

function process_perpage($page, $perpage)
{
    $perpage = isset($perpage) && intval($perpage) ? intval($perpage) : 20;
    $page = isset($page) && intval($page) ? intval($page) : 1;
    $start = ($page - 1) * $perpage;
    
    return array($start, $perpage, $page);
}

// for post compatibility issue
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Firefox/3.5.6 Tapatalk/ tapatalk';
foreach($_COOKIE as $co_key => $co_value)
{
    if (preg_match('/mobileBrowser$/s', $co_key)) unset($_COOKIE[$co_key]);
}

foreach($_GET as $get_key => $get_value)
    $_REQUEST[$get_key] = $get_value;

foreach($_POST as $post_key => $post_value)
    $_REQUEST[$post_key] = $post_value;


function mobi_parse_requrest()
{
    global $request_name, $request_params, $params_num;

    $ver = phpversion();
    if ($ver[0] >= 5) {
        $data = file_get_contents('php://input');
    } else {
        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
    }

    if (count($_SERVER) == 0)
    {
        $r = new xmlrpcresp('', 15, 'XML-RPC: '.__METHOD__.': cannot parse request headers as $_SERVER is not populated');
        echo $r->serialize('UTF-8');
        exit;
    }

    if(isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
        $content_encoding = str_replace('x-', '', $_SERVER['HTTP_CONTENT_ENCODING']);
    } else {
        $content_encoding = '';
    }

    if($content_encoding != '' && strlen($data)) {
        if($content_encoding == 'deflate' || $content_encoding == 'gzip') {
            // if decoding works, use it. else assume data wasn't gzencoded
            if(function_exists('gzinflate')) {
                if ($content_encoding == 'deflate' && $degzdata = @gzuncompress($data)) {
                    $data = $degzdata;
                } elseif ($degzdata = @gzinflate(substr($data, 10))) {
                    $data = $degzdata;
                }
            } else {
                $r = new xmlrpcresp('', 106, 'Received from client compressed HTTP request and cannot decompress');
                echo $r->serialize('UTF-8');
                exit;
            }
        }
    }

    if (isset($_FILES['attachment']['name']))
    {
        $request_name = 'upload_attach';
        $request_params = array();
        $params_num = 0;
    }
    else
    {
        $parsers = php_xmlrpc_decode_xml($data);
        $request_name = $parsers->methodname;
        $request_params = php_xmlrpc_decode(new xmlrpcval($parsers->params, 'array'));
        $params_num = count($request_params);
    }
}