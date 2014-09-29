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


// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################



// Wouldn't be fun if someone tried to manipulate every post in the database ;)
// Should be made into options I suppose - too many and you exceed what a cookie can hold anyway
$postlimit = 400;
$threadlimit = 200;

function ban_user_func($xmlrpc_params) {
	global $vbulletin,$db;
	 

	global $xmlrpcerruser;
	$threadarray = array();
	$postarray = array();
	$postinfos = array();
	$forumlist = array();
	$threadlist = array();
	$params = php_xmlrpc_decode($xmlrpc_params);

	$user_name =    mobiquo_encode($params[0],'to_local');
	 
	$userid   = get_userid_by_name($user_name);
	if (intval($userid) == 0)
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}
	$delete_other   = ($params[1] == 2) ? true : false;
	if (!can_moderate())
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	if(isset($params[2])){
		$vbulletin->GPC['reason']=  mobiquo_encode($params[2],'to_local');
	}


	$user_cache = array();


	$user_cache["$userid"] = fetch_userinfo($userid);
	cache_permissions($user_cache["$userid"]);
	$user_cache["$userid"]['joindate_string'] = vbdate($vbulletin->options['dateformat'], $user_cache["$userid"]['joindate']);



	require_once(DIR . '/includes/adminfunctions.php');
	require_once(DIR . '/includes/functions_banning.php');
	if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canbanusers')))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	// check that user has permission to ban the person they want to ban
	if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		foreach ($user_cache AS $userid => $userinfo)
		{
			if (can_moderate(0, '', $userinfo['userid'], $userinfo['usergroupid'] . (trim($userinfo['membergroupids']) ? ",$userinfo[membergroupids]" : ''))
			OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
			OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']
			OR is_unalterable_user($userinfo['userid']))
			{
				return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
			}
		}
	}
	else
	{
		foreach ($user_cache AS $userid => $userinfo)
		{
			if ($userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
			OR is_unalterable_user($userinfo['userid']))
			{
				return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
			}
		}
	}

	$vbulletin->GPC['usergroupid'] =   -1 ;
	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		if (!($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
		{
			$optiontitle = $usergroup['title'];
			$vbulletin->GPC['usergroupid'] = $usergroupid;
				
		}
	}

	if (!empty($user_cache))
	{

		$vbulletin->GPC['period'] = 'PERMANENT';

		if (!isset($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]) OR ($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		// check that the number of days is valid
		if ($vbulletin->GPC['period'] != 'PERMANENT' AND !preg_match('#^(D|M|Y)_[1-9][0-9]?$#', $vbulletin->GPC['period']))
		{
			return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
		}

		if ($vbulletin->GPC['period'] == 'PERMANENT')
		{
			// make this ban permanent
			$liftdate = 0;
		}
		else
		{
			// get the unixtime for when this ban will be lifted
			$liftdate = convert_date_to_timestamp($vbulletin->GPC['period']);
		}

		$user_dms = array();

		$current_bans = $db->query_read("
				SELECT user.userid, userban.liftdate, userban.bandate
				FROM " . TABLE_PREFIX . "user AS user
				LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
				WHERE user.userid IN (" . implode(',', array_keys($user_cache)) . ")
			");
		while ($current_ban = $db->fetch_array($current_bans))
		{
			$userinfo = $user_cache["$current_ban[userid]"];
			$userid = $userinfo['userid'];

			if ($current_ban['bandate'])
			{ // they already have a ban, check if the current one is being made permanent, continue if its not
				if ($liftdate AND $liftdate < $current_ban['liftdate'])
				{
					continue;
				}

				// there is already a record - just update this record
				$db->query_write("
						UPDATE " . TABLE_PREFIX . "userban SET
						bandate = " . TIMENOW . ",
						liftdate = $liftdate,
						adminid = " . $vbulletin->userinfo['userid'] . ",
						reason = '" . $db->escape_string($vbulletin->GPC['reason']) . "'
						WHERE userid = $userinfo[userid]
					");
			}
			else
			{
				// insert a record into the userban table
				/*insert query*/
				$db->query_write("
						INSERT INTO " . TABLE_PREFIX . "userban
						(userid, usergroupid, displaygroupid, customtitle, usertitle, adminid, bandate, liftdate, reason)
						VALUES
						($userinfo[userid], $userinfo[usergroupid], $userinfo[displaygroupid], $userinfo[customtitle], '" . $db->escape_string($userinfo['usertitle']) . "', " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ", $liftdate, '" . $db->escape_string($vbulletin->GPC['reason']) . "')
					");
			}

			// update the user record
			$user_dms[$userid] =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$user_dms[$userid]->set_existing($userinfo);
			$user_dms[$userid]->set('usergroupid', $vbulletin->GPC['usergroupid']);
			$user_dms[$userid]->set('displaygroupid', 0);

			// update the user's title if they've specified a special user title for the banned group
			if ($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle'] != '')
			{
				$user_dms[$userid]->set('usertitle', $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle']);
				$user_dms[$userid]->set('customtitle', 0);
			}
			$user_dms[$userid]->pre_save();
		}

		foreach ($user_dms AS $userdm)
		{
			$userdm->save();
		}

	}

	// delete threads that are defined explicitly as spam by being ticked
	$physicaldel = ($vbulletin->GPC['deletetype'] == 2) ? true : false;
	$skipped_user_prune = array();

	if ($delete_other AND !empty($user_cache) AND can_moderate(-1, 'canmassprune'))
	{
		$remove_all_posts = array();
		$user_checks = $db->query_read_slave("SELECT COUNT(*) AS total, userid AS userid FROM " . TABLE_PREFIX . "post WHERE userid IN (". implode(', ', array_keys($user_cache)) . ") GROUP BY userid");
		while ($user_check = $db->fetch_array($user_checks))
		{
			if (intval($user_check['total']) <= 50)
			{
				$remove_all_posts[] = $user_check['userid'];
			}
			else
			{
				$skipped_user_prune[] = $user_check['userid'];
			}
		}

		if (!empty($remove_all_posts))
		{
			$threads = $db->query_read_slave("SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE postuserid IN (". implode(', ', $remove_all_posts) . ")");
			while ($thread = $db->fetch_array($threads))
			{
				$threadids[] = $thread['threadid'];
			}

			// Yes this can pick up firstposts of threads but we check later on when fetching info, so it won't matter if its already deleted
			$posts = $db->query_read_slave("SELECT postid FROM " . TABLE_PREFIX . "post WHERE userid IN (". implode(', ', $remove_all_posts) . ")");
			while ($post = $db->fetch_array($posts))
			{
				$postids[] = $post['postid'];
			}
		}
	}
	$threadarray = array();
	if (!empty($threadids))
	{
		// Validate threads
		$threads = $db->query_read_slave("
			SELECT threadid, open, visible, forumid, title, postuserid
			FROM " . TABLE_PREFIX . "thread
			WHERE threadid IN (" . implode(',', $threadids) . ")
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
			else if ($thread['open'] != 10)
			{
				if (!can_moderate($thread['forumid'], 'canremoveposts') AND $physicaldel)
				{
					return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
				}
				else if (!can_moderate($thread['forumid'], 'candeleteposts') AND !$physicaldel)
				{
					return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
				}
			}

			$threadarray["$thread[threadid]"] = $thread;
			$forumlist["$thread[forumid]"] = true;
		}
	}

	$delinfo = array(
			'userid'          => $vbulletin->userinfo['userid'],
			'username'        => $vbulletin->userinfo['username'],
			'reason'          => $vbulletin->GPC['deletereason'],
			'keepattachments' => $vbulletin->GPC['keepattachments'],
	);
	foreach ($threadarray AS $threadid => $thread)
	{
		$countposts = $vbulletin->forumcache["$thread[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['countposts'];
		if (!$physicaldel AND $thread['visible'] == 2)
		{
			# Thread is already soft deleted
			continue;
		}

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threadman->set_existing($thread);

		// Redirect
		if ($thread['open'] == 10)
		{
			$threadman->delete(false, true, $delinfo);
		}
		else
		{
			$threadman->delete($countposts, $physicaldel, $delinfo);
		}
		unset($threadman);
	}

	if (!empty($postids))
	{
		// Validate Posts
		$posts = $db->query_read_slave("
			SELECT post.postid, post.threadid, post.parentid, post.visible, post.title,
				thread.forumid, thread.title AS thread_title, thread.postuserid, thread.firstpostid, thread.visible AS thread_visible
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
			WHERE postid IN (" . implode(',', $postids) . ")
			ORDER BY postid
		");
		while ($post = $db->fetch_array($posts))
		{
			$postarray["$post[postid]"] = $post;
			$threadlist["$post[threadid]"] = true;
			$forumlist["$post[forumid]"] = true;
			if ($post['firstpostid'] == $post['postid'])
			{	// deleting a thread so do not decremement the counters of any other posts in this thread
				$firstpost["$post[threadid]"] = true;
			}
			else if (!empty($firstpost["$post[threadid]"]))
			{
				$postarray["$post[postid]"]['skippostcount'] = true;
			}
		}
	}

	$gotothread = true;
	foreach ($postarray AS $postid => $post)
	{
		$foruminfo = fetch_foruminfo($post['forumid']);

		$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$postman->set_existing($post);
		$postman->delete(($foruminfo['countposts'] AND !$post['skippostcount']), $post['threadid'], $physicaldel, $delinfo);
		unset($postman);

		if ($vbulletin->GPC['threadid'] == $post['threadid'] AND $post['postid'] == $post['firstpostid'])
		{	// we've deleted the thread that we activated this action from so we can only return to the forum
			$gotothread = false;
		}
		else if ($post['postid'] == $postinfo['postid'] AND $physicaldel)
		{	// we came in via a post, which we have deleted so we have to go back to the thread
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . 't=' . $vbulletin->GPC['threadid'];
		}
	}

	foreach(array_keys($threadlist) AS $threadid)
	{
		build_thread_counters($threadid);
	}
	foreach (array_keys($forumlist) AS $forumid)
	{
		build_forum_counters($forumid);
	}

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
