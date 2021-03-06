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




// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'moderation');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user', 'forumdisplay', 'inlinemod');



// get special data templates from the datastore
$specialtemplates = array(
	'iconcache',
	'noavatarperms'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'USERCP_SHELL',
	'usercp_nav_folderbit',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'viewthreads' => array(
		'forumdisplay_sortarrow',
		'moderation_threads',
		'threadadmin_imod_menu_thread',
		'threadbit',
		'threadbit_deleted',
),
	'viewposts' => array(
		'moderation_posts',
		'search_results_postbit',
		'threadadmin_imod_menu_post',
),
	'viewvms' => array(
		'moderation_filter',
		'moderation_visitormessages',
		'memberinfo_visitormessage',
		'memberinfo_visitormessage_deleted',
		'memberinfo_visitormessage_ignored',
		'memberinfo_css',
),
	'viewgms' => array(
		'moderation_filter',
		'moderation_groupmessages',
		'memberinfo_css',
		'socialgroups_css',
		'socialgroups_message',
		'socialgroups_message_deleted',
		'socialgroups_message_ignored',
),
	'viewdiscussions' => array(
		'moderation_filter',
		'moderation_groupdiscussions',
		'memberinfo_css',
		'socialgroups_css',
		'socialgroups_discussion',
		'socialgroups_discussion_deleted',
		'socialgroups_discussion_ignored',
),
	'viewpcs' => array(
		'moderation_filter',
		'moderation_picturecomments',
		'picturecomment_css',
		'picturecomment_message_moderatedview',
),
	'viewpics' => array(
		'moderation_filter',
		'moderation_picturebit',
		'moderation_pictures',
		'picturecomment_css',
),
);

$actiontemplates['none'] =& $actiontemplates['viewthreads'];




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
if(file_exists(DIR . '/includes/functions_forumlist.php'.SUFFIX)){
	require_once(DIR . '/includes/functions_forumlist.php'.SUFFIX);
} else {
	require_once(DIR . '/includes/functions_forumlist.php');
}


// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################
function get_moderate_post_func($xmlrpc_params){

	global $vbulletin,$permissions,$db;




	$params = php_xmlrpc_decode($xmlrpc_params);
	$_REQUEST['do'] = 'viewposts';


	cache_moderators($vbulletin->userinfo['userid']);
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'daysprune'  => TYPE_INT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML,
		'type'       => TYPE_NOHTML,
	));

	// Values that are reused in templates
	$sortfield  =& $vbulletin->GPC['sortfield'];
	$perpage    =& $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$daysprune  =& $vbulletin->GPC['daysprune'];
	$type       =& $vbulletin->GPC['type'];
	$perpage  = 20;
	$pagenumber = 1;

	$type = 'moderated';
	$table = 'moderation';
	$permission = 'canmoderateposts';
	if (!can_moderate(0, 'canmoderateposts'))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}
	$postselect = '';
	$postjoin = '';
	$postfrom = "FROM " . TABLE_PREFIX . "moderation AS moderation
		INNER JOIN " . TABLE_PREFIX . "post AS post ON (moderation.postid = post.postid)";
	$posttype = 'reply';


	if ($vbulletin->options['threadmarking'])
	{
		cache_ordered_forums(1);
	}

	$modforums = array();
	if ($forumid)
	{
		require_once(DIR . '/includes/functions_misc.php');
		$forums = fetch_child_forums($forumid, 'ARRAY');
		$forums[] = $forumid;
		$forums = array_flip($forums);
	}
	else
	{
		$forums = $vbulletin->forumcache;
	}

	foreach ($forums AS $mforumid => $null)
	{
		$forumperms = $vbulletin->userinfo['forumpermissions']["$mforumid"];
		if (can_moderate($mforumid, $permission) AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
		{
			$modforums[] = $mforumid;
		}
	}

	if (empty($modforums))
	{
		return new xmlrpcresp(new xmlrpcval( array('result' => new xmlrpcval(false,"boolean"),
										'is_login_mod' => new xmlrpcval(true,"boolean"),
										 'result_text' =>  new xmlrpcval('security error (user may not have permission to access this feature)','base64')),"struct"));
	}

	$show['inlinemod'] = true;
	$url = SCRIPTPATH;


	if (!$daysprune)
	{
		$daysprune = ($vbulletin->userinfo['daysprune']) ? $vbulletin->userinfo['daysprune'] : 30;
	}
	$datecut = ($daysprune != -1) ? "AND $table.dateline >= " . (TIMENOW - ($daysprune * 86400)) : '';


	// complete form fields on page
	$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
	$daysprunesel = array($daysprunesel => 'selected="selected"');

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$vbulletin->GPC['sortorder'] = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	switch ($sortfield)
	{
		case 'title':
		case 'dateline':
		case 'username':
			$sqlsortfield = 'post.' . $sortfield;
			break;
		default:
			$handled = false;
			($hook = vBulletinHook::fetch_hook('moderation_posts_sort')) ? eval($hook) : false;
			if (!$handled)
			{
				$sqlsortfield = 'post.dateline';
				$sortfield = 'dateline';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('moderation_postsquery_postscount')) ? eval($hook) : false;

	$postscount = $db->query_first_slave("
		SELECT COUNT(*) AS posts
		$hook_query_fields
		$postfrom
		$hook_query_joins
		INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		WHERE type = '$posttype'
			AND forumid IN (" . implode(', ', $modforums) . ")
			$datecut
			$hook_query_where
	");
			$totalposts = $postscount['posts'];

			// set defaults
			sanitize_pageresults($totalposts, $pagenumber, $perpage, 200, 4);

			// display posts
			$limitlower = ($pagenumber - 1) * $perpage;
			$limitupper = ($pagenumber) * $perpage;

			if ($limitupper > $totalposts)
			{
				$limitupper = $totalposts;
				if ($limitlower > $totalposts)
				{
					$limitlower = ($totalposts - $perpage) - 1;
				}
			}
			if ($limitlower < 0)
			{
				$limitlower = 0;
			}
			if ($totalposts)
			{
				$hook_query_fields = $hook_query_joins = $hook_query_where = '';
				($hook = vBulletinHook::fetch_hook('moderation_postsquery_postid')) ? eval($hook) : false;

				$lastread = array();
				$postids = array();
				// Fetch ids
				$posts = $db->query_read_slave("
			SELECT post.postid, thread.forumid
			$hook_query_fields
			$postfrom
			$hook_query_joins
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			WHERE type = '$posttype'
				AND forumid IN (" . implode(', ', $modforums) . ")
				$datecut
				$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder
			LIMIT $limitlower, $perpage
		");
				while ($post = $db->fetch_array($posts))
				{
					$postids[] = $post['postid'];
					// get last read info for each thread
					if (empty($lastread["$post[forumid]"]))
					{
						if ($vbulletin->options['threadmarking'])
						{
							$lastread["$post[forumid]"] = max($vbulletin->forumcache["$post[forumid]"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
						}
						else
						{
							$lastread["$post[forumid]"] = max(intval(fetch_bbarray_cookie('forum_view', $post['forumid'])), $vbulletin->userinfo['lastvisit']);
						}
					}
				}
				$limitlower++;

				$hasposts = true;
				$postbits = '';
				$pagenav = '';
				$counter = 0;
				$toread = 0;

				$vbulletin->options['showvotes'] = intval($vbulletin->options['showvotes']);

				$hook_query_fields = $hook_query_joins = $hook_query_where = '';

				$posts = $db->query_read_slave("
			SELECT
				post.postid, post.title AS posttitle, post.dateline AS postdateline,
				post.iconid AS posticonid, post.pagetext, post.visible,
				IF(post.userid = 0, post.username, user.username) AS username,
				thread.threadid, thread.title AS threadtitle, thread.iconid AS threadiconid, thread.replycount,
				IF(thread.views = 0, thread.replycount + 1, thread.views) AS views, thread.firstpostid,
				thread.pollid, thread.sticky, thread.open, thread.lastpost, thread.forumid, thread.visible AS thread_visible,
				user.userid
				$postselect
				" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], ', threadread.readtime AS threadread') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			$postjoin
			" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")") . "
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
			$hook_query_joins
			WHERE post.postid IN (" . implode(', ', $postids) . ")
			$hook_query_where
			ORDER BY $sqlsortfield $sqlsortorder
		");
			unset($sqlsortfield, $sqlsortorder);

			require_once(DIR . '/includes/functions_forumdisplay.php');
			$return_array = array();

			while ($post = $db->fetch_array($posts))
			{
				$item['forumtitle'] = $vbulletin->forumcache["$item[forumid]"]['title'];

				// do post folder icon
				if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
				{
					// new if post hasn't been read or made since forum was last read
					$isnew = ($post['postdateline'] > $post['threadread'] AND $post['postdateline'] > $vbulletin->forumcache["$post[forumid]"]['forumread']);
				}
				else
				{
					$isnew = ($post['postdateline'] > $vbulletin->userinfo['lastvisit']);
				}

				if ($isnew)
				{
					$post['post_statusicon'] = 'new';
					$post['post_statustitle'] = $vbphrase['unread'];
				}
				else
				{
					$post['post_statusicon'] = 'old';
					$post['post_statustitle'] = $vbphrase['old'];
				}

				// allow icons?
				$post['allowicons'] = $vbulletin->forumcache["$post[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'];

				// get POST icon from icon cache
				$post['posticonpath'] =& $vbulletin->iconcache["$post[posticonid]"]['iconpath'];
				$post['posticontitle'] =& $vbulletin->iconcache["$post[posticonid]"]['title'];

				// show post icon?
				if ($post['allowicons'])
				{
					// show specified icon
					if ($post['posticonpath'])
					{
						$post['posticon'] = true;
					}
					// show default icon
					else if (!empty($vbulletin->options['showdeficon']))
					{
						$post['posticon'] = true;
						$post['posticonpath'] = $vbulletin->options['showdeficon'];
						$post['posticontitle'] = '';
					}
					// do not show icon
					else
					{
						$post['posticon'] = false;
						$post['posticonpath'] = '';
						$post['posticontitle'] = '';
					}
				}
				// do not show post icon
				else
				{
					$post['posticon'] = false;
					$post['posticonpath'] = '';
					$post['posticontitle'] = '';
				}

				$post['pagetext'] = preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siU', '', $post['pagetext']);

				// get first 200 chars of page text
				$post['pagetext'] = htmlspecialchars_uni(fetch_censored_text(trim(fetch_trimmed_title(strip_bbcode($post['pagetext'], 1), 200))));

				// get post title
				if ($post['posttitle'] == '')
				{
					$post['posttitle'] = fetch_trimmed_title($post['pagetext'], 50);
				}
				else
				{
					$post['posttitle'] = fetch_censored_text($post['posttitle']);
				}

				// format post text
				$post['pagetext'] = nl2br($post['pagetext']);

				// get info from post
				$post = process_thread_array($post, $lastread["$post[forumid]"], $post['allowicons']);

				$show['managepost'] = iif(can_moderate($post['forumid'], 'candeleteposts') OR can_moderate($post['forumid'], 'canremoveposts'), true, false);
				$show['approvepost'] = (can_moderate($post['forumid'], 'canmoderateposts')) ? true : false;
				$show['managethread'] = (can_moderate($post['forumid'], 'canmanagethreads')) ? true : false;
				$show['disabled'] = ($show['managethread'] OR $show['managepost'] OR $show['approvepost']) ? false : true;

				$show['moderated'] = (!$post['visible'] OR (!$post['thread_visible'] AND $post['postid'] == $post['firstpostid'])) ? true : false;
				$show['spam'] = ($show['moderated'] AND $post['spamlog_postid']) ? true : false;

				if ($post['pdel_userid'])
				{
					$post['del_username'] =& $post['pdel_username'];
					$post['del_userid'] =& $post['pdel_userid'];
					$post['del_reason'] = fetch_censored_text($post['pdel_reason']);
					$show['deleted'] = true;
				}
				else if ($post['tdel_userid'])
				{
					$post['del_username'] =& $post['tdel_username'];
					$post['del_userid'] =& $post['tdel_userid'];
					$post['del_reason'] = fetch_censored_text($post['tdel_reason']);
					$show['deleted'] = true;
				}
				else
				{
					$show['deleted'] = false;
				}
				$authorinfo = fetch_userinfo($post['userid'], 3);
					
				//vb36only
				cache_permissions($authorinfo, false);

				fetch_avatar_from_userinfo($authorinfo,true,false);
				if($authorinfo[avatarurl]){
					$icon_url=get_icon_real_url($authorinfo['avatarurl']);
				} else {
					$icon_url = '';
				}
				$is_deleted = false;
				if($post['visible'] == 2){
					$is_deleted = true;
				}
				$is_approved = true;
				if($post['visible'] == 0 or (!$thread['visible'] AND $post['postcount'] == 1)){
					$is_approved = false;
				}

				$return_post =new xmlrpcval(  array('topic_id'=>new xmlrpcval($post['threadid'],"string"),
                                             'post_id'=>new xmlrpcval( $post['postid'],"string"),
                                             'reply_number' => new xmlrpcval($post['replycount'],"int"),
                                             'post_position' => new xmlrpcval(0,"int"),
                                             'post_title'=>new xmlrpcval( mobiquo_encode($post['posttitle']),"base64"),
                                             'topic_title'=>new xmlrpcval( mobiquo_encode($post['threadtitle']),"base64"),
                                             'short_content'=>new xmlrpcval( mobiquo_encode(mobiquo_chop($post['pagetext'])),"base64"),
                                             'post_author_id'=>new xmlrpcval( $post['userid'],"string"),
                                                     'icon_url'=>new xmlrpcval($icon_url ,"string"),
						   'is_approved' => new xmlrpcval($is_approved,"boolean"),
	   						'is_deleted' => new xmlrpcval($is_deleted,"boolean"),
						   'can_approve' => new xmlrpcval($show['approvepost'],"boolean"),
							'can_move'  =>  new xmlrpcval($show['managethread'],"boolean"),

                 							 'can_delete' => new xmlrpcval($show['managepost'],"boolean"),
                                             'post_author_name'=>new xmlrpcval( mobiquo_encode($post['username']),"base64"),
                                             'post_time'=>new xmlrpcval(mobiquo_iso8601_encode( $post['postdateline']-$vbulletin->options['hourdiff'],$vbulletin->userinfo['tzoffset']),"dateTime.iso8601"),
                                             'forum_id'=>new xmlrpcval( $post['forumid'],"string"),
                                             'forum_name'=>new xmlrpcval(mobiquo_encode($post['forumtitle']),"base64")),"struct");
				$return_array[] = $return_post;
				exec_switch_bg();

			}

			$db->free_result($posts);
			unset($postids);
			}
			else
			{
				$totalposts = 0;
			}

			if (defined('NOSHUTDOWNFUNC'))
			{
				exec_shut_down();
			}
			return new xmlrpcresp(
			new xmlrpcval(
			array(
  					    	'total_post_num' => new xmlrpcval($totalposts,'int'),
                            'posts' => new xmlrpcval($return_array,'array'),
			),
                    'struct'
                    )
                    );
}

?>
