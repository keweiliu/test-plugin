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
require_once(CWD1.'/include/functions_private_message.php');


// #################### DEFINE IMPORTANT CONSTANTS #######################
define('GET_EDIT_TEMPLATES', 'newpm,insertpm');
define('THIS_SCRIPT', 'private');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
);

// get special data templates from the datastore
$specialtemplates = array(
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'USERCP_SHELL',
	'usercp_nav_folderbit'
);

// pre-cache templates used by specific actions
$actiontemplates = array();
$actiontemplates['insertpm'] =& $actiontemplates['newpm'];


// ######################### REQUIRE BACK-END ############################
if(file_exists('./global.php'.SUFFIX)){
	require_once('./global.php'.SUFFIX);
} else {
	require_once('./global.php');
}
if(file_exists(DIR . '/includes/functions_user.php'.SUFFIX)){
	require_once(DIR . '/includes/functions_user.php'.SUFFIX);
} else {
	require_once(DIR . '/includes/functions_user.php');
}
if(file_exists(DIR . '/includes/functions_misc.php'.SUFFIX)){
	require_once(DIR . '/includes/functions_misc.php'.SUFFIX);
} else {
	require_once(DIR . '/includes/functions_misc.php');
}
// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ###################### Start pm code parse #######################
function parse_pm_bbcode($bbcode, $smilies = true)
{
	global $vbulletin;
	if(file_exists(DIR . '/includes/class_bbcode.php'.SUFFIX)){
		require_once(DIR . '/includes/class_bbcode.php'.SUFFIX);
	} else {
		require_once(DIR . '/includes/class_bbcode.php');
	}
	$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
	return $bbcode_parser->parse($bbcode, 'privatemessage', $smilies);
}

// ###################### Start pm update counters #######################
// update the pm counters for $vbulletin->userinfo
function build_pm_counters()
{
	global $vbulletin;


	$pmcount = $vbulletin->db->query_first("
		SELECT
			COUNT(pmid) AS pmtotal,
			SUM(IF(messageread = 0 AND folderid >= 0, 1, 0)) AS pmunread
		FROM " . TABLE_PREFIX . "pm AS pm
		WHERE pm.userid = " . $vbulletin->userinfo['userid'] . "
	");

	$pmcount['pmtotal'] = intval($pmcount['pmtotal']);
	$pmcount['pmunread'] = intval($pmcount['pmunread']);

	if ($vbulletin->userinfo['pmtotal'] != $pmcount['pmtotal'] OR $vbulletin->userinfo['pmunread'] != $pmcount['pmunread'])
	{
		// init user data manager
		$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
		$userdata->set_existing($vbulletin->userinfo);
		$userdata->set('pmtotal', $pmcount['pmtotal']);
		$userdata->set('pmunread', $pmcount['pmunread']);
		$userdata->save();
	}
}


function check_pm_permession(){
	global $vbulletin,$permissions,$db;
	if (!$vbulletin->options['enablepms'])
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);

	}

	// the following is the check for actions which allow creation of new pms
	if ($permissions['pmquota'] < 1 OR !$vbulletin->userinfo['receivepm'])
	{
		$show['createpms'] = false;
	}

	// check permission to use private messaging
	if (($permissions['pmquota'] < 1 AND (!$vbulletin->userinfo['pmtotal'] OR in_array($_REQUEST['do'], array('insertpm', 'newpm')))) OR !$vbulletin->userinfo['userid'])
	{

		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	if (!$vbulletin->userinfo['receivepm'] AND in_array($_REQUEST['do'], array('insertpm', 'newpm')))
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);


	}



}

