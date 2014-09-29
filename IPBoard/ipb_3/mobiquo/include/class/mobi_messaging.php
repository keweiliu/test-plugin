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
require_once( 'mobi_messengerFunctions.php' );
class mobi_member_message {
	public function __construct( ipsRegistry $registry )
	{
		/* Make object */
		$this->registry = $registry;
		$this->DB       = $this->registry->DB();
		$this->settings =& $this->registry->fetchSettings();
		$this->request  =& $this->registry->fetchRequest();
		$this->lang     = $this->registry->getClass('class_localization');
		$this->member   = $this->registry->member();
		$this->memberData =& $this->registry->member()->fetchMemberData();
		$this->cache    = $this->registry->cache();
		$this->caches   =& $this->registry->cache()->fetchCaches();
		
		$this->messengerFunctions = new mobi_messengerFunctions( $this->registry );
	}
	
	private function checkPermission()
	{
		$member = $this->memberData;
		if ( ! $member['member_id'] )
		{
			get_error('messenger_no_guests');
		}

		if ( ! $member['g_use_pm'] )
		{
			get_error('messenger_disabled');
		}

		if ( $member['members_disable_pm'] )
		{
			get_error('messenger_disabled');
		}
		
		if( ! IPSLib::moduleIsEnabled( 'messaging', 'members' ) )
		{
			get_error('messenger_disabled');
		}
	}	
	
	public function get_box_info()
	{
		$this->checkPermission();
		
		$member = $this->registry->member()->fetchMemberData();
		$total = $this->messengerFunctions->buildMessageTotals();
		$message_room_count = $member['g_max_messages'] - $total['msg_total'];

		$box_info = array(
			array (
				'box_id' => '1',
				'box_name' => 'Inbox',
				'msg_count' => 0,
				'unread_count' => 0,
				'box_type' => 'INBOX',
			),
			array (
				'box_id' => '2',
				'box_name' => 'Outbox',
				'msg_count' => 0,
				'unread_count' => 0,
				'box_type' => 'SENT',
			)
		);
		
		return array(
			'message_room_count' => $message_room_count,
			'box_info' => $box_info,
		);
		
	}
	
	
	public function get_inbox_stat()
	{
		$this->checkPermission();		
		
		return $this->messengerFunctions->getPersonalTopicsCount( $this->memberData['member_id'], 'new' );
	}
	
