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

if (version_compare($app_version, '3.2.0', '>='))
{
    require_once ('class/mobi_search3.php');
    
    $mobi_search = new mobi_search($registry);
    $mobi_search->makeRegistryShortcuts($registry);
    $topics = $mobi_search->doExecute($registry);
} else {
    require_once ('class/forum_class.php');

    $forum = new forums_class($registry);
    $topics = $forum->hooks_watchedItems($registry);
}
