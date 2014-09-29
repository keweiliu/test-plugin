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
require_once (IPS_ROOT_PATH . 'applications/forums/extensions/usercpForms.php');

class mobi_usercpForms_forums extends usercpForms_forums
{
	public function runCustomEvent( $currentArea )
	{
		switch( $currentArea )
		{
			# Watched Topics / Forums
			case 'updateWatchTopics':
				$result = $this->_customEvent_updateTopics();
			break;
			case 'updateWatchForums':
				$result = $this->_customEvent_updateForums();
			break;
			case 'watch':
				if ( $this->request['do'] == 'saveWatch' )
				{
					$result = $this->_customEvent_watch( TRUE );
				}
				else
				{
					$result = $this->_customEvent_watch();
				}
			break;
		}

		// Turn off save button		
		$this->hide_form_and_save_button = 1;
		
		return $result;
	}
	
	
	/**
	 * Custom Event: Watch A Topic
	 *
	 * @access	private
	 * @author	Matt Mecham
	 * @param	bool	Whether to save it or not
	 * @return	void
	 */
	private function _customEvent_watch( $saveIt=FALSE )
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------		
		$watch      = $this->request['watch'] == 'forum' ? 'forum' : 'topic';
		$topicID    = intval($this->request['tid']);
		$forumID    = intval($this->request['fid']);
		$forum   	= array();
		$topic	    = array();
		###############################
		$this->request['auth_key'] = $this->member->form_hash;
		$this->request['emailtype'] = 'delayed';	
		###############################
		
		if ( $watch == 'topic' )
		{
			$topic = $this->DB->buildAndFetch( array( 'select' => '*', 'from' => 'topics', 'where' => 'tid='.$topicID ) );			
			if ( ! $topic['tid'] ) { 
				get_error("No Such Topic!");
			}			
			$forum   = $this->registry->getClass('class_forums')->forum_by_id[ $topic['forum_id'] ];
			$forumID = $topic['forum_id'];
		}
		else
		{
			$forum = $this->registry->getClass('class_forums')->forum_by_id[ $forumID ];
		}
		
		// Permy check
		if ( IPSMember::checkPermissions('read', $forumID ) !== TRUE )
		{
			get_error("No permission!");
		}
		
		// Passy check
		if ( ! in_array( $this->memberData['member_group_id'], explode(",", $forum['password_override']) ) AND ( isset($forum['password']) AND $forum['password'] != "" ) )
		{
			if ( $this->registry->getClass('class_forums')->forumsComparePassword( $forum['id'] ) != TRUE )
			{
				get_error("Do not Pass the Password Check!");
			}
		}
		
		//-----------------------------------------
		// Have we already subscribed?
		//-----------------------------------------		
		if ( $watch == 'forum' )
		{
			$tmp = $this->DB->buildAndFetch( array( 
													'select' => 'frid as tmpid',
													'from'   => 'forum_tracker',
													'where'  => "forum_id={$forumID} AND member_id=".$this->memberData['member_id'] 
											)	 );
		}
		else
		{
			$tmp = $this->DB->buildAndFetch( array( 
													'select' => 'trid as tmpid',
													'from'   => 'tracker',
													'where'  => "topic_id={$topicID} AND member_id=".$this->memberData['member_id'] 
											)	 );
		}		
		if ( $tmp['tmpid'] )
		{
			get_error("Already Subscribed!");
		}
		
		//-----------------------------------------
		// What to do...
		//-----------------------------------------
		
