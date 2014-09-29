<?php
/***************************************************************
* mobiquo.php                                                  *
* Copyright 2009 Quoord Systems Ltd. All Rights Reserved.     *
* Created by Dragooon (http://smf-media.com)                   *
****************************************************************
* This file or any content of the file should not be           *
* redistributed in any form of matter. This file is a part of  *
* Tapatalk package and should not be used and distributed      *
* in any form not approved by Quoord Systems Ltd.              *
* http://tapatalk.com | http://taptatalk.com/license.html      *
****************************************************************
* Main file for the SMF package, controls...everything         *
***************************************************************/
define('IN_MOBIQUO', true);

if (isset($_GET['welcome']))
{
    include('./smartbanner/app.php');
    exit;
}

$forum_root = dirname(dirname(__FILE__));
$ssi_guest_access = true;

error_reporting(0);

require_once('./config/config.php');
$mobiquo_config = get_mobiquo_config();

if(isset($mobiquo_config['mod_function']) && !empty($mobiquo_config['mod_function'])) {
    foreach($mobiquo_config['mod_function'] as $mod_function) {
        if (!function_exists($mod_function)) {
            eval("function $mod_function(){}");
        }
    }
}

if (file_exists($forum_root . '/SSI.php'))
    require_once($forum_root . '/SSI.php');
else
    die('SSI.php not found, could not initialize');

writeLog();

// Call Susb-Mobiquo.php, we need this for major assitance
require_once('./Subs-Mobiquo.php');
require_once('./database.php');

$server_data = array();

$user_info['id'] = $ID_MEMBER;

require_once('./Mobiquo-Functions.php');
require_once('./Mobiquo-Templates.php');
require_once('./lib/xmlrpc.inc');
require_once('./lib/xmlrpcs.inc');
require_once('./method_define.php');
require_once('./api/forum.php');
require_once('./api/topic.php');
require_once('./api/subscription.php');
require_once('./api/search.php');
require_once('./api/user.php');
require_once('./api/post.php');
require_once('./api/moderation.php');

header('Mobiquo_is_login: ' . ($user_info['is_guest'] ? 'false' : 'true'));
header('Content-type: text/xml');

$rpcServer = new xmlrpc_server($methods, false);
$rpcServer->setDebug(1);
$rpcServer->compress_response = true;
$rpcServer->response_charset_encoding = 'UTF-8';
$pass = $rpcServer->service();
if ($pass !== false)
	exit;

// Load settings and the database
loadMobiquoSettings();

// Parse the request
parseMobRequest();

// Are we closed?
if (!empty($context['in_maintenance']) && !$user_info['is_admin'] && !in_array($context['mob_request']['method'], array('get_config', 'login')))
    createErrorResponse(5, ' due to maintenance');

// Invalid method?
if (!function_exists('method_' . $context['mob_request']['method']))
    createErrorResponse('unknown_method', ' : ' . $context['mob_request']['method'], 'xmlrpc');

if (isset($mobiquo_config['hide_forum_id']) && count($mobiquo_config['hide_forum_id']) && !$user_info['is_admin'])
{
    $user_info['query_see_board'] .= ' AND b.ID_BOARD NOT IN ('. implode(',', $mobiquo_config['hide_forum_id']) .') ';
}

@ob_end_clean();

// Allright, method passed...call it
call_user_func('method_' . $context['mob_request']['method']);

exit;
