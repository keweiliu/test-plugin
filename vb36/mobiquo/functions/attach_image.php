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

@set_time_limit(0);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('GET_EDIT_TEMPLATES', true);
define('THIS_SCRIPT', 'newattachment');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(

);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_file.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

function remove_attach_func($xmlrpc_params)
{

	global $vbulletin;
	global $db;
	global $xmlrpcerruser;
	global $forumperms,$permissions;

	$decode_params = php_xmlrpc_decode($xmlrpc_params);
	$attachmentid = intval($decode_params[0]);
	$posthash =  $decode_params[2];
	$forumid =  intval($decode_params[1]);

	if (!$vbulletin->userinfo['userid']) // Guests can not post attachments
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	// Variables that are reused in templates


	$foruminfo = mobiquo_verify_id('forum', $forumid, 1, 1);


	$forumperms = fetch_permissions($foruminfo['forumid']);

	// No permissions to post attachments in this forum or no permission to view threads in this forum.
	if (empty($vbulletin->userinfo['attachmentextensions']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	if ((!$postid AND !$foruminfo['allowposting']) OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew'])) // newthread.php
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_STANDARD);



	$attachdata->condition = "attachmentid = $attachmentid";
	//if ($postid)
	//{
	//	$attachdata->condition .= " AND (attachment.postid = $postid OR attachment.posthash = '" . $db->escape_string($posthash) . "')";
	//}
	///	else
	//{
	$attachdata->condition .= " AND attachment.posthash = '" . $db->escape_string($posthash) . "'";
	//}
	if ($attachdata->delete())
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(true,"boolean"),

						 'result_text' =>  new xmlrpcval('','base64')),"struct"));
	} else {
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),

						 'result_text' =>  new xmlrpcval('','base64')),"struct"));
	}

}

?>