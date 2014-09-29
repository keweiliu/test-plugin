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

define('IN_MOBIQUO', true);
if (isset($_GET['welcome']))
{
    include('./smartbanner/app.php');
    exit;
}
define('MOBIQUO_DEBUG', 0);
if (isset($_SERVER['HTTP_APP_VAR'] ) && $_SERVER['HTTP_APP_VAR'])
    @header('App-Var: '.$_SERVER['HTTP_APP_VAR']);
if($_SERVER['REQUEST_METHOD'] == 'GET')
{
	include 'web.php';
}
define('CWD1', (($getcwd = getcwd()) ? $getcwd : '.'));
if(isset($_POST['session']) && isset($_POST['api_key']) && isset($_POST['subject']) && isset($_POST['body']) || isset($_POST['email_target']))
{
    include(CWD1."/functions/invitation.php");
}
if (function_exists('set_magic_quotes_runtime'))
    @set_magic_quotes_runtime(0);

include("./include/xmlrpc.inc");
include("./include/xmlrpcs.inc");
$_POST['xmlrpc'] = 'true';

if (isset($_SERVER['HTTP_DEBUG']) && $_SERVER['HTTP_DEBUG'] && file_exists(CWD1."/debug.on"))
{
    error_reporting(-1);
    @ini_set('display_errors', 1);
}
else
    error_reporting(0);

$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();
@ob_start();
require(CWD1."/include/common.php");
require(CWD1."/server_define.php");
require(CWD1.'/env_setting.php');
require(CWD1.'/xmlrpcresp.php');

define('SCRIPT_ROOT', get_root_dir());

chdir(SCRIPT_ROOT);

if(in_array($request_method, array('get_config', 'authorize_user', 'login', 'sign_in', 'register', 'prefetch_account')))
{
    define('THIS_SCRIPT', 'register');
    define('CSRF_PROTECTION', false);
    define('CSRF_SKIP_LIST', 'login');
}

if ($function_file_name && isset($server_param[$request_method]))
    require(CWD1.'/functions/'.$function_file_name.'.php');
else
    return_fault("Request function $request_method does not exist!");

if (strpos($request_method, 'm_') !== 0 || strpos($request_method, 'm_get') === 0)
{
    header('Mobiquo_is_login:'.(isset($vbulletin) && $vbulletin->userinfo['userid'] != 0 ? 'true' : 'false'));
}

if (!isset($tt_config))
{
    require_once(CWD1.'/config/config.php');
    $mobiquo_config = new mobiquo_config();
    $tt_config = $mobiquo_config->get_config();
}

// check if moderation function is allowed
if (strpos($request_method, 'm_') === 0 && !$tt_config['allow_moderate'])
    return_fault('Moderation action is not allowed on this forum!');

if (strpos($request_method, 'm_') === 0 && $vbulletin->userinfo['userid'] == 0)
    return_fault();

if($tt_config['guest_okay'] == 0 && $vbulletin->userinfo['userid'] == 0 && $request_method != 'get_config' && $request_method != 'login' && $request_method != 'register' && $request_method != 'sign_in' && $request_method != 'prefetch_account')
    return_fault();

if($tt_config['disable_search'] == 1){
    if($request_method == 'search_topic' or $request_method == 'search_post'){
        return_fault();
    }
}

if(!$tt_config['is_open'] && $request_method != 'logout_user' && $request_method != 'get_config')
    return_fault('Server is not available');

define('SHORTENQUOTE', $tt_config['shorten_quote']);

if(!empty($tt_config['hide_forum_id']))
{
    foreach($tt_config['hide_forum_id'] as $h_forumid) {
        $vbulletin->userinfo['forumpermissions'][$h_forumid] = 655374;
    }
}

$rpcServer = new xmlrpc_server($server_param, false);
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding ='UTF-8';
$rpcServer->service();

exit;

?>