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

function stick_topic_func($xmlrpc_params) {
	global $vbulletin,$db;
	 

	global $xmlrpcerruser;
	$params = php_xmlrpc_decode($xmlrpc_params);

	$threadids = intval($params[0]);
	 
	if (empty($threadids))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(false,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	if (!can_moderate())
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(false,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	$redirect = array();

	// Validate threads
	$threads = $db->query_read_slave("
		SELECT threadid, open, visible, forumid, postuserid, title
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN ($threadids)
			AND sticky = " . ($params[1] == 1  ? 0 : 1) . "
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
										'is_login_mod' => new xmlrpcval(false,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		if (!can_moderate($thread['forumid'], 'canmanagethreads'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(false,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(false,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}
		else if ($thread['visible'] == 2 AND !can_moderate($thread['forumid'], 'candeleteposts'))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(false,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		$threadarray["$thread[threadid]"] = $thread;
		if ($thread['open'] == 10)
		{
			$redirect[] = $thread['threadid'];
		}
	}

	if (!empty($threadarray))
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "thread
			SET sticky = " . ($params[1] == 1 ? 1 : 0) . "
			WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
		");

		foreach (array_keys($threadarray) AS $threadid)
		{
			if (!in_array($threadid, $redirect))
			{	// Don't add log entry for (un)sticking a redirect
				$modlog[] = array(
					'userid'   =>& $vbulletin->userinfo['userid'],
					'forumid'  =>& $threadarray["$threadid"]['forumid'],
					'threadid' => $threadid,
				);
			}
		}

		log_moderator_action($modlog, ($params[1] == 1) ? 'stuck_thread' : 'unstuck_thread');
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
