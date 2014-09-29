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
require_once(CWD1.'/include/functions_logout_user.php');


$phrasegroups = array();


$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

if(file_exists('./global.php'.SUFFIX)){
	require_once('./global.php'.SUFFIX);
} else {
	require_once('./global.php');
}
if(file_exists(DIR. '/includes/functions_login.php'.SUFFIX)){
	require_once(DIR . '/includes/functions_login.php'.SUFFIX);
} else {
	require_once(DIR . '/includes/functions_login.php');
}


function login_func($params) {
	return mobiquo_login($params);
}
function login_mod_func($params) {
	return mobiquo_login($params,'modcplogin');
}
function mobiquo_login($params,$mode = null) {
	global $xmlrpcerruser;

	$decode_params = php_xmlrpc_decode($params);
	$username = mobiquo_encode($decode_params[0],'to_local');
	$username = str_replace('&trade;', chr(153), $username);
	$password = mobiquo_encode($decode_params[1],'to_local');
	global $vbulletin;
	global $config;


	$vbulletin->GPC['logintype'] = $mode;
	if ($username && $password)
	{

		$return  = array();
		$vbulletin->GPC['username'] =$username;
		if(strlen($password) == 32){
			$vbulletin->GPC['md5password'] = $password;
			$vbulletin->GPC['md5password_utf'] = $password;
		} else {
			$vbulletin->GPC['password'] = $password;
		}


		$strikes = mobiquo_verify_strike_status($vbulletin->GPC['username']);
		if ($vbulletin->GPC['username'] == '')
		{
			$return = array( 7,'invalid user name/id.');
			return return_fault($return);
		}

		if(!$strikes){

			$return_text= "Wrong username or password. You have used up your failed login quota! Please wait 15 minutes before trying again.";
			$return =new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
											  'result_text' =>  new xmlrpcval(mobiquo_encode($return_text),'base64')
			),"struct");
			return new xmlrpcresp($return);
		}
		// make sure our user info stays as whoever we were (for example, we might be logged in via cookies already)
		$original_userinfo = $vbulletin->userinfo;

		if (!verify_authentication($vbulletin->GPC['username'], $vbulletin->GPC['password'], $vbulletin->GPC['md5password'], $vbulletin->GPC['md5password_utf'], $vbulletin->GPC['cookieuser'], true))
		{
			exec_strike_user($vbulletin->userinfo['username']);
			if ($vbulletin->options['usestrikesystem'])
			{
				$return_text= sprintf("You have entered an invalid username or password. Please enter the correct details and try again. Don't forget that the password is case sensitive.
You have used %3s out of 5 login attempts. After all 5 have been used, you will be unable to login for 15 minutes",$strikes['strikes'] + 1);
			}
			else
			{
				$return_text= "You have entered an invalid username or password. Please press the back button, enter the correct details and try again. Don't forget that the password is case sensitive.";
			}

			$return =new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
											  'result_text' =>  new xmlrpcval(mobiquo_encode($return_text),'base64')
			),"struct");
			return new xmlrpcresp($return);
		} else {

			exec_unstrike_user($vbulletin->GPC['username']);


			$member_groups = preg_split("/,/",$vbulletin->userinfo['membergroupids']);
			$group_block = false;

			if(trim($config['allowed_usergroup']) != ""){
				$group_block = true;
				$support_group = explode(",", $config['allowed_usergroup']);

				foreach($support_group as $support_group_id){

					if($vbulletin->userinfo['usergroupid'] == $support_group_id || in_array($support_group_id,$member_groups)) {
						$group_block = false;
					}

				}
			}

			$return_group_ids = array();
			foreach($member_groups AS $id)
			{
				if($id){
					array_push($return_group_ids,
					new xmlrpcval($id,"string")
					);
				}
			}
			array_push($return_group_ids,new xmlrpcval($vbulletin->userinfo['usergroupid'],"string")
			);

			if($group_block){
				$return_text = 'The usergroup you belong to does not have permission to login. Please contact your administrator. ';
				$return = new xmlrpcresp(
				new xmlrpcval(
				array(
      	                  'result' => new xmlrpcval(false,"boolean"),
						  'result_text' =>  new xmlrpcval(mobiquo_encode($return_text),'base64'),
						  'can_pm' => new xmlrpcval(false,"boolean"),
						  'can_send_pm' => new xmlrpcval(false,"boolean"),	
						  'can_moderate' => new xmlrpcval(false,"boolean"),	
					      'can_upload_avatar' =>  new xmlrpcval(false,"boolean"),	
					
						  'max_attachment' => new xmlrpcval($vbulletin->options['attachlimit'],"int"),
				),
                              "struct"
                              )
                              );

			} else {
				process_new_login($vbulletin->GPC['logintype'], $vbulletin->GPC['cookieuser'], $vbulletin->GPC['cssprefs']);
				$vbulletin->session->save();
				$permissions = cache_permissions($vbulletin->userinfo);
				$pmcount = $vbulletin->db->query_first("
					SELECT
						COUNT(pmid) AS pmtotal
					
					FROM " . TABLE_PREFIX . "pm AS pm
					WHERE pm.userid = '" . $vbulletin->userinfo['userid'] . "'
				");
				$mobiquo_can_upload_avatar = ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']);
				$pmcount['pmtotal'] = intval($pmcount['pmtotal']);
				$show['pmmainlink'] = ($vbulletin->options['enablepms'] AND ($vbulletin->userinfo['permissions']['pmquota'] OR $pmcount['pmtotal']));
				$show['pmtracklink'] = ($vbulletin->userinfo['permissions']['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm']);
				$show['pmsendlink'] = ($vbulletin->userinfo['permissions']['pmquota']);
				if (!($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])){
					if (!($vbulletin->usergroupcache["$usergroupid"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
					{
						$result_text = "You have been banned at this forum";
					} else {
						$result_text = "You do not have permission to access this forum.";
					}
				}
				if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['passwordexpires'])
				{

					$fetch_userinfo_options = (
					FETCH_USERINFO_AVATAR | FETCH_USERINFO_LOCATION |
					FETCH_USERINFO_PROFILEPIC | FETCH_USERINFO_SIGPIC |
					FETCH_USERINFO_USERCSS | FETCH_USERINFO_ISFRIEND
					);
					$mobiquo_userinfo = mobiquo_verify_id('user', $vbulletin->userinfo['userid'], 1, 1, $fetch_userinfo_options);
					$passworddaysold = floor((TIMENOW - $mobiquo_userinfo['passworddate']) / 86400);

					if ($passworddaysold >= $vbulletin->userinfo['permissions']['passwordexpires'])
					{
						$result_text = "Your password is ".$passworddaysold." days old, and has therefore expired.";
					}
				}



    $max_png_size = $vbulletin->userinfo['attachmentpermissions']['png']['permissions'] ? $vbulletin->userinfo['attachmentpermissions']['png']['size'] : 0;
    $max_jpg_size = $vbulletin->userinfo['attachmentpermissions']['jpeg']['permissions'] ? $vbulletin->userinfo['attachmentpermissions']['jpeg']['size'] : 0;

				$return = new xmlrpcresp(
				new xmlrpcval(
				array(
	      	                  'result' => new xmlrpcval(true,"boolean"),
							  'result_text' =>  new xmlrpcval($result_text,'base64'),
						      'usergroup_id' =>new xmlrpcval($return_group_ids,"array"),
							  'can_pm' => new xmlrpcval($show['pmmainlink'],"boolean"),
							  'can_send_pm' => new xmlrpcval(($show['pmmainlink'] AND $show['pmsendlink']),"boolean"),	
					  		  'can_moderate' => new xmlrpcval(can_moderate(),"boolean"),
				        	  'can_upload_avatar' =>  new xmlrpcval($mobiquo_can_upload_avatar,"boolean"),	
							  'max_attachment' => new xmlrpcval($vbulletin->options['attachlimit'],"int"),
							  'max_png_size' => new xmlrpcval(intval($max_png_size), "int"),
							  'max_jpg_size' => new xmlrpcval(intval($max_jpg_size), "int"),
				),
                         "struct"));
			}
		}
	}
	else
	{  $return =new xmlrpcval( array( 'result' => new xmlrpcval(false,"boolean"),
									  'result_text' =>  new xmlrpcval('','base64'),
								      'can_pm' => new xmlrpcval(false,"boolean"),
									  'can_send_pm' => new xmlrpcval(false,"boolean"),	
									  'can_moderate' => new xmlrpcval(false,"boolean"),	
									  'can_upload_avatar' =>  new xmlrpcval(false,"boolean"),
									  'max_attachment' => new xmlrpcval($vbulletin->options['attachlimit'],"int"),
	),"struct");

	}

	return $return;

}


?>