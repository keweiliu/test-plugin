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
defined('IN_MOBIQUO') or exit;
require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/forums/topics.php');

class topic_thread extends public_forums_forums_topics
{
    public $position = 0;
    
	public function doExecute( ipsRegistry $registry )
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------

		$post_data = array();
		$poll_data = '';
		$function  = '';
		$this->settings['display_max_posts'] = $this->request['post_per_page'];
        
        // add for get_thread_by_post
        if ( $this->request['p'] && !$this->request['t'])
        {
            $post_data = $this->DB->buildAndFetch( array( 'select' => '*', 'from' => 'posts', 'where' => 'pid=' . $this->request['p'] ) );
            $this->request['t'] = $post_data['topic_id'];
        }
		
		/* Print CSS */
		//$this->registry->output->addToDocumentHead( 'raw', "<link rel='stylesheet' type='text/css' title='Main' media='print' href='{$this->settings['css_base_url']}style_css/{$this->registry->output->skin['_csscacheid']}/ipb_print.css' />" );
		
		/* Followed stuffs */
		require_once( IPS_ROOT_PATH . 'sources/classes/like/composite.php' );/*noLibHook*/
		$this->_like = classes_like::bootstrap( 'forums', 'topics' );
		
		/* Init */
		if ( ! $this->registry->isClassLoaded('topics') )
		{
			$classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . "/sources/classes/topics.php", 'app_forums_classes_topics', 'forums' );
			$this->registry->setClass( 'topics', new $classToLoad( $this->registry ) );
		}
		
		try
		{
			/* Load up the data dudes */
			$this->registry->getClass('topics')->autoPopulate( null, false );
		}
		catch( Exception $crowdCheers )
		{
			$msg = str_replace( 'EX_', '', $crowdCheers->getMessage() );
			
			//$this->registry->output->showError( $msg, 10340, null, null, 404 );
			get_error($msg);
		}
		
		
		/* Shortcut */
		$this->forumClass = $this->registry->getClass('class_forums');
		
		/* Setup basics for this method */
		$topicData      = $this->registry->getClass('topics')->getTopicData();
		$forumData      = $this->forumClass->getForumById( $topicData['forum_id'] );
		
		/* Rating */
		$this->can_rate = $this->memberData['member_id'] ? intval( $this->memberData['g_topic_rate_setting'] ) : 0;

		/* Set up topic */
		$topicData = $this->topicSetUp( $topicData );

		/* Specific view? */
		$this->_doViewCheck();

		/* Get Posts */
		$_NOW = IPSDebug::getMemoryDebugFlag();

		$postData = $this->_getPosts();

		unset( $this->cached_members );
		
		/* Status? */
		if ( $topicData['_ppd_ok'] === TRUE )
		{
			/* status from PPD */
			if ( $this->forumClass->ppdStatusMessage )
			{
				$topicData['_fastReplyStatusMessage'][] = $this->forumClass->ppdStatusMessage;
			}
			
			/* status from mod posts */
			$topicData['_fastReplyStatusMessage'][] = $this->forumClass->fetchPostModerationStatusMessage( $this->memberData, $forumData, $topicData, 'reply' );
		}
		
		//-----------------------------------------
		// Update the item marker
		//-----------------------------------------
	
		//if ( ! $this->request['view'] )
		//{
			/* If we marked page 2 but land back on page 1 again we don't want to unmark it! */
			$lastMarked = $this->registry->classItemMarking->fetchTimeLastMarked( array( 'forumID' => $forumData['id'], 'itemID' => $topicData['tid'] ) );

			/* is this the very last page? */
			if ( $this->registry->getClass('topics')->isOnLastPage() )
			{
				/* ...then make the timestamp 'NOW' so polls will be cleared correctly */
				$this->_maxPostDate = IPS_UNIX_TIME_NOW;
			}
			
			if ( $lastMarked < $this->_maxPostDate )
			{
				$this->registry->getClass('classItemMarking')->markRead( array( 'forumID' => $forumData['id'], 'itemID' => $topicData['tid'], 'markDate' => $this->_maxPostDate, 'containerLastActivityDate' => $forumData['last_post'] ) );
			}
		//}
		
		/* Set has unread flag */
		$forumData['_hasUnreadTopics'] = $this->registry->getClass('class_forums')->getHasUnread( $forumData['id'] );
		
		IPSDebug::setMemoryDebugFlag( "TOPICS: Parsed Posts - Completed", $_NOW );
	
		//-----------------------------------------
		// Generate template
		//-----------------------------------------
		
		$topicData['id'] = $topicData['forum_id'];
		
		//-----------------------------------------
		// This has to be called first to set $this->poll_only
		//-----------------------------------------
		
		$poll_data	= ( $topicData['poll_state'] ) ? $this->_generatePollOutput() : '';

		$displayData = array( 'fast_reply'		    => $this->_getFastReplyData(),
							  'multi_mod'			=> $this->registry->getClass('topics')->getMultiModerationData(),
							  'reply_button'		=> $this->_getReplyButtonData(),
							  'active_users'		=> $this->_getActiveUserData(),
							  //'mod_links'			=> $this->_generateModerationPanel(),
							  'forumJump'			=> $this->forumClass->buildForumJump(),
							  'follow_data' 		=> $this->_like->render( 'summary', $topicData['tid'] ),
							  'poll_data'			=> $poll_data,
							  'load_editor_js'		=> false,
							  'smilies'				=> '' );

		//-----------------------------------------
		// If we can edit, but not reply, load JS still
		//-----------------------------------------

		if( !$displayData['fast_reply'] AND $this->_canEditAPost )
		{
			$displayData['load_editor_js']	= true;
			
			$classToLoad			= IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/editor/composite.php', 'classes_editor_composite' );
			$editor					= new $classToLoad();
			$displayData['smilies']	= $editor->fetchEmoticons();
		}
		
		$postData = $this->_parseAttachments( $postData );
		
		/* Rules */
		if( $forumData['show_rules'] == 2 )
		{
			IPSText::getTextClass( 'bbcode' )->parse_smilies			= 1;
			IPSText::getTextClass( 'bbcode' )->parse_html				= 1;
			IPSText::getTextClass( 'bbcode' )->parse_nl2br				= 1;
			IPSText::getTextClass( 'bbcode' )->parse_bbcode				= 1;
			IPSText::getTextClass( 'bbcode' )->parsing_section			= 'topics';
			IPSText::getTextClass( 'bbcode' )->parsing_mgroup			= $this->memberData['member_group_id'];
			IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others	= $this->memberData['mgroup_others'];

			if( ! $forumData['rules_raw_html'] )
			{
				$forumData['rules_text'] = IPSText::getTextClass( 'bbcode' )->preDisplayParse( $forumData['rules_text'] );
			}
		}
		
		/* Got soft delete pids? */
		if ( is_array( $this->_sdPids ) AND count( $this->_sdPids ) )
		{
			$displayData['sdData'] = IPSDeleteLog::fetchEntries( $this->_sdPids, 'post', false );
		}
		
		if( $topicData['starter_id'] )
		{
			$topicData['_starter']	= IPSMember::buildDisplayData( IPSMember::load( $topicData['starter_id'] ) );
		}
		else
		{
			$topicData['_starter']	= IPSMember::buildDisplayData( array(
																		'member_id'				=> 0,
																		'members_display_name'	=> $topicData['starter_name'] ? $this->settings['guest_name_pre'] . $topicData['starter_name'] . $this->settings['guest_name_suf'] : $this->lang->words['global_guestname'],
																)		);
		}
		
		/* Can we report? */
		$classToLoad	= IPSLib::loadLibrary( IPSLib::getAppDir('core') . '/sources/classes/reportLibrary.php', 'reportLibrary', 'core' );
		$reports		= new $classToLoad( $this->registry );
		
		$topicData['_canReport']	= $reports->canReport( 'post' );


        // ================== prepare xmlrpc return =================================================
        $result_post_data = array();
        $permission = $this->memberData['forumsModeratorData'][ $forumData['id'] ];

        // get breadcrumb
        $breadcrumb = array();
        if(is_array($this->nav) && count($this->nav) > 0)
        {
            foreach ($this->nav as $navigation)
            {
                $forum_id = preg_replace('/^.*?(\d+)$/', '$1', $navigation[1]);
                $forum_data = $this->forumClass->getForumById( $forum_id );
                $breadcrumb[] = new xmlrpcval(array(
                    'forum_id'      => new xmlrpcval($forum_id, 'string'),
                    'forum_name'    => new xmlrpcval($navigation[0], 'base64'),
                    'sub_only'      => new xmlrpcval(!$forum_data['sub_can_post'], 'boolean'),
                ), 'struct');
            }
        }

        if (isset($postData) AND count($postData))
        {
            foreach ($postData as $post_id => $data)
            {
                $post = $data['post'];
                $author = IPSMember::buildDisplayData($data['author'], array('spamStatus' => 1));

                $attachments = array ();
                if (isset($data['post']['attachments']) and is_array($data['post']['attachments'])) {
                    foreach($data['post']['attachments'] as $aid => $tmp)
                    {
                        $thumbnail_url = $tmp['attach_is_image'] && $tmp['attach_thumb_location'] ? $this->settings['upload_url'] . '/' . $tmp['attach_thumb_location'] : '';
                        $url = $this->registry->getClass('output')->formatUrl( $this->registry->getClass('output')->buildUrl( "app=core&amp;module=attach&amp;section=attach&amp;attach_id={$aid}", "public",'' ), "", "" );
                        $xmlrpc_attachment = new xmlrpcval(array(
                            'content_type'  => new xmlrpcval($tmp['attach_is_image'] ? 'image' : $tmp['attach_ext']),
                            'thumbnail_url' => new xmlrpcval(url_encode($thumbnail_url)),
                            'url'           => new xmlrpcval(url_encode($url))
                        ), 'struct');
                        $attachments[] = $xmlrpc_attachment;
                    }
                }

                $like_list = array();
                if (!empty($post['like']['names']))
                {
                    foreach ($post['like']['names'] as $likeuser)
                    {
                        if ($this->memberData['member_id'] == $likeuser['id'])
                            $likeuser['name'] = $this->memberData['members_display_name'];

                        $like_list[] = new xmlrpcval(array(
                            'userid'    => new xmlrpcval($likeuser['id'], 'string'),
                            'username'  => new xmlrpcval(mobi_unescape_html(to_utf8($likeuser['name'])), 'base64'),
                            'user_type' => new xmlrpcval(check_return_user_type($likeuser['name']),'base64'),
                        ), 'struct');
                    }
                }
                
                $can_like = IPSMember::canGiveRep( $post, $post );
                $is_liked = $post['like']['iLike'];
                $like_count = $post['like']['totalCount'];

                //$can_report = $topicData['_canReport'] and ( $this->memberData['member_id'] );
                $can_edit = $post['_can_edit'] === true;
                $is_approved = $post['queued'] != 1 && $post['queued'] != 2;
                $can_approve = $this->memberData['g_is_supmod'] == 1 || $permission['post_q'];
                $can_delete = $post['_can_delete'] === true;
                $is_deleted = $post['queued'] == 3;
                $can_move = $this->memberData['g_is_supmod'] == 1 || $permission['split_merge'];
                $is_online = $author['_online'];
                
                $is_spam = $author['spamStatus'] === TRUE;
                $can_mark_spam = $author['spamStatus'] === FALSE && $author['member_id'] != $this->memberData['member_id'];

                $xmlrpc_post = array(
                    'topic_id'          => new xmlrpcval($post['topic_id']),
                    'post_id'           => new xmlrpcval($post['pid']),
                    'post_title'        => new xmlrpcval('', 'base64'),
                    'post_content'      => new xmlrpcval(post_html_clean($post['post']), 'base64'),
                    'post_author_id'    => new xmlrpcval($post['author_id']),
                    'post_author_name'  => new xmlrpcval(subject_clean($post['author_name']), 'base64'),
                    'user_type'         => new xmlrpcval(check_return_user_type($post['author_name']),'base64'),
                    'icon_url'          => new xmlrpcval($author['pp_thumb_photo']),
                    'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
                    'timestamp'         => new xmlrpcval(intval($post['post_date']), 'string'),
                    'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
                );
                
                if ($is_online)     $xmlrpc_post['is_online']   = new xmlrpcval(true, 'boolean');
                //if ($can_report)    $xmlrpc_post['can_report']  = new xmlrpcval(true, 'boolean');
                if ($can_edit)      $xmlrpc_post['can_edit']    = new xmlrpcval(true, 'boolean');
                if ($can_approve)   $xmlrpc_post['can_approve'] = new xmlrpcval(true, 'boolean');
                if ($can_delete)    $xmlrpc_post['can_delete']  = new xmlrpcval(true, 'boolean');
                if ($is_deleted)    $xmlrpc_post['is_deleted']  = new xmlrpcval(true, 'boolean');
                if ($can_move)      $xmlrpc_post['can_move']    = new xmlrpcval(true, 'boolean');
                if ($is_spam)       $xmlrpc_post['is_spam']     = new xmlrpcval(true, 'boolean');
                if ($can_mark_spam) $xmlrpc_post['can_mark_spam'] = new xmlrpcval(true, 'boolean');
                if ($is_spam)       $xmlrpc_post['is_ban']      = new xmlrpcval(true, 'boolean');
                if ($can_mark_spam) $xmlrpc_post['can_ban']     = new xmlrpcval(true, 'boolean');
                
                if ($can_like)      $xmlrpc_post['can_like']    = new xmlrpcval(true, 'boolean');
                if ($is_liked)      $xmlrpc_post['is_liked']    = new xmlrpcval(true, 'boolean');
                if ($like_count)    $xmlrpc_post['like_count']  = new xmlrpcval($like_count, 'int');
                if ($like_list)     $xmlrpc_post['likes_info']  = new xmlrpcval($like_list, 'array');
                if ($attachments)   $xmlrpc_post['attachments'] = new xmlrpcval($attachments, 'array');
                
                $result_post_data[] = new xmlrpcval($xmlrpc_post, 'struct');
            }
        }

        // Allowed to upload?
        $can_upload = false;
        $perm_id = $this->memberData['org_perm_id'] ? $this->memberData['org_perm_id'] : $this->memberData['g_perm_id'];
        $perm_array = explode( ",", $perm_id );

        if ( $this->registry->permissions->check( 'upload', $forumData, $perm_array ) === true )
        {
            if ( $this->memberData['g_attach_max'] != -1 )
            {
                $can_upload = true;
            }
        }

        $can_reply = $this->memberData['member_id'] && $displayData['reply_button']['url'] ? true : false;
        $is_subscribed = $this->_like->isLiked($topicData['tid'], $this->memberData['member_id']);
        $can_subscribe = $this->memberData['member_id'] ? true : false;

        $is_poll = $topicData['poll_state'] ? true : false;
        $is_sticky = $topicData['pinned'] == 1;
        $is_closed = $topicData['state'] == 'closed';
        $is_approved = $topicData['approved'] > 0;
        
        $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = false;
        if ($this->memberData['g_is_supmod']) {
            $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = true;
        } else if ($this->memberData['is_mod']) {
            $can_rename = $permission['edit_topic'];
            $can_move = $permission['move_topic'] && $topicData['state'] != 'link';
            $can_delete = $permission['delete_topic'];
            
            $can_stick = $is_sticky ? $permission['unpin_topic'] : $permission['pin_topic'];
            $can_close = $is_closed ? $permission['open_topic'] : $permission['close_topic'];
            $can_approve = $permission['topic_q']; // invisible
        }

        $return_data = array (
            'total_post_num'=> new xmlrpcval($topicData['posts'] + 1, 'int'),
            'forum_id'      => new xmlrpcval($forumData['id']),
            'forum_name'    => new xmlrpcval(subject_clean($forumData['name']), 'base64'),
            'topic_id'      => new xmlrpcval($topicData['tid']),
            'topic_title'   => new xmlrpcval(subject_clean($topicData['title']), 'base64'),
            'can_upload'    => new xmlrpcval($can_upload, 'boolean'),
            'can_reply'     => new xmlrpcval($can_reply, 'boolean'),
            'can_report'    => new xmlrpcval(true, 'boolean'),
            'is_approved'   => new xmlrpcval($is_approved, 'boolean'),
        );

        if ($is_subscribed) $return_data['is_subscribed']   = new xmlrpcval(true, 'boolean');
        if ($can_subscribe) $return_data['can_subscribe']   = new xmlrpcval(true, 'boolean');
        if ($is_poll)       $return_data['is_poll']         = new xmlrpcval(true, 'boolean');
        if ($is_sticky)     $return_data['is_sticky']       = new xmlrpcval(true, 'boolean');
        if ($is_closed)     $return_data['is_closed']       = new xmlrpcval(true, 'boolean');
        if ($can_rename)    $return_data['can_rename']      = new xmlrpcval(true, 'boolean');
        if ($can_stick)     $return_data['can_stick']       = new xmlrpcval(true, 'boolean');
        if ($can_close)     $return_data['can_close']       = new xmlrpcval(true, 'boolean');
        if ($can_move)      $return_data['can_move']        = new xmlrpcval(true, 'boolean');
        if ($can_approve)   $return_data['can_approve']     = new xmlrpcval(true, 'boolean');
        if ($can_delete)    $return_data['can_delete']      = new xmlrpcval(true, 'boolean');
        if ($breadcrumb)    $return_data['breadcrumb']      = new xmlrpcval($breadcrumb, 'array');
        
        if ($this->position)$return_data['position']        = new xmlrpcval($this->position, 'int');

        $return_data['posts'] = new xmlrpcval($result_post_data, 'array');

        return $return_data;
    }
	
	/**
	 * Redirects to new post
	 * @param mixed $topicData
	 */
	public function returnNewPost( $topicData=false )
	{
		$topicData      = ( $topicData === false ) ? $this->registry->getClass('topics')->getTopicData() : $topicData;
		$forumData      = $this->forumClass->getForumById( $topicData['forum_id'] );
		$permissionData = $this->registry->getClass('topics')->getPermissionData();
		$st             = 0;
		$pid	        = "";
		$last_time      = $this->registry->classItemMarking->fetchTimeLastMarked( array( 'forumID' => $forumData['id'], 'itemID' => $topicData['tid'] ) );
		$query          = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery('visible');
		
		/* Can we deal with hidden posts? */
		if ( $this->registry->class_forums->canQueuePosts( $topicData['forum_id'] ) )
		{
			if ( $permissionData['softDeleteSee'] )
			{
				/* See queued and soft deleted */
				$query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array( 'approved', 'sdeleted', 'hidden' ) );
			}
			else
			{
				/* Otherwise, see queued and approved */
				$query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array( 'visible', 'hidden' ) );
			}
		}
		else
		{
			/* We cannot see hidden posts */
			if ( $permissionData['softDeleteSee'] )
			{
				/* See queued and soft deleted */
				$query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array('approved', 'sdeleted') );
			}
		}

		$this->DB->build( array( 'select' => 'MIN(pid) as pid',
								 'from'   => 'posts',
								 'where'  => "topic_id={$topicData['tid']} AND post_date > " . intval( $last_time ) . $query,
								 'limit'  => array( 0,1 ) )	);						
		$this->DB->execute();
		
		$post = $this->DB->fetch();
		
		if ( $post['pid'] )
		{
			$pid = "&#entry".$post['pid'];
			
			$this->DB->build( array( 'select' => 'COUNT(*) as posts', 'from' => 'posts', 'where' => "topic_id={$topicData['tid']} AND pid <= {$post['pid']}" . $query ) );										
			$this->DB->execute();
		
			$cposts = $this->DB->fetch();
			
			if ( (($cposts['posts']) % $this->settings['display_max_posts']) == 0 )
			{
				$pages = ($cposts['posts']) / $this->settings['display_max_posts'];
			}
			else
			{
				$number = ( ($cposts['posts']) / $this->settings['display_max_posts'] );
				$pages = ceil( $number);
			}
			
			$st = ($pages - 1) * $this->settings['display_max_posts'];
			
			if( $this->settings['post_order_sort'] == 'desc' )
			{
				$st = (ceil(($topicData['posts']/$this->settings['display_max_posts'])) - $pages) * $this->settings['display_max_posts'];
			}						
			
			$this->request['st'] = $st;
			$this->position = $cposts['posts'];

			//$this->registry->output->silentRedirect( $this->settings['base_url']."showtopic=".$topicData['tid']."&pid={$post['pid']}&st={$st}".$pid, $topicData['title_seo'] );
		}
		else
		{
			$this->returnLastPost( $topicData );
		}
	}
	
	/**
	 * Return last post
	 *
	 * @return	@e void
	 */
	public function returnLastPost( $topicData=false )
	{
		/* Init */
		$topicData      = ( $topicData === false ) ? $this->registry->getClass('topics')->getTopicData() : $topicData;
		$forumData      = $this->forumClass->getForumById( $topicData['forum_id'] );
		$permissionData = $this->registry->getClass('topics')->getPermissionData();
		$st             = 0;
		$query          = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery('visible');
		$_posts			= $topicData['posts'];
		
		if( $this->registry->class_forums->canQueuePosts( $topicData['forum_id'] ) )
		{
			$_posts	+= intval($topicData['topic_queuedposts']);
		}
		
		if( $permissionData['softDeleteSee'] )
		{
			$_posts	+= intval($topicData['topic_deleted_posts']);
		}

		/* Can we deal with hidden posts? */
		if ( $this->registry->class_forums->canQueuePosts( $topicData['forum_id'] ) )
		{
			if ( $permissionData['softDeleteSee'] )
			{
				/* See queued and soft deleted */
				$query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array( 'approved', 'sdeleted', 'hidden' ) );
			}
			else
			{
				/* Otherwise, see queued and approved */
				$query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array( 'visible', 'hidden' ) );
			}
		}
		else
		{
			/* We cannot see hidden posts */
			if ( $permissionData['softDeleteSee'] )
			{
				/* See queued and soft deleted */
				$query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array('approved', 'sdeleted') );
			}
		}
			
		if( $_posts )
		{
			if ( (($_posts + 1) % $this->settings['display_max_posts']) == 0 )
			{
				$pages = ($_posts + 1) / $this->settings['display_max_posts'];
			}
			else
			{
				$number = ( ($_posts + 1) / $this->settings['display_max_posts'] );
				$pages = ceil( $number );
			}
			
			$st = ($pages - 1) * $this->settings['display_max_posts'];
			
			if( $this->settings['post_order_sort'] == 'desc' )
			{
				$st = (ceil(($_posts/$this->settings['display_max_posts'])) - $pages) * $this->settings['display_max_posts'];
			}
		}
		
		$this->DB->build( array(  'select' => 'pid',
								  'from'   => 'posts',
								  'where'  => "topic_id=".$topicData['tid'] . $query,
								  'order'  => $this->settings['post_order_column'].' DESC',
								  'limit'  => array( 0,1 ) ) );
							 
		$this->DB->execute();
		
		$post = $this->DB->fetch();
		
		$this->request['st'] = $st;
		$this->position = $_posts + 1;

		//$this->registry->output->silentRedirect($this->settings['base_url']."showtopic=".$topicData['tid']."&pid={$post['pid']}&st={$st}&"."#entry".$post['pid'], $topicData['title_seo'] );
	}
	
	/**
	* Parse attachments
	*
	* @param	array	Array of post data
	* @return	string	HTML parsed by attachment class
	*/
	public function _parseAttachments( $postData )
	{
		/* Init */
		$topicData = $this->registry->getClass('topics')->getTopicData();
		$forumData = $this->forumClass->getForumById( $topicData['forum_id'] );
		
		//-----------------------------------------
		// No attachments?  Then what are you doing here?
		//-----------------------------------------
		
		if ( ! $topicData['topic_hasattach'] )
		{
			return $postData;
		}
		
		//-----------------------------------------
		// INIT. Yes it is
		//-----------------------------------------
		
		$postHTML = array();
		
		//-----------------------------------------
		// Separate out post content
		//-----------------------------------------
		
		foreach( $postData as $id => $post )
		{
			$postHTML[ $id ] = $post['post']['post'];
		}
		
		//-----------------------------------------
		// ATTACHMENTS!!!
		//-----------------------------------------
		
		if ( ! is_object( $this->class_attach ) )
		{
			//-----------------------------------------
			// Grab render attach class
			//-----------------------------------------
			
			//$classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'core' ) . '/sources/classes/attach/class_attach.php', 'class_attach' );
			require_once( 'mobi_class_attach.php' );
			$classToLoad = 'mobi_class_attach';
			$this->class_attach		   =  new $classToLoad( $this->registry );
		}
		
		//-----------------------------------------
		// Not got permission to view downloads?
		//-----------------------------------------
		
		if ( $this->registry->permissions->check( 'download', $this->registry->class_forums->forum_by_id[ $topicData['forum_id'] ] ) === FALSE )
		{
			$this->settings['show_img_upload'] =  0 ;
		}
		
		//-----------------------------------------
		// Continue...
		//-----------------------------------------
		
		$this->class_attach->type  = 'post';
		$this->class_attach->init();

		$attachHTML = $this->class_attach->renderAttachments( $postHTML, $this->attach_pids );

		/* Now parse back in the rendered posts */
		if( is_array($attachHTML) AND count($attachHTML) )
		{
			foreach( $attachHTML as $id => $data )
			{
				/* Get rid of any lingering attachment tags */
				if ( stristr( $data['html'], "[attachment=" ) )
				{
					//$data['html'] = IPSText::stripAttachTag( $data['html'] );
					$data['html'] = preg_replace( "#\[attachment=(\d+?)\:(?:[^\]]+?)\]#ies", '$this->inline_attach($1, $data)', $data['html'] );
				}
				
				$postData[ $id ]['post']['post']			= $data['html'];
				$postData[ $id ]['post']['attachmentHtml']	= $data['attachmentHtml'];
				$postData[ $id ]['post']['attachments']		= $data['attachments'];
			}
		}

		return $postData;
	}
	
    public function inline_attach($aid, &$data)
    {
        $replace = '';
        if (isset($data['attachments'][$aid]))
        {
            $url = $this->registry->getClass('output')->formatUrl( $this->registry->getClass('output')->buildUrl( "app=core&amp;module=attach&amp;section=attach&amp;attach_id={$aid}", "public",'' ), "", "" );
            if ($data['attachments'][$aid]['attach_is_image']) {
                $replace = '[img]'.$url.'[/img]';
            } else {
                $replace = '[url='.$url.']'.$data['attachments'][$aid]['attach_file'].'[/url]';
            }
            unset($data['attachments'][$aid]);
        }

        return $replace;
    }

	/**
	 * Tests to see if we're viewing a post, etc
	 *
	 * @return	@e void
	 */
	protected function _doViewCheck()
	{
		/* Init */
		$topicData      = $this->registry->getClass('topics')->getTopicData();
		$forumData      = $this->forumClass->getForumById( $topicData['forum_id'] );
		$permissionData = $this->registry->getClass('topics')->getPermissionData();
		
		if ( $this->request['view'] )
		{
			/* Determine what we can see */
			$_approved	= $this->registry->class_forums->fetchTopicHiddenQuery( array( 'visible' ), '' );
			
			/* Can we deal with hidden posts? */
			if ( $this->registry->class_forums->canQueuePosts( $topicData['forum_id'] ) )
			{
				if ( $permissionData['TopicSoftDeleteSee'] )
				{
					/* See queued and soft deleted */
					$_approved = $this->registry->class_forums->fetchTopicHiddenQuery( array( 'approved', 'sdeleted', 'hidden' ), '' );
				}
				else
				{
					/* Otherwise, see queued and approved */
					$_approved = $this->registry->class_forums->fetchTopicHiddenQuery( array( 'visible', 'hidden' ), '' );
				}
			}
			else
			{
				/* We cannot see hidden posts */
				if ( $permissionData['TopicSoftDeleteSee'] )
				{
					/* See queued and soft deleted */
					$_approved = $this->registry->class_forums->fetchTopicHiddenQuery( array( 'approved', 'sdeleted' ), '' );
				}
			}
				
			if ( $this->request['view'] == 'getnextunread' )
			{
				$tid   = $this->registry->getClass('topics')->getNextUnreadTopicId();
				
				if ( $tid )
				{
					$topic = $this->registry->getClass('topics')->getTopicById( $tid );
				
					$this->returnNewPost( $topic );
				}
				else
				{
					$this->registry->output->showError( 'topics_none_newer', 10356, null, null, 404 );
				}
			}
			else if ($this->request['view'] == 'new')
			{
				//-----------------------------------------
				// Newer 
				//-----------------------------------------

				$this->DB->build( array( 
												'select' => 'tid, title_seo',
												'from'   => 'topics',
												'where'  => "forum_id={$forumData['id']} AND {$_approved} AND state <> 'link' AND last_post > {$topicData['last_post']}",
												'order'  => 'last_post',
												'limit'  => array( 0,1 )
									)	);
				$this->DB->execute();
				
				if ( $this->DB->getTotalRows() )
				{
					$this->topic = $this->DB->fetch();
					
					$this->registry->output->silentRedirect( $this->settings['base_url']."showtopic=".$topicData['tid'], $topicData['title_seo'] );
				}
				else
				{
					$this->registry->output->showError( 'topics_none_newer', 10356, null, null, 404 );
				}
			}
			else if ($this->request['view'] == 'old')
			{
				//-----------------------------------------
				// Older
				//-----------------------------------------

				$this->DB->build( array( 
												'select' => 'tid, title_seo',
												'from'   => 'topics',
												'where'  => "forum_id={$forumData['id']} AND {$_approved} AND state <> 'link' AND last_post < {$topicData['last_post']}",
												'order'  => 'last_post DESC',
												'limit'  => array( 0,1 )
									)	);
									
				$this->DB->execute();
					
				if ( $this->DB->getTotalRows() )
				{
					$this->topic = $this->DB->fetch();
					
					$this->registry->output->silentRedirect( $this->settings['base_url']."showtopic=".$topicData['tid'], $topicData['title_seo'] );
				}
				else
				{
					$this->registry->output->showError( 'topics_none_older', 10357, null, null, 404 );
				}
			}
			else if ($this->request['view'] == 'getlastpost')
			{
				//-----------------------------------------
				// Last post
				//-----------------------------------------
				
				$this->returnLastPost();
			}
			else if ($this->request['view'] == 'getnewpost')
			{
				$this->returnNewPost();
			}
			else if ($this->request['view'] == 'findpost')
			{
				//-----------------------------------------
				// Find a post
				//-----------------------------------------
				
				$pid	= intval($this->request['p']);
				$query	= ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery('visible');
				
				/* Can we deal with hidden posts? */
				if ( $this->registry->class_forums->canQueuePosts( $topicData['forum_id'] ) )
				{
					if ( $permissionData['softDeleteSee'] )
					{
						/* See queued and soft deleted */
						$query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array( 'approved', 'sdeleted', 'hidden' ) );
					}
					else
					{
						/* Otherwise, see queued and approved */
						$query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array( 'visible', 'hidden' ) );
					}
				}
				else
				{
					/* We cannot see hidden posts */
					if ( $permissionData['softDeleteSee'] )
					{
						/* See queued and soft deleted */
						$query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array('approved', 'sdeleted') );
					}
				}
				
				if ( $pid > 0 )
				{
					$sort_value = $pid;
					$sort_field = ($this->settings['post_order_column'] == 'pid') ? 'pid' : 'post_date';
					
					if($sort_field == 'post_date')
					{
						$date = $this->DB->buildAndFetch( array( 'select' => 'post_date', 'from' => 'posts', 'where' => 'pid=' . $pid ) );

						$sort_value = $date['post_date'];
					}

					$this->DB->build( array( 'select' => 'COUNT(*) as posts', 'from' => 'posts', 'where' => "topic_id={$topicData['tid']} AND {$sort_field} <=" . intval( $sort_value ) . $query ) );										
					$this->DB->execute();
					
					$cposts = $this->DB->fetch();
					
					if ( (($cposts['posts']) % $this->settings['display_max_posts']) == 0 )
					{
						$pages = ($cposts['posts']) / $this->settings['display_max_posts'];
					}
					else
					{
						$number = ( ($cposts['posts']) / $this->settings['display_max_posts'] );
						$pages = ceil($number);
					}
					
					$st = ($pages - 1) * $this->settings['display_max_posts'];
					
					if( $this->settings['post_order_sort'] == 'desc' )
					{
						$st = (ceil(($topicData['posts']/$this->settings['display_max_posts'])) - $pages) * $this->settings['display_max_posts'];
					}
					
					$search_hl = '';
					if( !empty( $this->request['hl'] ) )
					{
						$search_hl .= "&amp;hl={$this->request['hl']}";
					}
					
					if( !empty( $this->request['fromsearch'] ) )
					{
						$search_hl .= "&amp;fromsearch={$this->request['fromsearch']}";
					}

					$this->request['st'] = $st;
					$this->position = $cposts['posts'];

					//$this->registry->output->silentRedirect( $this->settings['base_url']."showtopic=".$topicData['tid']."&st={$st}&p={$pid}{$search_hl}"."&#entry".$pid, $topicData['title_seo'] );
				}
				else
				{
					$this->returnLastPost();
				}
			}
		}
	}
	
	public function _getPosts()
	{
		/* Init */
		$topicData      = $this->registry->getClass('topics')->getTopicData();
		$forumData      = $this->forumClass->getForumById( $topicData['forum_id'] );
		$permissionData = $this->registry->getClass('topics')->getPermissionData();
		$first          = intval($this->request['st']) >=0 ? intval($this->request['st']) : 0;

		$pc_join = array();

		if ( $this->settings['post_order_column'] != 'post_date' )
		{
			$this->settings['post_order_column'] = 'pid';
		}

		if ( $this->settings['post_order_sort'] != 'desc' )
		{
			$this->settings['post_order_sort'] = 'asc';
		}

		if ( $this->settings['au_cutoff'] == "" )
		{
			$this->settings['au_cutoff'] = 15;
		}

		//-----------------------------------------
		// Moderator?
		//-----------------------------------------
		
		/* Default - just see all visible posts */
		$queued_query_bit = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery('visible');

		/* Can we deal with hidden posts? */
		if ( $this->registry->class_forums->canQueuePosts($topicData['forum_id']) )
		{
			if ( $permissionData['softDeleteSee'] )
			{
				/* See queued and soft deleted */
				$queued_query_bit = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array( 'visible', 'hidden', 'sdeleted' ) );
			}
			else
			{
				/* Otherwise, see queued and approved */
				$queued_query_bit = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array( 'visible', 'hidden' ) );
			}
			
			/* Specifically requesting to see queued posts only */
			if ( $this->request['modfilter'] AND  $this->request['modfilter'] == 'invisible_posts' )
			{
				$queued_query_bit = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery('hidden');
			}
		}
		else
		{
			/* We cannot see hidden posts */
			if ( $permissionData['softDeleteSee'] )
			{
				/* See queued and soft deleted */
				$queued_query_bit = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array('approved', 'sdeleted') );
			}
		}
		
		/* Did we specifically want to see soft deleted posts? */
		if ( $this->request['modfilter'] == 'deleted_posts' AND $permissionData['softDeleteSee'] )
		{
			$queued_query_bit = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery('sdeleted');
		}

		$this->DB->build( array(
								'select' => 'pid',
								'from'   => 'posts',
								'where'  => 'topic_id='.$topicData['tid']. $queued_query_bit,
								'order'  => $this->settings['post_order_column'].' '.$this->settings['post_order_sort'],
								'limit'  => array( $first, $this->settings['display_max_posts'] ) )	);

		$this->DB->execute();

		while( $p = $this->DB->fetch() )
		{
			$this->pids[ $p['pid'] ] = $p['pid'];
		}

		//-----------------------------------------
		// Do we have any PIDS?
		//-----------------------------------------

		if ( ! count( $this->pids ) )
		{
			if ( $first )
			{
				//-----------------------------------------
				// Add dummy PID, AUTO FIX
				// will catch this below...
				//-----------------------------------------

				$this->pids[] = 0;
			}

			if ( $this->request['modfilter'] == 'invisible_posts' )
			{
				$this->pids[] = 0;
			}
		}

		//-----------------------------------------
		// Attachment PIDS
		//-----------------------------------------

		$this->attach_pids = $this->pids;

		//-----------------------------------------
		// Fail safe
		//-----------------------------------------

		if ( ! is_array( $this->pids ) or ! count( $this->pids ) )
		{
			$this->pids = array( 0 => 0 );
		}
		
		/* Data Hook Location */
		$dataHook = array( 'members' => array(), 'postJoins' => array() );
		IPSLib::doDataHooks( $dataHook , 'topicViewQuery' );
		
		//-----------------------------------------
		// Joins
		//-----------------------------------------		
		
		$_extraMember = ( is_array($dataHook['members']) && count($dataHook['members']) ) ? ',m.'.implode(',m.', $dataHook['members']) : '';
		
		$_post_joins = array(
								array( 
										'select' => 'm.member_id as mid,m.name,m.member_group_id,m.email,m.joined,m.posts, m.last_visit, m.last_activity,m.login_anonymous,m.title as member_title, m.warn_level, m.warn_lastwarn, m.members_display_name, m.members_seo_name, m.has_gallery, m.has_blog, m.members_bitoptions,m.mgroup_others'.$_extraMember,
										'from'   => array( 'members' => 'm' ),
										'where'  => 'm.member_id=p.author_id',
										'type'   => 'left'
									),
								array( 
										'select' => 'pp.*',
										'from'   => array( 'profile_portal' => 'pp' ),
										'where'  => 'm.member_id=pp.pp_member_id',
										'type'   => 'left'
								)
							);
		
		/* Add data hook joins */					
		if ( is_array($dataHook['postJoins']) && count($dataHook['postJoins']) )
		{
			$_post_joins = array_merge( $_post_joins, $dataHook['postJoins'] );
		}
		
		/* Add custom fields join? */
		if( $this->settings['custom_profile_topic'] == 1 )
		{
			$_post_joins[] = array( 
									'select' => 'pc.*',
									'from'   => array( 'pfields_content' => 'pc' ),
									'where'  => 'pc.member_id=p.author_id',
									'type'   => 'left'
								);
		}							
		
		/* Reputation system enabled? */
		if( $this->settings['reputation_enabled'] )
		{
			/* Add the join to figure out if the user has already rated the post */
			$_post_joins[] = $this->registry->repCache->getUserHasRatedJoin( 'pid', 'p.pid', 'forums' );
			
			/* Add the join to figure out the total ratings for each post */
			if( $this->settings['reputation_show_content'] )
			{
				$_post_joins[] = $this->registry->repCache->getTotalRatingJoin( 'pid', 'p.pid', 'forums' );
			}
		}
		
		/* Cache? */
		if ( IPSContentCache::isEnabled() )
		{
			if ( IPSContentCache::fetchSettingValue('post') )
			{
				$_post_joins[] = IPSContentCache::join( 'post', 'p.pid' );
			}
			
			if ( IPSContentCache::fetchSettingValue('sig') )
			{
				$_post_joins[] = IPSContentCache::join( 'sig' , 'm.member_id', 'ccb', 'left', 'ccb.cache_content as cache_content_sig, ccb.cache_updated as cache_updated_sig' );
			}
		}
		
		/* Ignored Users */
		$ignored_users = array();
		
		foreach( $this->member->ignored_users as $_i )
		{
			if( $_i['ignore_topics'] )
			{
				$ignored_users[] = $_i['ignore_ignore_id'];
			}
		}
		
		//-----------------------------------------
		// Get posts
		//-----------------------------------------

		$this->DB->build( array( 'select'   => 'p.*',
								 'from'	    => array( 'posts' => 'p' ),
								 'where'    => "p.pid IN(" . implode( ',', $this->pids ) . ")",
								 'order'    => $this->settings['post_order_column'] . ' ' . $this->settings['post_order_sort'],
								 'add_join' => $_post_joins )	);

		$oq = $this->DB->execute();

		if ( ! $this->DB->getTotalRows() )
		{
			if ($first >= $this->settings['display_max_posts'])
			{
				//-----------------------------------------
				// AUTO FIX: Get the correct number of replies...
				//-----------------------------------------

				$this->DB->build( array(
										'select' => 'COUNT(*) as pcount',
										'from'   => 'posts',
										'where'  => "topic_id=".$topicData['tid']." and queued=0"
							)	);

				$newq   = $this->DB->execute();

				$pcount = $this->DB->fetch($newq);

				$pcount['pcount'] = $pcount['pcount'] > 0 ? $pcount['pcount'] - 1 : 0;

				//-----------------------------------------
				// Update the post table...
				//-----------------------------------------

				if ($pcount['pcount'] > 1)
				{
					$this->DB->update( 'topics', array( 'posts' => $pcount['pcount'] ), "tid=".$topicData['tid'] );

				}

				$this->registry->output->silentRedirect($this->settings['base_url']."showtopic={$topicData['tid']}&view=getlastpost");
			}
		}

		//-----------------------------------------
		// Render the page top
		//-----------------------------------------

		$topicData['go_new'] = isset($topicData['go_new']) ? $topicData['go_new'] : '';

		//-----------------------------------------
		// Format and print out the topic list
		//-----------------------------------------
		
		$adCodeSet = false;
		$post_data = array();

		while ( $row = $this->DB->fetch( $oq ) )
		{
			$row['member_id']	= $row['mid'];
			if(isset($row['cache_content']))
			{
				unset($row['cache_content']);
			}
			$row['post'] = post_bbcode_clean($row['post']);
			
			$_data = $this->parsePostRow( $row );
			IPSContentCache::update($row['pid'], 'post', false);
			
			$post_data[ $_data['post'] ['pid'] ] = $_data;

			//-----------------------------------------
			// Are we giving this bloke a good ignoring?
			//-----------------------------------------
			
			$post_data[ $row['pid'] ]['post']['_ignored']	= 0;

			if ( isset( $ignored_users ) && is_array( $ignored_users ) && count( $ignored_users ) )
			{
				if( in_array( $post_data[ $row['pid'] ]['author']['member_id'], $ignored_users ) )
				{
					if ( ! strstr( $this->settings['cannot_ignore_groups'], ','.$post_data[ $row['pid'] ]['post']['member_group_id'].',' ) )
					{
						$post_data[ $row['pid'] ]['post']['_ignored'] = 1;
						continue;
					}
				}
			}
			
			/* AD Code */
			$post_data[ $row['pid'] ]['post']['_adCode']	= '';

			if ( $this->registry->getClass('IPSAdCode')->userCanViewAds() && ! $adCodeSet )
			{
				if ( $this->registry->getClass('IPSAdCode')->getAdCode('ad_code_topic_view_code') )
				{
					$post_data[ $row['pid'] ]['post']['_adCode']	= $this->registry->getClass('IPSAdCode')->getAdCode('ad_code_topic_view_code');
					$adCodeSet										= true;
				}
			}
		}

		//-----------------------------------------
		// Print the footer
		//-----------------------------------------

		return $post_data;	
	}
	
}