	public function get_box($box_id = 1, $start_num=0, $end_num=20)
	{
		$this->checkPermission();
		$start 	= 0;
		$p_end  = 50;	
		$topic_ids = array();	
		foreach($this->messengerFunctions->_dirData as $FolderId => $data) {
			if ($FolderId == 'new' or $FolderId == 'draft') {
				continue;
			}
			$FolderData = $this->messengerFunctions->getPersonalTopicsList( $this->memberData['member_id'], 
																$FolderId,
																array( 'sort' => $sort, 'offsetStart' => $start, 'offsetEnd' => $p_end ) );
						
			foreach($FolderData as $id => $data){
				$topic_ids[$id] = $data['map_has_unread'];
			}
		}
		
		$inbox_data = array();
		$outbox_data = array();	
		foreach($topic_ids as $tid => $data) {
			try
			{
				
				######get conversations#################
				$conversationData = $this->messengerFunctions->fetchConversation( $tid, $this->memberData['member_id'], array( 'offsetStart' => 0, 'offsetEnd' => 100 ));
				//var_dump($conversationData['memberData']);
				
				foreach($conversationData['replyData'] as $msg_id => $msg_data) {
					$msg_data['msg_subject'] = $conversationData['topicData']['mt_title'];
					$msg_author_id = $msg_data['msg_author_id'];
					$msg_data['msg_author_username'] = $conversationData['memberData'][$msg_author_id]['name'];
					$msg_data['msg_author'] = $conversationData['memberData'][$msg_author_id]['members_display_name'];
					
					$msg_data['msg_to'] = array();
					foreach($conversationData['memberData'] as $member_id => $memData) {
						if ($member_id == $msg_data['msg_author_id']) {
							continue;
						}
						$msg_data['msg_to'][] = array(
						    'name'      => $memData['members_display_name'],
						    'username'  => $memData['name']
						);
						$msg_data['ids'][] 	  = $member_id;
					}
					
					if ($this->memberData['member_id'] == $msg_author_id) {
						$icon_member_id = $msg_data['ids'][0];
					} else {
						$icon_member_id = $msg_author_id;
					}
					$sender_data = $conversationData['memberData'][$icon_member_id];
		    		$msg_data['msg_sender_icon'] = get_avatar($icon_member_id);
					
					if ($data == 1) {#######has unread######
						$map_read_time = $conversationData['memberData'][$msg_author_id]['map_read_time'];
						if (time() > $map_read_time) {
							$msg_data['msg_is_unread'] = 1;
						}
					}
					
					if ($msg_data['msg_author_id'] == $this->memberData['member_id']) {
						if( isset($outbox_data['msg_date']) and is_array($outbox_data['msg_date'])) {
							$outbox_data[ $msg_data['msg_date'] ][] = $msg_data;
						} else {
							$outbox_data[ $msg_data['msg_date'] ] = array();
							$outbox_data[ $msg_data['msg_date'] ][] = $msg_data;
						}						
					} else {
						if( isset($inbox_data['msg_date']) and is_array($inbox_data['msg_date'])) {
							$inbox_data[ $msg_data['msg_date'] ][] = $msg_data;
						} else {
							$inbox_data[ $msg_data['msg_date'] ] = array();
							$inbox_data[ $msg_data['msg_date'] ][] = $msg_data;
						}
					}
				}
	 		}
			catch( Exception $error )
			{
				continue;
	 		}				
		}

		if ($box_id == 1) {
			$req_box_data = $inbox_data;
		} else {
			$req_box_data = $outbox_data;
		}
		
		krsort($req_box_data);
		
		//var_dump($req_box_data);
		$return_box_data = array();
		
		foreach ($req_box_data as $date => $datas) {
			foreach($datas as $data) {
				$return_box_data[] = $data;
			}
		}
		
		######get unread TOPICS number#############
		$newprvpm = 0;
		
		$total_msg_count = count($return_box_data);
		
		if ($box_id == 1) {
			foreach($return_box_data as $data) {
				if($data['msg_is_unread'] == 1) {
					$newprvpm++;
				}
			}
		}
		$return_list = array();
		$lists = array_slice($return_box_data, $start_num, $end_num-$start_num+1);
		foreach($lists as $list) {
			$message = $this->DB->buildAndFetch( array(
													'select' => 'm.msg_post', 
													'from'   => 'message_posts m', 
													'where'  => "m.msg_id= {$list['msg_id']} ")	);
		
			$list['msg_post'] = $message['msg_post'];
			$return_list[] = $list;
		}
		return array (
			'total_msg_count' => $total_msg_count,
			'total_unread_count' => $newprvpm,
			'list' => $return_list,
		);		
	}
	
	public function get_message($message_id)
	{
		$this->checkPermission();
		$start 	= 0;
		$p_end  = 50;	
		$message_id = intval($message_id);
		$topic = $this->DB->buildAndFetch( array(
													'select' => 'm.*', 
													'from'   => 'message_posts m', 
													'where'  => "m.msg_id= {$message_id} ")	);
		
		$tid = $topic['msg_topic_id'];
		
		if (! $tid) {
			return array();
		}

		try
		{
			######get conversations#################
			$conversationData = $this->messengerFunctions->fetchConversation( $tid, $this->memberData['member_id'], array( 'offsetStart' => 0, 'offsetEnd' => 100 ));
			$msg_data = $conversationData['replyData'][$message_id];
			if(!empty($msg_data['msg_post']))
			{
				$msg_data['msg_post'] = post_bbcode_clean($msg_data['msg_post']);
			}
			if (! $msg_data) {
				return array();
			}
			$msg_data['msg_subject'] = $conversationData['topicData']['mt_title'];
			$msg_author_id = $msg_data['msg_author_id'];
			//var_dump($conversationData['memberData'][$msg_author_id]);
			
			$msg_data['msg_author_username'] = $conversationData['memberData'][$msg_author_id]['name'];
			$msg_data['msg_author'] = $conversationData['memberData'][$msg_author_id]['members_display_name'];
			
			$msg_data['msg_to'] = array();
			foreach($conversationData['memberData'] as $member_id => $memData) {
				if ($member_id == $msg_data['msg_author_id']) {
					continue;
				}
				$msg_data['msg_to'][] = array(
				    'name'      => $memData['members_display_name'],
				    'username'  => $memData['name']
				);
				$msg_data['ids'][] = $member_id;
			}
			if ($this->memberData['member_id'] == $msg_author_id) {
				$icon_member_id = $msg_data['ids'][0];
			} else {
				$icon_member_id = $msg_author_id;
			}
			$sender_data = $conversationData['memberData'][$icon_member_id];
    		$msg_data['msg_sender_icon'] = get_avatar($icon_member_id);
			
			return $msg_data;
 		}
		catch( Exception $error )
		{
			return array ();
 		}
	}
	
