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
require_once( 'class/mobi_messaging.php' );
require_once( 'class/mbqExtt_public_core_search_search.php' );

$mobi_messaging = new mobi_member_message($registry);
$newprvpm = $mobi_messaging->get_inbox_stat();
$ombqExtt_public_core_search_search = new mbqExtt_public_core_search_search($registry);
$ombqExtt_public_core_search_search->makeRegistryShortcuts($registry);
$ombqExtt_public_core_search_search->doExecute($registry);