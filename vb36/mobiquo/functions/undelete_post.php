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

'deleteposts'  => array('threadadmin_deleteposts'),
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


// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################



// Wouldn't be fun if someone tried to manipulate every post in the database ;)
// Should be made into options I suppose - too many and you exceed what a cookie can hold anyway
$postlimit = 400;
$threadlimit = 200;

function undelete_post_func($xmlrpc_params) {
	global $vbulletin,$db;
	 

	global $xmlrpcerruser;
	$params = php_xmlrpc_decode($xmlrpc_params);
	$postids = $params[0];



	if (intval($postids) == 0)
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



	// Validate posts
	$posts = $db->query_read_slave("
		SELECT post.postid, post.threadid, post.parentid, post.visible, post.title, post.userid,
			thread.forumid, thread.title AS thread_title, thread.postuserid, thread.firstpostid, thread.visible AS thread_visible,
			forum.options AS forum_options
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum USING (forumid)
		WHERE postid IN ($postids)
			AND (post.visible = 2 OR (post.visible = 1 AND thread.visible = 2 AND post.postid = thread.firstpostid))
		ORDER BY postid
	");

	$deletethreads = array();

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

		if ((!$post['visible'] OR !$post['thread_visible']) AND !can_moderate($post['forumid'], 'canmoderateposts'))
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

		if ($post['firstpostid'] == $post['postid'])
		{	// undeleting a thread so need to update the $tinfo for any other posts in this thread
			$firstpost["$post[threadid]"] = true;
		}
		else if (!empty($firstpost["$post[threadid]"]))
		{
			$postarray["$post[postid]"]['thread_visible'] = 1;
		}
	}

	foreach ($postarray AS $postid => $post)
	{
		$tinfo = array(
			'threadid'    => $post['threadid'],
			'forumid'     => $post['forumid'],
			'visible'     => $post['thread_visible'],
			'firstpostid' => $post['firstpostid']
		);
		undelete_post($post['postid'], $post['forum_options'] & $vbulletin->bf_misc_forumoptions['countposts'], $post, $tinfo, false);
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
	}	return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(true,"boolean"),
						'is_login_mod' => new xmlrpcval(true,"boolean"),
						 'result_text' =>  new xmlrpcval('','base64')),"struct"));


}
?>
