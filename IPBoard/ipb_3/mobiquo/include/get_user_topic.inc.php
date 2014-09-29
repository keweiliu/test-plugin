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

if (version_compare($app_version, '3.1.0', '>=')) {
    require_once ('class/mobi_search2.php');
} else {
    require_once ('class/mobi_search.php');
}

$user_topic = new mobi_search($registry);
$user_topic->makeRegistryShortcuts($registry);
$user_topics = $user_topic->doExecute($registry);
