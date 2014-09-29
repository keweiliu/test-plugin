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

define('THIS_SCRIPT', 'member');
define('CSRF_PROTECTION', false);
define('BYPASS_STYLE_OVERRIDE', 1);
// get special phrase groups
$phrasegroups = array(
);

// get special data templates from the datastore
$specialtemplates = array(
);

// pre-cache templates used by all actions
$globaltemplates = array(
);


// pre-cache templates used by specific actions
$actiontemplates = array();


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

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################
function get_user_info_func($xmlrpc_params){
	global $permissions,$vbulletin,$show,$db;


	$params = php_xmlrpc_decode($xmlrpc_params);
	if(!$params[0])
	{
		$return = array(2,'no  user id param.');
		return return_fault($return);
	}


	$user_name =    mobiquo_encode($params[0],'to_local');
	$user_id   = get_userid_by_name($user_name);

	if(!$user_id){

		$return = array(7,'invalid user id');
		return return_fault($return);
	}

	$vbulletin->GPC['userid'] = $user_id;
	if (!($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']))
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}


	if (!$vbulletin->GPC['userid'])
	{
		$return = array(20,$vbulletin->GPC['username'].'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}


	$userinfo = mobiquo_verify_id('user', $vbulletin->GPC['userid'], 1, 1, 47);
	if(!is_array($userinfo)){
		return $userinfo;
	}
	if ($userinfo['usergroupid'] == 4 AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	$show['vcard'] = ($vbulletin->userinfo['userid'] AND $userinfo['showvcard']);


	// display user info
	$userperms = cache_permissions($userinfo, false);

	$mobiquo_return_array = array();



	$mobiquo_user_online = false;
	$mobiquo_user_online = (fetch_online_status($userinfo, true)) ? true : false;
	$mobiquo_return_display_text = "";
	if($prepared['usertitle']){
		$mobiquo_return_display_text .= $prepared['usertitle'];
	}

	if($userinfo['postorder'] == 0){
		$mobiquo_postorder = 'DATE_ASC';
	}
	else{
		$mobiquo_postorder = 'DATE_DESC';
	}

	$mobiquo_can_ban = true;
	require_once(DIR . '/includes/adminfunctions.php');
	require_once(DIR . '/includes/functions_banning.php');
	if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canbanusers')))
	{
		$mobiquo_can_ban = false;
	}

	// check that user has permission to ban the person they want to ban
	if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		if (can_moderate(0, '', $userinfo['userid'], $userinfo['usergroupid'] . (trim($userinfo['membergroupids']) ? ",$userinfo[membergroupids]" : ''))
		OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
		OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']
		OR ($userinfo['usergroupid'] == 5 OR $userinfo['ismoderator']))
		{
			$mobiquo_can_ban = false;
		}
	} else {
		if ($userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
		OR ($userinfo['usergroupid'] == 5 OR $userinfo['ismoderator']))
		{
			$mobiquo_can_ban = false;
		}

	}

	$user_action = "";
	if($userinfo['where']){
		$user_action = strip_tags($userinfo['action'].": ".$userinfo['where']);
	} else {
		$user_action = strip_tags($userinfo['action']);
	}
	if(strpos($userinfo['where'],'mobiquo/mobiquo.php')){
		$user_action = 'via Tapatalk Forum App';
	}

	$avatarurl = fetch_avatar_url($userinfo['userid']);
	$userinfo['avatarurl'] = $avatarurl[0];
		
		
	$profilefield_categories = array(0 => array());
	$profilefields_result = $db->query_read_slave("
		SELECT pf.profilefieldid, pf.profilefieldcategoryid, pf.required, pf.type, pf.data, pf.def, pf.height
		FROM " . TABLE_PREFIX . "profilefield AS pf
		LEFT JOIN " . TABLE_PREFIX . "profilefieldcategory AS pfc ON(pfc.profilefieldcategoryid = pf.profilefieldcategoryid)
		WHERE pf.form = 0 " . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), "
				AND pf.hidden = 0") . "
		ORDER BY pfc.displayorder, pf.displayorder
	");
	while ($profilefield = $db->fetch_array($profilefields_result))
	{
		$profilefield_categories["$profilefield[profilefieldcategoryid]"][] = $profilefield;
	}
	$mobiquo_return_array = array();
	foreach ($profilefield_categories AS $profilefieldcategoryid => $profilefields)
	{
		foreach ($profilefields AS $profilefield)
		{
			exec_switch_bg();
			fetch_profilefield_display($profilefield, $userinfo["field$profilefield[profilefieldid]"]);
			$mobiquo_return_array[] =  new xmlrpcval(array(
										"name" => new xmlrpcval(mobiquo_encode($profilefield[title]),'base64'),
										"value" => new xmlrpcval(mobiquo_encode($profilefield[value]),'base64')
			)
			,"struct");
		}
	}

	if($userinfo['usertitle']){
		$mobiquo_return_display_text .= $userinfo['usertitle'];
	}

	$return_user = array(
		      'thread_sort_order'    => new xmlrpcval($mobiquo_postorder,'string'),
              'user_id'=>new xmlrpcval($userinfo[userid],'string'),
              'user_name'=>new xmlrpcval(mobiquo_encode($userinfo[username]),'base64'),
              'reg_time'=>new xmlrpcval(mobiquo_iso8601_encode($userinfo[joindate]-$vbulletin->options['hourdiff'],$vbulletin->userinfo['tzoffset']),'dateTime.iso8601'),
              'post_count'=>new xmlrpcval($userinfo[posts],'int'),
              'custom_fields_list' =>new xmlrpcval($mobiquo_return_array,'array'),
			  'lastactivity_time' =>new xmlrpcval(mobiquo_iso8601_encode($userinfo[lastactivity]-$vbulletin->options['hourdiff'],$vbulletin->userinfo['tzoffset']),'dateTime.iso8601'),
              'can_ban' => new xmlrpcval($mobiquo_can_ban  ,"boolean"),
              'is_online' => new xmlrpcval($mobiquo_user_online  ,"boolean"),
    		  'can_ban' => new xmlrpcval($userinfo[isfriend]  ,"boolean"),
               'accept_pm' => new xmlrpcval(true  ,"boolean"),
        	  'current_activity' => new xmlrpcval(mobiquo_encode($user_action),'base64'),
              'display_text' => new xmlrpcval(mobiquo_encode($mobiquo_return_display_text),'base64'),
	);


	if($userinfo['avatarurl']){
		$return_user['icon_url']=new xmlrpcval(get_icon_real_url($userinfo['avatarurl']),'string');
	}

	else {
		$return_user['icon_url'] = new xmlrpcval('','string');
	}

	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}


	return  new xmlrpcresp(
	new xmlrpcval( $return_user,"struct"));
}

?>
