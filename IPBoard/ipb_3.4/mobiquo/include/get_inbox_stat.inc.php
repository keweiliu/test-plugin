<?php
/*======================================================================*\
|| #################################################################### ||
|| # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # This file is part of the Tapatalk package and should not be used # ||
|| # and distributed for any other purpose that is not approved by    # ||
|| # Quoord Systems Ltd.                                              # ||
|| # http://www.tapatalk.com | https://tapatalk.com/license.php       # ||
|| #################################################################### ||
\*======================================================================*/
defined('IN_MOBIQUO') or exit;
require_once( 'conversation.inc.php' );
require_once( 'class/mbqExtt_public_core_search_search.php' );

$conversation = new tapatalk_conversation($registry);
$conversation->makeRegistryShortcuts($registry);
$newprvpm = $conversation->doExecute($registry);

$ombqExtt_public_core_search_search = new mbqExtt_public_core_search_search($registry);
$ombqExtt_public_core_search_search->makeRegistryShortcuts($registry);
$ombqExtt_public_core_search_search->doExecute($registry);