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
require_once ( IPS_ROOT_PATH . 'applications/forums/modules_public/forums/boards.php');

class mobi_forums_boards extends public_forums_forums_boards
{
	public function doExecute( ipsRegistry $registry )
	{
		$active = $this->getActiveUserDetails();

		if ( ! is_array( $this->caches['stats'] ) )
		{
			$this->cache->setCache( 'stats', array(), array( 'array' => 1 ) );
		}
		
		$stats = $this->caches['stats'];
		$stats['total_posts'] = $stats['total_replies'] + $stats['total_topics'];
		
		return array_merge($active, $stats);
	}
}

$mobi_board_stat = new mobi_forums_boards($registry);
$mobi_board_stat->makeRegistryShortcuts($registry);
$board_stat = $mobi_board_stat->doExecute($registry);