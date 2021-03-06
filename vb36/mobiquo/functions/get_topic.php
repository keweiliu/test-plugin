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


define('THIS_SCRIPT', 'forumdisplay');
define('CSRF_PROTECTION', false);
// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();


$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();




// ####################### PRE-BACK-END ACTIONS ##########################
if(file_exists('./global.php'.SUFFIX)){
	require_once('./global.php'.SUFFIX);
} else {
	require_once('./global.php');
}
if(file_exists(DIR . '/includes/functions_forumlist.php'.SUFFIX)){
	require_once(DIR . '/includes/functions_forumlist.php'.SUFFIX);
} else {
	require_once(DIR . '/includes/functions_forumlist.php');
}
if(file_exists(DIR . '/includes/functions_bigthree.php'.SUFFIX)){
	require_once(DIR . '/includes/functions_bigthree.php'.SUFFIX);
} else {
	require_once(DIR . '/includes/functions_bigthree.php');
}
if(file_exists(DIR . '/includes/functions_forumdisplay.php'.SUFFIX)){
	require_once(DIR . '/includes/functions_forumdisplay.php'.SUFFIX);
} else {
	require_once(DIR . '/includes/functions_forumdisplay.php');
}
if(file_exists(DIR . '/includes/functions_user.php'.SUFFIX)){
	require_once(DIR . '/includes/functions_user.php'.SUFFIX);
} else {
	require_once(DIR . '/includes/functions_user.php');
}


