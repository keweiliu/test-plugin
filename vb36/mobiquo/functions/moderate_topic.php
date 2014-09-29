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
defined('CWD1') or exit;


define('THIS_SCRIPT', 'inlinemod');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('banning', 'threadmanage', 'posting', 'inlinemod');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'THREADADMIN',
	'threadadmin_authenticate'
);

// pre-cache templates used by specific actions
$actiontemplates = array(

	'deletethread' => array('threadadmin_deletethreads'),
);
$actiontemplates['mergethreadcompat'] =& $actiontemplates['mergethread'];

// ####################### PRE-BACK-END ACTIONS ##########################

if(file_exists('./global.php'.SUFFIX)){
	require_once('./global.php'.SUFFIX);
} else {
	require_once('./global.php');
}
if(file_exists(DIR.'/includes/functions_editor.php'.SUFFIX)){
	require_once(DIR.'/includes/functions_editor.php'.SUFFIX);
} else {
	require_once(DIR.'/includes/functions_editor.php');
}
if(file_exists(DIR.'/includes/functions_threadmanage.php'.SUFFIX)){
	require_once(DIR.'/includes/functions_threadmanage.php'.SUFFIX);
} else {
	require_once(DIR.'/includes/functions_threadmanage.php');
}
if(file_exists(DIR.'/includes/functions_databuild.php'.SUFFIX)){
	require_once(DIR.'/includes/functions_databuild.php'.SUFFIX);
} else {
	require_once(DIR.'/includes/functions_databuild.php');
}
if(file_exists(DIR.'/includes/functions_log_error.php'.SUFFIX)){
	require_once(DIR.'/includes/functions_log_error.php'.SUFFIX);
} else {
	require_once(DIR.'/includes/functions_log_error.php');
}
if(file_exists(DIR.'/includes/modfunctions.php'.SUFFIX)){
	require_once(DIR.'/includes/modfunctions.php'.SUFFIX);
} else {
	require_once(DIR.'/includes/modfunctions.php');
}



// Wouldn't be fun if someone tried to manipulate every post in the database ;)
// Should be made into options I suppose - too many and you exceed what a cookie can hold anyway
$postlimit = 400;
$threadlimit = 200;

function moderate_topic_func($xmlrpc_params) {
	global $vbulletin,$db;
	 


	$threadarray = array();
	$postarray = array();
	$postinfos = array();
	$forumlist = array();
	$threadlist = array();
	global $xmlrpcerruser;
	$params = php_xmlrpc_decode($xmlrpc_params);

	$threadids =   $params[0];
	 
	if (intval($threadids) == 0)
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	if (!can_moderate())
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	if($params[1] == 1){
		return approve_thread_func($threadids);
	}
	if($params[1] == 2){
		return unapprove_thread_func($threadids);
	}
}


function approve_thread_func($threadids){
	global $vbulletin,$db;
	$countingthreads = array();
	$firstposts = array();
	// Validate threads

	$threads = $db->query_read_slave("
		SELECT threadid, visible, forumid, postuserid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN($threadids)
			AND visible = 0
			AND open <> 10
	");
	while ($thread = $db->fetch_array($threads))
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
		!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
		OR
		!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
		OR
		(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
		)
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}


		if (!can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"] = true;
		$firstposts[] = $thread['firstpostid'];

		$foruminfo = fetch_foruminfo($thread['forumid']);
		if ($foruminfo['countposts'])
		{	// this thread is in a counting forum
			$countingthreads[] = $thread['threadid'];
		}
	}

	if (empty($threadarray))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	// Set threads visible
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "thread
		SET visible = 1
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
	");

	if (!empty($countingthreads))
	{	// Update post count for visible posts
		$userbyuserid = array();
		$posts = $db->query_read_slave("
			SELECT userid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid IN(" . implode(',', $countingthreads) . ")
				AND visible = 1
				AND userid > 0
		");
		while ($post = $db->fetch_array($posts))
		{
			if (!isset($userbyuserid["$post[userid]"]))
			{
				$userbyuserid["$post[userid]"] = 1;
			}
			else
			{
				$userbyuserid["$post[userid]"]++;
			}
		}

		if (!empty($userbyuserid))
		{
			$userbypostcount = array();
			$alluserids = '';

			foreach ($userbyuserid AS $postuserid => $postcount)
			{
				$alluserids .= ",$postuserid";
				$userbypostcount["$postcount"] .= ",$postuserid";
			}
			foreach($userbypostcount AS $postcount => $userids)
			{
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount\n";
			}

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET posts = posts +
				CASE
				$casesql
					ELSE 0
				END
				WHERE userid IN (0$alluserids)
			");
		}
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "moderation
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
			AND type = 'thread'
	");


	// Set thread redirects visible
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "thread
		SET visible = 1
		WHERE open = 10 AND pollid IN(" . implode(',', array_keys($threadarray)) . ")
	");

	foreach ($threadarray AS $threadid => $thread)
	{
		$modlog[] = array(
			'userid'   =>& $vbulletin->userinfo['userid'],
			'forumid'  =>& $thread['forumid'],
			'threadid' => $threadid,
		);
	}

	log_moderator_action($modlog, 'approved_thread');

	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');

	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(true,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('','base64')),"struct"));
}