// ############################### initialisation ###############################
function get_inbox_stat_func(){
	global $vbulletin,$permissions,$db;
	if (!$vbulletin->options['enablepms'])
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	// the following is the check for actions which allow creation of new pms
	if ($permissions['pmquota'] < 1 OR !$vbulletin->userinfo['receivepm'])
	{
		$show['createpms'] = false;
	}

	// check permission to use private messaging
	if (($permissions['pmquota'] < 1 AND (!$vbulletin->userinfo['pmtotal'] OR in_array($_REQUEST['do'], array('insertpm', 'newpm')))) OR !$vbulletin->userinfo['userid'])
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	if (!$vbulletin->userinfo['receivepm'] AND in_array($_REQUEST['do'], array('insertpm', 'newpm')))
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	construct_forum_jump();

	$onload = '';
	$show['trackpm'] = $cantrackpm = $permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm'];



	$folderid = $vbulletin->GPC['folderid'];

	$folderjump = mobiquo_construct_folder_jump(0, $vbulletin->GPC['folderid']);
	$foldername = $foldernames["{$vbulletin->GPC['folderid']}"];


	$pms = $db->query_first_slave("
                        SELECT
                            SUM(IF(pm.messageread <> 0, 1, 0)) AS readed,
                            SUM(IF(pm.messageread = 0, 1, 0)) AS unreaded
                        FROM " . TABLE_PREFIX . "pm AS pm
                        WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.folderid=0 
    ");

	$sub_threads_num = 0;

	if (!$vbulletin->options['threadmarking'])
	{
		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
		{
			$lastpost_info = ", IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastposts";

			$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
				"(tachythreadpost.threadid = subscribethread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';

			$lastpost_having = "HAVING lastposts > " . $vbulletin->userinfo['lastvisit'];
		}
		else
		{
			$lastpost_info = '';
			$tachyjoin = '';
			$lastpost_having = "AND lastpost > " . $vbulletin->userinfo['lastvisit'];
		}

		$getthreads = $db->query_read_slave("
			SELECT thread.threadid, thread.forumid, thread.postuserid, subscribethread.subscribethreadid
			$lastpost_info
			FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
			INNER JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
			$tachyjoin
			WHERE subscribethread.threadid = thread.threadid
				AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . "
				AND thread.visible = 1
				AND subscribethread.canview = 1
				$lastpost_having
		");
	}
	else
	{
		$readtimeout = TIMENOW - ($vbulletin->options['markinglimit'] * 86400);

		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
		{
			$lastpost_info = ", IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastposts";

			$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
				"(tachythreadpost.threadid = subscribethread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
		}
		else
		{
			$lastpost_info = ', thread.lastpost AS lastposts';
			$tachyjoin = '';
		}

		$getthreads = $db->query_read_slave("
			SELECT thread.threadid, thread.forumid, thread.postuserid,
				IF(threadread.readtime IS NULL, $readtimeout, IF(threadread.readtime < $readtimeout, $readtimeout, threadread.readtime)) AS threadread,
				IF(forumread.readtime IS NULL, $readtimeout, IF(forumread.readtime < $readtimeout, $readtimeout, forumread.readtime)) AS forumread,
				subscribethread.subscribethreadid
				$lastpost_info
			FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (subscribethread.threadid = thread.threadid)
			LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = thread.forumid AND forumread.userid = " . $vbulletin->userinfo['userid'] . ")
			$tachyjoin
			WHERE subscribethread.userid = " . $vbulletin->userinfo['userid'] . "
				AND thread.visible = 1
				AND subscribethread.canview = 1
			HAVING lastposts > IF(threadread > forumread, threadread, forumread)
		");
	}
	$threadids = array();
	$sub_threads_num = 0;

	if ($totalthreads = $db->num_rows($getthreads))
	{
		$forumids = array();
		$threadids = array();
		$killthreads = array();
		while ($getthread = $db->fetch_array($getthreads))
		{
			$forumperms = fetch_permissions($getthread['forumid']);
			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR ($getthread['postuserid'] != $vbulletin->userinfo['userid'] AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
			{
				$killthreads[] = $getthread['subscribethreadid'];
				continue;
			}
			$forumids["$getthread[forumid]"] = true;
			$threadids[] = $getthread['threadid'];
		}

	}

	unset($getthread);
	$db->free_result($getthreads);

	if (!empty($killthreads))
	{
		// Update thread subscriptions
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "subscribethread
			SET canview = 0
			WHERE subscribethreadid IN (" . implode(', ', $killthreads) . ")
		");
	}
	if(isset($threadids)){
		$sub_threads_num = count($threadids);
	}



	$return_pm  = array(
		'inbox_unread_count'=>new xmlrpcval($pms[unreaded],'int'),
		'subscribed_topic_unread_count' => new xmlrpcval($sub_threads_num,'int')
	);
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return  new xmlrpcresp(
	new xmlrpcval( $return_pm ,"struct")
	);
}



?>
