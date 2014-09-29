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
require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/ajax/topics.php');

class edit_post extends public_forums_ajax_topics
{
	/**
	 * Class entry point
	 *
	 * @access	public
	 * @param	object		Registry reference
	 * @return	void		[Outputs for the ajax handler]
	 */
	public function doExecute( ipsRegistry $registry ) 
	{
		/* Load topic class */
		if ( ! $this->registry->isClassLoaded('topics') && file_exists(IPSLib::getAppDir( 'forums' ) . "/sources/classes/topics.php"))
		{
			$classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . "/sources/classes/topics.php", 'app_forums_classes_topics', 'forums' );
			$this->registry->setClass( 'topics', new $classToLoad( $this->registry ) );
		}
	    
		$this->lang->loadLanguageFile( array( 'public_topic', 'public_mod' ), 'forums' );
		//-----------------------------------------
		// What to do?
		//-----------------------------------------
		
		switch( $this->request['do'] )
		{
			default:
			case 'editBoxShow':
				#############set $this->request##########
				$pid	= intval( $this->request['p'] );
				if ( ! $pid) {
					get_error("Parameters Error!");
				}
				$post = $this->DB->buildAndFetch( array( 
													'select' => 'topic_id', 
													'from'   => 'posts', 
													'where'  => "pid= {$pid}",
													)	);
				$tid	=	intval($post['topic_id']);
				if ( !$tid) {
					get_error("No Such Topic!");
				}
				$this->request['t'] = $tid;
				
				$topic = $this->DB->buildAndFetch( array( 
													'select' => 'forum_id', 
													'from'   => 'topics', 
													'where'  => "tid= {$tid}",
													)	);
				$fid	=	$topic['forum_id'];
				if ( !$fid) {
					get_error("No Such Forum!");
				}
				$this->request['f'] = $fid;
				#######################################
				return $this->editBoxShow();				
			break;
			
			case 'editBoxSave':
				#############set $this->request##########
				$pid	= intval( $this->request['p'] );
				if ( ! $pid) {
					get_error("Parameters Error!");
				}
				$post = $this->DB->buildAndFetch( array( 
													'select' => 'topic_id', 
													'from'   => 'posts', 
													'where'  => "pid= {$pid}",
													)	);
				$tid	=	intval($post['topic_id']);
				if ( !$tid) {
					get_error("No Such Topic!");
				}
				$this->request['t'] = $tid;
				
				$topic = $this->DB->buildAndFetch( array( 
													'select' => 'forum_id', 
													'from'   => 'topics', 
													'where'  => "tid= {$tid}",
													)	);
				$fid	=	$topic['forum_id'];
				if ( !$fid) {
					get_error("No Such Forum!");
				}
				$this->request['f'] = $fid;
				
				//$_POST['Post'] = to_local($_POST['Post']);
				//$this->request['Post'] = to_local($this->request['Post']);
				
				#######################################
				$this->editBoxSave();
			break;
			/*
			case 'saveTopicTitle':
				$this->saveTopicTitle();
			break;
			
			case 'saveTopicDescription':
				$this->saveTopicDescription();
			break;

			case 'rateTopic':
				$this->rateTopic();
			break;
			
			case 'postApproveToggle':
				$this->_postApproveToggle();
			break;
			*/
		}
	}
		
		
	public function editBoxShow()
	{
		$pid		 = intval( $this->request['p'] );
		$fid		 = intval( $this->request['f'] );
		$tid		 = intval( $this->request['t'] );
		$show_reason = 0;
		
		if ( ! $pid OR ! $tid OR ! $fid ) {
			get_error("Parameters Error!");
		}
		
		if ( $this->memberData['member_id'] ) {
			if ( $this->memberData['restrict_post'] )	{
				if ( $this->memberData['restrict_post'] == 1 ){
					get_error("No Permission!");
				}					
				$post_arr = IPSMember::processBanEntry( $this->memberData['restrict_post'] );
				
				if ( time() >= $post_arr['date_end'] )	{	// Update this member's profile
					IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'restrict_post' => 0 ) ) );
				}
				else	{
					get_error("No Permission!");
				}
			}
		}

		//-----------------------------------------
		// Get classes
		//-----------------------------------------
		
	//	if ( ! is_object( $this->postClass ) )
	//	{
			require_once ('mobi_classPostForms.php');
			/*
			require_once( IPSLib::getAppDir( 'forums' ) . "/sources/classes/post/classPost.php" );
			require_once( IPSLib::getAppDir( 'forums' ) . "/sources/classes/post/classPostForms.php" );
			*/
			$this->registry->getClass( 'class_localization')->loadLanguageFile( array( 'public_editors' ), 'core' );			
			$this->postClass		   =  new mobi_classPostForms( $this->registry );
	//	}
		
		# Forum Data
		$this->postClass->setForumData( $this->registry->getClass('class_forums')->forum_by_id[ $fid ] );
		
		# IDs
		$this->postClass->setTopicID( $tid );
		$this->postClass->setPostID( $pid );
		$this->postClass->setForumID( $fid );
		
		/* Topic Data */
		if ($this->registry->isClassLoaded('topics'))
		    $this->postClass->setTopicData( $this->registry->getClass('topics')->getTopicById( $tid ) );
		else
			$this->postClass->setTopicData( $this->DB->buildAndFetch( array( 
																		'select'   => 't.*, p.poll_only', 
																		'from'     => array( 'topics' => 't' ), 
																		'where'    => "t.forum_id={$fid} AND t.tid={$tid}",
																		'add_join' => array(
																							array( 
																									'type'	=> 'left',
																									'from'	=> array( 'polls' => 'p' ),
																									'where'	=> 'p.tid=t.tid'
																								)
																							)
								) 							)	 );
		
		# Set Author
		$this->postClass->setAuthor( $this->member->fetchMemberData() );
		
		# Get Edit form
		try
		{
			$postContent = $this->postClass->get_edit_post();
			global $app_version;
			if($app_version >= '3.4.0')
			{
				$postContent = ipb_convert_bbcode($postContent);
				$postContent = str_replace('<br />', "\n", $postContent);
			}	
			$postTitle = $this->postClass->get_title('edit');
			$post_id = $this->postClass->getPostID();
			return array (
				'post_id' => $post_id,
				'post_title' => $postTitle,
				'post_content' => $postContent,
			);
		}
		catch ( Exception $error)
		{
			get_error($error->getMessage());
		}
	}
		
		/**
	 * Saves the post
	 *
	 * @access	public
	 * @return	void
	 */
	public function editBoxSave()
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------

		$pid		   = intval( $this->request['p'] );
		$fid		   = intval( $this->request['f'] );
		$tid		   = intval( $this->request['t'] );
		$attach_pids   = array();

   		$this->request['post_edit_reason'] = $this->convertAndMakeSafe( $_POST['post_edit_reason'] );

   		//-----------------------------------------
		// Set things right
		//-----------------------------------------
		
		$this->request['Post'] =  $_POST['Post'];

		//-----------------------------------------
		// Check P|T|FID
		//-----------------------------------------

		if ( ! $pid OR ! $tid OR ! $fid )
		{
			get_error("Parameters Error!");
		}
		
		if ( $this->memberData['member_id'] )
		{
			if ( $this->memberData['restrict_post'] )
			{
				if ( $this->memberData['restrict_post'] == 1 )
				{
					get_error("No Permission!");
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
					get_error("No Permission!");
				}
			}
		}

		//-----------------------------------------
		// Load Lang
		//-----------------------------------------

		$this->registry->getClass( 'class_localization')->loadLanguageFile( array( 'public_topics' ) );


		require_once ('mobi_classPostForms.php');

		$this->registry->getClass( 'class_localization')->loadLanguageFile( array( 'public_editors' ), 'core' );			
		$this->postClass		   =  new mobi_classPostForms( $this->registry );

		
		# Forum Data
		$this->postClass->setForumData( $this->registry->getClass('class_forums')->forum_by_id[ $fid ] );
		
		# IDs
		$this->postClass->setTopicID( $tid );
		$this->postClass->setPostID( $pid );
		$this->postClass->setForumID( $fid );
		
		if( isset($this->request['post_htmlstatus']) )		// Off is "0"
		{
			$this->postClass->setSettings( array( 'post_htmlstatus' => $this->request['post_htmlstatus'] ) );
		}
		
		/* Topic Data */
		$this->postClass->setTopicData( $this->DB->buildAndFetch( array( 
																			'select'   => 't.*, p.poll_only', 
																			'from'     => array( 'topics' => 't' ), 
																			'where'    => "t.forum_id={$fid} AND t.tid={$tid}",
																			'add_join' => array(
																								array( 
																										'type'	=> 'left',
																										'from'	=> array( 'polls' => 'p' ),
																										'where'	=> 'p.tid=t.tid'
																									)
																								)
									) 							)	 );
		# Set Author
		$this->postClass->setAuthor( $this->member->fetchMemberData() );
		
		# Set from ajax
		$this->postClass->setIsAjax( TRUE );

		# Post Content
		$this->postClass->setPostContent( $this->request['Post'] );

		# Get Edit form
		try
		{
			/**
			 * If there was an error, return it as a JSON error
			 */
			if ( $this->postClass->editPost() === FALSE )
			{
				get_error($this->postClass->getPostError());
				//$this->returnJsonError( $this->postClass->getPostError() );
			}
			
			$topic = $this->postClass->getTopicData();
			$post  = $this->postClass->getPostData();
			
			//-----------------------------------------
			// Pre-display-parse
			//-----------------------------------------
			
			IPSText::getTextClass( 'bbcode' )->parse_smilies			= $post['use_emo'];
			IPSText::getTextClass( 'bbcode' )->parse_html				= ( $this->registry->getClass('class_forums')->forum_by_id[ $fid ]['use_html'] and $this->memberData['g_dohtml'] and $post['post_htmlstate'] ) ? 1 : 0;
			IPSText::getTextClass( 'bbcode' )->parse_nl2br				= $post['post_htmlstate'] == 2 ? 1 : 0;
			IPSText::getTextClass( 'bbcode' )->parse_bbcode				= $this->registry->getClass('class_forums')->forum_by_id[ $fid ]['use_ibc'];
			IPSText::getTextClass( 'bbcode' )->parsing_section			= 'topics';
			IPSText::getTextClass( 'bbcode' )->parsing_mgroup			= $this->postClass->getAuthor('member_group_id');
			IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others	= $this->postClass->getAuthor('mgroup_others');
				
			$post['post']	= IPSText::getTextClass( 'bbcode' )->preDisplayParse( $post['post'] );

			if ( IPSText::getTextClass( 'bbcode' )->error )
			{
				get_error($this->lang->words[ IPSText::getTextClass( 'bbcode' )->error ]);
			}			

			$edit_by	= '';
			
			if ( $post['append_edit'] == 1 AND $post['edit_time'] AND $post['edit_name'] )
			{
				$e_time		= $this->registry->getClass( 'class_localization')->getDate( $post['edit_time'] , 'LONG' );
				$edit_by	= sprintf( $this->lang->words['edited_by'], $post['edit_name'], $e_time );
			}
			
			/* Attachments */
			if ( ! is_object( $this->class_attach ) )
			{
				//-----------------------------------------
				// Grab render attach class
				//-----------------------------------------

				require_once( IPSLib::getAppDir('core') . '/sources/classes/attach/class_attach.php' );
				$this->class_attach  =  new class_attach( $this->registry );
			}

			$this->class_attach->type  = 'post';
			$this->class_attach->init();

			$attachHtml             = $this->class_attach->renderAttachments( array( $pid => $post['post'] ) );
			$post['post']           = $attachHtml[ $pid ]['html'];
			$post['attachmentHtml'] = $attachHtml[ $pid ]['attachmentHtml'];

			//$this->returnJsonArray( array( 'successString' => $output ) );
		}
		catch ( Exception $error )
		{
			get_error($error->getMessage());
			//$this->returnJsonError( $error->getMessage() );
		}
	}
}

?>