		if ( ! $saveIt )
		{
			return false;
			//return $this->registry->getClass('output')->getTemplate('ucp')->watchChoices( $forum, $topic, $watch );
		}
		else
		{
			// Auth check			
			if ( $this->request['auth_key'] != $this->member->form_hash )
			{
				get_error("Auth Key is Invalid!");
			}
		
			switch ( $this->request['emailtype'] )
			{
				case 'immediate':
					$_method = 'immediate';
					break;
				case 'delayed':
					$_method = 'delayed';
					break;
				case 'none':
					$_method = 'none';
					break;
				case 'daily':
					$_method = 'daily';
					break;
				case 'weekly':
					$_method = 'weekly';
					break;
				default:
					$_method = 'delayed';
					break;
			}

			if ( $watch == 'forum' )
			{
				$this->DB->insert( 'forum_tracker', array (
														 'member_id'        => $this->memberData['member_id'],
														 'forum_id'         => $forumID,
														 'start_date'       => time(),
														 'forum_track_type' => $_method,
											  )       );
				
				$this->registry->getClass('class_forums')->recacheWatchedForums( $this->memberData['member_id'] );
				
				//$this->registry->getClass('output')->redirectScreen( $this->lang->words['sub_added'], $this->settings['base_url'] . "showforum={$forumID}", $forum['name_seo'] );	
			
			}
			else
			{
				$this->DB->insert( 'tracker',  array (
												   'member_id'        => $this->memberData['member_id'],
												   'topic_id'         => $topicID,
												   'start_date'       => time(),
												   'topic_track_type' => $_method,
										)       );
				//$this->registry->getClass('output')->redirectScreen( $this->lang->words['sub_added'], $this->settings['base_url'] . "showtopic={$topicID}&st=" . $this->request['st'], $topic['title_seo'] );
			}
			
			return true;
		}
	}
	
	
		/**
	 * Custom Event: Update watched topics
	 *
	 * @access	private
	 * @author	Matt Mecham
	 * @return	void
	 */
	private function _customEvent_updateTopics()
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$ids     = array();
 		$allowed = array( 'none', 'immediate', 'delayed', 'daily', 'weekly' );
		###############################
		$this->request['auth_key'] = $this->member->form_hash;	
		###############################
		//-----------------------------------------
		// Get IDs
		//-----------------------------------------
		if ( is_array( $this->request['topicIDs'] ) )
		{
			foreach( $this->request['topicIDs'] as $id => $value )
			{
				$ids[] = intval( $id );
			}
		}
 		
 		if ( $this->request['auth_key'] != $this->member->form_hash )
 		{
 			get_error("Auth Key is Invalid!");
 		}
 	
 		if ( count($ids) > 0 )
 		{
 			if ( $this->request['trackchoice'] == 'unsubscribe' )
 			{
 				$this->DB->delete( 'tracker', "member_id='{$this->memberData['member_id']}' and topic_id IN ( " . implode( ",", $ids ) . ")" );
 				return true;
 			}
 			else if ( in_array( $this->request['trackchoice'], $allowed ) )
 			{
 				$this->DB->update( 'tracker', array( 'topic_track_type' => $this->request['trackchoice'] ), "member_id='{$this->memberData['member_id']}' and topic_id IN (".implode( ",", $ids ).")" );
 			}
 		}
 		return false;
 		
// 		if( $this->request['topicReturn'] )
// 		{
// 			$this->registry->getClass('output')->silentRedirect( $this->settings['base_url'] . 'showtopic=' . intval( $this->request['topicReturn'] ) );
// 		}
// 		else
// 		{
// 	    	$this->registry->getClass('output')->silentRedirect( $this->settings['base_url']."app=core&amp;module=usercp&amp;tab=forums&amp;area=topicsubs" );
// 	    }
	}
	
	private function _customEvent_updateForums()
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$ids     = array();
 		$allowed = array( 'none', 'immediate', 'delayed', 'daily', 'weekly' );

		//-----------------------------------------
		// Get IDs
		//-----------------------------------------
		
		if ( is_array( $this->request['forumIDs'] ) )
		{
			foreach( $this->request['forumIDs'] as $id => $value )
			{
				$ids[] = intval( $id );
			}
		}

		//-----------------------------------------
 		// what we doing?
 		//-----------------------------------------
 		
 		if ( count($ids) > 0 )
 		{
 			if ( $this->request['trackchoice'] == 'unsubscribe' )
 			{
 				$this->DB->buildAndFetch( array( 'delete' => 'forum_tracker', 'where' => "member_id=" . $this->memberData['member_id'] . " and forum_id IN (".implode( ",", $ids ).")" ) );
 			}
 			else if ( in_array( $this->request['trackchoice'], $allowed ) )
 			{
 				$this->DB->update( 'forum_tracker', array( 'forum_track_type' => $this->request['trackchoice'] ), "member_id=" . $this->memberData['member_id'] . " and forum_id IN (".implode( ",", $ids ).")" );
 			}
 		}

		$this->registry->getClass('class_forums')->recacheWatchedForums( $this->memberData['member_id'] );
        
        return true;
	}
	
	public function showForumSubs()
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$mainForumArray = array();
		$forums         = array();
		$forumIDs		= array();
		$topParents     = array();
		
		//-----------------------------------------
		// Turn off global form stuff
		//-----------------------------------------
		
		$this->hide_form_and_save_button = 1;
		
		//-----------------------------------------
 		// INIT...
 		//-----------------------------------------
 		
 		$remap = array( 'none'      => 'subs_none_title',
						'immediate' => 'subs_immediate',
						'delayed'   => 'subs_delayed',
						'daily'     => 'subs_daily',
						'weekly'    => 'subs_weekly'
					  );
					  
 		$mainForumArray = array();

 		//-----------------------------------------
 		// Query the DB for the subby toppy-ics - at the same time
 		// we get the forum and topic info, 'cos we rule.
 		//-----------------------------------------
 		
		$this->DB->build( array( 'select'	=> '*',
								 'from'	    => 'forum_tracker',
								 'where'	=> 'member_id=' . $this->memberData['member_id'] ) );
		$this->DB->execute();
		
 		while( $forum = $this->DB->fetch() )
 		{
			$topParents[]                 = $this->registry->getClass('class_forums')->fetchTopParentID( $forum['forum_id'] );
			$forum['_type']               = $remap[ $forum['forum_track_type'] ];
			$forums[ $forum['forum_id'] ] = $forum;
		}
		
		//-----------------------------------------
		// Get new count
		//-----------------------------------------
		
		if ( is_array( $forums ) AND count( $forums ) )
		{
			$this->DB->build( array( 'select'   => 'forum_id, COUNT(*) as newTopics',
									 'from'     => 'topics',
									 'where'    => 'forum_id IN (' . implode( ',', array_keys( $forums ) ) . ') AND last_post > ' . $this->memberData['last_visit'],
									 'group'    => 'forum_id' ) );
														
			$this->DB->execute();
			
			while( $row = $this->DB->fetch() )
			{
				$forums[ $row['forum_id'] ]['_newTopics'] = $row['newTopics'];
			}
		}
		
		//-----------------------------------------
		// Now, loop through all forums...
		//-----------------------------------------
		$forums_list = array();
		foreach( $this->registry->getClass('class_forums')->forum_cache['root'] as $id => $data )
		{
			if ( in_array( $id, $topParents ) )
			{
//				$mainForumArray[ $id ] = array( '_data'   => $data,
//												'_forums' => $this->_showForumSubsRecurse( $forums, $id ) );
                foreach ($this->_showForumSubsRecurse( $forums, $id ) as $forum_data)
                {
                    $forums_list[] = new xmlrpcval(array(
                        'forum_id'      => new xmlrpcval($forum_data['id']),
                        'forum_name'    => new xmlrpcval(subject_clean($forum_data['name']), 'base64'),
                        'icon_url'      => new xmlrpcval(''),
                        'is_protected'  => new xmlrpcval((isset($forum_data['password']) && $forum_data['password'] != '') ? true : false, 'boolean'),
                        'sub_only'      => new xmlrpcval(($forum_data['sub_can_post'] && (isset($forum_data['status']) && $forum_data['status'])) ? false : true, 'boolean'),
                    ), 'struct');
                }
			}
		}

		return $forums_list;
	}
	
    private function _showForumSubsRecurse( $forums, $root, $forumArray=array(), $depth=0 )
    {
    	if ( is_array( $this->registry->getClass('class_forums')->forum_cache[ $root ] ) AND count( $this->registry->getClass('class_forums')->forum_cache[ $root ] ) )
    	{
    		foreach( $this->registry->getClass('class_forums')->forum_cache[ $root ] as $id => $forum )
    		{
    			if ( in_array( $id, array_keys( $forums ) ) )
    			{ 
    				//-----------------------------------------
    				// Got perms to see this forum?
    				//-----------------------------------------
    		
    				if ( ! $this->registry->getClass('class_forums')->forum_by_id[ $forum['id'] ] )
    				{
    					continue;
    				}
    				
    				$forum['_depth']      = $depth;
    				$forum['_newTopics']  = $forums[ $forum['id'] ]['_newTopics'];
    				$forum['_type']       = $forums[ $forum['id'] ]['_type'];
    				
    				$forum['folder_icon'] = $this->registry->getClass('class_forums')->forumsNewPosts($forum);
    						
    				$forum['last_title'] = str_replace( "&#33;" , "!", $forum['last_title'] );
    				$forum['last_title'] = str_replace( "&quot;", '"', $forum['last_title'] );
    			
    				if ( IPSText::mbstrlen($forum['last_title']) > 30 )
    				{
    					$forum['last_title'] = IPSText::truncate( $forum['last_title'], 30 );
    				}
    		
    				$forumArray[ $forum['id'] ] = $forum;
    			}
    			
    			$forumArray = $this->_showForumSubsRecurse( $forums, $forum['id'], $forumArray, $depth + 1 );
    		}
    	}
    	
    	return $forumArray;
    }
}

