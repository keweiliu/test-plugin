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
include("./include/xmlrpc.inc");
include("./include/xmlrpcs.inc");

define('CWD1', (($getcwd = getcwd()) ? $getcwd : '.'));

chdir("../");
error_reporting(0);
$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();
@ob_start();
require_once(CWD1."/include/common.php");
require_once(CWD1.'/include/vbulletin_common.php');

include(CWD1."/method_register.php");

define('SUFFIX', (($suffix = get_suffix()) ? $suffix : ''));
define('IN_MOBIQUO',true);

$request = $HTTP_RAW_POST_DATA ? $HTTP_RAW_POST_DATA : file_get_contents('php://input');
$parsersR = php_xmlrpc_decode_xml($request);

$requestMethod = $parsersR->methodname;
$requestParams = $parsersR->params;

if($requestMethod == 'get_config' || $requestMethod == 'authorize_user' || $requestMethod == 'login')
{
    define('THIS_SCRIPT', 'register');
    define('CSRF_PROTECTION', false);
    define('CSRF_SKIP_LIST', 'login');
}

if($requestMethod == 'authorize_user' or $requestMethod =='logout_user'){
    require_once(CWD1.'/functions/authorize_user.php');
}
if($requestMethod == 'login' or $requestMethod == "login_mod"){
    require_once(CWD1.'/functions/login.php');
}
if($requestMethod == 'get_topic'){
    require_once(CWD1.'/functions/get_topic.php');
}
if($requestMethod == 'get_thread' or $requestMethod == 'get_thread_by_post' or $requestMethod == 'get_thread_by_unread'){
    require_once(CWD1.'/functions/get_thread.php');
}
if($requestMethod == 'get_forum' or $requestMethod == 'get_forum_all'){
    require_once(CWD1.'/functions/get_forum.php');
}
if($requestMethod == 'get_user_topic' or $requestMethod == 'get_user_reply_post' ){
    require_once(CWD1.'/functions/get_user_topics.php');
}
if($requestMethod == 'get_participated_topic'){
    require_once(CWD1.'/functions/get_participated_topic.php');
}
if($requestMethod == 'get_user_info'){
    require_once(CWD1.'/functions/get_user_info.php');
}
if($requestMethod == 'get_new_topic'){
    require_once(CWD1.'/functions/get_new_topic.php');
}
if($requestMethod == 'get_latest_topic'){
    require_once(CWD1.'/functions/get_latest_topic.php');
}
if($requestMethod == 'get_config'){
    require_once(CWD1.'/functions/get_config.php');
}

require_once(CWD1.'/functions/return_fault.php');

if($requestMethod == 'create_topic'){
    require_once(CWD1.'/functions/create_topic.php');
}
if($requestMethod == 'new_topic'){
    require_once(CWD1.'/functions/new_topic.php');
}
if($requestMethod == 'reply_topic'){
    require_once(CWD1.'/functions/reply_topic.php');
}
if($requestMethod == 'reply_post'){
    require_once(CWD1.'/functions/reply_post.php');
}
if($requestMethod == 'get_board_stat'){
    require_once(CWD1.'/functions/get_board_stat.php');
}
if($requestMethod == 'get_subscribed_topic'){
    require_once(CWD1.'/functions/get_subscribed_topic.php');
}
if($requestMethod == 'get_subscribed_forum'){
    require_once(CWD1.'/functions/get_subscribed_forum.php');
}
if($requestMethod == 'get_box_info' or $requestMethod =='get_box'or $requestMethod =='get_message' or $requestMethod == 'delete_message'
or $requestMethod == 'create_message' or $requestMethod == 'mark_pm_unread' or $requestMethod == 'get_quote_pm' or $requestMethod == 'report_pm'){
    require_once(CWD1.'/functions/get_pm_stat.php');
}
if($requestMethod == 'get_inbox_stat'){
    require_once(CWD1.'/functions/get_inbox_stat.php');
}
if($requestMethod == 'subscribe_topic'){
    require_once(CWD1.'/functions/subscribe_topic.php');
}
if($requestMethod == 'subscribe_forum'){
    require_once(CWD1.'/functions/subscribe_forum.php');
}
if($requestMethod == 'unsubscribe_forum'){
    require_once(CWD1.'/functions/unsubscribe_forum.php');
}
if($requestMethod == 'unsubscribe_topic'){
    require_once(CWD1.'/functions/unsubscribe_topic.php');
}
if($requestMethod == 'get_online_users'){
    require_once(CWD1.'/functions/get_online_users.php');
}
if($requestMethod == 'push_notify'){
    require_once(CWD1.'/functions/push_notify.php');
}
if($requestMethod == 'save_raw_post'){
    require_once(CWD1.'/functions/save_raw_post.php');
}
if($requestMethod == 'get_raw_post'){
    require_once(CWD1.'/functions/get_raw_post.php');
}
if($requestMethod == 'attach_image' or $requestMethod =="remove_attach"  or $requestMethod =="remove_attachment"){
    require_once(CWD1.'/functions/attach_image.php');
}
if($requestMethod == 'search_topic' or $requestMethod == 'search_post'){
    require_once(CWD1.'/functions/search_topic.php');
}
if($requestMethod == 'mark_all_as_read'){
    require_once(CWD1.'/functions/mark_all_as_read.php');
}
if($requestMethod == 'get_unread_topic'){
    require_once(CWD1.'/functions/get_unread_topic.php');
}
if($requestMethod == 'get_quote_post'){
    require_once(CWD1.'/functions/get_quote_post.php');
}
if($requestMethod == 'report_post'){
    require_once(CWD1.'/functions/report_post.php');;
}
if($requestMethod == 'login_forum'){
    require_once(CWD1.'/functions/login_forum.php');
}
if($requestMethod == 'get_announcement'){
    require_once(CWD1.'/functions/get_announcement.php');
}
if($requestMethod == 'get_friend_list'){
    require_once(CWD1.'/functions/get_friend_list.php');
}
if($requestMethod == 'add_friend'){
    require_once(CWD1.'/functions/add_friend.php');
}
if($requestMethod == 'remove_friend'){
    require_once(CWD1.'/functions/remove_friend.php');
}
if($requestMethod == 'm_get_moderate_topic'){
    require_once(CWD1.'/functions/get_moderate_topic.php');
}
if($requestMethod == 'm_get_moderate_post'){
    require_once(CWD1.'/functions/get_moderate_post.php');
}
if($requestMethod == 'm_get_delete_topic'){
    require_once(CWD1.'/functions/get_delete_topic.php');
}
if($requestMethod == 'm_get_delete_post'){
    require_once(CWD1.'/functions/get_delete_post.php');
}
if($requestMethod == 'm_delete_topic'){
    require_once(CWD1.'/functions/delete_topic.php');
}
if($requestMethod == 'm_undelete_topic'){
    require_once(CWD1.'/functions/undelete_topic.php');
}
if($requestMethod == 'm_delete_post'){
    require_once(CWD1.'/functions/delete_post.php');
}
if($requestMethod == 'm_undelete_post'){
    require_once(CWD1.'/functions/undelete_post.php');
}
if($requestMethod == 'm_ban_user'){
    require_once(CWD1.'/functions/ban_user.php');
}
if($requestMethod == 'm_stick_topic'){
    require_once(CWD1.'/functions/stick_topic.php');
}
if($requestMethod == 'm_open_close_topic'){
    require_once(CWD1.'/functions/open_close_topic.php');
}
if($requestMethod == 'm_approve_topic'){
    require_once(CWD1.'/functions/moderate_topic.php');
}
if($requestMethod == 'm_approve_post'){
    require_once(CWD1.'/functions/moderate_post.php');
}
if($requestMethod == 'm_move_topic'){
    require_once(CWD1.'/functions/move_topic.php');
}
if($requestMethod == 'm_close_topic'){
    require_once(CWD1.'/functions/open_close_topic.php');
}

