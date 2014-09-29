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


// ####################### SET PHP ENVIRONMENT ###########################

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'report');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('messaging');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'newpost_usernamecode',
	'reportitem'
);

// pre-cache templates used by specific actions
$actiontemplates = array();


// ######################### REQUIRE BACK-END ############################
if(file_exists('./global.php'.SUFFIX)){
	require_once('./global.php'.SUFFIX);
} else {
	require_once('./global.php');
}

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################
function report_post_func($xmlrpc_params){

	global $vbulletin,$permissions,$db;

	$decode_params = php_xmlrpc_decode($xmlrpc_params);
	$post_id = intval($decode_params[0]);
	if(isset($decode_params[1]) && strlen($decode_params[1]) > 0){
		$report_message= mobiquo_encode($decode_params[1],'to_local');
	} else {
		$report_message= mobiquo_encode("report",'to_local');
	}

	$vbulletin->GPC['postid']  = $post_id;

	//==============================================================
	// automatically query $postinfo, $threadinfo & $foruminfo if $threadid exists
	if ($vbulletin->GPC['postid'] AND $postinfo = mobiquo_verify_id('post', $vbulletin->GPC['postid'], 0, 1))
	{
		$postid =& $postinfo['postid'];
		$vbulletin->GPC['threadid'] =& $postinfo['threadid'];
	}

	// automatically query $threadinfo & $foruminfo if $threadid exists
	if ($vbulletin->GPC['threadid'] AND $threadinfo = mobiquo_verify_id('thread', $vbulletin->GPC['threadid'], 0, 1))
	{

		$threadid =& $threadinfo['threadid'];
		$vbulletin->GPC['forumid'] = $forumid = $threadinfo['forumid'];
		if ($forumid)
		{
			$foruminfo = fetch_foruminfo($threadinfo['forumid']);
			if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
			{
				$codestyleid = $foruminfo['styleid'];
			}
		}

		if ($vbulletin->GPC['pollid'])
		{
			$pollinfo = verify_id('poll', $vbulletin->GPC['pollid'], 0, 1);
			$pollid =& $pollinfo['pollid'];
		}
	}

	//===================================================

	if (!$vbulletin->userinfo['userid'])
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	$reportthread = ($rpforumid = $vbulletin->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
	$reportemail = ($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']);


	if (!$reportthread AND !$reportemail)
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}


	$perform_floodcheck = (
	!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	AND $vbulletin->options['emailfloodtime']
	AND $vbulletin->userinfo['userid']
	);

	if ($perform_floodcheck AND ($timepassed = TIMENOW - $vbulletin->userinfo['emailstamp']) < $vbulletin->options['emailfloodtime'])
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	$forumperms = fetch_permissions($threadinfo['forumid']);
	if (
	!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
	OR
	!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
	OR
	(($threadinfo['postuserid'] != $vbulletin->userinfo['userid']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
	)
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	if (!$postinfo['postid'])
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}
	if ((!$postinfo['visible'] OR $postinfo ['isdeleted']) AND !can_moderate($threadinfo['forumid']))
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);



	$vbulletin->input->clean_array_gpc('p', array(
			'reason' => TYPE_STR,
	));
	$vbulletin->GPC['reason'] = $report_message;
	if ($vbulletin->GPC['reason'] == '')
	{
		return new xmlrpcresp(new xmlrpcval(false,'boolean'));
	}
	// trim the reason so it's not too long
	if ($vbulletin->options['postmaxchars'] > 0)
	{
		$trimmed_reason = substr($vbulletin->GPC['reason'], 0, $vbulletin->options['postmaxchars']);
	}
	else
	{
		$trimmed_reason = $vbulletin->GPC['reason'];
	}

	if ($perform_floodcheck)
	{
		$flood_limit = ($reportemail ? $vbulletin->options['emailfloodtime'] : $vbulletin->options['floodchecktime']);
		require_once(DIR . '/includes/class_floodcheck.php');
		$floodcheck =& new vB_FloodCheck($vbulletin, 'user', 'emailstamp');
		$floodcheck->commit_key($vbulletin->userinfo['userid'], TIMENOW, TIMENOW - $flood_limit);
		if ($floodcheck->is_flooding())
		{
			eval(standard_error(fetch_error('report_post_floodcheck', $flood_limit, $floodcheck->flood_wait())));
		}
	}

	$mods = array();
	$moderators = $db->query_read_slave("
		SELECT DISTINCT user.email, user.languageid, user.userid, user.username
		FROM " . TABLE_PREFIX . "moderator AS moderator,
			" . TABLE_PREFIX . "user AS user
		WHERE user.userid = moderator.userid
			AND moderator.forumid IN ($foruminfo[parentlist]) AND moderator.forumid <> -1
	");
	while ($moderator = $db->fetch_array($moderators))
	{
		$mods["$moderator[userid]"] = $moderator;
		$modlist .= (!empty($modlist) ? ', ' : '') . unhtmlspecialchars($moderator['username']);
	}

	if (empty($modlist))
	{
		$modlist = $vbphrase['n_a'];
	}

	if ($reportthread)
	{
		// Determine if we need to create a thread or a post

		if (!$postinfo['reportthreadid'] OR
		!($rpthreadinfo = fetch_threadinfo($postinfo['reportthreadid'])) OR
		($rpthreadinfo AND (
		$rpthreadinfo['isdeleted'] OR
		!$rpthreadinfo['visible'] OR
		$rpthreadinfo['forumid'] != $rpforuminfo['forumid'])
		))
		{
			// post not been reported or reported thread was deleted/moderated/moved
			$reportinfo = array(
				'forumtitle'  => unhtmlspecialchars($foruminfo['title_clean']),
				'threadtitle' => unhtmlspecialchars($threadinfo['title']),
				'rusername'   => unhtmlspecialchars($vbulletin->userinfo['username']),
				'pusername'   => unhtmlspecialchars($postinfo['username']),
				'reason'      => $trimmed_reason,
			);
			eval(fetch_email_phrases('reportpost_thread', 0));

			if (!$vbulletin->options['rpuserid'] OR !($userinfo = fetch_userinfo($vbulletin->options['rpuserid'])))
			{
				$userinfo =& $vbulletin->userinfo;
			}
			$threadman =& datamanager_init('Thread_FirstPost', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$threadman->set_info('forum', $rpforuminfo);
			$threadman->set_info('skip_moderator_email', true);
			$threadman->set_info('skip_floodcheck', true);
			$threadman->set_info('skip_charcount', true);
			$threadman->set_info('mark_thread_read', true);
			$threadman->set_info('skip_title_error', true);
			$threadman->set_info('parseurl', true);
			$threadman->set('allowsmilie', true);
			$threadman->set('userid', $userinfo['userid']);
			$threadman->setr_info('user', $userinfo);
			$threadman->set('title', $subject);
			$threadman->set('pagetext', $message);
			$threadman->set('forumid', $rpforuminfo['forumid']);
			$threadman->set('visible', 1);
			if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
			{
				// not posting as the current user, IP won't make sense
				$threadman->set('ipaddress', '');
			}
			$rpthreadid = $threadman->save();

			$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$postman->set_info('skip_floodcheck', true);
			$postman->set_info('skip_charcount', true);
			$postman->set_info('parseurl', true);
			$postman->set('reportthreadid', $rpthreadid);

			// if $postinfo['reportthreadid'] exists then it means then the discussion thread has been deleted/moved
			$checkrpid = ($postinfo['reportthreadid'] ? $postinfo['reportthreadid'] : 0);
			$postman->condition = "postid = $postinfo[postid] AND reportthreadid = $checkrpid";
			if (!$postman->save(true, false, true)) // affected_rows = 0, meaning another user reported this before us (race condition)
			{
				// Delete the thread we just created
				if ($delthread = fetch_threadinfo($rpthreadid))
				{
					$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
					$threadman->set_existing($delthread);
					$threadman->delete($rpforuminfo['countposts'], true, NULL, false);
					unset($threadman);
				}

				// Get the reported thread id so we can now insert a post
				$rpinfo = $db->query_first("
					SELECT reportthreadid, forumid
					FROM " . TABLE_PREFIX . "post
					INNER JOIN " . TABLE_PREFIX . "thread USING (threadid)
					WHERE postid = $postinfo[postid]
				");
				if ($rpinfo['reportthreadid'])
				{
					$postinfo['reportthreadid'] = $rpinfo['reportthreadid'];
				}
			}
			else
			{
				$threadman->set_info('skip_moderator_email', false);
				$threadman->email_moderators(array('newthreademail', 'newpostemail'));
				$postinfo['reportthreadid'] = 0;
				$rpthreadinfo = array(
					'threadid'   => $rpthreadid,
					'forumid'    => $rpforuminfo['forumid'],
					'postuserid' => $userinfo['userid'],
				);

				// check the permission of the other user
				$userperms = fetch_permissions($rpthreadinfo['forumid'], $userinfo['userid'], $userinfo);
				if (($userperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($userperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) AND $userinfo['autosubscribe'] != -1)
				{
					$vbulletin->db->query_write("
						INSERT IGNORE INTO " . TABLE_PREFIX . "subscribethread
							(userid, threadid, emailupdate, folderid, canview)
						VALUES
							(" . $userinfo['userid'] . ", $rpthreadinfo[threadid], $userinfo[autosubscribe], 0, 1)
					");
				}
			}

			unset($threadman);
			unset($postman);
		}
		else
		{
			$rpthreadid = $postinfo['reportthreadid'];
		}

		if ($postinfo['reportthreadid'] AND
		$rpthreadinfo = fetch_threadinfo($postinfo['reportthreadid']) AND
		!$rpthreadinfo['isdeleted'] AND
		$rpthreadinfo['visible'] == 1 AND
		$rpthreadinfo['forumid'] == $rpforuminfo['forumid'])
		{
			// Already reported, thread still exists/visible, and thread is in the right forum.
			// Technically, if the thread exists but is in the wrong forum, we should create the
			// thread, but that should only occur in a race condition.
			$reportinfo = array(
				'rusername' => unhtmlspecialchars($vbulletin->userinfo['username']),
				'reason'    => $trimmed_reason,
			);
			eval(fetch_email_phrases('reportpost_post', 0));

			if (!$vbulletin->options['rpuserid'] OR (!$userinfo AND !($userinfo = fetch_userinfo($vbulletin->options['rpuserid']))))
			{
				$userinfo =& $vbulletin->userinfo;
			}

			$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
			$postman->set_info('thread', $rpthreadinfo);
			$postman->set_info('forum', $rpforuminfo);
			$postman->set_info('skip_floodcheck', true);
			$postman->set_info('skip_charcount', true);
			$postman->set_info('parseurl', true);
			$postman->set('threadid', $rpthreadid);
			$postman->set('userid', $userinfo['userid']);
			$postman->set('allowsmilie', true);
			$postman->set('visible', true);
			$postman->set('title', $subject);
			$postman->set('pagetext', $message);
			if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
			{
				// not posting as the current user, IP won't make sense
				$postman->set('ipaddress', '');
			}
			$postman->save();
			unset($postman);
		}
	}

	// Send Email to moderators/supermods/admins
	if ($reportemail)
	{
		$threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
		$postinfo['title'] = unhtmlspecialchars($postinfo['title']);

		if (empty($mods) OR $vbulletin->options['rpemail'] == 2)
		{
			$moderators = $db->query_read_slave("
				SELECT DISTINCT user.email, user.languageid, user.username, user.userid
				FROM " . TABLE_PREFIX . "usergroup AS usergroup
				INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
				WHERE usergroup.adminpermissions <> 0
				" . (!empty($mods) ? "AND userid NOT IN (" . implode(',', array_keys($mods)) . ")" : "") . "
			");

			while ($moderator = $db->fetch_array($moderators))
			{
				$mods["$moderator[userid]"] = $moderator;
			}
		}


		$reason =& $trimmed_reason;

		foreach ($mods AS $userid => $moderator)
		{
			if (!empty($moderator['email']))
			{
				$email_langid = ($moderator['languageid'] > 0 ? $moderator['languageid'] : $vbulletin->options['languageid']);

				($hook = vBulletinHook::fetch_hook('report_send_email')) ? eval($hook) : false;

				if ($rpthreadinfo)
				{	// had some permission checks here but it generated crazy queries
					eval(fetch_email_phrases('reportbadpost_discuss', $email_langid));
				}
				else
				{
					eval(fetch_email_phrases('reportbadpost_nodiscuss', $email_langid));
				}

				vbmail($moderator['email'], $subject, $message, true);
			}
		}
	}
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return new xmlrpcresp(new xmlrpcval($result,'boolean'));

}

?>