	public function get_quote_pm($message_id)
	{
		$this->checkPermission();

		$message_id = intval($message_id);
		//-----------------------------------------
		// Reset Classes
		//-----------------------------------------
		
		IPSText::resetTextClass('bbcode');
		IPSText::resetTextClass('editor');
		
		//-----------------------------------------
		// Load lang file
		//-----------------------------------------
		
		$this->registry->getClass( 'class_localization')->loadLanguageFile( array( "public_error", "public_editors" ), 'core' );
		
		//-----------------------------------------
		// Post Key
		//-----------------------------------------
		
		$this->_postKey = ( $this->request['postKey'] AND $this->request['postKey'] != '' ) ? $this->request['postKey'] : md5(microtime()); 
		
		
		$this->lang->words['the_max_length'] = $this->settings['max_post_length'] * 1024;
		
    	//-----------------------------------------
    	// Language
    	//-----------------------------------------
		
		/* Load post lang file for attachments stuff */
		$this->registry->class_localization->loadLanguageFile( array( 'public_post' ), 'forums' );
		$this->registry->class_localization->loadLanguageFile( array( 'public_messaging' ), 'members' );
		
		
		/* Messenger Totals */
		$totals = $this->messengerFunctions->buildMessageTotals();
    	
		return $this->_showForm($message_id);
	}
	
	public function delete_message($message_id)
	{
		$this->checkPermission();
		
		$message = $this->DB->buildAndFetch( array(
													'select' => 'm.*', 
													'from'   => 'message_posts m', 
													'where'  => "m.msg_id= {$message_id} ")	);
		
		if (! $message) {
			return false;
		}
		
		if ($message['msg_author_id'] == $this->memberData['member_id'] AND $message['msg_is_first_post']) {
			$this->request['topicID'] = $message['msg_topic_id'];
			$this->_deleteConversation();
		} elseif ($message['msg_author_id'] == $this->memberData['member_id']) {
			$this->request['msgID'] = $message['msg_id'];
			$this->request['topicID'] = $message['msg_topic_id'];
			$this->_deleteReply();
		} else {
			$this->request['topicID'] = $message['msg_topic_id'];
			$this->_deleteConversation();
		}
		return true;
	}
	
	private function _deleteReply()
	{
		$authKey    = $this->request['authKey'];
		$topicID	= intval( $this->request['topicID'] );
 		$msgID 	    = intval( $this->request['msgID'] );
		
		if ( $this->messengerFunctions->canAccessTopic( $this->memberData['member_id'], $topicID ) !== TRUE )
		{
			get_error("Can not delete this Message!");
		}
		
		if ( $this->messengerFunctions->deleteMessages( array( $msgID ), $this->memberData['member_id'] ) !== TRUE )
		{
			get_error("No Permissions to delete this Message!");
		}
	}
	
		/**
	 * Leave a conversation (non topic starter)
	 *
	 * @access 	private
	 * @return	mixed	void, or HTML
	 */
	private function _deleteConversation()
	{
		$topicID	 = intval( $this->request['topicID'] );
		try
		{
			$this->messengerFunctions->deleteTopics( $this->memberData['member_id'], array( $topicID )  );
		}
		catch( Exception $error )
		{
			$msg = $error->getMessage();
			$this->_errorString = $msg;			
			get_error($msg);
		}
	}
	
	
	public function create_message($action= 0, $reply_message_id = '0')
	{
		$this->request['auth_key'] = $this->member->form_hash;
		$this->checkPermission();
		$this->_postKey = ( $this->request['postKey'] AND $this->request['postKey'] != '' ) ? $this->request['postKey'] : md5(microtime());
		
		if ($action == 1) {######reply!!##############
			$topic = $this->DB->buildAndFetch( array(
													'select' => 'm.*', 
													'from'   => 'message_posts m', 
													'where'  => "m.msg_id= {$reply_message_id} ")	);
		
			$tid = $topic['msg_topic_id'];		
			$this->request['topicID'] = $tid;
			$this->_sendReply();
		} else {######Create New Topic!!##############
			$this->_sendNewPersonalTopic();
		}
		
	}
	
