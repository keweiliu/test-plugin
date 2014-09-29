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
function mobiquo_verify_id($idname, &$id, $alert = true, $selall = false, $options = 0)
{
	// verifies an id number and returns a correct one if it can be found
	// returns 0 if none found
	global $vbulletin, $threadcache, $vbphrase;

	if (empty($vbphrase["$idname"]))
	{
		$vbphrase["$idname"] = $idname;
	}
	$id = intval($id);
	switch ($idname)
	{
		case 'thread': $fault_code = 6; $fault_string = "invalid thread id is $id";break;
		case 'forum':  $fault_code =4; $fault_string = "invalid forum id is $id";break;
		case 'post':break;
		case 'user':  $fault_code =7;  $fault_string = "invalid user id is $id";break;

	}
	if (empty($id))
	{
		if ($alert)
		{
			return     return_fault(array($fault_code,$fault_string));
		}
		else
		{
			return 0;
		}
	}

	$selid = ($selall ? '*' : $idname . 'id');

	switch ($idname)
	{
		case 'thread':
		case 'forum':
		case 'post':
			$function = 'fetch_' . $idname . 'info';
			$tempcache = $function($id);
			if (!$tempcache AND $alert)
			{
				return return_fault(array($fault_code,$fault_string));
			}
			return ($selall ? $tempcache : $tempcache[$idname . 'id']);

		case 'user':
			$tempcache = fetch_userinfo($id, $options);
			if (!$tempcache AND $alert)
			{
				return array();
			}
			return ($selall ? $tempcache : $tempcache[$idname . 'id']);

		default:
			if (!$check = $vbulletin->db->query_first("SELECT $selid FROM " . TABLE_PREFIX . "$idname WHERE $idname" . "id = $id"))
			{
				if ($alert)
				{
					return return_fault(array($fault_code,$fault_string));
				}
				return ($selall ? array() : 0);
			}
			else
			{
				return ($selall ? $check : $check["$selid"]);
			}
	}
}

function mobiquo_encode($str,$mode = ''){
	global $stylevar;
	
	if (empty($str)) return $str;
	
	$in_encoding = $stylevar['charset'];
	$target_encoding = 'UTF-8';
	$support_encoding = false;


	if($mode == 'to_local'){
		$target_encoding = $stylevar['charset'];
		$in_encoding = 'UTF-8';
		if(function_exists('mb_list_encodings') ){
			$encode_list = mb_list_encodings();
			foreach($encode_list as $encode){
				if(strtolower($encode) == strtolower($target_encoding)){
					$support_encoding  = true;
					break;
				}
			}
		}


	} else {
		$str =strip_tags($str);
		if(function_exists('mb_list_encodings') ){
			$encode_list = mb_list_encodings();
			foreach($encode_list as $encode){
				if(strtolower($encode) == strtolower($in_encoding)){
					$support_encoding  = true;
					break;
				}
			}
		}
	}
	if(strtolower($target_encoding) == strtolower($in_encoding) ){
		if($mode !='to_local'){
			$str =  unescape_htmlentitles($str);
		}
		$str = escape_latin_code($str,$target_encoding);
		return $str;
	}else{
		if ($mode == 'to_local'){
			if(function_exists('mb_convert_encoding')){
				if($support_encoding == true){
					$str =  @mb_convert_encoding($str,'HTML-ENTITIES','UTF-8');
				}
			}

		}


		if (function_exists('mb_convert_encoding') AND $support_encoding == true AND $encoded_data = @mb_convert_encoding($str, $target_encoding, $in_encoding))
		{

			// if($mode != 'to_local'){
			$encoded_data =escape_latin_code($encoded_data ,$target_encoding);
			if($mode != 'to_local'){

				$encoded_data = unescape_htmlentitles($encoded_data);
			}
		}
		else if (function_exists('iconv') AND $encoded_data = @iconv($in_encoding, $target_encoding, $str))
		{
			// return $encoded_data;
			$encoded_data =escape_latin_code($encoded_data ,$target_encoding);
			if($mode != 'to_local'){
				$encoded_data = unescape_htmlentitles($encoded_data);
			}
		}
		else {
			$str = escape_latin_code($str ,$target_encoding);
			if($target_encoding == 'ISO-8859-1' && $mode == 'to_local'){
				$str = utf8_decode($str);
			}
			if($mode != 'to_local'){
				return unescape_htmlentitles($str);
			} else {
					
				return $str;
			}
		}
		return  $encoded_data;
	}
}
function mobiquo_get_user_icon($userid){
	global $vbulletin;
	
	if (empty($userid)) return '';
	
	$fetch_userinfo_options = (
	    FETCH_USERINFO_AVATAR
	);
	$userinfo = mobiquo_verify_id('user',$userid, 1, 1, $fetch_userinfo_options);
	if(!is_array($userinfo)){
		$userinfo = array();
	}
	$icon_url = "";
	if($vbulletin->options['avatarenabled']){
		fetch_avatar_from_userinfo($userinfo,true,false);

		if($userinfo[avatarurl]){

			$icon_url = get_icon_real_url($userinfo[avatarurl]);
		} else {
			$icon_url = '';
		}
	}
	return $icon_url;
}