$rpcServer = new xmlrpc_server($methodContainer,false);
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding ='UTF-8';

if(!array_key_exists($requestMethod,$methodContainer)){
    $request =  gFaultXmlRequest(new xmlrpcval(5,'int'),new xmlrpcval('no matched method','string'));
    $response = $rpcServer->service($request);
    exit(0);
}

if(isset($vbulletin) && $vbulletin->userinfo['userid'] != 0){
    header('Mobiquo_is_login:true');
} else {
    header('Mobiquo_is_login:false');
}

require_once(CWD1.'/config/config.php');

$mobiquo_config = new mobiquo_config();
$config =$mobiquo_config->get_config();

if($requestMethod == 'm_get_moderate_topic'
OR $requestMethod == 'm_get_moderate_post'
OR $requestMethod == 'm_get_delete_topic'
OR $requestMethod == 'm_get_moderate_post'
OR $requestMethod == 'm_delete_topic'
OR $requestMethod == 'm_undelete_topic'
OR $requestMethod == 'm_delete_post'
OR $requestMethod == 'm_undelete_post'
OR $requestMethod == 'm_ban_user'
OR $requestMethod == 'm_stick_topic'
OR $requestMethod == 'm_open_close_topic'
OR $requestMethod == 'm_close_topic'
OR $requestMethod == 'm_moderate_topic'
OR $requestMethod == 'm_moderate_post'
OR $requestMethod == 'm_move_topic'){
    if($config['allow_moderate'] == 0){
        $request =  gFaultXmlRequest(new xmlrpcval(5,'int'),new xmlrpcval('no matched method','string'));
        $response = $rpcServer->service($request);
        exit(0);
    }
}

if(trim($config['hide_forum_id']) != ""){
    $hideForumList = explode(",", $config['hide_forum_id']);
    foreach($hideForumList as $forumid){
        $vbulletin->userinfo['forumpermissions'][$forumid] = 655374;
    }
}
if($config['guest_okay'] == 0 &&  $vbulletin->userinfo['userid'] == 0 && $requestMethod != 'get_config' && $requestMethod != 'authorize_user'   && $requestMethod != 'login'){
    $request =  gFaultXmlRequest(new xmlrpcval(20,'int'),new xmlrpcval('security error (user may not have permission to access this feature)','string'));
    $response = $rpcServer->service($request);
} else {
    if($config['shorten_quote'] == 1){
        define('SHORTENQUOTE', 1);
    }
    if($config['disable_search'] == 1){
        if($requestMethod == 'search_topic' or $requestMethod == 'search_post'){
            $request =  gFaultXmlRequest(new xmlrpcval(20,'int'),new xmlrpcval('security error (user may not have permission to access this feature)','string'));
            $response = $rpcServer->service($request);
        }
    }
    if($requestMethod == 'logout_user' || $requestMethod == 'get_config'){
        define('RPC_NOAUTH',true);
    }else{
        define('RPC_NOAUTH',false);
    }

    if(!$config['is_open'] && !RPC_NOAUTH){
        $request =  gFaultXmlRequest(new xmlrpcval(2,'int'),new xmlrpcval('server not available','string'));
        $response = $rpcServer->service($request);
    }else{
        $response = $rpcServer->service($request);
    }
}

exit;