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



// ####################### SET PHP ENVIRONMENT ###########################


// #################### DEFINE IMPORTANT CONSTANTS #######################
define('GET_EDIT_TEMPLATES', 'newpm,insertpm');
define('THIS_SCRIPT', 'private');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'posting',
	'postbit',
	'pm',
	'reputationlevel',
	'user'
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'banemail',
	'noavatarperms',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'USERCP_SHELL',
	'usercp_nav_folderbit'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'editfolders' => array(
		'pm_editfolders',
		'pm_editfolderbit',
),
	'emptyfolder' => array(
		'pm_emptyfolder',
),
	'showpm' => array(
		'pm_showpm',
		'pm_messagelistbit_user',
		'postbit',
		'postbit_wrapper',
		'postbit_onlinestatus',
		'postbit_reputation',
		'bbcode_code',
		'bbcode_html',
		'bbcode_php',
		'bbcode_quote',
		'im_aim',
		'im_icq',
		'im_msn',
		'im_yahoo',
		'im_skype',
),
	'newpm' => array(
		'pm_newpm',
),
	'managepm' => array(
		'pm_movepm',
),
	'trackpm' => array(
		'pm_trackpm',
		'pm_receipts',
		'pm_receiptsbit',
),
	'messagelist' => array(
		'pm_messagelist',
		'pm_messagelist_periodgroup',
		'pm_messagelistbit',		'pm_messagelistbit_user',
		'pm_messagelistbit_ignore',
)
);
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



	// select correct part of forumjump
	$frmjmpsel['pm'] = 'class="fjsel" selected="selected"';
	construct_forum_jump();

	$onload = '';
	$show['trackpm'] = $cantrackpm = $permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm'];

	$vbulletin->input->clean_gpc('r', 'pmid', TYPE_UINT);


	// ############################### default do value ###############################
	$vbulletin->input->clean_array_gpc('r', array(
		'folderid'   => TYPE_INT,
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT
	));



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


	// count receipts
	$receipts = $db->query_first_slave("
		SELECT
			SUM(IF(readtime <> 0, 1, 0)) AS confirmed,
			SUM(IF(readtime = 0, 1, 0)) AS unconfirmed
		FROM " . TABLE_PREFIX . "pmreceipt
		WHERE userid = " . $vbulletin->userinfo['userid']
	);

	// get ignored users
	$ignoreusers = preg_split('#\s+#s', $vbulletin->userinfo['ignorelist'], -1, PREG_SPLIT_NO_EMPTY);

	$totalmessages = intval($messagecounters["{$vbulletin->GPC['folderid']}"]);
	$return_pm  = array(   'inbox_unread_count'=>new xmlrpcval($pms[unreaded],'int')
	);
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return  new xmlrpcresp(
	new xmlrpcval( $return_pm ,"struct"));
	// build pm counters bar, folder is 100 if we have no quota so red shows on the main bar

}




