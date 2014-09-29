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

function move_topic_func($xmlrpc_params) {
	global $vbulletin,$db;
	 

	global $xmlrpcerruser;
	$params = php_xmlrpc_decode($xmlrpc_params);

	$threadids =   intval($params[0]);
	$destforum_id =   intval($params[1]);
	if (empty($threadids))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	if(empty($destforum_id)){
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

	$redirect = array();

	$threads = $db->query_read_slave("
		SELECT threadid, open, visible, forumid, title, postuserid
		FROM " . TABLE_PREFIX . "thread
		WHERE threadid IN ($threadids)
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


		if ($thread['open'] == 10 AND !can_moderate($thread['forumid'], 'canmanagethreads'))
		{
			// No permission to remove redirects.
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}
		else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
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
		$forumlist["$thread[forumid]"]++;
	}

	if (empty($threadarray))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	$threadcount = count($threadarray);
	$forumcount = count($forumlist);


	// check whether dest can contain posts
	$destforumid = mobiquo_verify_id('forum', $destforum_id);

	$destforuminfo = fetch_foruminfo($destforumid);
	if (!$destforuminfo['cancontainthreads'] OR $destforuminfo['link'])
	{

		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	// check destination forum permissions
	$forumperms = fetch_permissions($destforuminfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	if ($vbulletin->GPC['redirect'] == 'none')
	{
		$method = 'move';
	}
	else
	{
		$method = 'movered';
	}

	$countingthreads = array();
	$redirectids = array();

	// Validate threads

	$thread = $threadarray[$threadids];

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

	$thread['prefix_plain_html'] = ($thread['prefixid'] ? htmlspecialchars_uni($vbphrase["prefix_$thread[prefixid]_title_plain"]) . ' ' : '');

	if (!can_moderate($thread['forumid'], 'canmanagethreads'))
	{
			
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}
	else if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
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

	if ($thread['visible'] == 2 AND !can_moderate($destforuminfo['forumid'], 'candeleteposts'))
	{
			
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}
	else if (!$thread['visible'] AND !can_moderate($destforuminfo['forumid'], 'canmoderateposts'))
	{
			
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	// Ignore all threads that are already in the destination forum
	if ($thread['forumid'] == $destforuminfo['forumid'])
	{
		$sameforum = true;
			
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	$forumlist["$thread[forumid]"] = true;

	if ($thread['open'] == 10)
	{
		$redirectids["$thread[pollid]"][] = $thread['threadid'];
	}
	else if ($thread['visible'])
	{
		$countingthreads[] = $thread['threadid'];
	}


	if (empty($threadarray))
	{
		if ($sameforum)
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}
		else
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}
	}

	// check to see if these threads are being returned to a forum they've already been in
	// if redirects exist in the destination forum, remove them
	$checkprevious = $db->query_read_slave("
		SELECT threadid
		FROM " . TABLE_PREFIX . "thread
		WHERE forumid = $destforuminfo[forumid]
			AND open = 10
			AND pollid IN(" . implode(',', array_keys($threadarray)) . ")
	");
	while ($check = $db->fetch_array($checkprevious))
	{
		$old_redirect =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$old_redirect->set_existing($check);
		$old_redirect->delete(false, true, NULL, false);
		unset($old_redirect);
	}

	// check to see if a redirect is being moved to a forum where its destination thread already exists
	// if so delete the redirect
	if (!empty($redirectids))
	{
		$checkprevious = $db->query_read_slave("
			SELECT threadid
			FROM " . TABLE_PREFIX . "thread
			WHERE forumid = $destforuminfo[forumid]
				AND threadid IN(" . implode(',', array_keys($redirectids)) . ")

		");
		while ($check = $db->fetch_array($checkprevious))
		{
			if (!empty($redirectids["$check[threadid]"]))
			{
				foreach($redirectids["$check[threadid]"] AS $threadid)
				{
					$old_redirect =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
					$old_redirect->set_existing($threadarray["$threadid"]);
					$old_redirect->delete(false, true, NULL, false);
					unset($old_redirect);

					# Remove redirect threadids from $threadarray so no log entry is entered below or new redirect is added
					unset($threadarray["$threadid"]);
				}
			}
		}
	}

	if (!empty($threadarray))
	{
		// Move threads
		// If mod can not manage threads in destination forum then unstick all moved threads
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "thread
			SET forumid = $destforuminfo[forumid]
			" . (!can_moderate($destforuminfo['forumid'], 'canmanagethreads') ? ", sticky = 0" : "") . "
			WHERE threadid IN(" . implode(',', array_keys($threadarray)) . ")
		");

		// update canview status of thread subscriptions
		update_subscriptions(array('threadids' => array_keys($threadarray)));

		// kill the post cache for these threads
		delete_post_cache_threads(array_keys($threadarray));

		$movelog = array();
		// Insert Redirects FUN FUN FUN
		if ($method == 'movered')
		{
			$redirectsql = array();
			if ($vbulletin->GPC['redirect'] == 'expires')
			{
				switch($vbulletin->GPC['frame'])
				{
					case 'h':
						$expires = mktime(date('H') + $vbulletin->GPC['period'], date('i'), date('s'), date('m'), date('d'), date('y'));
						break;
					case 'd':
						$expires = mktime(date('H'), date('i'), date('s'), date('m'), date('d') + $vbulletin->GPC['period'], date('y'));
						break;
					case 'w':
						$expires = $vbulletin->GPC['period'] * 60 * 60 * 24 * 7 + TIMENOW;
						break;
					case 'y':
						$expires =  mktime(date('H'), date('i'), date('s'), date('m'), date('d'), date('y') + $vbulletin->GPC['period']);
						break;
					case 'm':
					default:
						$expires =  mktime(date('H'), date('i'), date('s'), date('m') + $vbulletin->GPC['period'], date('d'), date('y'));
				}
			}
			foreach($threadarray AS $threadid => $thread)
			{
				if ($thread['visible'] == 1)
				{
					$thread['open'] = 10;
					$thread['pollid'] = $threadid;
					$thread['dateline'] = TIMENOW;
					unset($thread['threadid']);
					$redir =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
					foreach (array_keys($thread) AS $field)
					{
						// bypassing the verify_* calls; this data should be valid as is
						$redir->setr($field, $thread["$field"], true, false);
					}
					$redirthreadid = $redir->save();
					if ($vbulletin->GPC['redirect'] == 'expires')
					{
						$redirectsql[] = "$redirthreadid, $expires";
					}
					unset($redir);
				}
				else
				{
					// else this is a moderated or deleted thread so leave no redirect behind
					// insert modlog entry of just "move", not "moved with redirect"
					// unset threadarray[threadid] so thread_moved_with_redirect log entry is not entered below.

					unset($threadarray["$threadid"]);
					$movelog = array(
						'userid'   =>& $vbulletin->userinfo['userid'],
						'forumid'  =>& $thread['forumid'],
						'threadid' => $threadid,
					);
				}
			}

			if (!empty($redirectsql))
			{
				$db->query_write("
					INSERT INTO " . TABLE_PREFIX . "threadredirect
						(threadid, expires)
					VALUES
						(" . implode("), (", $redirectsql) . ")
				");
			}
		}

		if (!empty($movelog))
		{
			log_moderator_action($movelog, 'thread_moved_to_x', $destforuminfo['title']);
		}

		if (!empty($threadarray))
		{
			foreach ($threadarray AS $threadid => $thread)
			{
				$modlog[] = array(
					'userid'   =>& $vbulletin->userinfo['userid'],
					'forumid'  =>& $thread['forumid'],
					'threadid' => $threadid,
				);
			}

			log_moderator_action($modlog, ($method == 'move') ? 'thread_moved_to_x' : 'thread_moved_with_redirect_to_a', $destforuminfo['title']);

			if (!empty($countingthreads))
			{
				$posts = $db->query_read_slave("
					SELECT userid, threadid
					FROM " . TABLE_PREFIX . "post
					WHERE threadid IN(" . implode(',', $countingthreads) . ")
						AND visible = 1
						AND	userid > 0
				");
				$userbyuserid = array();
				while ($post = $db->fetch_array($posts))
				{
					$foruminfo = fetch_foruminfo($threadarray["$post[threadid]"]['forumid']);
					if ($foruminfo['countposts'] AND !$destforuminfo['countposts'])
					{	// Take away a post
						if (!isset($userbyuserid["$post[userid]"]))
						{
							$userbyuserid["$post[userid]"] = -1;
						}
						else
						{
							$userbyuserid["$post[userid]"]--;
						}
					}
					else if (!$foruminfo['countposts'] AND $destforuminfo['countposts'])
					{	// Add a post
						if (!isset($userbyuserid["$post[userid]"]))
						{
							$userbyuserid["$post[userid]"] = 1;
						}
						else
						{
							$userbyuserid["$post[userid]"]++;
						}
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
					foreach ($userbypostcount AS $postcount => $userids)
					{
						$casesql .= " WHEN userid IN (0$userids) THEN $postcount";
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
		}
	}

	foreach(array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}
	build_forum_counters($destforuminfo['forumid']);

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
