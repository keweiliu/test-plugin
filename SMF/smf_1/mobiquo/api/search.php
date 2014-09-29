<?php
/**
 * API Search functions
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

function mob_search_topic($rpcmsg, $subject_only = false)
{
	global $mobdb, $context, $sourcedir, $user_info, $modSettings, $scripturl, $modSettings, $messages_request;

	// Guest?
	if ($user_info['is_guest'])
		mob_error('guests not allowed');

	$string = $rpcmsg->getScalarValParam(0);

	$start = $rpcmsg->getScalarValParam(1);
	$end = $rpcmsg->getScalarValParam(2);
	$count = $end - $start > 50 ? 50 : $end - $start + 1;

	// We got an ID?
	if ($rpcmsg->getParam(3))
		$id_search = $rpcmsg->getScalarValParam(3);

	// Is it an existing search?
	$new_search = !isset($id_search) || empty($_SESSION['search_cache'][$id_search]);

	if (!$new_search)
		$_SESSION['search_cache'] = $_SESSION['search_cache'][$id_search];

	// We use a cheap hack to perform our search
	$_REQUEST['start'] = $_GET['start'] = isset($start_num) ? $start_num : 0;
	$modSettings['search_results_per_page'] = isset($limit) ? $limit : 20;
	$_REQUEST['search'] = $_POST['search'] = $string;
	$_REQUEST['advanced'] = $_POST['advanced'] = 0;
	$_REQUEST['subject_only'] = $_POST['subject_only'] = $subject_only;
	require_once($sourcedir . '/Search.php');
	PlushSearch2();

	// We got results?
	if (!isset($_SESSION['search_cache']))
		mob_error('search not successful');

	$count = $_SESSION['search_cache']['num_results'];
	$search_id = $_SESSION['search_cache']['ID_SEARCH'];

	// Cache it
	if (isset($id_search))
	{
		$search_cache = $_SESSION['search_cache'];
		unset($_SESSION['search_cache']);
		$_SESSION['search_cache'][$id_search] = $search_cache;
		unset ($search_cache);
	}

	// Get the results
	$topics = array();
	$tids = array();
	while ($topic = $context['get_topics']())
	{
		$match = $topic['matches'][0];

		$topics[$topic['id']] = $topic;
		$topics[$topic['id']]['first_post'] = $match;
		$topics[$topic['id']]['last_post'] = $match;
		$tids[] = $topic['id'];
	}

	if (!empty($tids))
	{
		// Check for notifications on this topic OR board.
		$mobdb->query("
		    SELECT sent, ID_TOPIC
            FROM {db_prefix}log_notify
            WHERE ID_TOPIC IN ({array_int:topic_ids})
                AND ID_MEMBER = {int:member}",
		array(
		    'topic_ids' => $tids,
		    'member' => $user_info['id']
		)
		);

		while ($row = $mobdb->fetch_assoc())
		{
			// Find if this topic is marked for notification...
			if (!empty($row['ID_TOPIC']))
				$topics[$row['ID_TOPIC']]['is_notify'] = true;
		}
		$mobdb->free_result();
	}

	foreach ($topics as $k => $topic)
		$topics[$k]['subject'] = $topic['first_post']['subject'];

	// Output the results
	return new xmlrpcresp(new xmlrpcval(array(
		'total_topic_num' => new xmlrpcval($count, 'int'),
		'search_id' => new xmlrpcval($search_id, 'string'),
		'topics' => new xmlrpcval(get_topics_xmlrpc($topics), 'array'),
	), 'struct'));
}

// Search the posts
function mob_search_post($rpcmsg)
{
	return mob_search_topic($rpcmsg, true);
}