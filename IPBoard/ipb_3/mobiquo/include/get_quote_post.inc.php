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
$_COOKIE[ipsRegistry::$settings['cookie_id'].'mqtids'] = str_replace('-', ',', $_GET['mqtids']);
if($app_version >="3.4.0")
{
	require_once ('class/topics_ajax.php');
    $forum_post = new mobi_forums_ajax_topics($registry);
}
else 
{
	require_once ('class/post_class.php');
	$forum_post = new forum_post($registry);

}
$forum_post->makeRegistryShortcuts($registry);
$quote_post = $forum_post->doExecute($registry);
