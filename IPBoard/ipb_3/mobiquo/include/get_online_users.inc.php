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
    require_once ('class/mbqExtt_public_members_online_online.php');
    $mbqExtt_public_members_online_online = new mbqExtt_public_members_online_online($registry);
    $mbqExtt_public_members_online_online->makeRegistryShortcuts($registry);
    $online_users = $mbqExtt_public_members_online_online->doExecute($registry);
}
else
{
    require_once ('class/board_stat.php');
    $mobi_board_stat = new mobi_board_stat($registry);
    $mobi_board_stat->makeRegistryShortcuts($registry);
    $online_users = $mobi_board_stat->doExecute($registry, 'online_users');
}