function get_box_info_func(){
	global $vbulletin,$permissions,$db,$messagecounters;
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



	// select correct part of forumjump
	$frmjmpsel['pm'] = 'class="fjsel" selected="selected"';


	$onload = '';
	$show['trackpm'] = $cantrackpm = $permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm'];

	$vbulletin->input->clean_gpc('r', 'pmid', TYPE_UINT);


	// ############################### default do value ###############################





	$vbulletin->input->clean_array_gpc('r', array(
		'folderid'   => TYPE_INT,
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT
	));



	$folderid = $vbulletin->GPC['folderid'];

	$folderjump = mobiquo_construct_folder_jump(0, $vbulletin->GPC['folderid']);
	$foldername = $foldernames["{$vbulletin->GPC['folderid']}"];




	// count receipts
	$receipts = $db->query_first_slave("
		SELECT
			SUM(IF(readtime <> 0, 1, 0)) AS confirmed,
			SUM(IF(readtime = 0, 1, 0)) AS unconfirmed
		FROM " . TABLE_PREFIX . "pmreceipt
		WHERE userid = " . $vbulletin->userinfo['userid']
	);

	// get ignored users
	$ignoreusers = preg_split('#\s+#s', $vbulletin->userinfo['ignorelist'], -1, PREG_SPLIT_NO_EMPTY);
	$return_folders = array();
	foreach($folderjump as $folder_id => $folder_info){
		$pms = $db->query_first_slave("
			SELECT 
				SUM(IF(pm.messageread <> 0, 1, 0)) AS readed,
			    SUM(IF(pm.messageread = 0, 1, 0)) AS unreaded
			FROM " . TABLE_PREFIX . "pm AS pm
			WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.folderid=" . $folder_id . "
		");

		$return_folder = array('box_id'=>new xmlrpcval($folder_id,"string"),
	    	                   'box_name'=>new xmlrpcval(mobiquo_encode($folder_info['box_name']),"base64"),
						    	'msg_count'=>new xmlrpcval(($pms[readed]+$pms[unreaded]),"int"),
						    	'unread_count'=>new xmlrpcval($pms[unreaded],"int"));
		if($folder_id == 0){
			$return_folder['box_type'] = new xmlrpcval('INBOX',"string");
		} elseif ( $folder_id == -1) {
			$return_folder['box_type'] = new xmlrpcval('SENT',"string");
		} else {
			$return_folder['box_type'] = new xmlrpcval('',"string");
		}
		$xmlrpc_return_folder = new xmlrpcval($return_folder,"struct");
		array_push($return_folders, $xmlrpc_return_folder);
	}




	// build pm counters bar, folder is 100 if we have no quota so red shows on the main bar





	$pmtotal = vb_number_format($vbulletin->userinfo['pmtotal']);

	$pmquota = vb_number_format($vbulletin->userinfo['permissions']['pmquota']);
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return new xmlrpcresp(
	new xmlrpcval(
	array(
                                        'message_room_count' => new xmlrpcval(($pmquota-$pmtotal),'int'),
                                        'list' => new xmlrpcval($return_folders,'array'),
        								'result' => new xmlrpcval(true,"boolean"),
        								'result_text' =>  new xmlrpcval('','base64'),
	),
                                'struct'
                                )
                                );
}

function get_box_func($xmlrpc_params)
{

	global $vbulletin,$permissions,$db,$messagecounters;
	$params = php_xmlrpc_decode($xmlrpc_params);
	if(isset($params[1])  && $params[1] >= 0) {
		$start_num = $params[1] ; }
		else{
			$start_num = 0;
		}
		if(isset($params[2])){
			$end_num   = $params[2];
		} else {
			$end_num = 19;
		}

		$folderid = $params[0];
		if($params[0] == ''){
			$return = array(18,'invalid message box id');
			return return_fault($return);
		}
		$message_num = $end_num-$start_num+1;
		$show['messagelist'] = true;
		if(!$vbulletin->options['enablepms'])
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

		// start navbar


		// select correct part of forumjump
		$frmjmpsel['pm'] = 'class="fjsel" selected="selected"';
		construct_forum_jump();

		$onload = '';
		$show['trackpm'] = $cantrackpm = $permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm'];

		// get a sensible value for $perpage
		// work out the $startat value
		$startat = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage'];

		// array to store private messages in period groups
		$pm_period_groups = array();

		// query private messages
		$pms = $db->query_read_slave("
			SELECT pm.*, pmtext.*
				" . iif($vbulletin->options['privallowicons'], ", icon.title AS icontitle, icon.iconpath") . "
			FROM " . TABLE_PREFIX . "pm AS pm
			LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
			" . iif($vbulletin->options['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
			WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.folderid=" . $folderid . "
			ORDER BY pmtext.dateline DESC
			LIMIT $start_num, " . $message_num . "
		");
		while ($pm = $db->fetch_array($pms))
		{
			$pm_period_groups[ fetch_period_group($pm['dateline']) ]["{$pm['pmid']}"] = $pm;
		}
		$db->free_result($pms);

		// display returned messages
		$show['pmcheckbox'] = true;
		$ignoreusers = preg_split('#\s+#s', $vbulletin->userinfo['ignorelist'], -1, PREG_SPLIT_NO_EMPTY);
		if(file_exists(DIR . '/includes/functions_bigthree.php'.SUFFIX)){
			require_once(DIR . '/includes/functions_bigthree.php'.SUFFIX);
		} else {
			require_once(DIR . '/includes/functions_bigthree.php');
		}

		$pms_count = $db->query_first_slave("
					SELECT
						SUM(IF(pm.messageread <> 0, 1, 0)) AS readed,
					    SUM(IF(pm.messageread = 0, 1, 0)) AS unreaded
					FROM " . TABLE_PREFIX . "pm AS pm
					WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.folderid=" . $folderid . "
		");

		$return_message_list = array();

		foreach ($pm_period_groups AS $groupid => $pms)
		{
			if (preg_match('#^(\d+)_([a-z]+)_ago$#i', $groupid, $matches))
			{
				$groupname = construct_phrase($vbphrase["x_$matches[2]_ago"], $matches[1]);
			}
			else
			{
				$groupname = $vbphrase["$groupid"];
			}
			$groupid = $vbulletin->GPC['folderid'] . '_' . $groupid;
			$collapseobj_groupid =& $vbcollapse["collapseobj_pmf$groupid"];
			$collapseimg_groupid =& $vbcollapse["collapseimg_pmf$groupid"];

			$messagesingroup = sizeof($pms);
			$messagelistbits = '';

			foreach ($pms AS $pmid => $pm)
			{


				if (in_array($pm['fromuserid'], $ignoreusers))
				{
					// from user is on Ignore List
					eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit_ignore') . '";');
				}
				else
				{
					switch($pm['messageread'])
					{
						case 0: // unread
							$pm['statusicon'] = 'new';
							break;

						case 1: // read
							$pm['statusicon'] = 'old';
							break;

						case 2: // replied to
							$pm['statusicon'] = 'replied';
							break;

						case 3: // forwarded
							$pm['statusicon'] = 'forwarded';
							break;
					}

					$pm['senddate'] = vbdate($vbulletin->options['dateformat'], $pm['dateline']);
					$pm['sendtime'] = vbdate($vbulletin->options['timeformat'], $pm['dateline']);
						
					$userinfo = mobiquo_verify_id('user',$pm['fromuserid'], 1, 1, $fetch_userinfo_options);
					$mobiquo_user_online = (fetch_online_status($userinfo, true)) ? true : false;
					$return_message = array('msg_id'=>new xmlrpcval($pm['pmid'],"string"),
						 	    	        'msg_state'=>new xmlrpcval(($pm['messageread']+1),"int"),
									    	'sent_date'=>new xmlrpcval(mobiquo_iso8601_encode($pm[dateline]-$vbulletin->options['hourdiff'],$vbulletin->userinfo['tzoffset']),"dateTime.iso8601"),
									    	'msg_from' => new xmlrpcval(mobiquo_encode($pm['fromusername']),"base64"),
									    	'msg_subject' => new xmlrpcval(mobiquo_encode($pm['title']),"base64"),
									    	'is_online' => new xmlrpcval($mobiquo_user_online  ,"boolean"),
									    	'short_content' => new xmlrpcval(mobiquo_encode(mobiquo_chop(post_content_clean($pm['message']))),"base64"),
					);

					$return_to_users  = array();
					if ($folderid == -1)
					{
						$users = unserialize($pm['touserarray']);
						$touser = array();
						$tousers = array();
						if (!empty($users))
						{
							foreach ($users AS $key => $item)
							{
								if (is_array($item))
								{
									foreach($item AS $subkey => $subitem)
									{
										$touser["$subkey"] = $subitem;
									}
								}
								else
								{
									$touser["$key"] = $item;
								}
							}
							uasort($touser, 'strnatcasecmp');
						}
						$icon_user_id ='';
						foreach ($touser AS $userid => $username)
						{
							if($icon_user_id == ""){
								$icon_user_id = $userid;
							}
							$return_to_user = new xmlrpcval(
							array('username'=>new xmlrpcval(mobiquo_encode($username),"base64"),
							),"struct");
							array_push($return_to_users,  $return_to_user);
						}
						$userbit = implode(', ', $tousers);
					}
					else
					{

						$userid =& $pm['fromuserid'];
						$username =& $pm['fromusername'];
						$icon_user_id = $pm['fromuserid'];
					}
	$fetch_userinfo_options = (
								FETCH_USERINFO_AVATAR
								);

								$authorinfo = fetch_userinfo($icon_user_id, $fetch_userinfo_options);
								fetch_avatar_from_userinfo($authorinfo,true,false);

								if($authorinfo[avatarurl]){
									$icon_url=get_icon_real_url($authorinfo['avatarurl']);
								} else {
									$icon_url = '';
								}
					$return_message['icon_url'] = new xmlrpcval(unhtmlspecialchars($icon_url),"string");
					$return_message['msg_to'] = new xmlrpcval($return_to_users,"array");

					$xmlrpc_return_message =new xmlrpcval( $return_message,"struct");
					$return_message_list[] =$xmlrpc_return_message;
				}
			}

			// free up memory not required any more
			unset($pm_period_groups["$groupid"]);




		}
		if (defined('NOSHUTDOWNFUNC'))
		{
			exec_shut_down();
		}
	 return new xmlrpcresp(
	 new xmlrpcval(
	 array(
                                        'total_unread_count' => new xmlrpcval($pms_count['unreaded'],'int'),
                                        'total_message_count' => new xmlrpcval(($pms_count['readed']+$pms_count['unreaded']),'int'),
	                                        'list' => new xmlrpcval($return_message_list,'array'),
	 ),
	                                'struct'
	                                )
	                                );


}

function get_message_func($xmlrpc_params)
{

	global $vbulletin,$permissions,$db,$messagecounters;
	$params = php_xmlrpc_decode($xmlrpc_params);


	$messageid = $params[0];
	$html_content= false;
	if(isset($params[2]) && $params[2]){
		$html_content = true;
	}
	$show['messagelist'] = true;
	if(!$vbulletin->options['enablepms'])
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



	$onload = '';
	$show['trackpm'] = $cantrackpm = $permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm'];
	if(file_exists(DIR . '/includes/class_postbit.php'.SUFFIX)){
		require_once(DIR . '/includes/class_postbit.php'.SUFFIX);
	} else {
		require_once(DIR . '/includes/class_postbit.php');
	}
	if(file_exists(DIR . '/includes/functions_bigthree.php'.SUFFIX)){
		require_once(DIR . '/includes/functions_bigthree.php'.SUFFIX);
	} else {
		require_once(DIR . '/includes/functions_bigthree.php');
	}



	$vbulletin->GPC['pmid'] = $messageid;


	$pm = $db->query_first_slave("
			SELECT
				pm.*, pmtext.*,
				" . iif($vbulletin->options['privallowicons'], "icon.title AS icontitle, icon.iconpath,") . "
				IF(ISNULL(pmreceipt.pmid), 0, 1) AS receipt, pmreceipt.readtime, pmreceipt.denied,
				sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight
			FROM " . TABLE_PREFIX . "pm AS pm
			LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
			" . iif($vbulletin->options['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
			LEFT JOIN " . TABLE_PREFIX . "pmreceipt AS pmreceipt ON(pmreceipt.pmid = pm.pmid)
			LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = pmtext.fromuserid)
			WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.pmid=" . $vbulletin->GPC['pmid'] . "
		");

	if (!$pm)
	{
		$return = array(27,'private message does not exist');
		return return_fault($return);
	}

	$folderjump = construct_folder_jump(0, $pm['folderid']);

	// do read receipt
	$show['receiptprompt'] = $show['receiptpopup'] = false;
	if ($pm['receipt'] == 1 AND $pm['readtime'] == 0 AND $pm['denied'] == 0)
	{
		if ($permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['candenypmreceipts'])
		{
			// set it to denied just now as some people might have ad blocking that stops the popup appearing
			$show['receiptprompt'] = $show['receiptpopup'] = true;
			$receipt_question_js = construct_phrase($vbphrase['x_has_requested_a_read_receipt'], unhtmlspecialchars($pm['fromusername']));
			$db->shutdown_query("UPDATE " . TABLE_PREFIX . "pmreceipt SET denied = 1 WHERE pmid = $pm[pmid]");
		}
		else
		{
			// they can't deny pm receipts so do not show a popup or prompt
			$db->shutdown_query("UPDATE " . TABLE_PREFIX . "pmreceipt SET readtime = " . TIMENOW . " WHERE pmid = $pm[pmid]");
		}
	}
	else if ($pm['receipt'] == 1 AND $pm['denied'] == 1)
	{
		$show['receiptprompt'] = true;
	}
	$pm_text = $pm['message'];
	$postbit_factory =& new vB_Postbit_Factory();
	$postbit_factory->registry =& $vbulletin;
	$postbit_factory->cache = array();
	$postbit_factory->bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$postbit_obj =& $postbit_factory->fetch_postbit('pm');
	$postbit = $postbit_obj->construct_postbit($pm);

	// update message to show read
	if ($pm['messageread'] == 0)
	{
		$db->shutdown_query("UPDATE " . TABLE_PREFIX . "pm SET messageread=1 WHERE userid=" . $vbulletin->userinfo['userid'] . " AND pmid=$pm[pmid]");
		if ($pm['folderid'] >= 0)
		{
			$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdm->set_existing($vbulletin->userinfo);
			$userdm->set('pmunread', 'IF(pmunread >= 1, pmunread - 1, 0)', false);
			$userdm->save(true, true);
			unset($userdm);
		}
	}

	$cclist = array();
	$bcclist = array();
	$ccrecipients = '';
	$bccrecipients = '';
	$touser = unserialize($pm['touserarray']);
	if (!is_array($touser))
	{
		$touser = array();
	}
	$return_to_users = array();
	$icon_user_id = "";

	foreach($touser AS $key => $item)
	{
		if (is_array($item))
		{
			if($key != 'bcc'){
				foreach($item AS $subkey => $subitem)
				{
					if($icon_user_id == ''){
						$icon_user_id == $subkey;
					}
					$username = $subitem;
					$userid = $subkey;
					$return_to_user = new xmlrpcval(
					array('username'=>new xmlrpcval(mobiquo_encode($username),"base64"),
					),"struct");
					array_push($return_to_users,  $return_to_user);
				}
			}
		}
		else
		{
			$username = $item;
			$userid = $key;
			//	$return_to_user = new xmlrpcval(
			//	array('username'=>new xmlrpcval(mobiquo_encode($username),"base64"),
			//	),"struct");
			//	array_push($return_to_users,  $return_to_user);
		}
	}



	if($html_content){
		$a = fetch_tag_list();
		unset($a['option']['quote']);
		unset($a['no_option']['quote']);
		unset($a['option']['url']);
		unset($a['no_option']['url']);

		$vbulletin->options['wordwrap']  = 0;
		 
		$pm_content =preg_replace("/\[\/img\]/siU",'[/img1]',$pm_text);
		$bbcode_parser =& new vB_BbCodeParser($vbulletin, $a);

		$pm_content = $bbcode_parser->parse( $pm_content, $thread[forumid], false);
		$pm_content =preg_replace("/\[\/img1\]/siU",'[/IMG]',$pm_content);
		 
		$pm_content =  htmlspecialchars_uni($pm_content);


		$pm_content = mobiquo_encode(post_content_clean_html($pm_content));

	} else {
		$pm_content  =   mobiquo_encode(post_content_clean($pm_text));
	}
	$userinfo = mobiquo_verify_id('user',$pm['fromuserid'], 1, 1, $fetch_userinfo_options);
	$mobiquo_user_online = (fetch_online_status($userinfo, false)) ? true : false;

	$return_message = array('msg_id'=>new xmlrpcval($pm['pmid'],"string"),
						    	'sent_date'=>new xmlrpcval(mobiquo_iso8601_encode($pm[dateline]-$vbulletin->options['hourdiff'],$vbulletin->userinfo['tzoffset']),"dateTime.iso8601"),
						    	'msg_from' => new xmlrpcval(mobiquo_encode($pm['fromusername']),"base64"),
						    	'msg_subject' => new xmlrpcval(mobiquo_encode($pm['title']),"base64"),
						    	'is_online' => new xmlrpcval($mobiquo_user_online  ,"boolean"),
								 'allow_smilies' => new xmlrpcval($pm['allowsmilie'],"boolean"), 
						   		 'text_body' => new xmlrpcval($pm_content,"base64"),
	);
	$return_message['icon_url'] = new xmlrpcval('','string');
	$return_message['msg_to'] = new xmlrpcval($return_to_users,"array");

	if($pm[folderid] == -1){
			   $fetch_userinfo_options = (
                                                                FETCH_USERINFO_AVATAR
                                                                );

                                                                $authorinfo = fetch_userinfo($icon_user_id, $fetch_userinfo_options);
                                                                fetch_avatar_from_userinfo($authorinfo,true,false);

                                                                if($authorinfo[avatarurl]){
                                                                        $icon_url=get_icon_real_url($authorinfo['avatarurl']);
                                                                } else {
                                                                        $icon_url = '';
                                                                }

		$return_message['icon_url']=new xmlrpcval(get_icon_real_url($icon_url),'string');

	} else{
		if($pm[avatarurl]){
			$return_message['icon_url']=new xmlrpcval(get_icon_real_url($pm[avatarurl]),'string');
		}
	}

	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return  new xmlrpcresp(
	new xmlrpcval( $return_message,"struct"));

}

function delete_message_func($xmlrpc_params)
{

	global $vbulletin,$permissions,$db,$messagecounters;
	$params = php_xmlrpc_decode($xmlrpc_params);
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

	// check that we have an array to work with


	// make sure the ids we are going to work with are sane
	$messageids = array();
	$messageids[] = $params[0];


	$pmids = array();
	$textids = array();

	// get the pmid and pmtext id of messages to be deleted
	$pms = $db->query_read_slave("
				SELECT pmid
				FROM " . TABLE_PREFIX . "pm
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
					AND pmid IN(" . implode(', ', $messageids) . ")
			");

	// check to see that we still have some ids to work with
	if ($db->num_rows($pms) == 0)
	{
		$return = array(27,'pravite message does not exist');
		return return_fault($return);

	}

	// build the final array of pmids to work with
	while ($pm = $db->fetch_array($pms))
	{
		$pmids[] = $pm['pmid'];
	}

	// delete from the pm table using the results from above
	$deletePmSql = "DELETE FROM " . TABLE_PREFIX . "pm WHERE pmid IN(" . implode(', ', $pmids) . ")";
	$db->query_write($deletePmSql);

	build_pm_counters();
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return new xmlrpcresp(new xmlrpcval(true,'boolean'));
}



function create_message_func($xmlrpc_params)
{

	global $vbulletin,$permissions,$db,$messagecounters;
	$params = php_xmlrpc_decode($xmlrpc_params);
	$show['messagelist'] = true;
	if(!$vbulletin->options['enablepms'])
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


	if ($permissions['pmquota'] < 1)
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}
	else if (!$vbulletin->userinfo['receivepm'])
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}
	$vbulletin->GPC['message'] = in_text_clean(mobiquo_encode($params[2],'to_local'));
	// include useful functions
	if(file_exists(DIR . '/includes/functions_newpost.php'.SUFFIX)){
		require_once(DIR . '/includes/functions_newpost.php'.SUFFIX);
	} else {
		require_once(DIR . '/includes/functions_newpost.php');
	}

	// unwysiwygify the incoming data
	//	if ($vbulletin->GPC['wysiwyg'])
	//	{
	if(file_exists(DIR . '/includes/functions_wysiwyg.php'.SUFFIX)){
		require_once(DIR . '/includes/functions_wysiwyg.php'.SUFFIX);
	} else {
		require_once(DIR . '/includes/functions_wysiwyg.php');
	}

	$vbulletin->GPC['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $vbulletin->options['privallowhtml']);
	//	}

	// parse URLs in message text
	if ($vbulletin->options['privallowbbcode'])
	{
		$vbulletin->GPC['message'] = convert_url_to_bbcode($vbulletin->GPC['message']);
	}


	if($params[3] == 1 && $params[4]) {

		$pm_id = $params[4];
	}

	if($params[3] == 2 && $params[4]) {
		$pm_id = $params[4];
		$forward = 1;
	}
	if(is_array($params[0])){
		$mobiquo_recipient = implode(';',$params[0]);
	} else {
		$mobiquo_recipient = $params[0];
	}
	$pm['message'] =$vbulletin->GPC['message'];
	$pm['title'] =  mobiquo_encode($params[1],'to_local');
	$pm['parseurl'] = 1;
	$pm['savecopy'] = 1;
	$pm['signature'] = 1;
	$pm['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
	$pm['sendanyway'] =& $vbulletin->GPC['sendanyway'];
	$pm['receipt'] =& $vbulletin->GPC['receipt'];
	$pm['recipients'] =  mobiquo_encode($mobiquo_recipient,'to_local');
	$pm['bccrecipients'] =& $vbulletin->GPC['bccrecipients'];
	$pm['pmid'] = $pm_id;
	$pm['iconid'] =& $vbulletin->GPC['iconid'];
	$pm['forward'] = $forward;
	$pm['folderid'] =& $vbulletin->GPC['folderid'];

	// *************************************************************
	// PROCESS THE MESSAGE AND INSERT IT INTO THE DATABASE

	$errors = array(); // catches errors

	if ($vbulletin->userinfo['pmtotal'] > $permissions['pmquota'] OR ($vbulletin->userinfo['pmtotal'] == $permissions['pmquota'] AND $pm['savecopy']))
	{
		$errors[] = fetch_error('yourpmquotaexceeded');
	}

	// check for message flooding
	if ($vbulletin->options['pmfloodtime'] > 0 AND !$vbulletin->GPC['preview'])
	{
		if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND !can_moderate())
		{
			$floodcheck = $db->query_first("
				SELECT pmtextid, title, dateline
				FROM " . TABLE_PREFIX . "pmtext AS pmtext
				WHERE fromuserid = " . $vbulletin->userinfo['userid'] . "
				ORDER BY dateline DESC
			");

			if (($timepassed = TIMENOW - $floodcheck['dateline']) < $vbulletin->options['pmfloodtime'])
			{
				$errors[] = fetch_error('pmfloodcheck', $vbulletin->options['pmfloodtime'], ($vbulletin->options['pmfloodtime'] - $timepassed));
			}
		}
	}

	// create the DM to do error checking and insert the new PM
	$pmdm =& datamanager_init('PM', $vbulletin, ERRTYPE_ARRAY);

	$pmdm->set_info('savecopy',      $pm['savecopy']);
	$pmdm->set_info('receipt',       $pm['receipt']);
	$pmdm->set_info('cantrackpm',    $cantrackpm);
	$pmdm->set_info('parentpmid', $pm['pmid']);
	$pmdm->set_info('forward',       $pm['forward']);
	$pmdm->set_info('bccrecipients', $pm['bccrecipients']);
	if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	{
		$pmdm->overridequota = true;
	}

	$pmdm->set('fromuserid', $vbulletin->userinfo['userid']);
	$pmdm->set('fromusername', $vbulletin->userinfo['username']);
	$pmdm->setr('title', $pm['title']);
	$pmdm->set_recipients($pm['recipients'], $permissions, 'cc');
	$pmdm->set_recipients($pm['bccrecipients'], $permissions, 'bcc');
	$pmdm->setr('message', $pm['message']);
	$pmdm->setr('iconid', $pm['iconid']);
	$pmdm->set('dateline', TIMENOW);
	$pmdm->setr('showsignature', $pm['signature']);
	$pmdm->set('allowsmilie', $pm['disablesmilies'] ? 0 : 1);



	$pmdm->pre_save();

	// deal with user using receivepmbuddies sending to non-buddies
	if ($vbulletin->userinfo['receivepmbuddies'] AND is_array($pmdm->info['recipients']))
	{
		$users_not_on_list = array();

		// get a list of super mod groups
		$smod_groups = array();
		foreach ($vbulletin->usergroupcache AS $ugid => $groupinfo)
		{
			if ($groupinfo['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
			{
				// super mod group
				$smod_groups[] = $ugid;
			}
		}

		// now filter out all moderators (and super mods) from the list of recipients
		// to check against the buddy list
		$check_recipients = $pmdm->info['recipients'];
		$mods = $db->query_read_slave("
			SELECT user.userid
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON (moderator.userid = user.userid)
			WHERE user.userid IN (" . implode(',', array_keys($check_recipients)) . ")
				AND ((moderator.userid IS NOT NULL AND moderator.forumid <> -1)
				" . (!empty($smod_groups) ? "OR user.usergroupid IN (" . implode(',', $smod_groups) . ")" : '') . "
				)
		");
		while ($mod = $db->fetch_array($mods))
		{
			unset($check_recipients["$mod[userid]"]);
		}

		if (!empty($check_recipients))
		{
			// filter those on our buddy list out
			$users = $db->query_read_slave("
				SELECT userlist.relationid
				FROM " . TABLE_PREFIX . "userlist AS userlist
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
					AND userlist.relationid IN(" . implode(array_keys($check_recipients), ',') . ")
					AND type = 'buddy'
			");
			while ($user = $db->fetch_array($users))
			{
				unset($check_recipients["$user[relationid]"]);
			}
		}

		// what's left must be those who are neither mods or on our buddy list
		foreach ($check_recipients AS $userid => $user)
		{
			$users_not_on_list["$userid"] = $user['username'];
		}

		if (!empty($users_not_on_list) AND (!$vbulletin->GPC['sendanyway'] OR !empty($errors)))
		{
			$users = '';
			foreach ($users_not_on_list AS $userid => $username)
			{
				$users .= "<li><a href=\"member.php?$session[sessionurl]u=$userid\" target=\"profile\">$username</a></li>";
			}
			$pmdm->error('pm_non_contacts_cant_reply', $users);
		}
	}

	// process errors if there are any
	$errors = array_merge($errors, $pmdm->errors);

	if (!empty($errors))
	{
		$error_string = mobiquo_encode(implode('',$errors));
		$return = array(18,$error_string);
			
		return return_fault($return);
	}
	else if ($vbulletin->GPC['preview'] != '')
	{
		define('PMPREVIEW', 1);
		$foruminfo = array(
			'forumid' => 'privatemessage',
			'allowicons' => $vbulletin->options['privallowicons']
		);
		$preview = process_post_preview($pm);
		$_REQUEST['do'] = 'newpm';
	}
	else
	{
		// everything's good!
		$pmdm->save();

		// force pm counters to be rebuilt
		$vbulletin->userinfo['pmunread'] = -1;
		build_pm_counters();
		if (defined('NOSHUTDOWNFUNC'))
		{
			exec_shut_down();
		}
		return new xmlrpcresp(new xmlrpcval(true,'boolean'));



	}
}


function mark_pm_unread_func($xmlrpc_params){
	global $vbulletin,$permissions,$db;
	$params = php_xmlrpc_decode($xmlrpc_params);



	$messageid = $params[0];
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

	$messageids = array();
	$messageids[] = $messageid;

	$db->query_write("UPDATE " . TABLE_PREFIX . "pm SET messageread=0 WHERE userid=" . $vbulletin->userinfo['userid'] . " AND pmid IN (" . implode(', ', $messageids) . ")");
	build_pm_counters();


	// deselect messages
	setcookie('vbulletin_inlinepm', '', TIMENOW - 3600, '/');

	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return new xmlrpcresp(new xmlrpcval(true,'boolean'));


}

function get_quote_pm_func($xmlrpc_params)
{

	global $vbulletin,$permissions,$db,$messagecounters;
	$params = php_xmlrpc_decode($xmlrpc_params);


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
	require_once(DIR . '/includes/functions_newpost.php');
	$messageid = $params[0];
	if($pm = $vbulletin->db->query_first_slave("
				SELECT pm.*, pmtext.*
				FROM " . TABLE_PREFIX . "pm AS pm
				LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
				WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.pmid=" . $messageid . "
		"))
	{
		$originalposter = fetch_quote_username($pm['fromusername']);

		// allow quotes to remain with an optional request variable
		// this will fix a problem with forwarded PMs and replying to them
		if ($vbulletin->GPC['stripquote'])
		{
			$pagetext = strip_quotes($pm['message']);
		}
		else
		{
			// this is now the default behavior -- leave quotes, like vB2
			$pagetext = $pm['message'];
		}
		$pagetext = trim(htmlspecialchars_uni($pagetext));

		eval('$pm[\'message\'] = "' . fetch_template('newpost_quote', 0, false) . '";');

		// work out FW / RE bits
		if (preg_match('#^' . preg_quote($vbphrase['forward_prefix'], '#') . '(\s+)?#i', $pm['title'], $matches))
		{
			$pm['title'] = substr($pm['title'], strlen($vbphrase['forward_prefix']) + (isset($matches[1]) ? strlen($matches[1]) : 0));
		}
		else if (preg_match('#^' . preg_quote($vbphrase['reply_prefix'], '#') . '(\s+)?#i', $pm['title'], $matches))
		{
			$pm['title'] = substr($pm['title'], strlen($vbphrase['reply_prefix']) + (isset($matches[1]) ? strlen($matches[1]) : 0));
		}
		else
		{
			$pm['title'] = preg_replace('#^[a-z]{2}:#i', '', $pm['title']);
		}

	}

	$return_message = array('msg_id'=>new xmlrpcval($pm['pmid'],"string"),
						    	'msg_subject' => new xmlrpcval(mobiquo_encode($pm['title']),"base64"),
						    	'text_body' => new xmlrpcval(mobiquo_encode($pm['message']),"base64"),
	);
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	return  new xmlrpcresp(
	new xmlrpcval( $return_message,"struct"));

}

function report_pm_func($xmlrpc_params)
{

	global $vbulletin,$permissions,$db,$messagecounters;
	$decode_params = php_xmlrpc_decode($xmlrpc_params);






	$show['messagelist'] = true;
	if(!$vbulletin->options['enablepms'])
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

	$vbulletin->GPC['pmid'] = intval($decode_params[0]);

	if(isset($decode_params[1]) && strlen($decode_params[1]) > 0){
		$report_message= mobiquo_encode($decode_params[1],'to_local');
	} else {
		$report_message= mobiquo_encode("report",'to_local');
	}
	$vbulletin->GPC['reason'] = 	$report_message;
	$reportthread = ($rpforumid = $vbulletin->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
	$reportemail = ($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']);

	if (!$reportthread AND !$reportemail)
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}


	$pminfo = $db->query_first_slave("
				SELECT
					pm.*, pmtext.*
				FROM " . TABLE_PREFIX . "pm AS pm
				LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
				WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.pmid=" . $vbulletin->GPC['pmid'] . "
			");

	if (!$pminfo)
	{
		return new xmlrpcresp(new xmlrpcval(false,'boolean'));
	}

	require_once(DIR . '/includes/class_reportitem.php');
	$reportobj = new vB_ReportItem_PrivateMessage($vbulletin);
	$reportobj->set_extrainfo('pm', $pminfo);
	$perform_floodcheck = $reportobj->need_floodcheck();

	if ($perform_floodcheck)
	{
		$reportobj->perform_floodcheck_precommit();
	}




	if ($vbulletin->GPC['reason'] == '')
	{
		return new xmlrpcresp(new xmlrpcval(false,'boolean'));
	}

	$reportobj->do_report($vbulletin->GPC['reason'], $pminfo);

	$url =& $vbulletin->url;
	return new xmlrpcresp(new xmlrpcval(true,'boolean'));


}
?>
