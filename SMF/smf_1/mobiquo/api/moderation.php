<?php
/**
 * Moderation API function
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

function mob_m_stick_topic($rpcmsg)
{
	global $mobdb, $sourcedir, $user_info, $context, $topic, $board, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	checkSession('session');

	$topic = $rpcmsg->getScalarValParam(0);
	$mode = $rpcmsg->getScalarValParam(1) == 1 ? 1 : 0;

	// Load the topic info
	$topicinfo = get_topicinfo($topic);
	if (empty($topicinfo))
		mob_error('topic not found');
	$board = $topicinfo['id_board'];
	$topic = $topicinfo['id_topic'];
	loadBoard();
	loadPermissions();

	// Check the permissions
	if (!allowedTo('make_sticky'))
		mob_error('permission denied');

	// Set the sticky
	$mobdb->query('
		UPDATE {db_prefix}topics
		SET isSticky = {int:mode}
		WHERE id_topic = {int:topic}',
		array(
			'mode' => $mode,
			'topic' => $topic,
		)
	);

	logAction('sticky', array('topic' => $topic));
	sendNotifications($topic, 'sticky');

	return new xmlrpcresp(new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
	), 'struct'));
}


// Locks/Unlocks a topic
function mob_m_close_topic($rpcmsg)
{
	global $mobdb, $context, $user_info, $board, $topic, $sourcedir;

	checkSession('session');

	require_once($sourcedir . '/Subs-Post.php');

	$topic = $rpcmsg->getScalarValParam(0);
	$topicinfo = get_topicinfo($topic);
	if (empty($topicinfo))
		mob_error('topic not found');

	$topic = $topicinfo['id_topic'];
	$board = $topicinfo['id_board'];

	loadBoard();
	loadPermissions();

	if (!(allowedTo('lock_any') || ($topicinfo['id_member_started'] == $user_info['id'] && allowedTo('lock_own'))))
		mob_error('locking not allowed');

	$mode = $rpcmsg->getScalarValParam(1) == 1 ? 0 : (allowedTo('lock_any') ? 1 : 2);

	$mobdb->query('
		UPDATE {db_prefix}topics
		SET locked = {int:locked}
		WHERE id_topic = {int:topic}',
		array(
			'topic' => $topic,
			'locked' => $mode,
		)
	);

	logAction('lock', array('topic' => $topic));
	sendNotifications($topic, $mode != 0 ? 'lock' : 'unlock');

	return new xmlrpcresp(new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
	), 'struct'));
}

function mob_m_delete_topic($rpcmsg)
{
	global $mobdb, $context, $sourcedir, $topic, $board, $user_info;

	require_once($sourcedir . '/RemoveTopic.php');
	require_once($sourcedir . '/Subs-Post.php');

	$topicinfo = get_topicinfo($rpcmsg->getScalarValParam(0));
	if (empty($topicinfo))
		mob_error('topic not found');

	$topic = $topicinfo['id_topic'];
	$board = $topicinfo['id_board'];

	loadBoard();
	loadPermissions();

	// Check for permissions
	if (!(allowedTo('remove_any') || ($topicinfo['id_member_started'] == $user_info['id'] && allowedTo('remove_own'))))
		mob_error('cannot remove topic');

	// Remove the topic
	logAction('remove', array('topic' => $topic));
	sendNotifications($topic, 'remove');

	removeTopics(array($topic));

	return new xmlrpcresp(new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
	), 'struct'));
}

function mob_m_delete_post($rpcmsg)
{
	global $mobdb, $context, $sourcedir, $topic, $board, $user_info;

	require_once($sourcedir . '/RemoveTopic.php');
	require_once($sourcedir . '/Subs-Post.php');

	$postinfo = get_postinfo($rpcmsg->getScalarValParam(0));
	if (empty($postinfo))
		mob_error('post not found');

	$topic = $postinfo['id_topic'];
	$board = $postinfo['id_board'];

	loadBoard();
	loadPermissions();

	if (!(allowedTo('delete_any') || (allowedTo('delete_replies') && $postinfo['id_member_started'] == $user_info['id']) || (allowedTo('delete_own') && $postinfo['id_member'] == $user_info['id'])))
		mob_error('cannot delete post');

	// Remove the post
	logAction('delete', array('topic' => $topic, 'subject' => $postinfo['subject'], 'member' => $postinfo['id_member']));
	removeMessage($postinfo['id_msg']);

	return new xmlrpcresp(new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
	), 'struct'));
}

// Moves a topic
function mob_m_move_topic($rpcmsg)
{
	global $mobdb, $context, $sourcedir, $topic, $board, $user_info;

	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Boards.php');

	$topicinfo = get_topicinfo($rpcmsg->getScalarValParam(0));
	$newboard = get_boardinfo($rpcmsg->getScalarValParam(1));

	if (empty($topicinfo) || empty($newboard))
		mob_error('topic not found');

	$topic = $topicinfo['id_topic'];
	$board = $topicinfo['id_board'];

	loadBoard();
	loadPermissions();

	if (!(allowedTo('move_any') || (allowedTo('move_own') && $topicinfo['id_member_started'] == $user_info['id'])) || $board == $newboard['id_board'])
		mob_error('cannot move topic');

	// Send the notifications
	logAction('move', array('topic' => $topicinfo['id_topic'], 'board_from' => $board, 'board_to' => $newboard['id_board']));
	sendNotifications($topic, 'move');

	move_topic($topic, $board, $newboard, $topicinfo);

	// Return the true response
	return new xmlrpcresp(new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
	), 'struct'));
}

// Changes the topic's subject
function mob_m_rename_topic($rpcmsg)
{
	global $topic, $board, $mobdb, $user_info, $func, $sourcedir;

	$topicinfo = get_topicinfo($rpcmsg->getScalarValParam(0));
	$subject = strtr($func['htmlspecialchars']($rpcmsg->getScalarValParam(1)), array("\r" => '', "\n" => '', "\t" => ''));

	if (empty($topicinfo))
		mob_error('topic not found');
	if (trim($subject) == '')
		mob_error('Invalid subject');

	require_once($sourcedir . '/Subs-Post.php');

	$topic = $topicinfo['id_topic'];
	$board = $topicinfo['id_board'];

	loadBoard();
	loadPermissions();

	// Check for permissions
	if (!((!$topicinfo['locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_own') && $topicinfo['id_member_started'] == $user_info['id']))))
		mob_error('cannot rename topic');

	$mobdb->query('
		UPDATE {db_prefix}messages
		SET subject = {string:subject}
		WHERE ID_MSG = {int:msg}',
		array(
			'msg' => $topicinfo['id_first_msg'],
			'subject' => $subject,
		)
	);
	updateStats('subject', $topic, $subject);

	return new xmlrpcresp(new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
	), 'struct'));
}

// Moves a post, splits it etc
function mob_m_move_post($rpcmsg)
{
	global $board, $sc, $topic, $mobdb, $user_info, $context, $sourcedir, $func;

	checkSession('session');

	require_once($sourcedir . '/SplitTopics.php');
	require_once($sourcedir . '/Subs-Boards.php');
	require_once($sourcedir . '/Subs-Post.php');

	$postinfo = get_postinfo($rpcmsg->getScalarValParam(0));
	$topicinfo = get_topicinfo($postinfo['id_topic']);
	$topic = $postinfo['id_topic'];
	$board = $postinfo['id_board'];

	loadBoard();
	loadPermissions();

	// Make sure this is not the first post
	if ($postinfo['id_msg'] == $topicinfo['id_first_msg'])
		mob_error('cannot move first post of the topic');

	// Are we moving the post to a new topic?
	if (!is_null($rpcmsg->getScalarValParam(1)) && $rpcmsg->getScalarValParam(1) != '')
	{
		// We need to have move_any for this
		if (!allowedTo('move_any'))
			mob_error('cannot move post to a topic');

		$topicinfo = get_topicinfo($rpcmsg->getScalarValParam(1));
		if (empty($topicinfo))
			mob_error('topic not found');

		// Split the post into a new one first
		$new_topic = splitTopic($postinfo['id_topic'], array($postinfo['id_msg']), $postinfo['subject']);
		if (empty($new_topic))
			mob_error('something bad happened');

		// Merge the topic into the existing one
		do_merge(array($topicinfo['id_topic'], $new_topic));
	}
	// Or we split the post into an absolutely new topic
	else
	{
		$topicinfo = get_topicinfo($topic);

		// We can have move_own for this
		if (!(allowedTo('move_any') || (allowedTo('move_own') && $user_info['id'] == $topicinfo['id_member_started'])))
			mob_error('cannot move post to a new topic');

		$subject = strtr($func['htmlspecialchars']($rpcmsg->getScalarValParam(2)), array("\r" => '', "\n" => '', "\t" => ''));
		$new_board = $rpcmsg->getParam(3) ? get_boardinfo($rpcmsg->getScalarValParam(3)) : get_boardinfo($postinfo['id_board']);

		if (trim($subject) == '' || empty($new_board))
			mob_error('subject or board invalid');

		// Split the topic
		$new_topic = splitTopic($postinfo['id_topic'], array($postinfo['id_msg']), $subject);
		if (empty($new_topic))
			mob_error('something bad happened');

		if ($board != $new_board['id_board'])
			move_topic($new_topic, $board, $new_board, $topicinfo);
	}

	// Return a true response
	return new xmlrpcresp(new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
	), 'struct'));
}

// Merges two topics
function mob_m_merge_topic($rpcmsg)
{
	global $mobdb, $func, $board, $topic, $context;

	// Get the topics
	$topic_1 = $rpcmsg->getScalarValParam(1);
	$topic_2 = $rpcmsg->getScalarValParam(0);
	if ($topic_1 == $topic_2)
		mob_error('same topic');

	$topicinfo_1 = get_topicinfo($topic_1);
	$topicinfo_2 = get_topicinfo($topic_2);
	if (empty($topicinfo_1) || empty($topicinfo_2))
		mob_error('topics not found');

	$topic = $topic_1;
	$board = $topicinfo_1['id_board'];

	loadBoard();
	loadPermissions();

	 // do_merge will check for our permissions
	do_merge(array($topic_1, $topic_2));

	// Return a true response
	return new xmlrpcresp(new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
	), 'struct'));
}

// Bans a user
function mob_m_ban_user($rpcmsg)
{
	global $mobdb, $context, $func, $user_info, $modSettings, $user_info, $sourcedir;

	checkSession('session');

	// Cannot ban an user?
	if (!allowedTo('manage_bans'))
		mob_error('cannot ban users');

	$reason = strtr($func['htmlspecialchars']($rpcmsg->getParam(2) ? $rpcmsg->getScalarValParam(2) : ''), array("\r" => '', "\n" => '', "\t" => ''));
	$username = $rpcmsg->getScalarValParam(0);

	require_once($sourcedir . '/Subs-Auth.php');

	// If we have an user ID, use it otherwise search for the user
	if (!is_null($id_user))
	{
		$request = $mobdb->query('
			SELECT ID_MEMBER
			FROM {db_prefix}members
			WHERE ID_MEMBER = {int:member}',
			array(
				'member' => $id_user,
			)
		);
		if ($mobdb->num_rows($request) == 0)
			$id_user = null;
		else
			list ($id_user) = $mobdb->fetch_row($request);
		$mobdb->free_result($request);
	}

	// Otherwise search from the DB,
	if (is_null($id_user))
	{
	    $username = utf8ToAscii($username);
		$members = findMembers($username);
		if (empty($members))
			mob_error('user not found');
		$member_ids = array_keys($members);
		$id_user = $members[$member_ids[0]]['id'];
	}
	$member = $id_user;

	// Create the ban
	$mobdb->query('
		INSERT INTO {db_prefix}ban_groups
			(name, ban_time, cannot_access, expire_time, reason)
		VALUES
			({string:name}, {int:time}, 1, NULL, {string:reason})',
		array(
			'time' => time(),
			'name' => 'Tapatalk ban (' . $username . ')',
			'reason' => $reason,
		)
	);
	$id_ban_group = $mobdb->insert_id();

	// Insert the user into the ban
	$mobdb->query('
		INSERT INTO {db_prefix}ban_items
			(ID_BAN_GROUP, ID_MEMBER)
		VALUES
			({int:group}, {int:member})',
		array(
			'group' => $id_ban_group,
			'member' => $member,
		)
	);

	// Do we have to delete every post made by this user?
	// !!! Optimize this
	if ($rpcmsg->getScalarValParam(1) == 2)
	{
		require_once($sourcedir . '/RemoveTopic.php');

		@ignore_user_abort();
		@set_time_limit(0);

		$request = $mobdb->query('
			SELECT m.ID_MSG AS id_msg
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}topics AS t ON (t.ID_TOPIC = m.ID_TOPIC)
			WHERE m.ID_MEMBER = {int:member}
				AND (t.ID_FIRST_MSG != m.ID_MSG OR t.numReplies = 0)',
			array(
				'member' => $member,
			)
		);
		while ($row = $mobdb->fetch_assoc($request))
			removeMessage($row['id_msg']);
		$mobdb->free_result($request);
	}

	// Return a true response
	return new xmlrpcresp(new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
	), 'struct'));
}