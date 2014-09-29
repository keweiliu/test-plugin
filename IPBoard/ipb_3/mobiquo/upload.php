<?php
if($_SERVER['REQUEST_METHOD'] == 'GET')
{
	echo '<b>Attachment Upload Interface for Tapatalk Application</b><br/><br/>';
	echo '<br/><br/><a href="https://tapatalk.com/api.php" target="_blank">Tapatalk API for Universal Forum Access</a><br>
    For more details, please visit <a href="https://tapatalk.com" target="_blank">https://tapatalk.com</a>';
	exit;
}
include('./mobiquo.php');
