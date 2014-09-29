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
    $result = follow_item('topics', false);
} else {
    require_once ('class/manual_class.php');
    
    $usercp_manualResolver = new mobi_public_core_usercp_manualResolver($registry);
    $usercp_manualResolver->makeRegistryShortcuts($registry);
    $result = $usercp_manualResolver->doExecute($registry);
}
