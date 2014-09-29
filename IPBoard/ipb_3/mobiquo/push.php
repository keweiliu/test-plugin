<?php
define('IN_MOBIQUO', true);
define('FORUM_ROOT', dirname(dirname(__FILE__)));
require_once( FORUM_ROOT.'/initdata.php');
error_reporting(E_ALL & ~E_NOTICE);
######IPS#######################################
require_once( IPS_ROOT_PATH . 'sources/base/ipsRegistry.php' );
require_once( IPS_ROOT_PATH . 'sources/base/ipsController.php' );
include './lib/class_push.php';
$registry = ipsRegistry::instance();
$registry->init();
$charset = IPS_DOC_CHAR_SET;
$settings =& $registry->fetchSettings();
$board_url = $settings['board_url'];
$DB = $registry->DB();
$table_exist = $DB->checkForTable('tapatalk_users');

$server_ip = tapatalk_push::do_post_request(array('ip' => 1));
if(!empty($settings['tapatalk_push_key']))
{
	$return_status = tapatalk_push::do_post_request(array('key' => $settings['tapatalk_push_key'],'test' => 1));
}
else 
{
	$return_status = 'Please set Tapatalk API Key in forum setting.';
}
echo '<b>Tapatalk Push Notification Status Monitor</b><br/>';
echo '<br/>Push notification test: ' . (($return_status === '1') ? '<b>Success</b>' : '<font color="red"><b>Failed ('.$return_status.')</b></font>');
echo '<br/>Current server IP: ' . $server_ip;
echo '<br/>Current forum url: ' . $board_url;
echo '<br/>Tapatalk user table existence: ' . ($table_exist ? 'Yes' : 'No');
echo '<br/><br/><a href="https://tapatalk.com/api.php" target="_blank">Tapatalk API for Universal Forum Access</a><br>
    For more details, please visit <a href="https://tapatalk.com" target="_blank">https://tapatalk.com</a>';