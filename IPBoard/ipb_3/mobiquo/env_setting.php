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

mobi_parse_requrest();

if (!$request_name && isset($_POST['method_name'])) $request_name = $_POST['method_name'];

$function_file_name = $request_name;

switch ($request_name) {
    case 'attach_image':
        if ($params_num >= 3) {
            $_GET["app"]        =    "core";
            $_GET["module"]        =    "attach";
            $_GET["section"]    =    "attach";
            $_GET["do"]            =   "attachUploadiFrame";
            $_GET["attach_rel_module"]    = "post";
            $_GET["attach_rel_id"]        = "0";
            $_GET["attach_post_key"]    = md5(microtime());
            $_GET["forum_id"]            = $request_params[3];
            $_GET["fetch_all"]            = "1";
            //$_GET["MAX_FILE_SIZE"]         = "10000000000";
            $fp = tmpfile();

            fwrite($fp, $request_params[0]);
            $file_info = stream_get_meta_data($fp);
            $tmp_name = $file_info['uri'];

            $_FILES['FILE_UPLOAD'] = array(
                'name'      => $request_params[1],
                'type'      => $request_params[2] == 'image/jpeg' ? 'image/jpeg' : 'image/png',
                'tmp_name'  => $tmp_name,
                'error'     => 0,
                'size'      => filesize($tmp_name)
            );
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'authorize_user':
        if ($params_num == 2) {
            $_POST['username'] = $request_params[0];
            $_POST['password'] = $request_params[1];
            $_POST["app"] = "core";
            $_POST["module"] = "global";
            $_POST["section"] = "login";
            $_GET["app"] = "core";
            $_GET["module"] = "global";
            $_GET["section"] = "login";
            $_POST["request_method"] = "post";
            $_POST['rememberMe'] = 1;
            $_POST["do"] = "process";
            $_GET["do"] = "process";
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'login':
        if ($params_num >= 2 && $params_num <= 4) {
            $_POST['username'] = $request_params[0];
            $_POST['password'] = $request_params[1];
            $_POST["app"] = "core";
            $_POST["module"] = "global";
            $_POST["section"] = "login";
            $_GET["app"] = "core";
            $_GET["module"] = "global";
            $_GET["section"] = "login";
            $_POST["request_method"] = "post";
            $_POST['rememberMe'] = 1;
            $_POST["do"] = "process";
            $_GET["do"] = "process";
            
            $_POST["anonymous"] = isset($request_params[2]) ? $request_params[2] : false;
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'register':
        $_GET["app"] = "core";
        $_GET["module"] = "global";
        $_GET["section"] = "register";
        $_POST["app"] = "core";
        $_POST["module"] = "global";
        $_POST["section"] = "register";
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
        if(count($request_params) == 5)
        {
            $_POST['tt_token'] = $request_params[3];
            $_POST['tt_code'] = $request_params[4];
        }
        break;
    case 'update_password':
        $_GET['app'] = 'core';
        $_GET['area'] = 'email';
        $_GET['module'] = 'usercp';
        $_GET['tab'] = 'core';
        $_POST['MAX_FILE_SIZE'] = 0;
        $_POST['current_pass'] = !isset($request_params[2]) ? $request_params[0] : 'true';
        $_POST['do'] = 'save';
        $_POST['in_email_1'] = '';
        $_POST['in_email_2'] = '';
        $_POST['new_pass_1'] = !isset($request_params[2]) ? $request_params[1] : $request_params[0] ;
        $_POST['new_pass_2'] = !isset($request_params[2]) ? $request_params[1] : $request_params[0] ;
        $_POST['password'] = '';
        $_POST['s'] = '';
        $_POST['submitForum'] = 'Save changes';
        if(isset($request_params[2]))
        {
            $_POST['tt_token'] = $request_params[1];
            $_POST['tt_code'] = $request_params[2]; 
            $_POST['username'] = $request_params[3];
            $_POST['new_pass_1'] = $request_params[0];
            $_POST['new_pass_2'] = $request_params[0];
        }
        break;
    case 'update_email':
        $_GET['app'] = 'core';
        $_GET['area'] = 'email';
        $_GET['module'] = 'usercp';
        $_GET['tab'] = 'core';
        $_POST['MAX_FILE_SIZE'] = 0;
        $_POST['current_pass'] = '';
        $_POST['do'] = 'save';
        $_POST['in_email_1'] = $request_params[1];
        $_POST['in_email_2'] = $request_params[1];
        $_POST['new_pass_1'] = '';
        $_POST['new_pass_2'] = '';
        $_POST['password'] = $request_params[0];
        $_POST['s'] = '';
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
    case 'create_message':
        if ($params_num == 3 || $params_num == 5) {
            #$_POST["entered_name"] = $request_params[0];
            $_POST["sendType"]      = "invite";
            $_POST["msg_title"]     = $request_params[1];
            $_POST["ed-0_wysiwyg_used"] = "0";
            $_POST["editor_ids"]    = array("" => "ed-0" );
            $_POST["Post"]          = $request_params[2];
            $_POST["msgContent"]    = $request_params[2];
            $_POST["topicID"]       = "0";
            $_POST["dosubmit"]      = "Send Message";
            $_POST["request_method"] ="post";
            $users = $request_params[0];
            $action_id = $request_params[3];
            $reply_message_id = $request_params[4];

            if (isset($users) and count($users)) {
                $_POST["entered_name"] = $users[0];
                unset($users[0]);
                $_POST["inviteUsers"] = join(',', $users);
            } else {
                get_error('No Recipients Setted!');
            }

        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'create_topic':
        if ($params_num >= 4)
        {
            $_POST["TopicTitle"]        = $request_params[1];
            $_POST["TopicDesc"]         = "";
            $_POST["poll_question"]     = "";
            $_POST["ed-0_wysiwyg_used"] = "0";
            $_POST["editor_ids"]        = array( 0 =>  "ed-0" );
            $_POST["Post"]              = $request_params[3];
            $_POST["enableemo"]         = "yes";
            $_POST["enablesig"]         = "yes";
            $_POST["mod_options"]       = "nowt";
            $_POST["open_time_date"]    = "";
            $_POST["open_time_time"]    = "";
            $_POST["close_time_date"]   = "";
            $_POST["close_time_time"]   = "";
            $_POST["iconid"]            = "0";
            $_POST["st"]                = "0";
            $_POST["app"]               = "forums";
            $_POST["module"]            = "post";
            $_POST["section"]           = "post";
            $_POST["do"]                = "new_post_do";
            $_POST["p"]                 = "0";
            $_POST["t"]                 = "";
            $_POST["f"]                 = $request_params[0];
            $_POST["attach_id"]         = $request_params[4];
            $_POST["parent_id"]         = "0";
            $_POST["removeattachid"]    = "0";
            $_POST["dosubmit"]          = "Post New Topic";
        }
        else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'new_topic':
        if ($params_num >= 3)
        {
            $_POST["TopicTitle"]        = $request_params[1];
            $_POST["TopicDesc"]         = "";
            $_POST["poll_question"]     = "";
            $_POST["ed-0_wysiwyg_used"] = "0";
            $_POST["editor_ids"]        = array( 0 => "ed-0" );
            $_POST["Post"]              = $request_params[2];
            $_POST["enableemo"]         = "yes";
            $_POST["enablesig"]         = "yes";
            $_POST["mod_options"]       = "nowt";
            $_POST["open_time_date"]    = "";
            $_POST["open_time_time"]    = "";
            $_POST["close_time_date"]   = "";
            $_POST["close_time_time"]   = "";
            $_POST["iconid"]            = "0";
            $_POST["st"]                = "0";
            $_POST["app"]               = "forums";
            $_POST["module"]            = "post";
            $_POST["section"]           = "post";
            $_POST["do"]                = "new_post_do";
            $_POST["p"]                 = "0";
            $_POST["t"]                 = "";
            $_POST["f"]                 = $request_params[0];
            //$_POST["attach_id"]         = $request_params[4][0];
            $_POST["parent_id"]         = "0";
            $_POST["removeattachid"]    = "0";
            $_POST["dosubmit"]          = "Post New Topic";
            $_POST["isRte"]             = 0;
            $_POST["attach_post_key"]   = isset($request_params[5]) ? $request_params[5] : 0;
        }
        else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'delete_message':
        if ($params_num == 1 || $params_num == 2) {
            $msg_id = $request_params[0];
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_board_stat': break;
    case 'get_box':
        if ($params_num >= 1) {
           // $_GET['filter'] = 'privatepm'; //$request_params[0];
               $box_id = ($request_params[0] == '1') ? '1' : '2';
            $start_num = isset($request_params[1]) ? $request_params[1] : '0';
            $end_num = isset($request_params[2]) ? $request_params[2] : '19';
            if ($start_num > $end_num) {
                get_error('Line: '.__LINE__);
            } elseif ($end_num - $start_num >= 50) {
                $end_num = $start_num + 49;
            }
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_box_info': break;
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
    case 'get_message':
        if ($params_num >= 1 || $params_num <= 3) {
            $msg_id = $request_params[0];
            $return_html  = isset($request_params[2]) ? $request_params[2] : false;
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_new_topic':
        $start_num = isset($request_params[0]) ? $request_params[0] : '0';
        $end_num = isset($request_params[1]) ? $request_params[1] : '19';
        if ($start_num > $end_num) {
            get_error('Line: '.__LINE__);
        } elseif ($end_num - $start_num >= 50) {
            $end_num = $start_num + 49;
        }
        break;
    case 'get_latest_topic':
        $start_num = isset($request_params[0]) ? $request_params[0] : '0';
        $end_num = isset($request_params[1]) ? $request_params[1] : '19';
        if ($start_num > $end_num) {
            get_error('Line: '.__LINE__);
        } elseif ($end_num - $start_num >= 50) {
            $end_num = $start_num + 49;
        }
        
        
        // for 3.2.0
        $_GET["app"] = "core";
        $_GET["module"] = "search";
        $_GET["do"] = "viewNewContent";
        $_GET["search_app"] = "forums";
        $_GET["period"] = "month";
        list($_GET['st'], $search_per_page) = process_page($request_params[0], $request_params[1]);
        break;
    case 'get_online_users': break;
    case 'get_raw_post':
        if ($params_num == 1) {
            $_GET['p'] = $request_params[0];
            $_GET['do'] = 'editBoxShow';
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_subscribed_topic':
        $_GET["app"] = "core";
        $_GET["module"] = "search";
        $_GET["do"] = "followed";
        $_GET["search_app"] = "forums";
        $_GET["contentType"] = "topics";
        $_GET["contentType"] = "topics";
        list($_GET['st'], $search_per_page) = process_page($request_params[0], $request_params[1]);
        break;
    case 'get_thread':
        if ($params_num >= 1) {
            $topic_id = $request_params[0];
            $return_html  = isset($request_params[3]) ? $request_params[3] : false;
            
            if (preg_match('/^ann_/', $topic_id))
            {
                $_GET["announce_id"] = intval(str_replace('ann_', '', $topic_id));
            }
            else
            {
                // for 3.3.0
                $_GET["request_method"] = "get";
                $_SERVER['REQUEST_URI'] = "/topic/" .$topic_id . "-mobiquo/";
                
                $_GET["showtopic"]  = $topic_id;
                $_GET["app"]        = "forums";
                $_GET["module"]     = "forums";
                $_GET["section"]    = "topics";
                $_GET["t"]          = $topic_id;
                list($_GET['st'], $_GET['post_per_page']) = process_page($request_params[1], $request_params[2]);
            }

        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_thread_by_post':
        if ($params_num >= 1) {
            $_GET["request_method"] = "get";
            $_GET["app"]        = "forums";
            $_GET["module"]     = "forums";
            $_GET["section"]    = "topics";
            $_GET["view"]       = 'findpost';
            $_GET["p"]          = intval($request_params[0]);
            $_GET['post_per_page'] = isset($request_params[1]) ? intval($request_params[1]) : 20;
            $return_html = isset($request_params[2]) ? $request_params[2] : false;
            $function_file_name = 'get_thread';
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_thread_by_unread':
        if ($params_num >= 1) {
            $topic_id = $request_params[0];
            $posts_per_request  = isset($request_params[1]) ? intval($request_params[1]) : 20;
            $return_html  = isset($request_params[2]) ? $request_params[2] : false;
            $_GET["request_method"] = "get";
            $_SERVER['REQUEST_URI'] = "/topic/" .$topic_id . "-mobiquo/";
            //showtopic
            $_GET["showtopic"]  = $topic_id;
            $_GET["app"]        = "forums";
            $_GET["module"]     = "forums";
            $_GET["section"]    = "topics";
            $_GET["t"]          = $topic_id;
            $_GET["view"]       = 'getnewpost';
            
            $_POST['t']         = $topic_id;
            $_POST["view"]       = 'getnewpost';
            $_GET['post_per_page'] = $posts_per_request;
            $function_file_name = 'get_thread';
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_topic':
        if ($params_num >= 1) {
            $forum_id = $request_params[0];
            $start_num = isset($request_params[1]) ? $request_params[1] : '0';
            $end_num = isset($request_params[2]) ? $request_params[2] : '19';
            $mode = isset($request_params[3]) && $request_params[3] ? strtolower($request_params[3]) : 'normal';
            $_GET['f'] = $forum_id;
            $_GET['request_method'] = 'post';
            $_GET['showforum'] = $forum_id;
            $_GET["app"] = "forums";
            $_GET["module"] = "forums";
            $_GET["section"] = "forums";
            $_SERVER['REQUEST_URI'] = "/forum/". $forum_id ."-mobiquo";
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
        if ($params_num <= 2) {
            if (isset($request_params[1]) && !empty($request_params[1]))
                $_GET['id'] = intval($request_params[1]);
            elseif (isset($request_params[0]))
                $_GET['user_name']  = $request_params[0];
            
            $_GET["app"]        = "members";
            $_GET["module"]     = "profile";
            $_GET["section"]    = "view";
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_user_reply_post':
        $username = $request_params[0];
        $_POST["app"] = "core";
        $_POST["module"] = "search";
        $_POST["do"] = "user_reply";
        $_POST["user_name"] = $request_params[0];
        $_POST["mid"] = $request_params[1];
        $_POST["search_app"] = 'forums';
        $_GET["st"] = 0;
        $_GET["search_per_page"] = 50;
            
        // for 3.3.0
        $_GET['tab'] = 'forums:posts';
        break;
    case 'get_user_topic':
        $_POST["app"]= "core";
        $_POST["module"] ="search";
        $_POST["do"] = "user_topic";
        $_POST["user_name"] = $request_params[0];
        $_POST["mid"] = $request_params[1];
        $_POST["search_filter_app"] = array( "forums" =>"1" );
        $_POST["search_app"] = 'forums';
        $_POST["userMode"] = 'title';
        $_POST["view_by_title"] = "1";
        $_GET["st"] = 0;
        $_GET["search_per_page"] = 50;
        break;
    case 'get_unread_topic':
        $start_num = isset($request_params[0]) ? $request_params[0] : '0';
        $end_num = isset($request_params[1]) ? $request_params[1] : '19';
        if ($start_num > $end_num) {
            get_error('Line: '.__LINE__);
        } elseif ($end_num - $start_num >= 50) {
            $end_num = $start_num + 49;
        }
        $_GET["start_num"] = $start_num;
        $_GET["end_num"] = $end_num;
        $_GET["st"] = $start_num;
        $_GET["search_per_page"] = $end_num - $start_num + 1;
        $_GET["app"] = "core";
        $_GET["module"] = "search";
        $_GET["do"] = "new_posts";
        $_GET["search_filter_app"] = array ("forums" => "1");
        $_GET["search_app"] = 'forums';
        $_GET["period"] = 'unread';
        
        // for 3.2.0
        list($_GET['st'], $search_per_page) = process_page($request_params[0], $request_params[1]);
        
        break;
    case 'search_topic':
        $_GET["app"] = "core";
        $_GET["module"] = "search";
        $_GET["section"] = "search";
        $_GET["do"] = "quick_search";
        $_GET["search_app"] = 'forums';
        $_GET["fromsearch"] = "1";
        $_GET["search_filter_app"] = array("all" => "1");
        $_GET["search_author"]     = "";
        $_GET["search_sort_by"] = "0";
        $_GET["search_sort_order"] = "0";
        //$_GET["search_term"] = $request_params[0];
        $_GET["search_term"] = str_replace(' ', '+', $request_params[0]);
        $_GET["search_date_start"] = "";
        $_GET["search_date_end"] = "";
        $_GET["search_app_filters"] = array("forums"=> array("noPreview"=> "1","pCount"    => "", "pViews"=> ""));
        $_GET["submit"] = "Perform the search";

        $start_num = isset($request_params[1]) ? $request_params[1] : '0';
        $end_num = isset($request_params[2]) ? $request_params[2] : '19';
        if ($start_num > $end_num) {
            get_error('Out-of-range');
        } elseif ($end_num - $start_num >= 50) {
            $end_num = $start_num + 49;
        }

        $_GET["start_num"] = $start_num;
        $_GET["end_num"] = $end_num;
        $_GET["st"] = $start_num;
        $_GET["search_per_page"] = $end_num - $start_num + 1;
        
        // for 3.2.0
        list($_GET['st'], $search_per_page) = process_page($request_params[1], $request_params[2]);
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
    case 'logout_user': break;
    case 'reply_topic':
        if ($params_num >= 4) {
            $_POST["poll_question"]     = "";
            $_POST["ed-0_wysiwyg_used"] ="0";
            $_POST["editor_ids"]        = array(0 => "ed-0");
            $_POST["Post"]              = $request_params[2];
            $_POST["enableemo"]         = "yes";
            $_POST["enablesig"]         = "yes";
            $_POST["iconid"]            = "0";
            $_POST["st"]                = "0" ;
            $_POST["app"]               = "forums";
            $_POST["module"]            = "post";
            $_POST["section"]           = "post";
            $_POST["do"]                = "reply_post_do";
            $_POST["p"]                 = "0";
            $_POST["t"]                 = $request_params[0];
            //$_POST["f"]               = $request_params[4];
            $_POST["parent_id"]         = "0";
            //$_POST["attach_id"]         = $request_params[4];
            $_POST["removeattachid"]    = "0";
            $_POST["dosubmit"]          = "Add Reply";
            $_POST["request_method"]    = "post";
            $_POST["attach_post_key"]   = isset($request_params[5]) ? $request_params[5] : 0;
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'reply_post':
        if ($params_num >= 4) {
            $_POST["poll_question"]     = "";
            $_POST["ed-0_wysiwyg_used"] ="0";
            $_POST["editor_ids"]        = array(0 => "ed-0");
            $_POST["Post"]              = $request_params[3];
            $_POST["enableemo"]         = "yes";
            $_POST["enablesig"]         = "yes";
            $_POST["iconid"]            = "0";
            $_POST["st"]                = "0" ;
            $_POST["app"]               = "forums";
            $_POST["module"]            = "post";
            $_POST["section"]           = "post";
            $_POST["do"]                = "reply_post_do";
            $_POST["p"]                 = "0";
            $_POST["t"]                 = $request_params[1];
            $_POST["f"]                 = $request_params[0];
            $_POST["parent_id"]         = "0";
            //$_POST["attach_id"]         = $request_params[4][0];
            $_POST["removeattachid"]    = "0";
            $_POST["dosubmit"]          = "Add Reply";
            $_POST["request_method"]    = "post";
            $_POST["isRte"]             = 0;
            $_POST["attach_post_key"]   = isset($request_params[5]) ? $request_params[5] : 0;
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_quote_post':
        if ($params_num == 1) {
            $_GET["app"] = "forums";
            $_GET["module"] = "post";
            $_GET["section"]= "post";
            $_GET["do"] = "reply_post";
            $_GET["mqtids"] = $request_params[0];
            $_GET["request_method"] = "get";
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_quote_pm':
        if ($params_num == 1) {
            $_GET["app"] = "members";
            $_GET["module"] = "messaging";
            $_GET["section"]= "send";
            $_GET["do"] = "replyForm";
            $msg_id = $request_params[0];
            $_GET["request_method"] = "get";
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'save_raw_post':
        
        if ($params_num == 3) {
            $_GET['p'] = $request_params[0];
            $_POST['Post'] = $request_params[2];
            $_GET['Post'] = $request_params[2];
            $_GET['do'] = 'editBoxSave';
            $_POST['add_edit'] = '';
            $_POST['post_edit_reason'] = "";
            $_POST['post_htmlstatus'] = "";
            $_POST['_'] = "";
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'subscribe_topic':
        if ($params_num <= 2)
        {
            $_POST["app"] = "core";
            $_POST["module"] = "usercp";
            $_POST["tab"] = "forums";
            $_POST["area"] = "watch";
            $_POST["watch"] = "topic";
            $_POST["do"] = "saveWatch";
            $_POST["tid"] = $request_params[0];
            
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
            $_POST["app"] = "core";
            $_POST["module"] = "usercp";
            $_POST["tab"] = "forums";
            $_POST["area"] = "updateWatchTopics";
            $_POST["do"] = "saveIt";
            $_POST["topicIDs"] = array( $request_params[0] => 1);
            $_POST["trackchoice"] = "unsubscribe";
        } else {
            get_error('Line: '.__LINE__);
        }
        break;
    case 'get_participated_topic':
        $_GET["app"] = "core";
        $_GET["module"] = "search";
        $_GET["section"] = "search";
        $_GET["do"] = "quick_search";
        $_GET["search_app"] = "forums";
        $_GET["fromsearch"] = "1";
        $_GET["search_filter_app"] = array( "all" => "1" );
        $_GET["search_term"] = "";
        $_GET["search_sort_by"] = "0";
        $_GET["search_sort_order"] = "0";
        $_GET["search_author"] = $request_params[0];
        if(isset($request_params[4]))
        {
            $_GET['mid'] = $request_params[4];
        }
        $_GET["search_date_start"] = "";
        $_GET["search_date_end"] = "";
        $_GET["search_app_filters"] = array("forums"=> array("noPreview"=> "1", "pCount"    => "", "pViews"=> "", 'sortKey' => 'date', 'sortDir' => 'desc'));
        $_GET["submit"] = "Perform the search";
        
        // for 3.2.0
        $_GET["userMode"] = "all";
        
        $start_num = isset($request_params[1]) ? $request_params[1] : '0';
        $end_num = isset($request_params[2]) ? $request_params[2] : '19';
        if ($start_num > $end_num) {
            get_error('Out-of-range');
        } elseif ($end_num - $start_num >= 50) {
            $end_num = $start_num + 49;
        }

        $_GET["start_num"] = $start_num;
        $_GET["end_num"] = $end_num;
        
        // for 3.2.0
        list($_GET['st'], $search_per_page) = process_page($request_params[1], $request_params[2]);
        break;
   case 'report_post':
        $_GET["app"] = "core";
        $_GET["module"] = "reports";
        $_GET["rcom"] = "post";
        $_GET["message"] = isset($request_params[1]) && trim($request_params[1]) ? $request_params[1] : "Spam - Report from Tapatalk";
        $_GET["send"] = "1";
        $_GET["post_id"] = $request_params[0];
        break;
    case 'report_pm':
        $_GET["app"] = "core";
        $_GET["module"] = "reports";
        $_GET["rcom"] = "messages";
        $_GET["message"] = isset($request_params[1]) && trim($request_params[1]) ? $request_params[1] : "Spam - Report from Tapatalk";
        $_GET["send"] = "1";
        $_GET["msg"] = $request_params[0];
        $_GET["ctyp"] = 'message';
        $_GET["st"] = "0";
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
        $_GET['attach_rel_module'] = 'post';
        $_GET["attach_rel_id"] = "0";
        $_GET["attach_post_key"] = empty($_POST['group_id']) ? md5(microtime()) : $_POST['group_id'];
        $_GET['forum_id'] = $_POST['forum_id'];
        $_GET["fetch_all"] = "1";
        
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
        $_GET['attach_rel_module'] = 'post';
        $_GET["attach_rel_id"] = isset($request_params[3]) ? $request_params[3] : 0;
        $_GET["attach_post_key"] = $request_params[2];
        $_GET['forum_id'] = $request_params[1];
        $_GET['attach_id'] = $request_params[0];
        break;
    case 'get_alert':
        $_GET['perpage'] = isset($request_params[1]) && intval($request_params[1]) ? intval($request_params[1]) : 20;
        $_GET['st'] = isset($request_params[0]) && intval($request_params[0]) ? (intval($request_params[0]) - 1) * $_GET['perpage'] : 0;
        break;
    
    // conversation part
    case 'get_conversations':
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'inbox';
        $_GET['folderID'] = 'myconvo';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        break;
    case 'get_conversation':
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'showConversation';
        $_GET['topicID'] = $request_params[0];
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[1], $request_params[2]);
        $return_html  = isset($request_params[3]) ? $request_params[3] : false;
        break;
    case 'reply_conversation':
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
    case 'new_conversation':
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
    case 'invite_participant':
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'addParticipants';
        
        $_POST['inviteNames'] = $request_params[0];
        $_POST['topicID'] = $request_params[1];
        break;
    case 'get_quote_conversation':
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'send';
        $_GET['do'] = 'replyForm';
        $_GET['topicID'] = $request_params[0];
        $_GET['msgID'] = $request_params[1];
        break;
    case 'delete_conversation':
        $_GET['app'] = 'members';
        $_GET['module'] = 'messaging';
        $_GET['section'] = 'view';
        $_GET['do'] = 'deleteConversation';
        $_GET['topicID'] = $request_params[0];
        break;
    
    // moderation part
    case 'm_stick_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $_GET['do'] = $request_params[1] == 2 ? '16' : '15';
        $function_file_name = 'moderate';
        break;
    case 'm_close_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $_GET['do'] = $request_params[1] == 2 ? '00' : '01';
        $function_file_name = 'moderate';
        break;
    case 'm_delete_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $_GET['do'] = $request_params[1] == 2 ? '09' : '08';
        $function_file_name = 'moderate';
        break;
    case 'm_undelete_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $_GET['do'] = 'topic_restore';
        $function_file_name = 'moderate';
        break;
    case 'm_approve_topic':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['t'] = $request_params[0];
        $function_file_name = 'moderate';
        
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
        $_POST['selectedtids'] = array($request_params[0]);
        $_POST['df'] = $request_params[1];
        $function_file_name = 'moderate';
        break;
    case 'm_rename_topic':
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
        $function_file_name = 'moderate';
        break;
    case 'm_delete_post':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['p'] = $request_params[0];
        $_GET['pid'] = array($request_params[0]);
        $_GET['do'] = $request_params[1] == 2 ? 'p_hdelete' : '04';
        $function_file_name = 'moderate';
        break;
    case 'm_undelete_post':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['p'] = $request_params[0];
        $_GET['pid'] = array($request_params[0]);
        $_GET['do'] = 'p_hrestore';
        $function_file_name = 'moderate';
        break;
    case 'm_approve_post':
        $_GET['app'] = 'forums';
        $_GET['module'] = 'moderate';
        $_GET['section'] = 'moderate';
        $_GET['do'] = 'postchoice';
        $_GET['p'] = $request_params[0];
        $_GET['pid'] = $request_params[0];
        $_GET['selectedpids'] = array($request_params[0]);
        $function_file_name = 'moderate';
        
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
        $_POST['p'] = $request_params[0];
        $_POST['pid'] = array($request_params[0]);
        $_POST['topic_url'] = $request_params[1];
        $_POST['selectedpids'] = array($request_params[0]);
        $_POST['do'] = 'postchoice';
        $_POST['tact'] = 'move';
        $_POST['submit'] = 'Move Posts';
        $function_file_name = 'moderate';
        break;
    case 'm_mark_as_spam':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['member_id'] = $request_params[0];
        $_GET['do'] = 'setAsSpammer';
        $function_file_name = 'modcp';
        break;
    case 'm_ban_user':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['member_name'] = $request_params[0];
        $_GET['do'] = 'setAsSpammer';
        $function_file_name = 'modcp';
        break;
    case 'm_get_delete_topic':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['fromapp'] = 'forums';
        $_GET['tab'] = 'deletedtopics';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        $function_file_name = 'modcp';
        break;
    case 'm_get_delete_post':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['fromapp'] = 'forums';
        $_GET['tab'] = 'deletedposts';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        $function_file_name = 'modcp';
        break;
    case 'm_get_moderate_topic':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['fromapp'] = 'forums';
        $_GET['tab'] = 'unapprovedtopics';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        $function_file_name = 'modcp';
        break;
    case 'm_get_moderate_post':
        $_GET['app'] = 'core';
        $_GET['module'] = 'modcp';
        $_GET['fromapp'] = 'forums';
        $_GET['tab'] = 'unapprovedposts';
        list($_GET['st'], $_GET['perpage']) = process_page($request_params[0], $request_params[1]);
        $function_file_name = 'modcp';
        break;
    case 'search_post':
        $_GET["app"] = "core";
        $_GET["module"] = "search";
        $_GET["section"] = "search";
        $_GET["do"] = "quick_search";
        $_GET["search_app"] = 'forums';
        $_GET["fromsearch"] = "1";
        $_GET["search_filter_app"] = array( "all" => "1" );
        $_GET["search_author"] = "";
        $_GET["search_sort_by"] = "0";
        $_GET["search_sort_order"] = "0";
        //$_GET["search_term"] = $request_params[0];
        $_GET["search_term"] = str_replace(' ', '+', $request_params[0]);
        $_GET["search_date_start"] = "";
        $_GET["search_date_end"] = "";
        $_GET["search_app_filters"] = array("forums"=> array("noPreview"=> "1","pCount"    => "", "pViews"=> "", "contentOnly"=> "1"));
        $_GET["submit"] = "Perform the search";

        $start_num = isset($request_params[1]) ? $request_params[1] : '0';
        $end_num = isset($request_params[2]) ? $request_params[2] : '19';
        if ($start_num > $end_num) {
            get_error('Out-of-range');
        } elseif ($end_num - $start_num >= 50) {
            $end_num = $start_num + 49;
        }

        $_GET["start_num"] = $start_num;
        $_GET["end_num"]        = $end_num;
        $_GET["st"] = $start_num;
        $_GET["search_per_page"] = $end_num - $start_num + 1;
        
        // for 3.2.0
        list($_GET['st'], $search_per_page) = process_page($request_params[1], $request_params[2]);
        break;
    case 'search':
        
        $search_filter = $request_params[0];
        if(empty($search_filter['perpage']))
        {
            $search_filter['perpage'] = 19;
        }
        $_GET["app"] = "core";
        $_GET["module"] = "search";
        $_GET["do"] = "search";
        $_GET["andor_type"] = "or";
        $_GET["search_app"] = 'forums';
        $_GET['search_tags'] = '';
        $_GET['search_content'] = (isset($search_filter['titleonly']) && $search_filter['titleonly']) ? 'titles' : 'both';
        (isset($search_filter['titleonly']) && $search_filter['titleonly']) && $_POST['content_title_only'] = 1;
        $_GET["search_date_start"] = "";
        $_GET["search_date_end"] = "";
        if(empty($search_filter['page']) || ($search_filter['page'] == 1))
        {
            $_GET["fromsearch"] = "1";
            $_GET["section"] = "search";
            $_POST["submit"] = "Search Now";
            $_GET['search_app_filters']['core'] = array('sortKey' => 'date','sortDir' => 0);
            $_GET['search_app_filters']['members'] = array(
                    'searchInKey' => 'members',
                    'members' => Array
                        (
                            'sortKey' => 'date',
                            'sortDir' => 0
                        ),

                    'comments' => Array
                        (
                            'sortKey' => 'date',
                            'sortDir' => 0
                        ));
            
        }
        else 
        {
            isset($search_filter['searchid']) && $_GET['sid'] = $search_filter['searchid'];
            $_GET['st'] = !empty($search_filter['page']) ? ($search_filter['page']-1) * $search_filter['perpage'] : 0;
        }
        //isset($search_filter['keywords']) && $_GET['search_term'] = $search_filter['keywords'];
        isset($search_filter['keywords']) && $_GET['search_term'] = str_replace(' ', '+', $search_filter['keywords']);
        isset($search_filter['searchuser']) && $_GET['search_author'] = $search_filter['searchuser'];
        //isset($search_filter['userid']) && $_GET['uid'] = $search_filter['userid'];
        $_GET['search_app_filters']['forums'] = 
        array(
                'sortKey' => 'date',
                'noPreview' => (isset($search_filter['showposts']) && $search_filter['showposts']) ? 0 : 1,
                'pCount' => '',
                'pViews' => '',
                'sortDir' => 0 ,
                'contentOnly' => 0          
        );
        $_GET["search_filter_app"] = array( "all" => "1" );
        
        $start_num = !empty($search_filter['page']) ? ($search_filter['page']-1) * $search_filter['perpage'] : 0;
        $end_num = $start_num + $search_filter['perpage']-1;
        

        $_GET["start_num"] = $start_num;
        $_GET["end_num"]   = $end_num;
        $_GET['search_app_filters']['forums']['forums'] = array();
        if(!empty($search_filter['forumid']))
        {
            $_GET['search_app_filters']['forums']['forums'] = array($search_filter['forumid']);
        }
        if(!empty($search_filter['only_in']))
        {
            $_GET['search_app_filters']['forums']['forums'] = array_unique(array_merge($_GET['search_app_filters']['forums']['forums'], $search_filter['only_in']));
        }
        break;
    
}







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