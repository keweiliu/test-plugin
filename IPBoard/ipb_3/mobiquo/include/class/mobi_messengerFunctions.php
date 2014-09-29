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
require_once( IPSLib::getAppDir( 'members' ) . '/sources/classes/messaging/messengerFunctions.php' );
class mobi_messengerFunctions extends messengerFunctions
{
	public function fetchConversation( $topicID, $readingMemberID, $filters=array() )
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$readingMemberID   = intval( $readingMemberID );
		$topicID     	   = intval( $topicID );
		$oStart	           = intval( $filters['offsetStart'] );
		$oEnd 	           = intval( $filters['offsetEnd'] );
		$replyData         = array();
 		$topicData         = array();
		$remapData         = array();
		$memberData		   = array();
		$missingMembers    = array();
		$whereExtra		   = '';
		
		//-----------------------------------------
		// Figure out sort key
		//-----------------------------------------
		
		switch ( $filters['sort'] )
 		{
 			case 'rdate':
 				$sortKey = 'msg.msg_date DESC';
 			break;
 			default:
 				$sortKey = 'msg.msg_date ASC';
 			break;
 		}

		if ( ! $topicID )
		{
			return array( 'topicData' => $topicData, 'replyData' => $replyData );
		}
		else
		{
			/* Get member data */
			$memberData = $this->fetchTopicParticipants( $topicID, TRUE );
			
			/* Get reading member's data */
			$readingMemberData = $memberData[ $readingMemberID ];
			
			/* Fetch topic data */
			$topicData = $this->fetchTopicData( $topicID, FALSE );
			
			/* Topic deleted? Grab topic starter details, as they won't be in the participant array */
			if ( $topicData['mt_is_deleted'] AND ( $topicData['mt_starter_id'] > 0 ) )
			{
				$memberData[ $topicData['mt_starter_id'] ] = IPSMember::load( $topicData['mt_starter_id'], 'all' );
				$memberData[ $topicData['mt_starter_id'] ]['_canBeBlocked']   = IPSMember::isIgnorable( $memberData[ $topicData['mt_starter_id'] ]['member_group_id'], $memberData[ $topicData['mt_starter_id'] ]['mgroup_others'] );
				$memberData[ $topicData['mt_starter_id'] ]                    = IPSMember::buildDisplayData( $memberData[ $topicData['mt_starter_id'] ], array( '__all__' => 1 ) );
				$memberData[ $topicData['mt_starter_id'] ]['map_user_active'] = 1;
				
				/* Set flag for topic participant starter */
				$memberData[ $topicData['mt_starter_id'] ]['map_is_starter'] = 1;
				
				foreach( $memberData as $id => $data )
				{
					$memberData[ $id ]['_topicDeleted'] = 1;
				}
			}
		
			/* Can access this topic? */
			if ( $this->canAccessTopic( $readingMemberID, $topicData, $memberData ) !== TRUE )
			{
				/* Banned? */
				if ( $readingMemberData['map_user_banned'] )
				{
					throw new Exception( "YOU_ARE_BANNED" );
				}
				else
				{
					throw new Exception( "NO_READ_PERMISSION" );
				}
			}
		
			/* Reply Data */
	 		$this->DB->build( array(  'select'	    => 'msg.*',
 									  'from'	    => array( 'message_posts' => 'msg' ),
									  'where'	    => "msg.msg_topic_id=" . $topicID . $whereExtra,
									  'order'	    => $sortKey,
									  'limit'	    => array( $oStart, $oEnd ),
									  'add_join'	=> array(
															array( 'select' => 'iu.*',
															   	   'from'   => array( 'ignored_users' => 'iu' ),
																   'where'  => 'iu.ignore_owner_id=' . $readingMemberID . ' AND iu.ignore_ignore_id=msg.msg_author_id',
																   'type'   => 'left' ),
															array( 'select' => 'm.member_group_id, m.mgroup_others',
															   	   'from'   => array( 'members' => 'm' ),
																   'where'  => 'm.member_id=msg.msg_author_id',
																   'type'   => 'left' ),
															) 
							)		);
 			$o = $this->DB->execute();

	 		//-----------------------------------------
	 		// Get the messages
	 		//-----------------------------------------

	 		while( $msg = $this->DB->fetch( $o ) )
 			{
				$msg['_ip_address'] = "";
				
				/* IP Address */
				if ( $msg['msg_ip_address'] AND $readingMemberData['g_is_supmod'] == 1 )
				{
					$msg['_ip_address'] = $msg['msg_ip_address'];
				}
				
				/* Edit */
				$msg['_canEdit']   = $this->_conversationCanEdit( $msg, $topicData, $readingMemberData );
				
				/* Delete */
				$msg['_canDelete'] = $this->_conversationCanDelete( $msg, $topicData, $readingMemberData );
				$msg['msg_post'] = post_bbcode_clean($msg['msg_post']);
				/* Format Message */
				$msg['msg_post'] = $this->_formatMessageForDisplay( $msg['msg_post'], $msg );
			
				/* Member missing? */
				if ( ! isset($memberData[ $msg['msg_author_id'] ]) )
				{
					$missingMembers[ $msg['msg_author_id'] ] = $msg['msg_author_id'];
				}
				
				$replyData[ $msg['msg_id'] ] = $msg;
			}
		}
		
