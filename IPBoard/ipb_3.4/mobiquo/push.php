<?php

define('IN_MOBIQUO', true);
require_once(dirname(dirname(__FILE__)).'/initdata.php');

######IPS#######################################
require_once( IPS_ROOT_PATH . 'sources/base/ipsRegistry.php' );
require_once( IPS_ROOT_PATH . 'sources/base/ipsController.php' );
include './lib/class_push.php';
$registry = ipsRegistry::instance();
$registry->init();
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

$push_slug = tapatalk_push::load_push_slug();
if (!empty($push_slug) && is_array($push_slug));
{
    echo '<br/>Push Slug Status : ' . ($push_slug[5] == 1 ? 'Stick' : 'Free');
}

echo '<br/><br/><a href="https://tapatalk.com/api.php" target="_blank">Tapatalk API for Universal Forum Access</a><br>
    For more details, please visit <a href="https://tapatalk.com" target="_blank">https://tapatalk.com</a>';