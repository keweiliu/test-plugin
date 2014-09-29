<?php
/**
 * Forum API functions
 *
 * @author Shitiz Garg
 * @copyright Copyright 2009 Quoord Systems Ltd. All Rights Reserved.
 * @license This file or any content of the file should not be
 * 			redistributed in any form of matter. This file is a part of
 * 			Tapatalk package and should not be used and distributed
 * 			in any form not approved by Quoord Systems Ltd.
 * 			http://tapatalk.com | http://taptatalk.com/license.html
 */

if (!defined('IN_MOBIQUO'))
	die('Hacking Attempt...');

function mob_get_participated_forum()
{
	global $mobdb, $context, $scripturl, $settings, $user_info;

	if ($user_info['is_guest'])
		mob_error('guests not allowed');

	$request = $mobdb->query('
		SELECT b.name, b.ID_BOARD AS id_board'. ($user_info['is_guest'] ? ", 1 AS is_read, 0 AS new_from" : ", (IFNULL(lb.ID_MSG, 0) >= b.ID_MSG_UPDATED) AS is_read, IFNULL(ln.sent, -1) AS is_notify") . '
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (m.ID_BOARD = b.ID_BOARD)' . (!$user_info['is_guest'] ? "
            LEFT JOIN {db_prefix}log_boards AS lb ON (lb.ID_BOARD = b.ID_BOARD AND lb.ID_MEMBER = {int:member})
            LEFT JOIN {db_prefix}log_notify AS ln ON (ln.ID_BOARD = b.ID_BOARD AND ln.ID_MEMBER = {int:member})" : '') . '
		WHERE m.ID_MEMBER = {int:member}
			AND {query_see_board}
		GROUP BY b.ID_BOARD
		ORDER BY m.posterTime DESC',
		array(
			'member' => $user_info['id'],
		)
	);
	$boards = array();
	while ($row = $mobdb->fetch_assoc($request))
		$boards[] = new xmlrpcval(array(
			'forum_id' => new xmlrpcval($row['id_board'], 'string'),
			'forum_name' => new xmlrpcval(processSubject($row['name']), 'base64'),
			'new_post' => new xmlrpcval($row['is_read'], 'boolean'),
			'icon_url' => new xmlrpcval(get_board_icon($row['id_board'], $row['is_read'], false), 'string'),
		), 'struct');
	$mobdb->free_result($request);

	return new xmlrpcresp(new xmlrpcval(array(
		'total_forums_num' => new xmlrpcval(count($boards), 'int'),
		'forums' => new xmlrpcval($boards, 'array'),
	), 'struct'));
}

function mob_get_id_by_url($rpcmsg)
{
	global $boardurl;

	$topic = '';
	$post = '';
	$board = '';

	$url = $rpcmsg->getScalarValParam(0);
	$url = parse_url($url);
	$host = parse_url($boardurl);

	// Make sure this belongs to the same site
	if ($url['host'] == $host['host'] && $host['path'] == substr($url['path'], 0, strlen($host['path'])))
	{
		// Parse the GET
		$get = parse_get($url['query']);
		$topic = isset($get['topic']) ? (strpos($get['topic'], '.') !== false ? substr($get['topic'], 0, strpos($get['topic'], '.')) : $get['topic']) : '';
		$board = isset($get['board']) ? (strpos($get['board'], '.') !== false ? substr($get['board'], 0, strpos($get['board'], '.')) : $get['board']) : '';
		$post = isset($get['msg']) ? $get['msg'] : (isset($get['topic']) && substr($get['topic'], strpos($get['topic'], '.') + 1, 3) == 'msg' ? substr($get['topic'], strpos($get['topic'], '.') + 4) : '');
	}

	return new xmlrpcresp(new xmlrpcval(array(
		'post_id' => new xmlrpcval($post, 'string'),
		'forum_id' => new xmlrpcval($board, 'string'),
		'topic_id' => new xmlrpcval($topic, 'string'),
	), 'struct'));
}