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
require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/post/post.php');

class forum_post extends public_forums_post_post
{
	public function doExecute( ipsRegistry $registry )
    {
 		###########################################################
     	$this->request['s'] = $this->memberData['publicSessionID'];
    	$this->request['auth_key'] = $this->member->form_hash;
		
		if ($this->request['mqtids']) {
		    $quote_pids = explode('-', $this->request['mqtids']);
		    
		    $first_quote_pid = $quote_pids[0];
		    
			$post = $this->DB->buildAndFetch( array( 
													'select'   => 'p.*', 
													'from'     => array( 'posts' => 'p' ), 
													'where'    => "p.pid={$first_quote_pid}",
										) );
	    	
	    	$this->request['t'] = $post['topic_id'];
		}
		
    	if ($this->request['t']) {
	    	$topic = $this->DB->buildAndFetch( array( 
													'select'   => 't.*', 
													'from'     => array( 'topics' => 't' ), 
													'where'    => "t.tid={$this->request['t']}",
										) );
	    	
	    	$this->request['f'] = $topic['forum_id'];
    	}
//    	if ($this->request['attach_id']) {
//	    	$attach = $this->DB->buildAndFetch( array( 
//													'select'   => 'a.*', 
//													'from'     => array( 'attachments' => 'a' ), 
//													'where'    => "a.attach_id={$this->request['attach_id']}",
//										) );
//	    	
//	    	$this->request['attach_post_key'] = $attach['attach_post_key'];
//    	}
    	
    	$_POST["Post"] = to_local($_POST["Post"]);
    	$this->request["Post"] = to_local($this->request["Post"]);
    	
    	$_POST["TopicTitle"] = to_local($_POST["TopicTitle"]);
    	$this->request["TopicTitle"] = to_local($this->request["TopicTitle"]);
    	###############################################
		$doCodes = array(
							'new_post'       => array( '0'  , 'new'     ),
							'new_post_do'    => array( '1'  , 'new'     ),
							'reply_post'     => array( '0'  , 'reply'   ),
							'reply_post_do'  => array( '1'  , 'reply'   ),
							'edit_post'      => array( '0'  , 'edit'    ),
							'edit_post_do'   => array( '1'  , 'edit'    )
						);
						
		$do = $this->request['do'];

		//-----------------------------------------
        // Make sure our input doCode element is legal.
        //-----------------------------------------
        
        if ( ! isset( $doCodes[ $do ] ) )
        {
        	get_error( " Request Error!");
        	//$this->registry->getClass('output')->showError( 'posting_bad_action', 103125 );
        }
		
		//-----------------------------------------
        // Check the input
        //-----------------------------------------
        
        $this->request[ 't' ] =  intval($this->request['t'] );
        $this->request[ 'p' ] =  intval($this->request['p'] );
        $this->request[ 'f' ] =  intval($this->request['f'] );
        $this->request[ 'st'] =  intval($this->request['st'] );
        
		//-----------------------------------------
		// Grab the post class
		//-----------------------------------------
		
		//require_once( IPSLib::getAppDir( 'forums' ) . '/sources/classes/post/classPost.php' );
		//require_once( IPSLib::getAppDir( 'forums' ) . '/sources/classes/post/classPostForms.php' );
		require_once( 'mobi_classPostForms.php' );
		
		$this->_postClass = new mobi_classPostForms( $registry );
		
		//-----------------------------------------
		// Set up some stuff
		//-----------------------------------------
		
		# IDs
		$this->_postClass->setTopicID( $this->request['t'] );
		$this->_postClass->setPostID( $this->request['p'] );
		$this->_postClass->setForumID( $this->request['f'] );
		
		# Topic Title and description - use _POST as it is cleaned in the function.
		# We wrap this because the title may not be set when showing a form and would
		# throw a length error
		if ( $_POST['TopicTitle'] )
		{
			$this->_postClass->setTopicTitle( $_POST['TopicTitle'] );
			$this->_postClass->setTopicDescription( $_POST['TopicDesc'] );
		}
		
		# Is Preview Mode
		$this->_postClass->setIsPreview( ( $this->request['preview'] ) ? TRUE : FALSE );

		# Forum Data
		$this->_postClass->setForumData( $this->registry->getClass('class_forums')->forum_by_id[ $this->request['f'] ] );
		
		# Topic Data
		$this->_postClass->setTopicData( $this->DB->buildAndFetch( array( 
																			'select'   => 't.*, p.poll_only', 
																			'from'     => array( 'topics' => 't' ), 
																			'where'    => "t.forum_id={$this->_postClass->getForumID()} AND t.tid={$this->_postClass->getTopicID()}",
																			'add_join' => array(
																								array( 
																										'type'	=> 'left',
																										'from'	=> array( 'polls' => 'p' ),
																										'where'	=> 'p.tid=t.tid'
																									)
																								)
									) 							)	 );
		
		
		# Published
		$this->_postClass->setPublished( $this->_checkPostModeration( $doCodes[ $do ][1] ) === TRUE ? TRUE : FALSE );
		
		# Post Content
		$this->_postClass->setPostContent( isset( $_POST['Post'] ) ? $_POST['Post'] : '' );
		
		# Set Author
		$this->_postClass->setAuthor( $this->memberData['member_id'] );
	
		# Mod Options
		$this->_postClass->setModOptions( $this->request['mod_options'] );
		
		# Set Settings
		if ( ! $doCodes[ $do ][0] )
		{
			if ( $this->_postClass->getIsPreview() !== TRUE )
			{
				/* Showing form */
				$this->request['enablesig'] = ( isset( $this->request['enablesig'] ) ) ? $this->request['enablesig'] : 'yes';
				$this->request['enableemo'] = ( isset( $this->request['enableemo'] ) ) ? $this->request['enableemo'] : 'yes';
			}
		}
		
		//$this->request['enabletrack'] = is_subscribed($this->request[ 't' ]) ? 1 : 0;
		/* wztmdf begin */
		global $request_name,$member,$app_version;
		if (version_compare($app_version, '3.2.0', '>='))
		{
			if (($request_name == 'new_topic' || $request_name == 'reply_post') && $member && $member['auto_track']) {
				$this->request['enabletrack'] = 1;
			} else {
				$this->request['enabletrack'] = is_subscribed($this->request[ 't' ]) ? 1 : 0;
			}
		}
		/* wztmdf end */
		
		$this->_postClass->setSettings( array( 'enableSignature' => ( $this->request['enablesig']  == 'yes' ) ? 1 : 0,
											   'enableEmoticons' => ( $this->request['enableemo']  == 'yes' ) ? 1 : 0,
											   'post_htmlstatus' => intval( $this->request['post_htmlstatus'] ),
											   'enableTracker'   => intval( $this->request['enabletrack'] ) ) );
											
		//-----------------------------------------
        // Checks...
        //-----------------------------------------
        if (! $this->registry->getClass('class_forums')->forumsCheckAccess( $this->_postClass->getForumData('id'), 1, 'forum', $this->_postClass->getTopicData(), true )) {
        	get_error("No permission!");
        }
		
        //-----------------------------------------
        // Are we allowed to post at all?
        //-----------------------------------------
        
        if ( $this->memberData['member_id'] )
        {
        	if ( $this->memberData['restrict_post'] )
        	{
        		if ( $this->memberData['restrict_post'] == 1 )
        		{
        			get_error("You are restrict to post!");
        		}
        		
        		$post_arr = IPSMember::processBanEntry( $this->memberData['restrict_post'] );
        		
        		if ( time() >= $post_arr['date_end'] )
        		{
        			//-----------------------------------------
        			// Update this member's profile
        			//-----------------------------------------
        			
					IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'restrict_post' => 0 ) ) );
        		}
        		else
        		{	
        			get_error("Topic is end!");
        		}
        	}
        	
        	//-----------------------------------------
        	// Flood check..
        	//-----------------------------------------
			
        	if (  ! in_array( $do, array( 'edit_post', 'edit_post_do', 'poll_add', 'poll_add_do' ) ) )
        	{
				if ( $this->settings['flood_control'] > 0 )
				{
					if ( $this->memberData['g_avoid_flood'] != 1 )
					{
						if ( time() - $this->memberData['last_post'] < $this->settings['flood_control'] )
						{
							get_error("Restrict by Flood Control!");
						}
					}
				}
			}
        }
        else if ( $this->member->is_not_human == 1 )
        {
        	get_error("You are restrict to post!");
        }
        
        //-----------------------------------------
        // Show form or process?
        //-----------------------------------------
        if ( $doCodes[ $do ][0] )
        {
        	if ( $this->request['auth_key'] != $this->member->form_hash )
			{
				get_error("Auth Key is Invalid");
			}
			
			try
			{	
				$this->_postClass->checkGuestCaptcha();
			}
			catch( Exception $error )
			{
				$this->_postClass->setPostError( $error->getMessage() );
				get_error("Restrict by Check Guest Captcha!");
			}
        	
        	$this->_check_guest_name();
        	$this->checkDoublePost();
        	
        	return $this->saveForm( $doCodes[ $do ][1] );
        }
        elseif ( !$doCodes[ $do ][0]  and $doCodes[ $do ][1] == 'reply') {
        	$postContent = $this->_postClass->get_quote_post();
        	$postTitle = $this->_postClass->get_title('reply');
        	$post_id = $this->request['mqtids'];
        	return array (
        	    'post_title'   => $postTitle,
        		'post_content' => $postContent,
        		'post_id'	   => $post_id,
        	);
        }
        else
        {
        	get_error("Parameters Error!");
        }
	}
	
	
	public function saveForm( $type )
	{
		switch( $type )
		{
			case 'reply':
				try
				{
				    $this->_postClass->setIsAjax(TRUE);
				    
					if ( $this->_postClass->addReply() === FALSE )
					{
						switch($this->_postClass->_postErrors) {
							case 'NO_TOPIC_ID': 
							case 'NO_FORUM_ID':
							case 'NO_AUTHOR_SET':
								get_error('Parameters Error!');
								break;
							case 'NO_CONTENT':
								get_error('No post content set!');
								break;
							case 'NO_SUCH_TOPIC':
								get_error('No such topic!');
								break;
							case 'NO_SUCH_FORUM':
								get_error('No such forum!');
								break;
							case 'NO_REPLY_PERM':
								get_error('Author cannot reply to this topic!');
								break;
							case 'TOPIC_LOCKED':
								get_error('The topic is locked!');
								break;
							case 'NO_POST_FORUM':
								get_error('Unable to post in this forum!');
								break;
							case 'FORUM_LOCKED':
								get_error('Forum read only!');
								break;
							case 'post_too_short':
							case 'post_too_long':
								get_error('Post content length is invalid!');
								break;
							default:
								get_error($this->_postClass->_postErrors);
								break;
						}
						return array(
							'result' => false,							
						);
					}

					$topic = $this->_postClass->getTopicData();
					$post  = $this->_postClass->getPostData();					
					
					#################
					return array(
						'result' => true,
						'post_id' => $post['pid'],
						'state' => $post['queued'],
					);
					#################
				}
				catch( Exception $error )
				{
					get_error("Reply Topic Error!");
				}
			break;
			case 'new':
				try
				{
				    if ($this->_postClass->getPublished() !== TRUE )
				        $this->_postClass->setPublished(0);
				    
					if ( $this->_postClass->addTopic() === FALSE )
					{
						switch($this->_postClass->_postErrors) {
							case 'NO_FORUM_ID':
								get_error('No forum ID set!');
								break;
							case 'NO_AUTHOR_SET':
								get_error('No Author set!');
								break;
							case 'NO_CONTENT':
								get_error('No post content set!');
								break;
							case 'NO_SUCH_TOPIC':
								get_error('No such topic!');
								break;
							case 'NO_SUCH_FORUM':
								get_error('No such forum!');
								break;
							case 'NO_REPLY_PERM':
								get_error('Author cannot reply to this topic!');
								break;
							case 'FORUM_LOCKED':
								get_error('Forum read only!');
								break;
							case 'NO_POST_FORUM':
								get_error('Unable to post in this forum!');
								break;
							case 'post_too_short':
							case 'post_too_long':
								get_error('Post content length is invalid!');
								break;							
							default:
								get_error($this->_postClass->_postErrors);
								break;
						}
						return array(
							'result' => false,							
						);
					}
					
					$topic = $this->_postClass->getTopicData();
					$post  = $this->_postClass->getPostData();
					global $app_version;
					if (version_compare($app_version, '3.2.0', '>='))
					{
						$this->registry->getClass('classItemMarking')->markRead( array( 'forumID' => $this->_postClass->getForumData('id'), 'itemID' => $topic['tid'], 'markDate' => IPS_UNIX_TIME_NOW) );
					}
					else 
					{
						$this->registry->getClass('classItemMarking')->markRead( array( 'forumID' => $this->_postClass->getForumData('id'), 'itemID' => $topic['tid']) );
					}
					
					###############
					return array(
						'result' => true,
						'topic_id' => $topic['tid'],
						'state' => $topic['approved'] ? 0 : 1,
					);
					###############
				}
				catch( Exception $error )
				{
					get_error("Start Topic Error!");
				}
			break;
			/*
			case 'edit':
				try
				{
					if ( $this->_postClass->editPost() === FALSE )
					{
						$this->lang->loadLanguageFile( array('public_error'), 'core' );
						
						$this->showForm( $type );
					}
					
					$topic = $this->_postClass->getTopicData();
					$post  = $this->_postClass->getPostData();
					
					# Redirect
					ipsRegistry::getClass('output')->redirectScreen( $this->lang->words['post_edited'], $this->settings['base_url'] . "showtopic={$topic['tid']}&st=" . $this->request['st'] . "#entry{$post['pid']}", $topic['title_seo'] );
					
				}
				catch( Exception $error )
				{
					$this->registry->getClass('output')->showError( $error->getMessage(), 103132 );
				}
			break;
			*/
		}
	}
	
	private function _checkPostModeration( $type )
	{
		/* Does this member have mod_posts enabled? */
		if ( $this->memberData['mod_posts'] )
		{
			/* Mod Queue Forever */
			if ( $this->memberData['mod_posts'] == 1 )
			{
				return FALSE;
			}
			else
			{
				/* Do we need to remove the mod queue for this user? */
				$mod_arr = IPSMember::processBanEntry( $this->memberData['mod_posts'] );
				
				/* Yes, they are ok now */
				if ( time() >= $mod_arr['date_end'] )
				{
					IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'mod_posts' => 0 ) ) );
				}
				/* Nope, still don't want to see them */
				else
				{
					return FALSE;
				}
			}
		}
		
		/* Group can bypass mod queue */
		if( $this->memberData['g_avoid_q'] )
		{
			return TRUE;
		}
		
		/* Is the member's group moderated? */
		if ( $this->_postClass->checkGroupIsPostModerated( $this->memberData ) === TRUE )
		{
			return FALSE;
		}
		
		/* Check to see if this forum has moderation enabled */
		$forum = $this->_postClass->getForumData();

		switch( intval( $forum['preview_posts'] ) )
		{
			default:
			case 0:
				return TRUE;
			break;
			case 1:
				return FALSE;
			break;
			case 2:
				return ( $type == 'new' ) ? FALSE : TRUE;
			break;
			case 3:
				return ( $type == 'reply' ) ? FALSE : TRUE;
			break;
		}
		
		/* Our post can be seen! */
		return TRUE;
	}
	
	
	
	private function _check_guest_name()
	{
		/* is this even used anymore? 
		   I disabled it 'cos it was adding the prefix and suffix twice when using a 'found' name
		   -- Matt */
		
		if ( ! $this->memberData['member_id'] )
		{
			$this->request['UserName'] = trim( $this->request['UserName'] );
			$this->request['UserName'] = str_replace( '<br />', '', $this->request['UserName'] );
			
			$this->request['UserName'] = $this->request['UserName'] ? $this->request['UserName'] : $this->lang->words['global_guestname'] ;
			$this->request['UserName'] = IPSText::mbstrlen( $this->request['UserName'] ) > $this->settings['max_user_name_length'] ? $this->lang->words['global_guestname'] : $this->request['UserName'];
			
		}
		
		return;
	}	
}
