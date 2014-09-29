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
if(file_exists(DIR. '/includes/functions_editor.php'.SUFFIX)){
	require_once(DIR. '/includes/functions_editor.php'.SUFFIX);
} else {
	require_once(DIR. '/includes/functions_editor.php');
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

function moderate_post_func($xmlrpc_params) {
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
		return approve_post_func($threadids);
	}
	if($params[1] == 2){
		return unapprove_post_func($threadids);
	}
}


function approve_post_func($postids){
	global $vbulletin,$db;
	// Validate posts
	$posts = $db->query_read_slave("
		SELECT post.postid, post.threadid, post.visible, post.title, post.userid, post.dateline,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible,
			thread.firstpostid,
			user.usergroupid, user.displaygroupid, user.membergroupids, user.posts, usertextfield.rank # for rank updates
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (post.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (post.userid = usertextfield.userid)
		WHERE postid IN ($postids)
			AND (post.visible = 0 OR (post.visible = 1 AND thread.visible = 0 AND post.postid = thread.firstpostid))
		ORDER BY postid
	");

	while ($post = $db->fetch_array($posts))
	{

		$forumperms = fetch_permissions($post['forumid']);
		if 	(
		!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
		OR
		!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
		OR
		(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
		)
		{
				
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		if (!can_moderate($post['forumid'], 'canmoderateposts'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}
		else if ($post['thread_visible'] == 2 AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;

		if ($post['firstpostid'] == $post['postid'])
		{	// approving a thread so need to update the $tinfo for any other posts in this thread
			$firstpost["$post[threadid]"] = true;
		}
		else if (!empty($firstpost["$post[threadid]"]))
		{
			$postarray["$post[postid]"]['thread_visible'] = 1;
		}
	}

	if (empty($postarray))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	foreach ($postarray AS $postid => $post)
	{
		$tinfo = array(
			'threadid'    => $post['threadid'],
			'forumid'     => $post['forumid'],
			'visible'     => $post['thread_visible'],
			'firstpostid' => $post['firstpostid']
		);

		$foruminfo = fetch_foruminfo($post['forumid']);
		approve_post($postid, $foruminfo['countposts'], true, $post, $tinfo, false);
	}

	foreach (array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}
	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');

	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(true,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('','base64')),"struct"));
}


function unapprove_post_func($postids){
	global $vbulletin,$db;
	$posts = $db->query_read_slave("
		SELECT post.postid, post.threadid, post.visible, post.title, post.userid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.visible AS thread_visible,
			thread.firstpostid,
			user.usergroupid, user.displaygroupid, user.membergroupids, user.posts, usertextfield.rank # for rank updates
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (post.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (post.userid = usertextfield.userid)
		WHERE postid IN ($postids)
			AND (post.visible > 0 OR (post.visible = 1 AND thread.visible > 0 AND post.postid = thread.firstpostid))
	");

	$firstpost = array();
	while ($post = $db->fetch_array($posts))
	{
		$forumperms = fetch_permissions($post['forumid']);
		if 	(
		!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
		OR
		!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
		OR
		(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $post['postuserid'] != $vbulletin->userinfo['userid'])
		)
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		if (!can_moderate($post['forumid'], 'canmoderateposts'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}
		else if (($post['visible'] == 2 OR $post['thread_visible'] == 2) AND !can_moderate($post['forumid'], 'candeleteposts'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		$postarray["$post[postid]"] = $post;
		$threadlist["$post[threadid]"] = true;
		$forumlist["$post[forumid]"] = true;
		if ($post['firstpostid'] == $post['postid'] AND $post['thread_visible'] == 1)
		{	// unapproving a thread so do not decremement the counters of any other posts in this thread
			$firstpost["$post[threadid]"] = true;
		}
		else if (!empty($firstpost["$post[threadid]"]))
		{
			$postarray["$post[postid]"]['skippostcount'] = true;
		}
	}

	if (empty($postarray))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	foreach ($postarray AS $postid => $post)
	{
		$foruminfo = fetch_foruminfo($post['forumid']);
		$tinfo = array(
			'threadid'    => $post['threadid'],
			'forumid'     => $post['forumid'],
			'visible'     => $post['thread_visible'],
			'firstpostid' => $post['firstpostid']
		);
		// Can't send $thread without considering that thread_visible may change if we approve the first post of a thread
		unapprove_post($postid, ($foruminfo['countposts'] AND !$post['skippostcount']), true, $post, $tinfo, false);
	}

	foreach (array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}

	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

	// empty cookie
	setcookie('vbulletin_inlinepost', '', TIMENOW - 3600, '/');


	// empty cookie
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(true,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('','base64')),"struct"));
}


?>