function get_vb_message($tempname){
	if (!function_exists('fetch_phrase'))
	{
		require_once(DIR . '/includes/functions_misc.php');
	}
	$phrase =fetch_phrase('redirect_friendspending','frontredirect', 'redirect_', true, false, $languageid, false);


	return $phrase;
}

function get_post_from_id($postid,$html_content){
	global $vbulletin;
	global $db;
	global $xmlrpcerruser;
	global $forumperms;
	global $permissions;


	$post = $db->query_first_slave("
	SELECT
		post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
		user.*, userfield.*, usertextfield.*,
		" . iif($foruminfo['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
		IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
		" . iif($vbulletin->options['avatarenabled'], ',avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight') . "
		,editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline, editlog.reason AS edit_reason,
		postparsed.pagetext_html, postparsed.hasimages,
		sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
		sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight
		" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
		$hook_query_fields
	FROM " . TABLE_PREFIX . "post AS post
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
	LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
	LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
	" . iif($foruminfo['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
	" . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
	LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
	LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
	LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
	LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
	$hook_query_joins
	WHERE post.postid = $postid
");

	// Tachy goes to coventry
	if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
	{
		// do not show post if part of a thread from a user in Coventry and bbuser is not mod
		eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
	}
	if (in_coventry($post['userid']) AND !can_moderate($threadinfo['forumid']))
	{
		// do not show post if posted by a user in Coventry and bbuser is not mod
		eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
	}



	$postbit_factory = new vB_Postbit_Factory();
	$postbit_factory->registry =& $vbulletin;
	$postbit_factory->forum =& $foruminfo;
	$postbit_factory->thread =& $threadinfo;
	$postbit_factory->cache = array();
	$postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$postbit_obj =& $postbit_factory->fetch_postbit('post');
	$postbit_obj->highlight =& $replacewords;
	$postbit_obj->cachable = (!$post['pagetext_html'] AND $vbulletin->options['cachemaxage'] > 0 AND (TIMENOW - ($vbulletin->options['cachemaxage'] * 60 * 60 * 24)) <= $threadinfo['lastpost']);
	$mobiquo_attachments = $post[attachments];

	$postbits = $postbit_obj->construct_postbit($post);

	// save post to cache if relevant
	if ($postbit_obj->cachable)
	{
		/*insert query*/
		$db->shutdown_query("
			REPLACE INTO " . TABLE_PREFIX . "postparsed (postid, dateline, hasimages, pagetext_html, styleid, languageid)
			VALUES (
			$post[postid], " .
			intval($threadinfo['lastpost']) . ", " .
			intval($postbit_obj->post_cache['has_images']) . ", '" .
			$db->escape_string($postbit_obj->post_cache['text']) . "', " .
			intval(STYLEID) . ", " .
			intval(LANGUAGEID) . "
				)
		");
	}



	$return_attachments = array();

	if(is_array($mobiquo_attachments)){

		foreach($mobiquo_attachments as $attach) {
			$attachment_url = "";
			preg_match_all('/href=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\"/',unhtmlspecialchars($post[imageattachmentlinks]),$image_attachment_matchs);
			preg_match_all('/href=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\"/',unhtmlspecialchars($post[otherattachments]),$other_attachment_matchs);
			preg_match_all('/href=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\".+img.+?src=\"(.+attachmentid='.$attach[attachmentid].'.+?)\"/s',unhtmlspecialchars($post[thumbnailattachments]),$thumbnail_attachment_matchs);
			preg_match_all('/src=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\"/',unhtmlspecialchars($post[imageattachments]),$small_image_attachment_matchs);


			$type = "other";
			if($image_attachment_matchs[1][0]) {
				$type = "image";
				$attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$image_attachment_matchs[1][0];
			}
			if($other_attachment_matchs[1][0]){
				$type = "other";
				$attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$other_attachment_matchs[1][0];
			}
			if($small_image_attachment_matchs[1][0]) {
				$type = "image";
				$attachment_thumbnail_url= $GLOBALS[vbulletin]->options[bburl].'/'.$small_image_attachment_matchs[1][0];
				$attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$small_image_attachment_matchs[1][0];
			}
			if($thumbnail_attachment_matchs[1][0]){
				$type = "image";
				$attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$thumbnail_attachment_matchs[1][0];
				$attachment_thumbnail_url = $GLOBALS[vbulletin]->options[bburl].'/'.$thumbnail_attachment_matchs[2][0];
			}
			if(empty($attachment_url)){
				$attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'."attachment.php?attachmentid=".$attach[attachmentid];
			}
			$return_attachment = new xmlrpcval(
			array('filename'=>new xmlrpcval($attach[filename],"base64"),
           	          'filesize'=>new xmlrpcval($attach[filesize],"int"),
           	     	  'url'=>new xmlrpcval(unhtmlspecialchars($attachment_url),"string"),
			     	  'thumbnail_url'=>new xmlrpcval(unhtmlspecialchars($attachment_thumbnail_url),"string"),
           	     	  'content_type'=>new xmlrpcval($type,"string")),"struct");
			array_push($return_attachments,$return_attachment);
		}
	}

	if($html_content){
		$a = fetch_tag_list();
		unset($a['option']['quote']);
		unset($a['no_option']['quote']);
		unset($a['option']['url']);
		unset($a['no_option']['url']);

		$vbulletin->options['wordwrap']  = 0;
		 
		$post_content =preg_replace("/\[\/img\]/siU",'[/img1]',$post['pagetext']);
		$bbcode_parser =& new vB_BbCodeParser($vbulletin, $a);
		$post_content = $bbcode_parser->parse( $post_content, $thread[forumid], false);
		$post_content =preg_replace("/\[\/img1\]/siU",'[/IMG]',$post_content);
		 
		$post_content =  htmlspecialchars_uni($post_content);
		$post_content = mobiquo_encode(post_content_clean_html($post_content));

	} else {
		$post_content  =   mobiquo_encode(post_content_clean($post['pagetext']));
	}


	if(SHORTENQUOTE == 1 && preg_match('/^(.*\[quote\])(.+)(\[\/quote\].*)$/si', $post_content)){
		$new_content = "";
		$segments = preg_split('/(\[quote\].+\[\/quote\])/isU',$post_content,-1, PREG_SPLIT_DELIM_CAPTURE);

		foreach($segments as $segment){
			$short_quote = $segment;
			if(preg_match('/^(\[quote\])(.+)(\[\/quote\])$/si', $segment,$quote_matches)){
				if(function_exists('mb_strlen') && function_exists('mb_substr')){
					if(mb_strlen($quote_matches[2], 'UTF-8') > 170){
						$short_quote = $quote_matches[1].mb_substr($quote_matches[2],0,150,'UTF-8').$quote_matches[3];
					}
				}
				else{
					if(strlen($quote_matches[2]) > 170){
						$short_quote = $quote_matches[1].substr($quote_matches[2],0,150).$quote_matches[3];
					}
				}
				$new_content .= $short_quote;
			} else {
				$new_content .= $segment;
			}
		}

		$post_content = $new_content;
	}
	$mobiquo_can_edit = false;
	if(isset($post['editlink']) AND strlen($post['editlink']) > 0){
		$mobiquo_can_edit = true;
	}
	$mobiquo_user_online = (fetch_online_status($post, false)) ? true : false;

	$return_post = array('topic_id'=>new xmlrpcval($post['threadid'],"string"),
                                     'post_id'=>new xmlrpcval($post['postid'],"string"),
                                     'post_title'=>new xmlrpcval(mobiquo_encode($post['title']),"base64"),
                                     'post_content'=>new xmlrpcval($post_content,"base64"),
                                     'post_author_id'=>new xmlrpcval($post['userid'],"string"),
                                     'post_author_name'=>new xmlrpcval(mobiquo_encode($post['postusername']),"base64"),
                                     'post_time'=>new xmlrpcval(mobiquo_iso8601_encode($post['dateline']-$vbulletin->options['hourdiff'],$vbulletin->userinfo['tzoffset']),'dateTime.iso8601'),
                                     'post_count' => new xmlrpcval($post['postcount'],"int"),
       								 'can_delete' => new xmlrpcval($show['deleteposts'],"boolean"),
                          			 'can_edit' => new xmlrpcval($mobiquo_can_edit,"boolean"),
       								 'is_online' => new xmlrpcval($mobiquo_user_online,"boolean"),
       								 'allow_smilie' => new xmlrpcval($post['allowsmilie'],"boolean"), 
       								 'allow_smilies' => new xmlrpcval($post['allowsmilie'],"boolean"), 
                                    'attachments'=>new xmlrpcval($return_attachments,"array")
	);

	$return_post['icon_url'] = new xmlrpcval('','string');
	if($post[avatarurl]){
		$return_post['icon_url']=new xmlrpcval(get_icon_real_url($post[avatarurl]),'string');
	}
	$return_post[attachment_authority] = new xmlrpcval(0,"int");
	if(!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment'])){
		$return_post[attachment_authority] = new xmlrpcval(4,"int");
	}
	return $return_post;
}

function fetch_avatar_from_userinfo(&$userinfo, $thumb = false, $returnfakeavatar = true){
	global $vbulletin, $stylevar;

	$avwidth = '';
	$avheight = '';
		
	if ($userinfo['avatarid'])
	{
		$avatarurl = $userinfo['avatarpath'];
	}
	else
	{
		if ($userinfo['hascustomavatar'] AND $vbulletin->options['avatarenabled'] AND ($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'] OR $userinfo['adminavatar']))
		{
			if ($vbulletin->options['usefileavatar'])
			{
				$avatarurl = $vbulletin->options['avatarurl'] . "/avatar$userinfo[userid]_$userinfo[avatarrevision].gif";
			}
			else
			{
				$avatarurl = 'image.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]&amp;dateline=$userinfo[avatardateline]";
			}
				
			$userinfo['avatarurl'] = $avatarurl;
		}
		else
		{
			$avatarurl = '';
		}
	}
	if ($avatarurl == '')
	{
		$show['avatar'] = false;
	}
	else
	{
		$show['avatar'] = true;
	}

}
?>