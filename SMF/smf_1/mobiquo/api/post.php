<?php
/**
 * Posts API function
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

// Returns the thread
function mob_get_thread($rpcmsg)
{
	$start = $rpcmsg->getScalarValParam(1);
	$end = $rpcmsg->getScalarValParam(2);
	$count = $end - $start + 1;
	$GLOBALS['return_html'] = $rpcmsg->getParam(3) ? $rpcmsg->getScalarValParam(3) : false;

	return mob__get_thread($rpcmsg->getScalarValParam(0), null, $start, $count);
}

// Returns the thread from this post
function mob_get_thread_by_post($rpcmsg)
{
	$GLOBALS['return_html'] = $rpcmsg->getParam(2) ? $rpcmsg->getScalarValParam(2) : false;
	return mob__get_thread(null, $rpcmsg->getScalarValParam(0), null, null, $rpcmsg->getScalarValParam(1));
}

// Returns the thread starting from first unread post
function mob_get_thread_by_unread($rpcmsg)
{
	global $mobdb, $user_info;
	$GLOBALS['return_html'] = $rpcmsg->getParam(2) ? $rpcmsg->getScalarValParam(2) : false;

	// Return the thread
	return mob__get_thread($rpcmsg->getScalarValParam(0), null, null, null, $rpcmsg->getScalarValParam(1), true);
}

function mob__get_thread($_topic = null, $post = null, $start = 0, $limit = 20, $per_page = null, $from_new = false)
{
	global $mobdb, $context, $modSettings, $scripturl, $user_info, $memberContext, $user_profile, $board, $topic;

	// If we are not given the topic ID, we load the start, limit and the topic
	$topic = $_topic;
	$position = 0;
	if (is_null($topic))
	{
		if (is_null($post) || is_null($per_page))
			mob_error('invalid parameters');

		$limit = $per_page;

		// Get the topic
		$request = $mobdb->query('
			SELECT ID_TOPIC AS id_topic
			FROM {db_prefix}messages
			WHERE id_msg = {int:post}',
			array(
				'post' => $post,
			)
		);
		list ($topic) = $mobdb->fetch_row($request);
		$mobdb->free_result($request);

		// Get the start value
		$request = $mobdb->query('
			SELECT COUNT(*)
			FROM {db_prefix}messages
			WHERE id_msg < {int:msg}
				AND id_topic = {int:topic}',
			array(
				'topic' => $topic,
				'msg' => $post,
			)
		);
		list ($start) = $mobdb->fetch_row($request);
		$position = $start;
		$mobdb->free_result($request);
	}

	// Load the topic info
	$request = $mobdb->query('
		SELECT t.ID_TOPIC AS id_topic, t.ID_FIRST_MSG AS id_first_msg, t.ID_LAST_MSG AS id_last_msg, t.ID_MEMBER_STARTED AS id_member_started,
				' . ($user_info['is_guest'] ? '0' : 'ln.ID_TOPIC') . ' AS is_notify, t.locked, t.isSticky AS is_sticky, t.numReplies AS replies, t.numViews As views,
				' . ($user_info['is_guest'] ? 't.ID_LAST_MSG + 1' : 'IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1') . ' AS new_from,
				b.id_board, b.name, m.subject
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
			INNER JOIN {db_prefix}messages AS m ON (m.ID_MSG = t.ID_FIRST_MSG)' . ($user_info['is_guest'] ? '' : '
			LEFT JOIN {db_prefix}log_notify AS ln ON (ln.ID_TOPIC = t.ID_TOPIC AND ln.ID_MEMBER = {int:member})
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:topic} AND lt.id_member = {int:member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.ID_BOARD AND lmr.id_member = {int:member})') . '
		WHERE t.ID_TOPIC = {int:topic}
		LIMIT 1',
		array(
			'topic' => $topic,
			'member' => $user_info['id'],
		)
	);
	if ($mobdb->num_rows($request) == 0)
		mob_error('topic not found or out of reach');
	$topicinfo = $mobdb->fetch_assoc();
	$mobdb->free_result($request);

	if ($from_new)
	{
		// Get the start value
		$request = $mobdb->query('
			SELECT COUNT(*)
			FROM {db_prefix}messages
			WHERE id_msg < {int:msg}
			AND id_topic = {int:topic}',
			array(
				'topic' => $topic,
				'msg' => $topicinfo['new_from'],
			)
		);
		list ($start) = $mobdb->fetch_row($request);
		$mobdb->free_result($request);

		$position = $start;
		$limit = $per_page;
	}

	// Emulate the permissions
	$topic = $topicinfo['id_topic'];
	$board = $topicinfo['id_board'];
	loadBoard();
	loadPermissions();

	// Up the views!
	if (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] != $id_topic)
		$mobdb->query('
		    UPDATE {db_prefix}topics
		    SET numViews = numViews + 1
		    WHERE ID_TOPIC = {int:topic}',
		array(
		    'topic' => $topic,
		)
		);

	// If this user is not a guest, mark this topic as read
	if (!$user_info['is_guest'])
	{
		$mobdb->query('
		    REPLACE INTO {db_prefix}log_topics
		        (id_member, id_topic, id_msg)
		    VALUES
		        ({int:member}, {int:topic}, {int:msg})',
			array(
			    'member' => $user_info['id'],
			    'topic' => $topic,
			    'msg' => $modSettings['maxMsgID'],
			)
		);
	}

	// Set the last read topic
	$_SESSION['last_read_topic'] = $id_topic;

	// Fix the start
	$start = max(0, (int) $start - ((int) $start % (int) $limit));

	// Load posts
	$posts = array();
	$id_posts = array();
	$id_members = array();
	$request = $mobdb->query('
		SELECT m.ID_MSG AS id_msg, m.subject, m.body, m.ID_MEMBER AS id_member, m.smileysEnabled AS smileys_enabled,
				m.posterName AS poster_name, m.posterTime AS poster_time, ID_MSG_MODIFIED < {int:new_from} AS is_read
		FROM {db_prefix}messages AS m
		WHERE m.ID_TOPIC = {int:topic}
		ORDER BY m.ID_MSG ASC
		LIMIT {int:start}, {int:limit}',
		array(
			'topic' => $topic,
			'start' => $start,
			'limit' => $limit,
			'new_from' => $topicinfo['new_from'],
		)
	);
	while ($row = $mobdb->fetch_assoc($request))
	{
		$posts[] = $row;
		$id_posts[] = $row['id_msg'];
		$id_members[] = $row['id_member'];
	}
	$mobdb->free_result($request);

	// Load all the member data and context
	loadMemberData($id_members);

	// Load the attachments if we need to
	$attachments = array();
	if (!empty($modSettings['attachmentEnable']) && allowedTo('view_attachments'))
	{
		$request = $mobdb->query('
			SELECT a.ID_ATTACH as id_attach, a.filename, thumb.id_attach AS id_thumb, a.ID_MSG AS id_msg, a.width, a.height
			FROM {db_prefix}attachments AS a
				LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
			WHERE a.ID_MSG IN ({array_int:msg})
				AND a.attachmentType = 0',
			array(
				'msg' => $id_posts,
			)
		);
		while ($row = $mobdb->fetch_assoc($request))
		{
			if (empty($attachments[$row['id_msg']]))
				$attachments[$row['id_msg']] = array();

			$attachments[$row['id_msg']][] = new xmlrpcval(array(
				'content_type' => new xmlrpcval(!empty($row['width']) && !empty($row['height']) ? 'image' : 'other', 'string'),
				'thumbnail_url' => new xmlrpcval(!empty($row['id_thumb']) ? $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $row['id_thumb'] . ';image' : '', 'string'),
				'url' => new xmlrpcval($scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $row['id_attach'], 'string'),
			), 'struct');
		}
		$mobdb->free_result($request);
	}

	$topic_started = $topicinfo['id_member_started'] == $user_info['id'] && !$user_info['is_guest'];

	// Load the posts into a proper array
	foreach ($posts as $k => $post)
	{
		loadMemberContext($post['id_member']);
		$post_attachments = isset($attachments[$post['id_msg']]) ? $attachments[$post['id_msg']] : array();
		$member = !empty($memberContext[$post['id_member']]) ? $memberContext[$post['id_member']] : array();

		$posts[$k] = new xmlrpcval(array(
			'post_id'           => new xmlrpcval($post['id_msg'], 'string'),
			'post_title'        => new xmlrpcval(processSubject($post['subject']), 'base64'),
			'post_content'      => new xmlrpcval(processBody($post['body']), 'base64'),
			'post_author_id'    => new xmlrpcval(!empty($member) ? $member['id'] : 0, 'string'),
			'post_author_name'  => new xmlrpcval(processUsername(!empty($member) ? $member['name'] : $row['poster_name']), 'base64'),
			'is_online'         => new xmlrpcval(!empty($member) ? $user_profile[$post['id_member']]['isOnline'] : false, 'boolean'),
			'can_edit'          => new xmlrpcval((!$topicinfo['locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $topic_started) || (allowedTo('modify_own') && $post['id_member'] == $user_info['id'])), 'boolean'),
			'icon_url'          => new xmlrpcval($member['avatar']['href'], 'string'),
			'post_time'         => new xmlrpcval(mobiquo_time($post['poster_time']), 'dateTime.iso8601'),
			'allow_smileys'     => new xmlrpcval($post['smileys_enabled'], 'boolean'),
			'attachments'       => new xmlrpcval($post_attachments, 'array'),
			'can_delete'        => new xmlrpcval($post['id_msg'] != $topicinfo['id_first_msg'] && (allowedTo('delete_any') || (allowedTo('delete_replies') && $topic_started) || (allowedTo('delete_own') && $user_info['id'] == $post['id_member'])), 'boolean'),
			'can_approve'       => new xmlrpcval(false, 'boolean'),
			'can_stick'         => new xmlrpcval(allowedTo('make_sticky'), 'boolean'),
			'can_move'          => new xmlrpcval($topicinfo['id_first_msg'] != $post['id_msg'] && (allowedTo('move_any') || ($topic_started && allowedTo('move_own'))), 'boolean'), // We cannot split the first post
			'can_ban'           => new xmlrpcval(allowedTo('manage_bans'), 'boolean'),
		), 'struct');
	}

	// Return the topic
	return new xmlrpcresp(new xmlrpcval(array(
	   'total_post_num' => new xmlrpcval($topicinfo['replies'] + 1, 'int'),
		'forum_id'      => new xmlrpcval($topicinfo['id_board'], 'string'),
		'forum_name'    => new xmlrpcval(processSubject($topicinfo['name']), 'base64'),
		'topic_id'      => new xmlrpcval($topicinfo['id_topic'], 'string'),
		'topic_title'   => new xmlrpcval(processSubject($topicinfo['subject']), 'base64'),
        'view_number'   => new xmlrpcval($topicinfo['views'], 'int'),
		'is_subscribed' => new xmlrpcval($topicinfo['is_notify'], 'boolean'),
		'can_subscribe' => new xmlrpcval(allowedTo('mark_notify') && !$user_info['is_guest'], 'boolean'),
		'is_closed'     => new xmlrpcval($topicinfo['locked'], 'boolean'),
		'can_reply'     => new xmlrpcval(allowedTo('post_reply_any') || (allowedTo('post_reply_own') && $topic_started), 'boolean'),
		'can_delete'    => new xmlrpcval(allowedTo('remove_any') || ($topic_started && allowedTo('remove_own')), 'boolean'),
		'can_close'     => new xmlrpcval(allowedTo('lock_any') || ($topic_started && allowedTo('lock_own')), 'boolean'),
		'can_approve'   => new xmlrpcval(false, 'boolean'),
		'can_stick'     => new xmlrpcval(allowedTo('make_sticky'), 'boolean'),
		'can_move'      => new xmlrpcval(allowedTo('move_any') || ($topic_started && allowedTo('move_own')), 'boolean'),
		'can_rename'    => new xmlrpcval(allowedTo('modify_any') || ($topic_started && allowedTo('modify_own')), 'boolean'),
		'can_ban'       => new xmlrpcval(allowedTo('manage_bans'), 'boolean'),
		'position'      => new xmlrpcval($position, 'int'),
		'posts'         => new xmlrpcval($posts, 'array'),
	), 'struct'));
}