		/* Members who've deleted a closed conversation? */
		if ( count( $missingMembers ) )
		{
			$_members = IPSMember::load( array_keys( $missingMembers ), 'all' );
			
			foreach( $_members as $id => $data )
			{
				$data['_canBeBlocked']   = IPSMember::isIgnorable( $memberData[ $topicData['mt_starter_id'] ]['member_group_id'], $memberData[ $topicData['mt_starter_id'] ]['mgroup_others'] );
				$data['map_user_active'] = 0;
				$memberData[ $data['member_id'] ] = IPSMember::buildDisplayData( $data, array( '__all__' => 1 ) );
			}
		}
		
		/* Update reading member's read time */
		$this->DB->update( 'message_topic_user_map', array( 'map_read_time' => time(), 'map_has_unread' => 0 ), 'map_user_id=' . intval($readingMemberData['member_id']) . ' AND map_topic_id=' . $topicID );
		
		/* Reduce the number of 'new' messages */
		$_newMsgs = intval( $this->getPersonalTopicsCount( $readingMemberID, 'new') );
		
		if ( $memberData[ $readingMemberID ]['map_has_unread'] )
		{
			$_pc = $this->rebuildFolderCount( $readingMemberID, array( 'new' => $_newMsgs ), TRUE );
			IPSMember::save( $readingMemberID, array( 'core' => array( 'msg_count_new' => $_newMsgs, 'msg_show_notification' => 0 ) ) );
			
			/* is this us? */
			if ( $readingMemberID == $this->memberData['member_id'] )
			{
				/* Reset folder data */
				$this->_dirData = $this->explodeFolderData( $_pc );
				
				/* Reset global new count */
				$this->memberData['msg_count_new'] = $_newMsgs;
			}
 		}

		/* Clean up topic title */
		$topicData['mt_title'] = str_replace( '[attachmentid=', '&#91;attachmentid=', $topicData['mt_title'] );
		
		/* Ensure our read time is updated */
		$memberData[ $readingMemberID ]['map_read_time'] = time();
		
		/* Do we  have a deleted user? */
		if ( isset( $memberData[0] ) AND $memberData[0]['member_id'] == 0 )
		{
			$memberData[0] = IPSMember::buildDisplayData(IPSMember::setUpGuest( "Deleted Member" ), array( '__all__' => 1 ) );
		}
		
		//-----------------------------------------
		// Attachments?
		//-----------------------------------------

		if ( $topicData['mt_hasattach'] )
		{
			//-----------------------------------------
			// INIT. Yes it is
			//-----------------------------------------

			$postHTML = array();

			//-----------------------------------------
			// Separate out post content
			//-----------------------------------------

			foreach( $replyData as $id => $post )
			{
				$postHTML[ $id ] = $post['msg_post'];
			}
			
			if ( ! is_object( $this->class_attach ) )
			{
				//require_once( IPSLib::getAppDir( 'core' ) . '/sources/classes/attach/class_attach.php' );
				require_once( 'mobi_class_attach.php' );
				$this->class_attach = new mobi_class_attach( $this->registry );
			}
		
			$this->class_attach->type  = 'msg';
			$this->class_attach->init();
		
			$attachHTML = $this->class_attach->renderAttachments( $postHTML );
			
			/* Now parse back in the rendered posts */
			foreach( $attachHTML as $id => $data )
			{
				/* Get rid of any lingering attachment tags */
				if ( stristr( $data['html'], "[attachment=" ) )
				{
					$data['html'] = IPSText::stripAttachTag( $data['html'] );
				}

				$replyData[ $id ]['msg_post']       = $data['html'];
				$replyData[ $id ]['attachmentHtml'] = $data['attachmentHtml'];
			}
		}
		