function get_topic_func( $xmlrpc_params){
	global $vbulletin,$show;
	global $db;
	global $vbphrase, $stylevar;
	global $newthreads, $dotthreads, $perpage, $ignore;


	global $xmlrpcerruser;
	$params = php_xmlrpc_decode($xmlrpc_params);
	// ######################### REQUIRE BACK-END ############################

	// #######################################################################
	// ######################## START MAIN SCRIPT ############################
	// #######################################################################
	if(!$params[0])
	{
		$return = array(2,'no forum id param.');
		return return_fault($return);
	}
	$vbulletin->GPC['forumid'] = $params[0];
	$threadinfo = array();
	$foruminfo = array();

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
	}
	else if ($vbulletin->GPC['forumid'])
	{
		$foruminfo = mobiquo_verify_id('forum', $vbulletin->GPC['forumid'], 0, 1);
		if(!is_array($foruminfo)){
			$return = array(4,'invalid forum id ' .  $vbulletin->GPC['forumid']);
			return return_fault($return);
		}
		$forumid =& $foruminfo['forumid'];

		if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
		{
			$codestyleid =& $foruminfo['styleid'];
		}
	}

	if(isset($params[1]) && $params[1] && $params[1] >= 0) {
		$start_num = $params[1] ;
	}else{
		$start_num = 0;
	}
	if(isset($params[2]) && $params[2]){
		$end_num   = $params[2];
	} else {
		$end_num = 19;
	}
	$api_mode = $params[3];

	$thread_num = $end_num-$start_num+1;
	$return_thread = array();
	$return_post = array();
		
	$unread_announce_num = 0;
	$total_sticky_num = 0;
	$unread_sticky_num = 0;
	// get permission to view forum
	$_permsgetter_ = 'forumdisplay';
	$forumperms = fetch_permissions($foruminfo['forumid']);




	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);

	}
	// disable thread preview if we can't view threads
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
	{
		$vbulletin->options['threadpreview'] = 0;
	}

	$mobiquo_can_post = true;
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) OR !$foruminfo['allowposting'])
	{
		$mobiquo_can_post = false;
	}
	$mobiquo_can_upload = false;
	if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'] AND !empty($vbulletin->userinfo['attachmentextensions'])){
		$mobiquo_can_upload = true;
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// get vbulletin->iforumcache - for use by makeforumjump and forums list
	// fetch the forum even if they are invisible since its needed
	// for the title but we'll unset that further down
	// also fetch subscription info for $show['subscribed'] variable
	cache_ordered_forums(1, 1, $vbulletin->userinfo['userid']);

	$return_prefixes = array();
	$permcheck = true;



	$show['newthreadlink'] = iif(!$show['search_engine'] AND $foruminfo['allowposting'], true, false);
	$show['threadicons'] = iif ($foruminfo['allowicons'], true, false);
	$show['threadratings'] = iif ($foruminfo['allowratings'], true, false);
	$show['subscribed_to_forum'] = ($vbulletin->forumcache["$foruminfo[forumid]"]['subscribeforumid'] != '' ? true : false);

	if (!$daysprune)
	{
		if ($vbulletin->userinfo['daysprune'])
		{
			$daysprune = $vbulletin->userinfo['daysprune'];
		}
		else
		{
			$daysprune = iif($foruminfo['daysprune'], $foruminfo['daysprune'], 30);
		}
	}

	// ### GET FORUMS, PERMISSIONS, MODERATOR iCACHES ########################


	// get an array of child forum ids for this forum
	// define max depth for forums display based on $vbulletin->options[forumhomedepth]


	// #############################################################################
	// get read status for this forum and children


	// admin tools
	$show['adminoptions'] = can_moderate($foruminfo['forumid']);
	$show['post_new_announcement'] = can_moderate($foruminfo['forumid'], 'canannounce');
	$show['addmoderator'] = ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']);

	$curforumid = $foruminfo['forumid'];
	//construct_forum_jump();

	/////////////////////////////////
	if ($foruminfo['cancontainthreads'] or true)
	{

		/////////////////////////////////
		if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
		{
			$foruminfo['forumread'] = $vbulletin->forumcache["$foruminfo[forumid]"]['forumread'];
			$lastread = max($foruminfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
		}
		else
		{
			$bbforumview = intval(fetch_bbarray_cookie('forum_view', $foruminfo['forumid']));
			$lastread = max($bbforumview, $vbulletin->userinfo['lastvisit']);
		}

		// Inline Moderation
		$show['movethread'] = (can_moderate($forumid, 'canmanagethreads')) ? true : false;
		$show['deletethread'] = (can_moderate($forumid, 'candeleteposts') OR can_moderate($forumid, 'canremoveposts')) ? true : false;
		$show['approvethread'] = (can_moderate($forumid, 'canmoderateposts')) ? true : false;
		$show['openthread'] = (can_moderate($forumid, 'canopenclose')) ? true : false;
		$show['inlinemod'] = ($show['movethread'] OR $show['deletethread'] OR $show['approvethread'] OR $show['openthread']) ? true : false;
		$show['spamctrls'] = ($show['inlinemod'] AND $show['deletethread']);
		$url = $show['inlinemod'] ? SCRIPTPATH : '';

		// fetch popup menu


		// get announcements

		$announcebits = '';
		if ($show['threadicons'] AND $show['inlinemod'])
		{
			$announcecolspan = 6;
		}
		else if (!$show['threadicons'] AND !$show['inlinemod'])
		{
			$announcecolspan = 4;
		}
		else
		{
			$announcecolspan = 5;
		}

		$mindate = TIMENOW - 2592000; // 30 days

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';




		$vbulletin->options['oneannounce'] = false;
		$announcements = $db->query_read_slave("
				SELECT
					announcement.announcementid, startdate, title, announcement.views,announcement.pagetext,
					user.username, user.userid, user.usertitle, user.customtitle, user.usergroupid,
					IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
					" . (($vbulletin->userinfo['userid']) ? ", NOT ISNULL(announcementread.announcementid) AS readannounce" : "") . "
					$hook_query_fields
				FROM " . TABLE_PREFIX . "announcement AS announcement
				" . (($vbulletin->userinfo['userid']) ? "LEFT JOIN " . TABLE_PREFIX . "announcementread AS announcementread ON (announcementread.announcementid = announcement.announcementid AND announcementread.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = announcement.userid)
				$hook_query_joins
				WHERE startdate <= " . TIMENOW . "
					AND enddate >= " . TIMENOW . "
					AND " . fetch_forum_clause_sql($foruminfo['forumid'], 'forumid') . "
					$hook_query_where
				ORDER BY startdate DESC, announcement.announcementid DESC
				" . iif($vbulletin->options['oneannounce'], "LIMIT 1")
					);
					while ($announcement = $db->fetch_array($announcements))
					{
						fetch_musername($announcement);
						$announcement['title'] = fetch_censored_text($announcement['title']);
						$announcement['postdate'] = vbdate($vbulletin->options['dateformat'], $announcement['startdate']);
						if ($announcement['readannounce'] OR $announcement['startdate'] <= $mindate)
						{
							$announcement['statusicon'] = 'old';
						}
						else
						{	$unread_announce_num ++;
						$announcement['statusicon'] = 'new';
						}
						if( $api_mode == 'ANN'){
							$announcement['views'] = vb_number_format($announcement['views']);
							$announcementidlink = iif(!$vbulletin->options['oneannounce'] , "&amp;a=$announcement[announcementid]");
							if($vbulletin->options['threadpreview'] == 0){
								$announcement[pagetext] = '';
							}

								
							$return_post = new xmlrpcval( array( 'forum_id'=>new xmlrpcval($thread[forumid],'string'),
						'topic_id'=>new xmlrpcval($announcement[announcementid],'string'),
						'topic_title'=>new xmlrpcval(mobiquo_encode($announcement[title]),'base64'),
						'topic_author_id'=>new xmlrpcval($announcement[userid],'string'),
						'topic_author_name'=>new xmlrpcval(mobiquo_encode($announcement[username]),'base64'),
						'reply_number'=>new xmlrpcval(0,'int'),
						'view_number'=>new xmlrpcval($announcement[views],'int'), 
					    'issubscribed' => new xmlrpcval(false,'boolean'),
						'is_subscribed' => new xmlrpcval(false,'boolean'),
						'can_subscribe' => new xmlrpcval(false,'boolean'),
                        'new_post' =>new xmlrpcval(false,'boolean'),
                        'icon_url'=>new xmlrpcval($icon_url ,"string"),
                    	'is_closed' => new xmlrpcval(true,"boolean"),
                        'can_move' =>  new xmlrpcval(false,"boolean"),
                    	'can_close' => new xmlrpcval(false ,"boolean"),
                    	'can_delete' => new xmlrpcval(false,"boolean"),
                    	'can_sticky' => new xmlrpcval(false,"boolean"),
                    	'can_stick' => new xmlrpcval(false,"boolean"),	
                        'can_approve' => new xmlrpcval(false,"boolean"),
                    	'is_approve' => new xmlrpcval(true,"boolean"),
						'last_reply_user'=>new xmlrpcval(mobiquo_encode($announcement[username]),'base64'),
						'short_content'=>new xmlrpcval(mobiquo_encode(mobiquo_chop(post_content_clean($announcement['pagetext']))),'base64'),
						'last_reply_time'=>new xmlrpcval(mobiquo_iso8601_encode($announcement[startdate]-$vbulletin->options['hourdiff'],$vbulletin->userinfo['tzoffset']),'dateTime.iso8601')),
					                            'struct'
					                            );

					                            array_push($return_thread,$return_post);
						}
					}
					if( $api_mode == 'ANN'){
						// add to the navbits
						if (defined('NOSHUTDOWNFUNC'))
						{
							exec_shut_down();
						}
						return new xmlrpcresp(
						new xmlrpcval(
						array(
										'forum_id'  => new xmlrpcval($foruminfo['forumid'] ,"string"),
           								'forum_name'  => new xmlrpcval(mobiquo_encode($foruminfo['title']),"base64"),
              							'can_close' => new xmlrpcval(false ,"boolean"),
                    					'can_delete' => new xmlrpcval(false,"boolean"),
                                        'can_move' =>  new xmlrpcval(false,"boolean"),
                    					'can_sticky' => new xmlrpcval(false,"boolean"),	
                    	                'can_stick' => new xmlrpcval(false,"boolean"),	
              							'can_upload' =>  new xmlrpcval(false,'boolean'),
              							'can_post' => new xmlrpcval(false ,"boolean"),
              							'prefixes'=>new xmlrpcval($return_prefixes,"array"),
                                        'total_topic_num' => new xmlrpcval(count($return_thread),'int'),
                                        'topics' => new xmlrpcval($return_thread,'array'),
						),
                                'struct'
                                )
                                );
					}

					if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
					{
						$limitothers = "AND postuserid = " . $vbulletin->userinfo['userid'] . " AND " . $vbulletin->userinfo['userid'] . " <> 0";
					}
					else
					{
						$limitothers = '';
					}

					if (can_moderate($foruminfo['forumid']))
					{
						$redirectjoin = "LEFT JOIN " . TABLE_PREFIX . "threadredirect AS threadredirect ON(thread.open = 10 AND thread.threadid = threadredirect.threadid)";
					}
					else
					{
						$redirectjoin = '';
					}

					// filter out deletion notices if can't be seen
					if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice'] OR can_moderate($foruminfo['forumid']))
					{
						$canseedelnotice = true;
						$deljoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND deletionlog.type = 'thread')";
					}
					else
					{
						$canseedelnotice = false;
						$deljoin = '';
					}

					// remove threads from users on the global ignore list if user is not a moderator
					if ($Coventry = fetch_coventry('string') AND !can_moderate($foruminfo['forumid']))
					{
						$globalignore = "AND postuserid NOT IN ($Coventry) ";
					}
					else
					{
						$globalignore = '';
					}

					// look at thread limiting options
					$stickyids = '';
					$stickycount = 0;
					if ($daysprune != -1)
					{
						if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
						{
							$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
				"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ")";
							$datecut = " AND (thread.lastpost >= " . (TIMENOW - ($daysprune * 86400)) . " OR tachythreadpost.lastpost >= " . (TIMENOW - ($daysprune * 86400)) . ")";
						}
						else
						{
							$datecut = "AND lastpost >= " . (TIMENOW - ($daysprune * 86400));
							$tachyjoin = "";
						}
						$show['noposts'] = false;
					}
					else
					{
						$tachyjoin = "";
						$datecut = "";
						$show['noposts'] = true;
					}

					// complete form fields on page
					$daysprunesel = iif($daysprune == -1, 'all', $daysprune);
					$daysprunesel = array($daysprunesel => 'selected="selected"');

				


					// default sorting methods
					if (empty($sortfield))
					{
						$sortfield = $foruminfo['defaultsortfield'];
					}
					if (empty($vbulletin->GPC['sortorder']))
					{
						$vbulletin->GPC['sortorder'] = $foruminfo['defaultsortorder'];
					}

					// look at sorting options:
					if ($vbulletin->GPC['sortorder'] != 'asc')
					{
						$sqlsortorder = 'DESC';
						$order = array('desc' => 'selected="selected"');
						$vbulletin->GPC['sortorder'] = 'desc';
					}
					else
					{
						$sqlsortorder = '';
						$order = array('asc' => 'selected="selected"');
					}

					$sqlsortfield2 = '';

					switch ($sortfield)
					{
						case 'title':
							$sqlsortfield = 'thread.title';
							break;
						case 'lastpost':
							$sqlsortfield = 'lastpost';
							break;
						case 'replycount':
						case 'views':
						case 'postusername':
							$sqlsortfield = $sortfield;
							break;
						case 'voteavg':
							if ($foruminfo['allowratings'])
							{
								$sqlsortfield = 'voteavg';
								$sqlsortfield2 = 'votenum';
								break;
							}
						case 'dateline':
							$sqlsortfield = 'thread.dateline';
							break;
							// else, use last post
						default:
							$handled = false;

							if (!$handled)
							{
								$sqlsortfield = 'lastpost';
								$sortfield = 'lastpost';
							}
					}
					$sort = array($sortfield => 'selected="selected"');

					if (!can_moderate($forumid, 'canmoderateposts'))
					{
						if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice']))
						{
							$visiblethreads = " AND visible = 1 ";
						}
						else
						{
							$visiblethreads = " AND visible IN (1,2)";
						}
					}
					else
					{
						$visiblethreads = " AND visible IN (0,1,2)";
					}

					$hook_query_fields = $hook_query_joins = $hook_query_where = '';


					# Include visible IN (0,1,2) in order to hit upon the 4 column index
					$threadscount = $db->query_first_slave("
				SELECT COUNT(*) AS threads, SUM(IF(thread.lastpost > $lastread AND open <> 10, 1, 0)) AS newthread
				$hook_query_fields
				FROM " . TABLE_PREFIX . "thread AS thread
				$tachyjoin
				$hook_query_joins
				WHERE forumid = $foruminfo[forumid]
					AND sticky = 0
					$visiblethreads
					$globalignore
					$limitothers
					$datecut
					$hook_query_where
			");
					$totalthreads = $threadscount['threads'];
					$thread_list['total_topic_num'] = $totalthreads;

					if($totalthreads == 0 and $api_mode != 'TOP') {
						return new xmlrpcresp(
						new xmlrpcval(
						array(
                						'can_upload' =>  new xmlrpcval($mobiquo_can_upload,'boolean'),
                						'can_post' => new xmlrpcval($mobiquo_can_post ,"boolean"),
                						'prefixes'=>new xmlrpcval($return_prefixes,"array"),
                                        'total_topic_num' => new xmlrpcval($totalthreads,'int'),
						),
                                'struct'
                                )
                                );
					}
					if($totalthreads < $start_num  or $start_num > $end_num) {

						$return = array( 3,'out of range');
						return return_fault($return);
					}

					$newthreads = $threadscount['newthread'];

					// set defaults
					//sanitize_pageresults($totalthreads, $pagenumber, $perpage, 200, $vbulletin->options['maxthreads']);

					// get number of sticky threads for the first page
					// on the first page there will be the sticky threads PLUS the $perpage other normal threads
					// not quite a bug, but a deliberate feature!
					//	if ($pagenumber == 1 OR  $vbulletin->options['showstickies'] )
					//	{
					$stickies = $db->query_read_slave("
			SELECT thread.threadid, lastpost, open
			FROM " . TABLE_PREFIX . "thread AS thread
			WHERE forumid = $foruminfo[forumid]
				AND sticky = 1
				$visiblethreads
				$limitothers
				$globalignore
		");
				$stickycount = 0;
				while ($thissticky = $db->fetch_array($stickies))
				{
					$stickycount++;
					if ($thissticky['lastpost'] >= $lastread AND $thissticky['open'] <> 10)
					{


						$unread_sticky_num ++ ;
						$newthreads++;
					}
					$stickyids .= ",$thissticky[threadid]";
				}
				$db->free_result($stickies);
				unset($thissticky, $stickies);
				//	}


				$limitlower = $start_num;
				$limitupper = $end_num;

				if ($limitupper > $totalthreads)
				{
					$limitupper = $totalthreads;
					if ($limitlower > $totalthreads)
					{
						$limitlower = ($totalthreads - $perpage) - 1;
					}
				}
				if ($limitlower < 0)
				{
					$limitlower = 0;
				}

				if ($foruminfo['allowratings'])
				{
					$vbulletin->options['showvotes'] = intval($vbulletin->options['showvotes']);
					$votequery = "
			IF(votenum >= " . $vbulletin->options['showvotes'] . ", votenum, 0) AS votenum,
			IF(votenum >= " . $vbulletin->options['showvotes'] . " AND votenum > 0, votetotal / votenum, 0) AS voteavg,
		";
				}
				else
				{
					$votequery = '';
				}


				$previewfield = "post.pagetext AS preview,";
				$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";


				if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
				{
					$lastpost_info1 = ",IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost";
					$lastpost_info2 = "IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost, " .
						"IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter, " .
						"IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid";

					$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
						"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
				}
				else
				{
					$lastpost_info1 = "";
					$lastpost_info2 = "thread.lastpost, thread.lastposter, thread.lastpostid";
					$tachyjoin = "";
				}
				$hook_query_fields = $hook_query_joins = $hook_query_where = '';

				$getthreadids = $db->query_read_slave("
				SELECT " . iif($sortfield == 'voteavg', $votequery) . " thread.threadid
				$lastpost_info1
				$hook_query_fields
				FROM " . TABLE_PREFIX . "thread AS thread
				$tachyjoin
				$hook_query_joins
				WHERE forumid = $foruminfo[forumid]
					AND sticky = 0
					$visiblethreads
					$globalignore
					$limitothers
					$datecut
					$hook_query_where
					AND thread.open != 10
				ORDER BY sticky DESC, $sqlsortfield $sqlsortorder
				LIMIT $start_num, $thread_num
			");




					$ids = '';
					while ($thread = $db->fetch_array($getthreadids))
					{
						$ids .= ',' . $thread['threadid'];
					}
					if($api_mode == 'TOP') {
						$ids = $stickyids;
						$totalthreads    =  $stickycount;
					}


					$db->free_result($getthreadids);
					unset ($thread, $getthreadids);


					$hook_query_fields = $hook_query_joins = $hook_query_where = '';


					$threads = $db->query_read_slave("
		SELECT $votequery $previewfield
			thread.threadid, thread.title AS threadtitle, thread.forumid, pollid, open, replycount, postusername, postuserid, thread.iconid AS threadiconid,
			$lastpost_info2, thread.dateline, IF(views<=replycount, replycount+1, views) AS views, notes, thread.visible, sticky, votetotal, thread.attach,
			hiddencount, deletedcount
			" . (($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid']) ? ", NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed" : "") . "
			" . ($deljoin ? ", deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason" : "") . "
			" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? ", threadread.readtime AS threadread" : "") . "
			" . ($redirectjoin ? ", threadredirect.expires" : "") . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "thread AS thread
		$deljoin
			" . (($vbulletin->options['threadsubscribed'] AND $vbulletin->userinfo['userid']) ?  " LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON(subscribethread.threadid = thread.threadid AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . " AND canview = 1)" : "") . "
			" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
			$previewjoin
			$tachyjoin
			$redirectjoin
			$hook_query_joins
		WHERE thread.threadid IN (0$ids) $hook_query_where
		ORDER BY sticky DESC, $sqlsortfield $sqlsortorder
	");
			unset($limitothers, $delthreadlimit, $deljoin, $datecut, $votequery, $sqlsortfield, $sqlsortorder, $threadids, $sqlsortfield2);

			// Get Dot Threads
			$dotthreads = fetch_dot_threads_array($ids);
			if ($vbulletin->options['showdots'] AND $vbulletin->userinfo['userid'])
			{
				$show['dotthreads'] = true;
			}
			else
			{
				$show['dotthreads'] = false;
			}

			unset($ids);

			// prepare sort things for column header row:
			$sorturl = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumid&amp;daysprune=$daysprune";
			$oppositesort = iif($vbulletin->GPC['sortorder'] == 'asc', 'desc', 'asc');

			if ($totalthreads > 0 OR $stickyids)
			{
				if ($totalthreads > 0)
				{
					$limitlower++;
				}
				// check to see if there are any threads to display. If there are, do so, otherwise, show message

				if ($vbulletin->options['threadpreview'] > 0)
				{
					// Get Buddy List
					$buddy = array();
					if (trim($vbulletin->userinfo['buddylist']))
					{
						$buddylist = preg_split('/( )+/', trim($vbulletin->userinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
						foreach ($buddylist AS $buddyuserid)
						{
							$buddy["$buddyuserid"] = 1;
						}
					}
					DEVDEBUG('buddies: ' . implode(', ', array_keys($buddy)));
					// Get Ignore Users
					$ignore = array();
					if (trim($vbulletin->userinfo['ignorelist']))
					{
						$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
						foreach ($ignorelist AS $ignoreuserid)
						{
							if (!$buddy["$ignoreuserid"])
							{
								$ignore["$ignoreuserid"] = 1;
							}
						}
					}
					DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));
				}

				$show['threads'] = true;
				$threadbits = '';
				$threadbits_sticky = '';
				$counter = 0;
				$toread = 0;
				$thread_list = array('topics'=>array());


				while ($thread = $db->fetch_array($threads))
				{ // AND $counter++ < $perpage)
					// build thread data
					$thread_replycount = $thread[replycount];
					$thread_views      = $thread[views];
					$thread = process_thread_array($thread, $lastread, $foruminfo['allowicons']);
					$realthreadid = $thread['realthreadid'];

					if ($thread['sticky'])
					{
						$threadbit =& $threadbits_sticky;
					}
					else
					{
						$threadbit =& $threadbits;
					}



					// Soft Deleted Thread
					if ($thread['visible'] != 2 || can_moderate($forumid) ? true : false)
					{
							
						if (!$thread['visible'])
						{
							$thread['hiddencount']++;
						}
						$show['moderated'] = ($thread['hiddencount'] > 0 AND can_moderate($forumid, 'canmoderateposts')) ? true : false;
						$show['deletedthread'] = ($thread['deletedcount'] > 0 AND $canseedelnotice) ? true : false;

						if(file_exists(DIR . '/includes/functions_bigthree.php'.SUFFIX)){
							require_once(DIR . '/includes/functions_bigthree.php'.SUFFIX);
						} else {
							require_once(DIR . '/includes/functions_bigthree.php');
						}
							
						$coventry = fetch_coventry('string');


						$addinfo = $db->query_first_slave("
							SELECT post.pagetext,  thread.lastposter
							FROM " . TABLE_PREFIX . "thread AS thread
						 LEFT JOIN ". TABLE_PREFIX."post AS post on post.postid = thread.firstpostid
							WHERE thread.threadid =$thread[threadid]
						
							AND post.visible = 1
						". ($coventry ? "AND post.userid NOT IN ($coventry)" : '') . "
			         	");


						if($show['gotonewpost']){
							$mobiquo_new_post = 1;
						} else{
							$mobiquo_new_post = 0;
						}
						$fetch_userinfo_options = (
						FETCH_USERINFO_AVATAR
						);

						$authorinfo = fetch_userinfo($thread[postuserid], 3);

						//vb36only
						cache_permissions($authorinfo, false);

						fetch_avatar_from_userinfo($authorinfo,true,false);

						if($authorinfo[avatarurl]){
							$icon_url=get_icon_real_url($authorinfo['avatarurl']);
						} else {
							$icon_url = '';
						}

						if($vbulletin->options['threadpreview'] == 0){
							$addinfo[pagetext] = '';
						}
						$is_deleted = false;
						if($thread['visible'] == 2){
							$is_deleted = true;
						}
						$is_approved = true;
						if($thread['visible'] == 0){
							$is_approved = false;
						}
						$mobiquo_isclosed = iif($thread['open'], false, true);
						$mobiquo_attach = iif(($thread['attach']>0),1,0);
						$return_post = new xmlrpcval( array( 'forum_id'=>new xmlrpcval($thread[forumid],'string'),
						'topic_id'=>new xmlrpcval($thread[threadid],'string'),
						'topic_title'=>new xmlrpcval(mobiquo_encode($thread[threadtitle]),'base64'),
						'topic_author_id'=>new xmlrpcval($thread[postuserid],'string'),
						'topic_author_name'=>new xmlrpcval(mobiquo_encode($thread[postusername]),'base64'),
						'reply_number'=>new xmlrpcval($thread_replycount,'int'),
						'view_number'=>new xmlrpcval($thread_views,'int'), 
					   	 'issubscribed' => new xmlrpcval($thread[issubscribed],'boolean'),
						'is_subscribed' => new xmlrpcval($thread[issubscribed],'boolean'),
						'is_deleted' => new xmlrpcval($is_deleted,'boolean'),
						'can_subscribe' => new xmlrpcval(true,'boolean'),
                       			 'new_post' =>new xmlrpcval($mobiquo_new_post,'boolean'),
                      			  'icon_url'=>new xmlrpcval($icon_url ,"string"),
                    				'is_closed' => new xmlrpcval($mobiquo_isclosed,"boolean"),
                    				'attachment' => new xmlrpcval($mobiquo_attach,'string'),	
                      			  'can_move' =>  new xmlrpcval($show['movethread'],"boolean"),
                    				'can_close' => new xmlrpcval($show['openthread'] ,"boolean"),
						 'is_sticky' => new xmlrpcval($thread[sticky],"boolean"),
                    				'can_delete' => new xmlrpcval($show['deletethread'],"boolean"),
                    				'can_sticky' => new xmlrpcval($show['movethread'],"boolean"),
                    			'can_stick' => new xmlrpcval($show['movethread'],"boolean"),
                       			 'can_approve' => new xmlrpcval($show['approvethread'],"boolean"),
                   			 	'is_approve' => new xmlrpcval($is_approved,"boolean"),
     						'is_approved' => new xmlrpcval($is_approved,"boolean"),
						'last_reply_user'=>new xmlrpcval(mobiquo_encode($addinfo[lastposter]),'base64'),
						'short_content'=>new xmlrpcval(mobiquo_encode(mobiquo_chop(post_content_clean($addinfo['pagetext']))),'base64'),
						'last_reply_time'=>new xmlrpcval(mobiquo_iso8601_encode($thread[lastpost]-$vbulletin->options['hourdiff'],$vbulletin->userinfo['tzoffset']),'dateTime.iso8601')),
					                            'struct'
					                            );

					                            array_push($return_thread,$return_post);
					}

				}
				$db->free_result($threads);
				unset($thread, $counter);


				//$pagenav = construct_page_nav($pagenumber, $perpage, $totalthreads, 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumid", ""
				//	. (!empty($vbulletin->GPC['perpage']) ? "&amp;pp=$perpage" : '')
				//	. (!empty($vbulletin->GPC['prefixid']) ? "&amp;prefixid=" . $vbulletin->GPC['prefixid'] : '')
				//	. (!empty($vbulletin->GPC['sortfield']) ? "&amp;sort=$sortfield" : "")
				//	. (!empty($vbulletin->GPC['sortorder']) ? "&amp;order=" . $vbulletin->GPC['sortorder'] : '')
				//	. (!empty($vbulletin->GPC['daysprune']) ? "&amp;daysprune=$daysprune" : '')
				//);
				//   $thread_list['total_topic_num'] = $totalthreads;

			}
			unset($threads, $dotthreads);

			// get colspan for bottom bar
			$foruminfo['bottomcolspan'] = 6;
			if ($foruminfo['allowicons'])
			{
				$foruminfo['bottomcolspan']++;
			}
			if ($foruminfo['allowratings'])
			{
				$foruminfo['bottomcolspan']++;
			}

			$show['threadslist'] = true;

			/////////////////////////////////
	} // end forum can contain threads
	else
	{
		$show['threadslist'] = false;
	}
	/////////////////////////////////
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}

	$mobiquo_require_prefix  = false;


	return new xmlrpcresp(
	new xmlrpcval(
	array(
		
										'unread_announce_count' => new xmlrpcval($unread_announce_num ,'int'),
								        'unread_sticky_count' =>  new xmlrpcval($unread_sticky_num ,'int'),
              							'can_close' => new xmlrpcval($show['openthread'] ,"boolean"),
            							'forum_id'  => new xmlrpcval($foruminfo['forumid'] ,"string"),
           								'forum_name'  => new xmlrpcval(mobiquo_encode($foruminfo['title']),"base64"),
                    					'can_delete' => new xmlrpcval($show['deletethread'],"boolean"),
                                        'can_move' =>  new xmlrpcval($show['movethread'],"boolean"),
                    					'can_sticky' => new xmlrpcval($show['movethread'],"boolean"),	
                    					'can_stick' => new xmlrpcval($show['movethread'],"boolean"),
              							'can_upload' =>  new xmlrpcval($mobiquo_can_upload,'boolean'),
              							'can_post' => new xmlrpcval($mobiquo_can_post ,"boolean"),
              							'prefixes'=>new xmlrpcval($return_prefixes,"array"),
            							'require_prefix' =>   new xmlrpcval($mobiquo_require_prefix,"boolean"),
                                        'total_topic_num' => new xmlrpcval($totalthreads,'int'),
                                        'topics' => new xmlrpcval($return_thread,'array'),


	),
                                'struct'
                                )
                                );


}









?>
