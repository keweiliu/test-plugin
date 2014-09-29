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
define('IN_MOBIQUO', true);
define('IPS_PUBLIC_SCRIPT', 'index.php');
define('IPB_THIS_SCRIPT', 'public');
if (isset($_GET['welcome']))
{
    include('./smartbanner/app.php');
    exit;
}
@ob_start();

include('./lib/xmlrpc.inc');
include('./lib/xmlrpcs.inc');
require('./config/config.php');
require('./server_define.php');
require('./mobiquo_common.php');
require('./env_setting.php');
require('./xmlrpcresp.php');

######IPS#######################################
require_once( dirname(dirname(__FILE__)).'/initdata.php');
error_reporting(0);
require_once( IPS_ROOT_PATH . 'sources/base/ipsRegistry.php' );
require_once( IPS_ROOT_PATH . 'sources/base/ipsController.php' );

if ($tapatalk_handle == 'system')
{
    ipsController::run();
    
    if (!defined('MOBIQUO_HEAD_READY'))
    {
        $registry = ipsRegistry::instance();
        $member = $registry->member()->fetchMemberData();
        @header('Mobiquo_is_login:'.($member['member_id'] ? 'true' : 'false'));
    }
}
else
{
    $registry = ipsRegistry::instance();
    $registry->init();
    $member = $registry->member()->fetchMemberData();
    
    if (!defined('MOBIQUO_HEAD_READY'))
        @header('Mobiquo_is_login:'.($member['member_id'] ? 'true' : 'false'));
    
    // add for google map image when posting
    if (ipsRegistry::$settings['img_ext'])
        ipsRegistry::$settings['img_ext'] = ipsRegistry::$settings['img_ext'].',/maps/api/staticmap';
    
    if (isset($search_per_page))
        ipsRegistry::$settings['search_per_page'] = $search_per_page;
    
    $settings =& $registry->fetchSettings();
    $board_url = $settings['board_url'];
    merge_ipb_option($mobiquo_config);
    #################################################
    
    
    ipsRegistry::$settings['upload_url'] = url_encode(ipsRegistry::$settings['upload_url']);
    if ($request_name && isset($server_param[$request_name]))
    {
        if (file_exists('./include/'.$function_file_name.'.inc.php'))
        {
            require('./include/'.$function_file_name.'.inc.php');
            if ($tapatalk_handle)
            {
                $tapatalk_controller_name = 'tapatalk_'.$tapatalk_handle;
                $tapatalk_controller = new $tapatalk_controller_name($registry);
                $tapatalk_controller->makeRegistryShortcuts($registry);
                $tapatalk_controller->doExecute($registry);
            }
        }
    }
}

$rpcServer = new xmlrpc_server($server_param, false);
$rpcServer->setDebug(1);
$rpcServer->compress_response = true;
$rpcServer->response_charset_encoding = 'UTF-8';
$rpcServer->service(isset($server_data) ? $server_data : null);

exit;