		/* Return */
		return array( 'topicData' => $topicData, 'replyData' => $replyData, 'memberData' => $memberData );
	}
	
	protected function _setMaxMessages( $member )
 	{
		$groups_id  = explode( ',', $member['mgroup_others'] );
 		$groupCache = $this->caches['group_cache'];

 		if ( count( $groups_id ) )
		{
			foreach( $groups_id as $pid )
			{
				if ( ! isset($groupCache[ $pid ]['g_id']) OR ! $groupCache[ $pid ]['g_id'] )
				{
					continue;
				}
				
				if ( $member['g_max_messages'] > 0 AND $groupCache[ $pid ]['g_max_messages'] > $member['g_max_messages'] )
				{
					$member['g_max_messages'] = $groupCache[ $pid ]['g_max_messages'];
				}
				else if ( $groupCache[ $pid ]['g_max_messages'] == 0 )
				{
					$member['g_max_messages'] = 0;
				}
			}
		}
		
		return $member;
 	}
	
	/**
	 * Strip away non-active participants
	 *
	 * @access	private
	 * @param	array 		Array of current topic participants (as returned by fetchTopicParticipants)
	 * @return	array
	 */
	protected function _stripNonActiveParticipants( $topicParticipants )
	{
		$_participants = array();
		
		if ( ! is_array( $topicParticipants ) )
		{
			return array();
		}
		
		foreach( $topicParticipants as $id => $data )
		{
			if ( $data['map_user_active'] )
			{
				$_participants[ $id ] = $data;
			}
		}
		
		return $_participants;
	}

	/**
	 * Function to format the actual message (applies BBcode, etc)
	 *
	 * @access	private
	 * @param	string		Raw text
	 * @param	array 		PM data
	 * @return	string		Processed text
	 */
	protected function _formatMessageForDisplay( $msgContent, $data=array() )
	{
		//-----------------------------------------
		// Reset Classes
		//-----------------------------------------
		
		IPSText::resetTextClass('bbcode');
		
		//-----------------------------------------
		// Post process the editor
		// Now we have safe HTML and bbcode
		//-----------------------------------------
		
		$this->settings[ 'max_emos'] =  0 ;

 		IPSText::getTextClass('bbcode')->parse_smilies				= 1;
 		IPSText::getTextClass('bbcode')->parse_nl2br				= 1;
 		IPSText::getTextClass('bbcode')->parse_html					= 0;
 		IPSText::getTextClass('bbcode')->parse_bbcode				= 1;
 		IPSText::getTextClass('bbcode')->parsing_section			= 'pms';
		IPSText::getTextClass( 'bbcode' )->parsing_mgroup			= $data['member_group_id'];
		IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others	= $data['mgroup_others'];
 		
 		$msgContent = IPSText::getTextClass('bbcode')->preDisplayParse( $msgContent );
 	
 		if ( IPSText::getTextClass('bbcode')->error != "" )
 		{
	 		//throw new Exception( "BBCODE_" . IPSText::getTextClass('bbcode')->error );
 		}

		return $msgContent;
	}
	
	protected function _conversationCanEdit( $msg, $topicData, $readingMemberData )
	{
		if ( $topicData['mt_is_deleted'] )
		{
			return FALSE;
		}
		
		/* Is it a system PM? */
		if ( $topicData['mt_is_system'] )
		{
			return FALSE;
		}
		
		if ( ( $msg['msg_author_id'] == $readingMemberData['member_id'] ) OR ( $readingMemberData['g_is_supmod'] == 1 ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Determines whether the message can be deleted
	 *
	 * @access	private
	 * @param	array 		Message data
	 * @param	array 		Topic data
	 * @param	array 		Reading member data
	 * @return	bool
	 */
	protected function _conversationCanDelete( $msg, $topicData, $readingMemberData )
	{
		if ( $topicData['mt_is_deleted'] )
		{
			return FALSE;
		}
		
		if ( ( $msg['msg_author_id'] == $readingMemberData['member_id'] ) OR ( $readingMemberData['g_is_supmod'] == 1 ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
		/**
	 * Flag members for a PC count reset
	 *
	 * @access	private
	 * @param	array 		Array of MEMBER ids
	 * @return	boolean
	 */
	protected function _flagForCountReset( $members )
	{
		if ( count( $members ) )
		{
			/* OK, so this is a bit naughty and should really go via IPSMember::save()
			   however, it would take a fair bit of rewriting to 'fix it' so that it could
			   save out with multiple IDs. So.... */
			$this->DB->update( 'members', array( 'msg_count_reset' => 1 ), 'member_id IN (' . implode( ',', array_keys( $members ) ) . ')' );
		}
		
		return TRUE;
	}
	
	/**
	 * Makes attachments permananent
	 *
	 * @access	private
	 * @param	string		Post Key
	 * @param	int			Msg ID
	 * @param	int			Topic ID
	 * @return	int
	 */
	protected function _makeAttachmentsPermanent( $postKey, $msgID, $topicID )
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$cnt = array( 'cnt' => 0 );
		
		//-----------------------------------------
		// Attachments: Re-affirm...
		//-----------------------------------------
		
		require_once( IPSLib::getAppDir( 'core' ) . '/sources/classes/attach/class_attach.php' );
		$class_attach                  =  new class_attach( $this->registry );
		$class_attach->type            =  'msg';
		$class_attach->attach_post_key =  $postKey;
		$class_attach->attach_rel_id   =  $msgID;
		$class_attach->init();
		
		$return = $class_attach->postProcessUpload( array( 'mt_id' => $topicID ) );

		return intval( $return['count'] );
	}
	
	/**
	 * Default folder for people won't don't have any
	 *
	 * @access	private
	 * @return	array
	 */
	protected function _fetchDefaultFolders()
	{
		return array( 'new'   	=> array(   'id'        => 'new',
									      'real'      => $this->lang->words['msgFolder_new'],
									   	  'count'     => 0,
									   	  'protected' => 1 ),
					  'myconvo' => array( 'id'        => 'myconvo',
									      'real'      => $this->lang->words['msgFolder_myconvo'],
									      'count'     => 0,
									      'protected' => 1 ),
					  'drafts'  => array( 'id'        => 'drafts',
										  'real'      => $this->lang->words['msgFolder_drafts'],
										  'count'     => 0,
										  'protected' => 1 ) );
	}
}
?>