require_once (IPS_ROOT_PATH . 'applications/members/extensions/usercpForms.php');
class mobi_usercpForms_members extends usercpForms_members
{
    public function saveAvatar()
	{
		if( ! $this->settings['avatars_on'] )
		{
			get_error('members_profile_disabled');
		}
		
		try
		{
			IPSMember::getFunction()->saveNewAvatar( $this->memberData['member_id'] );
		}
		catch( Exception $error )
		{
			$msg = $error->getMessage();
			
			switch( $msg )
			{
				case 'NO_MEMBER_ID':
					return array( 0 => $this->lang->words['saveavatar_nomem'] );
				break;
				case 'NO_PERMISSION':
					return array( 0 => $this->lang->words['saveavatar_noperm'] );
				break;
				case 'UPLOAD_NO_IMAGE':
					return array( 0 => $this->lang->words['saveavatar_nofile'] );
				break;
				case 'UPLOAD_INVALID_FILE_EXT':
					return array( 0 => $this->lang->words['saveavatar_noimg'] );
				break;
				case 'UPLOAD_TOO_LARGE':
					return array( 0 => $this->lang->words['saveavatar_toobig'] );
				break;
				case 'UPLOAD_CANT_BE_MOVED':
					return array( 0 => $this->lang->words['saveavatar_chmod'] );
				break;
				case 'UPLOAD_NOT_IMAGE':
					return array( 0 => $this->lang->words['saveavatar_badimg'] );
				break;
				case 'NO_AVATAR_TO_SAVE':
					return array( 0 => $this->lang->words['saveavatar_noimg2'] );
				break;
				case 'INVALID_FILE_EXT':
					return array( 0 => $this->lang->words['saveavatar_badimgext'] );
				break;
			}
		}
	}}