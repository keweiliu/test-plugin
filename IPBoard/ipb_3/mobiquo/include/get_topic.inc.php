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
    require_once ('class/get_topic_class_3_3.php');
    $get_topics = new forum_topic($registry);
    $get_topics->makeRegistryShortcuts($registry);
    $topics = $get_topics->doExecute($registry);
}
else
{
    require_once ('class/get_topic_class.php');
    $get_topics = new forum_topic($registry);
    $get_topics->makeRegistryShortcuts($registry);
    $topics = $get_topics->doExecute($registry, $start_num, $end_num, $mode);
}