	private function _sendReply()
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$authKey    = $this->request['authKey'];
		$topicID	= intval( $this->request['topicID'] );
 		$msgContent = to_local($_POST['msgContent']);
		
 		//-----------------------------------------
 		// Error checking
 		//-----------------------------------------
 		
 		if ( IPSText::mbstrlen( trim( IPSText::br2nl( $_POST['msgContent'] ) ) ) < 2 )
 		{
 			get_error("The length of Content is invalid!");
 		}
 		
 		if ( $this->request['auth_key'] != $this->member->form_hash )
		{
			get_error("Author Key is invalid!");
		}
		
 		//-----------------------------------------
 		// Add reply
 		//-----------------------------------------
 		
		try
		{
 			$msgID = $this->messengerFunctions->sendReply( $this->memberData['member_id'], $topicID, $msgContent, array( 'postKey' => $this->_postKey ) );
 		}
		catch( Exception $error )
		{
			$msg = $error->getMessage();
			
			if ( strstr( $msg, 'BBCODE_' ) )
			{
				$msg = str_replace( 'BBCODE_', '', $msg );
				get_error($this->lang->words[ $msg ]);
			}
			else if ( isset($this->lang->words[ 'err_' . $msg ]) )
			{
				$_msgString = $this->lang->words[ 'err_' . $msg ];
				$_msgString = str_replace( '#NAMES#'   , implode( ",", $this->messengerFunctions->exceptionData ), $_msgString );
				$_msgString = str_replace( '#TONAME#'  , $toMember['members_display_name']    , $_msgString );
				$_msgString = str_replace( '#FROMNAME#', $this->memberData['members_display_name'], $_msgString );
			}
			else
			{
				$_msgString = $this->lang->words['err_UNKNOWN'] . ' ' . $msg;
			}
			get_error($_msgString);
		}
	}
	
	
	private function _sendNewPersonalTopic()
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		if( $this->messengerFunctions->checkHasHitMax() )
		{
			get_error("Surpass the Max count Per day!");
		}
		
		$msgTitle     = to_local(IPSText::getTextClass('bbcode')->stripBadWords( trim( IPSText::parseCleanValue( $_POST['msg_title'] ) ) ));
		$authKey      = $this->request['auth_key'];
		$sendToName   = to_local($this->request['entered_name']);
		$sendToID	  = intval( $this->request['toMemberID'] );
		$sendType     = trim( $this->request['sendType'] );
		$_inviteUsers = to_local(trim( $this->request['inviteUsers'] ));
 		$msgContent   = to_local($_POST['Post']);
		$topicID      = $this->request['topicID'];
		$inviteUsers  = array();
		$draft        = ( $this->request['save'] ) ? TRUE : FALSE;
		
 		//-----------------------------------------
 		// Error checking
 		//-----------------------------------------
 		
 		if ( IPSText::mbstrlen( trim( $msgTitle ) ) < 2 )
 		{
 			get_error("The length of Title is invalid!");
 		}
 		
 		if ( IPSText::mbstrlen( trim( IPSText::br2nl( $_POST['Post'] ) ) ) < 3 )
 		{
 			get_error("The length of Content is invalid!");
 		}
 		
 		if ( $this->request['auth_key'] != $this->member->form_hash )
		{
			get_error("Author Key is invalid!");
		}
 		
 		if ( $sendToID AND $sendToName == "" )
 		{
 			get_error("Please set members you're sending to!");
 		}
		
		//-----------------------------------------
		// Invite Users
		//-----------------------------------------
		
		if ( $this->memberData['g_max_mass_pm'] AND $_inviteUsers )
		{
			$_tmp = array();
			
			foreach( explode( ',', $_inviteUsers ) as $name )
			{
				$name = trim( $name );
				
				if ( $name )
				{
					$inviteUsers[] = $name;
				}
			}
		}

		//-----------------------------------------
		// Grab member ID
		//-----------------------------------------
		
		$toMember = ( $sendToID ) ? IPSMember::load( $sendToID, 'core' ) :  IPSMember::load( $sendToName, 'core', 'displayname' );
 		
		if ( ! $toMember['member_id'] )
		{
			get_error("Please set members you're sending to!");
		}
		
 		//-----------------------------------------
 		// Send .. or.. save...
 		//-----------------------------------------

		try
		{
 			$this->messengerFunctions->sendNewPersonalTopic( $toMember['member_id'], 
 						$this->memberData['member_id'], $inviteUsers, $msgTitle, $msgContent, array( 'isDraft'  => $draft,
																						'topicID'  => $topicID,
																						 'sendMode' => $sendType,
																						 'postKey'  => $this->_postKey ) );
 		}
		catch( Exception $error )
		{
			$msg = $error->getMessage();
			
			if ( strstr( $msg, 'BBCODE_' ) )
			{
				$msg = str_replace( 'BBCODE_', '', $msg );
				get_error($this->lang->words[ $msg ]);
			}
			else if ( isset($this->lang->words[ 'err_' . $msg ]) )
			{
				$_msgString = $this->lang->words[ 'err_' . $msg ];
				$_msgString = str_replace( '#NAMES#'   , implode( ",", $this->messengerFunctions->exceptionData ), $_msgString );
				$_msgString = str_replace( '#TONAME#'  , $toMember['members_display_name']    , $_msgString );
				$_msgString = str_replace( '#FROMNAME#', $this->memberData['members_display_name'], $_msgString );
				$_msgString = str_replace( '#DATE#'    , $this->messengerFunctions->exceptionData[0], $_msgString );
			}
			else
			{
				$_msgString = $this->lang->words['err_UNKNOWN'] . ' ' . $msg;
			}
			get_error($_msgString);
		}	
	}
	
	private function _showForm( $msgID )
	{
		$msgData = $this->DB->buildAndFetch( array( 'select' => 'mp.*',
                                                    'from'   => array( 'message_posts' => 'mp'),
                                                    'where'  => 'mp.msg_id=' . intval( $msgID ),
                                                    'add_join'	=> array(
                                                    	array( 'select' => 'mt.mt_title',
                                                    	   	   'from'   => array( 'message_topics' => 'mt' ),
                                                    		   'where'  => 'mp.msg_topic_id = mt.mt_id',
                                                    		   'type'   => 'left' ),
                                                    	),
                                                    ));
		
		$memberData                  = IPSMember::load( $msgData['msg_author_id'], 'all' );
		$memberData['_canBeBlocked'] = IPSMember::isIgnorable( $memberData['member_group_id'], $memberData['mgroup_others'] );
		$memberData                  = IPSMember::buildDisplayData( $memberData, array( '__all__' => 1 ) );
		$msgData                     = array_merge( $msgData, $memberData );
		
		$displayData['id'] = $msgData['msg_id'];
		$displayData['title'] = $msgData['mt_title'];
		
		if ( $msgData['msg_post'] )
		{
			IPSText::getTextClass('bbcode')->parse_html		= $this->settings['msg_allow_html'];
			IPSText::getTextClass('bbcode')->parse_nl2br	= 1;
			IPSText::getTextClass('bbcode')->parse_smilies	= 1;
			IPSText::getTextClass('bbcode')->parse_bbcode	= $this->settings['msg_allow_code'];
			IPSText::getTextClass('bbcode')->parsing_section = 'pms';

			$msgData['msg_post'] = IPSText::getTextClass('bbcode')->preEditParse( $msgData['msg_post'] );
			
			$displayData['message'] = "[quote name='".IPSText::getTextClass( 'bbcode' )->makeQuoteSafe($msgData['members_display_name'])."' timestamp='" . $msgData['msg_date'] ."']\n{$msgData['msg_post']}".'[/quote]'."\n\n\n";
		}

 		//-----------------------------------------
 		// Is this RTE? If so, convert BBCode
 		//-----------------------------------------
 		
 		if ( IPSText::getTextClass('editor')->method == 'rte' AND $displayData['message'] )
 		{
			if ( count( $errors ) or $preview )
			{
				$displayData['message'] = stripslashes( $displayData['message'] );
			}
			
 			$displayData['message'] = IPSText::getTextClass('bbcode')->convertForRTE( $displayData['message'] );
 		}
 		else if ( $displayData['message'] )
 		{
 			$displayData['message'] = IPSText::stripslashes( $displayData['message'] );
 		}
 		$displayData['message'] = ipb_convert_bbcode($displayData['message']);
 		return $displayData;
 	}
}
