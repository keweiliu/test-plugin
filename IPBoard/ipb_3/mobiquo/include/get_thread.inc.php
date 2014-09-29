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
if (isset($_GET["announce_id"]))
{
    require_once ('class/announcements.php');
    $topic_thread = new mobi_announcements($registry);
    $topic_thread->makeRegistryShortcuts($registry);
    $topic_thread = $topic_thread->doExecute($registry);
}
else if (version_compare($app_version, '3.3.0', '>='))
{
    require_once ('class/get_thread_class_3_3.php');
    $topic_thread = new topic_thread($registry);
    $topic_thread->makeRegistryShortcuts($registry);
    $topic_thread = $topic_thread->doExecute($registry);
}
else if (version_compare($app_version, '3.2.0', '>='))
{
    require_once ('class/get_thread_class_3_2.php');
    $topic_thread = new topic_thread($registry);
    $topic_thread->makeRegistryShortcuts($registry);
    $topic_thread = $topic_thread->doExecute($registry);
}
else
{
    require_once ('class/get_thread_class.php');
    $topic_thread = new topic_thread($registry);
    $topic_thread->makeRegistryShortcuts($registry);
    $topic_thread = $topic_thread->doExecute($registry);
}
