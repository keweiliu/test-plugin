<?php

if(!defined('IN_MOBIQUO')) exit;

$latest_version_link = @file_get_contents('http://api.tapatalk.com/v.php?sys=ip30&link');
if(fileperms('upload.php') < 755)
{
	$upload_acc = 'Inaccessible';
}
else 
{
	$upload_acc = 'OK';
}
if(fileperms('push.php') < 755)
{
	$push_acc = 'Inaccessible';
}
else 
{
	$push_acc = 'OK';
}
echo '<span><b>Forum XMLRPC Interface for Tapatalk Application</b><br /><br />';
echo 'Current Tapatalk plugin version: '.substr($mobiquo_config['version'], 5).'<br />';
echo 'Latest Tapatalk plugin version:<u>'.$latest_version_link.'</u><br />';
echo 'Attachment upload interface status: <a href="upload.php"><u>'.$upload_acc.'</u></a><br />';
echo 'Push notification interface status: <u><a href="push.php">'.$push_acc.'</u></a><br />';
echo '<br /><a href="https://tapatalk.com/api.php" target="_blank">Tapatalk API for Universal Forum Access</a><br />
    For more details, please visit <a href="https://tapatalk.com" target="_blank">https://tapatalk.com</a>';