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
define('NOROBOT', TRUE);
define('CURSCRIPT', 'logging');
require_once ('class/authorize_user_class.php');

$authorize_user =  new authorize_user($registry);
$result = $authorize_user->doExecute($registry);

if ($result[0]) {
	$login_result = true;
	$result_text = $result[0];
} else {
	$login_result = false;
	$result_text = $result[2];
}