function unapprove_thread_func($threadids){
	global $vbulletin,$db;
	$threadarray = array();
	$countingthreads = array();
	$modrecords = array();

	// Validate threads
	$threads = $db->query_read_slave("
		SELECT threadid, visible, forumid, title, postuserid, firstpostid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN($threadids)
			AND visible > 0
			AND open <> 10
	");
	while ($thread = $db->fetch_array($threads))
	{

		$forumperms = fetch_permissions($thread['forumid']);
		if 	(
		!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
		OR
		!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
		OR
		(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $thread['postuserid'] != $vbulletin->userinfo['userid'])
		)
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}


		if (!can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		$threadarray["$thread[threadid]"] = $thread;
		$forumlist["$thread[forumid]"] = true;

		$foruminfo = fetch_foruminfo($thread['forumid']);
		if ($thread['visible'] AND $foruminfo['countposts'])
		{	// this thread is visible AND in a counting forum
			$countingthreads[] = $thread['threadid'];
		}

		$modrecords[] = "($thread[threadid], $thread[firstpostid], 'thread', " . TIMENOW . ")";
	}

	if (empty($threadarray))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	// Set threads hidden
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "thread
		SET visible = 0
		WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
	");


	if (!empty($countingthreads))
	{	// Update post count for visible posts
		$userbyuserid = array();
		$posts = $db->query_read_slave("
			SELECT userid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid IN(" . implode(',', $countingthreads) . ")
				AND visible = 1
				AND userid > 0
		");
		while ($post = $db->fetch_array($posts))
		{
			if (!isset($userbyuserid["$post[userid]"]))
			{
				$userbyuserid["$post[userid]"] = -1;
			}
			else
			{
				$userbyuserid["$post[userid]"]--;
			}
		}

		if (!empty($userbyuserid))
		{
			$userbypostcount = array();
			$alluserids = '';

			foreach ($userbyuserid AS $postuserid => $postcount)
			{
				$alluserids .= ",$postuserid";
				$userbypostcount["$postcount"] .= ",$postuserid";
			}
			foreach($userbypostcount AS $postcount => $userids)
			{
				$casesql .= " WHEN userid IN (0$userids) THEN $postcount\n";
			}

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET posts = CAST(posts AS SIGNED) +
				CASE
				$casesql
					ELSE 0
				END
				WHERE userid IN (0$alluserids)
			");
		}
	}
	 
	// Insert Moderation Records
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "moderation
		(threadid, postid, type, dateline)
		VALUES
		" . implode(',', $modrecords) . "
	");

	// Clean out deletionlog
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "deletionlog
		WHERE primaryid IN(" . implode(',', array_keys($threadarray)) . ")
			AND type = 'thread'
	");

	foreach ($threadarray AS $threadid => $thread)
	{
		$modlog[] = array(
			'userid'   =>& $vbulletin->userinfo['userid'],
			'forumid'  =>& $thread['forumid'],
			'threadid' => $threadid,
		);
	}

	log_moderator_action($modlog, 'unapproved_thread');

	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinethread', '', TIMENOW - 3600, '/');
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(true,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('','base64')),"struct"));

}


?>
