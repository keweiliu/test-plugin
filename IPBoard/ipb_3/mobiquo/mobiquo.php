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
define('FORUM_ROOT', dirname(dirname(__FILE__)));
define('IPB_THIS_SCRIPT', 'public' );
define('IPS_ENFORCE_ACCESS', true);

@ob_start();

include('./lib/xmlrpc.inc');
include('./lib/xmlrpcs.inc');
$_SERVER['SCRIPT_NAME'] = str_replace(basename($_SERVER['SCRIPT_NAME']), 'index.php', $_SERVER['SCRIPT_NAME']);

if (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome/') === false) $_SERVER['HTTP_USER_AGENT'] .= ' tapatalk';

require_once( FORUM_ROOT.'/initdata.php');
error_reporting(0);
require('./config/config.php');
require('./server_define.php');
require('./env_setting.php');
######IPS#######################################
require_once( IPS_ROOT_PATH . 'sources/base/ipsRegistry.php' );
require_once( IPS_ROOT_PATH . 'sources/base/ipsController.php' );

$registry = ipsRegistry::instance();
$registry->init();
$charset = IPS_DOC_CHAR_SET;
$member = $registry->member()->fetchMemberData();

// add for google map image when posting
if (ipsRegistry::$settings['img_ext'])
    ipsRegistry::$settings['img_ext'] = ipsRegistry::$settings['img_ext'].',/maps/api/staticmap';

if (isset($search_per_page))
    ipsRegistry::$settings['search_per_page'] = $search_per_page;

merge_ipb_option($mobiquo_config);
$settings =& $registry->fetchSettings();
$board_url = $settings['board_url'];
$board_name = $settings['board_name'];
header('Mobiquo_is_login:'.($member['member_id'] ? 'true' : 'false'));
#################################################
require('./mobiquo_common.php');
require('./xmlrpcresp.php');
require('./MbqAppEnv.php');
MbqAppEnv::init();
$app_version = ipboard_version();
if(empty($request_name))
{
	require 'web.php';
	exit;
}
ipsRegistry::$settings['upload_url'] = url_encode(ipsRegistry::$settings['upload_url']);
if ($request_name && isset($server_param[$request_name])) {
    if (!in_array($request_name, array('get_config', 'login', 'authorize_user', 'logout_user'))) {
        if ($settings['board_offline'] == 1 and ($member['g_access_offline'] != 1)) {
            get_error('Board Offline!');
        }
        
        if ($settings['force_login'] == 1 and (!$member['member_id'])) {
            get_error('Forum force login!');
        }
    }
    require('./include/'.$function_file_name.'.inc.php');
}
error_reporting(0);
@ob_clean();
$rpcServer = new xmlrpc_server($server_param, false);
$rpcServer->setDebug(1);
$rpcServer->compress_response = true;
$rpcServer->response_charset_encoding = 'UTF-8';
$rpcServer->service(isset($server_data) ? $server_data : null);

exit;

?>