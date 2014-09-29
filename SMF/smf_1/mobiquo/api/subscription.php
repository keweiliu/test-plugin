<?php
/**
 * Subscription API functions
 *
 * @author Shitiz Garg
 * @copyright Copyright 2009 Quoord Systems Ltd. All Rights Reserved.
 * @license This file or any content of the file should not be
 * 			redistributed in any form of matter. This file is a part of
 * 			Tapatalk package and should not be used and distributed
 * 			in any form not approved by Quoord Systems Ltd.
 * 			http://tapatalk.com
 */

if (!defined('IN_MOBIQUO'))
	die('Hacking Attempt...');

function mob_get_subscribed_topic($rpcmsg)
{
	global $mobdb, $context, $scripturl, $settings;

	// Start and end...The usual business
	$start = $rpcmsg->getParam(0) ? $rpcmsg->getScalarValParam(0) : 0;
	$end = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : 9;
	
	list($start, $limit) = process_page($start, $end);

	// Get the subscribed topic ID's
	$topics = get_subscribed_tids();
	$count = count($topics);
	$topics = !empty($topics) ? get_topics('t.ID_TOPIC IN ({array_int:topics})', array('topics' => $topics), $start, $limit, false) : array();

	// Return the topics
	return new xmlrpcresp(new xmlrpcval(array(
		'total_topic_num' => new xmlrpcval($count, 'int'),
		'topics' => new xmlrpcval($topics, 'array'),
	), 'struct'));
}
