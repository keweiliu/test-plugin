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

if (version_compare($app_version, '3.3.0', '>=')) {
    require_once ('class/mobi_member.php');
    
    ipsRegistry::$request['app'] = 'members';
    ipsRegistry::$request['section'] = 'load';
    ipsRegistry::$request['module'] = 'ajax';
    
    $mobi_member = new mobi_members_load($registry);
    $mobi_member->makeRegistryShortcuts($registry);
    $user_posts = $mobi_member->doExecute($registry);
}
else
{
    if (version_compare($app_version, '3.1.0', '>=')) {
        require_once ('class/mobi_search2.php');
    } else {
        require_once ('class/mobi_search.php');
    }
    
    $user_post = new mobi_search($registry);
    $user_post->makeRegistryShortcuts($registry);
    $user_posts = $user_post->doExecute($registry);
}