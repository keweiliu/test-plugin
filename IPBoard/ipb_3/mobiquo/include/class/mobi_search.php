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

require_once (IPS_ROOT_PATH . 'applications/core/modules_public/search/search.php');
class mobi_search extends public_core_search_search
{
	public function doExecute( ipsRegistry $registry)
	{
		$this->request['do'] = 'quick_search';
		$this->request['search_app'] = 'core';
		if(empty($_GET['st']))
		{
			$this->request['submit'] = 'Perform the search';
		}
		
		
		######################find the id by username
		$this->request['user_name'] = to_local($this->request['user_name']);			
		$user_name = $this->request['user_name'];
		
		$member = $this->DB->buildAndFetch( array( 
													'select' => 'member_id', 
													'from'   => 'members', 
													'where'  => "members_display_name='{$user_name}' or name='{$user_name}'" 
											)	 );	
		$member_id = $member['member_id'];
		if(!empty($member_id))
		{
			$this->request['mid'] = $member_id;
		}		
	    else 
	    {
	    	$this->request['mid'] = intval($_POST['mid']);
	    }
		###########################################	
		/* Basic Search */
		if( isset( $this->request['search_filter_app'] ) && is_array( $this->request['search_filter_app'] ) )
		{
			foreach( $this->request['search_filter_app'] as $app => $checked )
			{
				/* Bypass the all filter */
				if( $app == 'all' )
				{
					$this->request['search_app'] 					= 'forums';
					$this->request['search_filter_app']['forums']	= 1;
					break;
				}
				
				/* Add to the array */
				if( $checked )
				{
					$this->request['search_app'] = $app;
					break;
				}
			}
		}

		$this->request['search_app'] = $this->request['search_app'] ? $this->request['search_app'] : 'forums';

		/* Load Search Plugin */
		try
		{
			/* If it's not a search operation, like new content or user post for example, we use mysql instead of sphinx */
			if( $this->request['do'] != 'quick_search' )
			{
				$this->search_plugin = IPSSearchIndex::getSearchPlugin( 'index' );
			}
			else
			{
				$this->search_plugin = IPSSearchIndex::getSearchPlugin();				
			}
		}
		catch( Exception $error )
		{
			switch( $error->getMessage() )
			{
				case 'INVALID_BASIC_SEARCH_PLUGIN_FILE':
					get_error("Invalid Search!");
				break;
				
				case 'INVALID_BASIC_SEARCH_PLUGIN_CLASS':
					get_error("Invalid Search!");
				break;
				
				case 'INVALID_INDEX_PLUGIN_FILE':
					get_error("Invalid Search!");
				break;
				
				case 'INVALID_INDEX_PLUGIN_CLASS':
					get_error("Invalid Search!");
				break;
			}
		}
		
		/* Check Access */
		$this->_canSearch();		
		
		/* Load language */
		$this->registry->class_localization->loadLanguageFile( array( 'public_search' ), 'core' );
		$this->registry->class_localization->loadLanguageFile( array( 'public_forums' ), 'forums' );
		/* What to do */
		switch( $this->request['do'] )
		{
			/*
			case 'active':
				$this->activeContent();
			break;
			*/
			case 'user_reply':
				return $this->viewUserContent_reply();
			break;
			case 'user_topic':
				return $this->viewUserContent_topic();
			break;
			
			case 'new_posts':
				if (isset($this->request['start_num']) and isset($this->request['end_num'])) {
					return $this->viewNewPosts($this->request['start_num'], $this->request['end_num']);					
				} else {
					get_error("Paremeters Error!");
				}					
			break;
			
			case 'quick_search':
				if (isset($this->request['start_num']) and isset($this->request['end_num'])) {
					$_GET["search_term"] = to_local($_GET["search_term"]);
					$this->request['search_term'] = $_GET["search_term"];
					return $this->searchResults($this->request['start_num'], $this->request['end_num']);
				} else {
					get_error("Paremeters Error!");
				}
			break;
			/*
			default:
			case 'search_form':	
				$this->searchAdvancedForm();
			break;
			*/
		}
		
	}

	public function viewUserContent_reply()
	{
		/* INIT */
		$id 	    = intval( $this->request['mid'] );
		$member	    = IPSMember::load( $id, 'core' );
		$beginStamp = 0;
		
		/* Content Title Only? */
		$this->search_plugin->onlyTitles = $this->request['view_by_title'] == 1 ? true : false;		
		
		/* Set flag for viewing author content */
		$this->search_plugin->searchAuthor = true;
		
		/* Set the member_id */
		$this->search_plugin->setCondition( 'member_id', '=', $id );		
		
		/* Check for application restriction */
		$search_app_filter = array();
		
		if( isset( $this->request['search_filter_app'] ) && is_array( $this->request['search_filter_app'] ) )
		{
			if( ! in_array( $this->settings['search_method'], array( 'traditional', 'sphinx' ) ) )
			{
				/* Bypass all this if we are searching all apps */
				if( $this->request['search_filter_app']['all'] != 1 )
				{
					foreach( $this->request['search_filter_app'] as $app => $checked )
					{
						/* Bypass the all filter */
						if( $app == 'all' )
						{
							continue;
						}
						
						/* Add to the array */
						if( $checked )
						{
							$search_app_filter[] = "'$app'";
						}
					}
					
					/* Add this condition to the search */
					$this->search_plugin->setCondition( 'app', 'IN', implode( ',', $search_app_filter ) );				
				}
			}
		}
		
		/* Cut by date for efficiency? */
		if ( $this->settings['search_ucontent_days'] AND method_exists( $this->search_plugin, 'setBeginTimeStamp' ) )
		{
			$this->search_plugin->setBeginTimeStamp( ( $member['last_post'] ? $member['last_post'] : time() ) - 86400 * intval( $this->settings['search_ucontent_days'] ) );
			
			if ( method_exists( $this->search_plugin, 'getBeginTimeStamp' ) )
			{
				$beginStamp = $this->search_plugin->getBeginTimeStamp();
			}
		}
		
		/* Count the number of results */
		$total_results = $this->search_plugin->getSearchCount( '', '', $this->search_plugin->onlyTitles );

		/* Do Pagination Stuff */
		$st       = isset( $this->request['st'] ) ? intval( $this->request['st'] ) : 0;
		$per_page = $this->settings['search_per_page'] ? $this->settings['search_per_page'] : 25;
		#################set the limit##############
		$per_page = 20;
		###########################################
		/* Add in application filter url bit */
		$urlbit = '';
		if( isset( $this->request['search_filter_app'] ) && is_array( $this->request['search_filter_app'] ) && count( $this->request['search_filter_app'] ) )
		{
			foreach( $this->request['search_filter_app'] as $app => $checked )
			{
				$urlbit .= "&amp;search_filter_app[{$app}]={$checked}";
			}
		}	
		if( $this->request['view_by_title'] == 1 )
		{
			$urlbit .= '&amp;view_by_title=1';			
		}
		
		/*
		$links = $this->registry->output->generatePagination( array( 
																	'totalItems'		=> $total_results,
																	'itemsPerPage'		=> $per_page,
																	'currentStartValue'	=> $st,
																	'baseUrl'			=> 'app=core&amp;module=search&amp;do=user_posts&amp;mid=' . $id . $urlbit,
															)	);
		*/
		/* Showing */
		$showing = array( 'start' => $st + 1, 'end' => ( $st + $per_page ) > $total_results ? $total_results : $st + $per_page );

		/* Loop through the search results and build the output */
		$search_entries = array();
		$search_results = array();
		$topic_ids      = array();
			
		foreach( $this->search_plugin->getSearchResults( '', array( $st, $per_page ), 'date', '', $this->search_plugin->onlyTitles ) as $r )
		{
			/* Hack Job */
			if( $r['app'] == 'forums' && $r['type_2'] == 'topic' )
			{
				$topic_ids[] = $r['type_id_2'];
			}
			
			/* Add to the entries array */
			$search_entries[] = $r;
		}
		
		/* Get dots */
		$this->_retrieveTopics( $topic_ids );

		foreach( $search_entries as $r )
		{
			########get new post#########################			
			$is_read = ipsRegistry::getClass('classItemMarking')->isRead( array( 'forumID' => $r['forum_id'], 
																'itemID' => $r['topic_id'], 
																'itemLastUpdate' => $r['edit_time'] ? $r['edit_time'] : $r['post_time']
																),  'forums' );
			$r['is_read'] = $is_read;

			#######get forum name ####################
			$forum_id = $r['forum_id'];
			$r['forum_name'] = $this->registry->class_forums->forum_by_id[ $forum_id ]['name'];
			
			#######get POST POSITION ####################
			$queued_query_bit = ' and queued=0';
		 	if ( $this->registry->getClass('class_forums')->canQueuePosts( $r['forum_id'] ) )
			{
				$queued_query_bit = "";
			}

			$post_position = $this->DB->buildAndFetch( array( 
												'select' => 'COUNT(*) as post_position', 
												'from'   => 'posts', 
												'where'  => "topic_id= {$r['topic_id']} and pid <= {$r['pid']} " . $queued_query_bit)	);
			$r['post_position'] = $post_position['post_position'];

			#######get icon_url####################
    		$r['icon_url'] = get_avatar($r['author_id']);

			$search_results[] = $r;
		}

		return $search_results;
	}
	
	public function viewUserContent_topic()
	{
		/* INIT */
		$id 	    = intval( $this->request['mid'] );
		$member	    = IPSMember::load( $id, 'core' );
		$beginStamp = 0;
		
		/* Content Title Only? */
		$this->search_plugin->onlyTitles = $this->request['view_by_title'] == 1 ? true : false;		
		
		/* Set flag for viewing author content */
		$this->search_plugin->searchAuthor = true;
		
		/* Set the member_id */
		$this->search_plugin->setCondition( 'member_id', '=', $id );		
		
		/* Check for application restriction */
		$search_app_filter = array();
		
		if( isset( $this->request['search_filter_app'] ) && is_array( $this->request['search_filter_app'] ) )
		{
			if( ! in_array( $this->settings['search_method'], array( 'traditional', 'sphinx' ) ) )
			{
				/* Bypass all this if we are searching all apps */
				if( $this->request['search_filter_app']['all'] != 1 )
				{
					foreach( $this->request['search_filter_app'] as $app => $checked )
					{
						/* Bypass the all filter */
						if( $app == 'all' )
						{
							continue;
						}
						
						/* Add to the array */
						if( $checked )
						{
							$search_app_filter[] = "'$app'";
						}
					}
					
					/* Add this condition to the search */
					$this->search_plugin->setCondition( 'app', 'IN', implode( ',', $search_app_filter ) );				
				}
			}
		}
		
		/* Cut by date for efficiency? */
		if ( $this->settings['search_ucontent_days'] AND method_exists( $this->search_plugin, 'setBeginTimeStamp' ) )
		{
			$this->search_plugin->setBeginTimeStamp( ( $member['last_post'] ? $member['last_post'] : time() ) - 86400 * intval( $this->settings['search_ucontent_days'] ) );
			
			if ( method_exists( $this->search_plugin, 'getBeginTimeStamp' ) )
			{
				$beginStamp = $this->search_plugin->getBeginTimeStamp();
			}
		}
		
		/* Count the number of results */
		$total_results = $this->search_plugin->getSearchCount( '', '', $this->search_plugin->onlyTitles );

		/* Do Pagination Stuff */
		$st       = isset( $this->request['st'] ) ? intval( $this->request['st'] ) : 0;
		$per_page = $this->settings['search_per_page'] ? $this->settings['search_per_page'] : 25;
		#################set the limit##############
		$per_page = 20;
		###########################################
		/* Add in application filter url bit */
		$urlbit = '';
		if( isset( $this->request['search_filter_app'] ) && is_array( $this->request['search_filter_app'] ) && count( $this->request['search_filter_app'] ) )
		{
			foreach( $this->request['search_filter_app'] as $app => $checked )
			{
				$urlbit .= "&amp;search_filter_app[{$app}]={$checked}";
			}
		}	
		if( $this->request['view_by_title'] == 1 )
		{
			$urlbit .= '&amp;view_by_title=1';			
		}
		
		/*
		$links = $this->registry->output->generatePagination( array( 
																	'totalItems'		=> $total_results,
																	'itemsPerPage'		=> $per_page,
																	'currentStartValue'	=> $st,
																	'baseUrl'			=> 'app=core&amp;module=search&amp;do=user_posts&amp;mid=' . $id . $urlbit,
															)	);
		*/
		/* Showing */
		$showing = array( 'start' => $st + 1, 'end' => ( $st + $per_page ) > $total_results ? $total_results : $st + $per_page );

		/* Loop through the search results and build the output */
		$search_entries = array();
		$search_results = array();
		$topic_ids      = array();
			
		foreach( $this->search_plugin->getSearchResults( '', array( $st, $per_page ), 'date', '', $this->search_plugin->onlyTitles ) as $r )
		{
			/* Hack Job */
			if( $r['app'] == 'forums' && $r['type_2'] == 'topic' )
			{
				$topic_ids[] = $r['type_id_2'];
			}
			
			/* Add to the entries array */
			$search_entries[] = $r;
		}
		
		/* Get dots */
		$this->_retrieveTopics( $topic_ids );		
		
		$topic_array = array();
		if( ( $this->settings['cpu_watch_update'] == 1 ) and ( $this->memberData['member_id'] ) and is_array($topic_ids) and count($topic_ids))
		{
			$this->DB->build( array( 
									'select' => 'topic_id, trid as trackingTopic',
									'from'   => 'tracker',
									'where'  => 'member_id=' . $this->memberData['member_id'] . ' AND topic_id IN(' . implode( ',', $topic_ids ) . ')',
							)	);			
			$this->DB->execute();
			
			while( $p = $this->DB->fetch() )
			{
				$topic_array[ $p['topic_id'] ] = 1;
			}
		}
		
		$author_info = array();
		foreach( $search_entries as $r )
		{
			########get new post#########################
			$is_read = ipsRegistry::getClass( 'classItemMarking')->isRead( array( 'forumID' => $r['forum_id'], 
																'itemID' => $r['tid'], 
																'itemLastUpdate' => $r['last_post'] 
																),  'forums' );
			$r['is_read'] = $is_read;
			
			#######get forum name ####################
			$forum_id = $r['forum_id'];
			$r['forum_name'] = $this->registry->class_forums->forum_by_id[ $forum_id ]['name'];
			
			
			#######get icon_url####################
    		$r['icon_url'] = get_avatar($r['last_poster_id']);
    		
    		// get last post author username
    		if (isset($author_info[$r['last_poster_id']]['username'])) {
    		    $r['last_poster_username'] = $author_info[$r['last_poster_id']]['username'];
    		} else {
        		$topic_author_name = $this->DB->buildAndFetch( array(
                                                    'select' => 'name',
                                                    'from'   => 'members',
                                                    'where'  => "member_id= {$r['last_poster_id']} "));
                $r['last_poster_username'] = $topic_author_name['name'];
                $author_info[$r['last_poster_id']]['username'] = $topic_author_name['name'];
            }
    		
    		$r['issubscribed'] = ($topic_array[ $r['tid'] ] ? true : false);
			########################################			
			$search_results[] = $r;
		}
		
		return $search_results;
	}
	
	private function _canSearch()
	{
		/* Check the search setting */
		if( ! $this->settings['allow_search'] )
		{
			get_error("Search Not Allowed!");
		}
		
		/* Check the member authorization */
		if( ! isset( $this->memberData['g_use_search'] ) || ! $this->memberData['g_use_search'] )
		{
			get_error("No Permission to Search!");
		}		
	}
	
	
	private function _retrieveTopics( $ids )
	{
		/* Query posts - this is so the stupid "you have posted" dot shows up on topic icons */
		$this->_topicArray = array();
		
		if( ! $this->settings['show_user_posted'] )
		{
			return;
		}		
		
		if( count( $ids ) )
		{
			$this->DB->build( array( 
									'select' => 'author_id, topic_id',
									'from'   => 'posts',
									'where'  => 'author_id=' . $this->memberData['member_id'] . ' AND topic_id IN(' . implode( ',', $ids ) . ')',
							)	);
									  
			$this->DB->execute();
			
			while( $p = $this->DB->fetch() )
			{
				$this->_topicArray[ $p['topic_id'] ] = $p['author_id'];
			}			
		}
	}	
	

	public function viewNewPosts($start_num = 0, $end_num = 20)
	{
		$this->search_plugin->onlyTitles	= true;
		$_METHOD                            = ( method_exists( $this->search_plugin, 'viewNewPosts_count' ) && method_exists( $this->search_plugin, 'viewNewPosts_fetch' ) ) ? 'custom' : 'standard';
		$asForum							= ( method_exists( $this->search_plugin, 'getShowAsForum' ) ) ? $this->_getShowAsForum() : false;
		#############
		$this->memberData['bw_vnc_type'] = 1;
		
		################
		/* Do we have a manual method? */
		if ( $_METHOD == 'custom' )
		{
			$total_results = $this->search_plugin->viewNewPosts_count();
		}
		else
		{
			/* Call the unread items function */
			$this->search_plugin->setUnreadConditions();

			/* Check for application restriction */
			$search_app_filter = array();
			if( isset( $this->request['search_filter_app'] ) && is_array( $this->request['search_filter_app'] ) )
			{
				if( ! in_array( $this->settings['search_method'], array( 'traditional', 'sphinx' ) ) )
				{
					/* Bypass all this if we are searching all apps */
					if( $this->request['search_filter_app']['all'] != 1 )
					{
						foreach( $this->request['search_filter_app'] as $app => $checked )
						{
							/* Bypass the all filter */
							if( $app == 'all' )
							{
								continue;
							}
						
							/* Add to the array */
							if( $checked )
							{
								$search_app_filter[] = "'$app'";
							}
						}
					
						/* Add this condition to the search */
						$this->search_plugin->setCondition( 'app', 'IN', implode( ',', $search_app_filter ) );				
					}
				}
			}
			/* Exclude forums */
			if( $this->settings['vnp_block_forums'] )
			{
				if( $this->request['search_app'] == 'forums' )
				{				
					$this->search_plugin->setCondition( 't.forum_id', 'NOT IN', $this->settings['vnp_block_forums'] );
				}
			}
			/* Only Titles */
			//$this->search_plugin->setCondition( 'content_title', '<>', "''" );
			$group_by = '';
			if( $this->request['search_app'] == 'forums' )
			{
				$group_by = 'topic_id';
			}

			if( !$this->search_plugin->removeMe )
			{
				/* Count the number of results */
				$total_results	= $this->search_plugin->getSearchCount( '', '', true );
			}
			else
			{
				$total_results	= 0;
			}
		}
		/* Do Pagination Stuff */
		$st = $start_num;
		$per_page = $end_num - $start_num + 1;
		//$st       = isset( $this->request['st'] ) ? intval( $this->request['st'] ) : 0;
		//$per_page = $this->settings['search_per_page'] ? $this->settings['search_per_page'] : 25;
		
		/* Add in application filter url bit */
		$urlbit = '';
		if( isset( $this->request['search_filter_app'] ) && is_array( $this->request['search_filter_app'] ) && count( $this->request['search_filter_app'] ) )
		{
			foreach( $this->request['search_filter_app'] as $app => $checked )
			{
				$urlbit .= "&amp;search_filter_app[{$app}]={$checked}";
			}
		}		

		/* Showing */
		$showing = array( 'start' => $st + 1, 'end' => ( $st + $per_page ) > $total_results ? $total_results : $st + $per_page );

		/* Loop through the search results and build the output */
		$search_entries = array();
		$search_results = array();
		$topic_ids      = array();
		
		/* Do we have a manual method? */
		if ( $_METHOD == 'custom' )
		{
			$search_entries = $this->search_plugin->viewNewPosts_fetch( array( $st, $per_page ) );
			
			foreach( $search_entries as $data )
			{
				$topic_ids[] = $data['tid'];
			}
				
		}
		else
		{
			if( !$this->search_plugin->removeMe )
			{
				foreach( $this->search_plugin->getSearchResults( '', array( $st, $per_page ), 'date', '', true ) as $r )
				{
					/* Hack Job */
					if( $r['app'] == 'forums' && $r['type_2'] == 'topic' )
					{
						$topic_ids[] = $r['type_id_2'];
					}
				
					/* Add to the entries array */
					$search_entries[] = $r;
				}
			}
		}
		
		/* Get dots */
		$this->_retrieveTopics( $topic_ids );
		
		$topic_array = array();
		if( ( $this->settings['cpu_watch_update'] == 1 ) and ( $this->memberData['member_id'] ) and is_array($topic_ids) and count($topic_ids))
		{
			$this->DB->build( array( 
									'select' => 'topic_id, trid as trackingTopic',
									'from'   => 'tracker',
									'where'  => 'member_id=' . $this->memberData['member_id'] . ' AND topic_id IN(' . implode( ',', $topic_ids ) . ')',
							)	);			
			$this->DB->execute();
			
			while( $p = $this->DB->fetch() )
			{
				$topic_array[ $p['topic_id'] ] = 1;
			}
		}
		
		/* Parse results */
		$author_info = array();
		foreach( $search_entries as $r )
		{
			$is_read = ipsRegistry::getClass( 'classItemMarking')->isRead( array( 'forumID' => $r['forum_id'], 
																'itemID' => $r['tid'], 
																'itemLastUpdate' => $r['last_post'] 
																),  'forums' );
			if ($is_read) {
				if ( $total_results > 0) {
					$total_results--;
				}								
				continue;
			}
			
			//-----------------------------------------
			// Are we actually a moderator for this forum?
			//-----------------------------------------
			
			if ( ! $this->memberData['g_is_supmod'] )
			{
				$moderator = $this->memberData['forumsModeratorData'];
				
				if ( !isset($moderator[ $r['forum_id'] ]) OR !is_array( $moderator[ $r['forum_id'] ] ) )
				{
					$this->memberData['is_mod'] = 0;
				}
			}
    		
    		$can_delete = 0;
			if ($this->memberData['is_mod'] == 1 and ($this->memberData['g_is_supmod'] == 1 || $this->memberData['forumsModeratorData'][ $r['forum_id'] ]['delete_topic'])) {
				$can_delete = 1;
			}
			
			//get forum name 
			$forum_id = $r['forum_id'];
			$r['forum_name'] = $this->registry->class_forums->forum_by_id[ $forum_id ]['name'];
			
			//get icon_url
    		$r['icon_url'] = get_avatar($r['last_poster_id']);
    		
    		// get last post author username
    		if (isset($author_info[$r['last_poster_id']]['username'])) {
    		    $r['last_poster_username'] = $author_info[$r['last_poster_id']]['username'];
    		} else {
        		$topic_author_name = $this->DB->buildAndFetch( array(
                                                    'select' => 'name',
                                                    'from'   => 'members',
                                                    'where'  => "member_id= {$r['last_poster_id']} "));
                $r['last_poster_username'] = $topic_author_name['name'];
                $author_info[$r['last_poster_id']]['username'] = $topic_author_name['name'];
            }
			
		 	// get TOPIC CONTENT and shord content
		  	$post_data = $this->DB->buildAndFetch( array( 
													'select' => 'post, post_htmlstate', 
													'from'   => 'posts', 
													'where'  => "topic_id= {$r['tid']} and queued=0",
													'order' => 'pid desc')	);			
		 	$r['short_content'] = get_short_content($post_data['post'], $post_data['post_htmlstate']);
		 	
		 	$r['issubscribed'] = $topic_array[ $r['tid'] ] ? true : false;
		 							
			$search_results[] = $r;
		}
		return array(
			'total_topic_num' => $total_results,
			'list'			  => $search_results,
			);
	}
	
	public function searchResults($start_num=0, $end_num=19)
	{
		/* Search Term */
		$asForum		= ( method_exists( $this->search_plugin, 'getShowAsForum' ) ) ? $this->_getShowAsForum() : false;
		$search_term	= str_replace( "&quot;", '"',  IPSText::parseCleanValue( rawurldecode( $this->request['search_term'] ) ) );
		$search_term	= str_replace( "&amp;", '&',  $search_term );
		$removedTerms	= array();
		/* Did we come in off a post request? */
		if ( $this->request['request_method'] == 'post' )
		{
			/* Set a no-expires header */
			$this->registry->getClass('output')->setCacheExpirationSeconds( 30 * 60 );
		}
		
		/* Sort some form elements out */
		$this->request['search_sort_by']    = ( $this->request['search_sort_by']    && $this->request['search_sort_by']    != 'date' ) ? 'relevance' : 'date';
		$this->request['search_sort_order'] = ( $this->request['search_sort_order'] && $this->request['search_sort_order'] != 'desc' ) ? 'asc' : 'desc';
		
		/* Check for disallowed search terms */
		while( preg_match_all( "/(?:^|\s+)(img|quote|code|html|javascript|a href|color|span|div|border|style)(?:\s+|$)/", $search_term, $removed_search_terms ) )
		{
			$removedTerms[]	= $removed_search_terms[0][0];
			$search_term	= preg_replace( "/(?:^|\s+)(?:img|quote|code|html|javascript|a href|color|span|div|border|style)(?:\s+|$)/", '', $search_term );
		}		
		
		/* Remove some formatting */
		//$search_term = str_replace( array( '|', '\\', '/' ), '', $search_term );
		// | is an OR operator for sphinx - don't want to block globally
		$search_term = str_replace( array( '\\', '/' ), '', $search_term );
		if( strlen( $search_term ) < 4 && ! $this->request['search_author'])
		{
			get_error("Query String Length Too Short!");
		}
		
		if( ( $this->settings['min_search_word'] && strlen( $search_term ) < $this->settings['min_search_word'] ) && ! $this->request['search_author'] )
		{
			get_error("Query String Length Less Than Setting!");
			//$this->searchAdvancedForm( sprintf( $this->lang->words['search_term_short'], $this->settings['min_search_word'] ), $removedTerms );
			//return;
		}
		
		/* Save date for the form */
		$this->request['_search_date_start'] = $this->request['search_date_start'];
		$this->request['_search_date_end']   = $this->request['search_date_end'];

		/* Default End Date */
		if( $this->request['search_date_start'] && ! $this->request['search_date_end'] )
		{
			$this->request['search_date_end'] = 'now';
		}
//		if( strtotime( $this->request['search_date_start'] ) > strtotime( $this->request['search_date_end'] ) )
//		{
//			$this->searchAdvancedForm( $this->lang->words['search_invalid_date_range'] );
//			return;	
//		}
		
		/*if( strtotime( $this->request['search_date_start'] ) > time() || strtotime( $this->request['search_date_end'] ) > time() )
		{
			$this->searchAdvancedForm( $this->lang->words['search_invalid_date_future'] );
			return;	
		}*/
		
		if( strtotime( $this->request['search_date_start'] ) > time() )
		{
			$this->request['search_date_start']	= 'now';
		}
		
		if( strtotime( $this->request['search_date_end'] ) > time() )
		{
			$this->request['search_date_end']	= 'now';
		}

		/* Cleanup */
		$this->request['search_higlight'] = str_replace( '.', '', $this->request['search_term'] );

		/* Search Flood Check */
		if( $this->memberData['g_search_flood'] )
		{
			/* Check for a cookie */
			$last_search = IPSCookie::get( 'sfc' );
			$last_term	= str_replace( "&quot;", '"', IPSCookie::get( 'sfct' ) );
			$last_term	= str_replace( "&amp;", '&',  $last_term );			
			
			/* If we have a last search time, check it */
			if( $last_search && $last_term )
			{
				if( ( time() - $last_search ) <= $this->memberData['g_search_flood'] && $last_term != $search_term )
				{
					get_error("Search Flood Check Error!");
					//$this->searchAdvancedForm( sprintf( $this->lang->words['xml_flood'], $this->memberData['g_search_flood'] ) );
				//	return;					
				}
				else
				{
					/* Reset the cookie */
					IPSCookie::set( 'sfc', time() );
					IPSCookie::set( 'sfct', $search_term );
				}
			}
			/* Set the cookie */
			else
			{
				IPSCookie::set( 'sfc', time() );
				IPSCookie::set( 'sfct', $search_term );
			}
		}
		
		/**
		 * Ok this is an upper limit.
		 * If you needed to change this, you could do so via conf_global.php by adding:
		 * $INFO['max_search_word'] = #####;
		 */
		$this->settings['max_search_word'] = $this->settings['max_search_word'] ? $this->settings['max_search_word'] : 300;
		
		if( $this->settings['max_search_word'] && strlen( $search_term ) > $this->settings['max_search_word'] )
		{
			get_error("Query String Length Too Long!");
			//$this->searchAdvancedForm( sprintf( $this->lang->words['search_term_long'], $this->settings['max_search_word'] ) );
			//return;
		}
		
		/* Search titles only? */
		$content_titles_only = isset( $this->request['content_title_only'] ) && $this->request['content_title_only'] ? true : false;

		/* Show as titles? */
		if( ( $this->request['show_as_titles'] AND $this->settings['enable_show_as_titles'] ) OR ( $content_titles_only ) )
		{
			$this->search_plugin->onlyTitles = true;
		}

		/* Check for application restriction */
		$search_app_filter	= array();
		$traditionalkey		= '';

		if( isset( $this->request['search_filter_app'] ) && is_array( $this->request['search_filter_app'] ) )
		{
			if( ! in_array( $this->settings['search_method'], array( 'traditional', 'sphinx' ) ) )
			{
				/* Bypass all this if we are searching all apps */
				if( $this->request['search_filter_app']['all'] != 1 )
				{
					foreach( $this->request['search_filter_app'] as $app => $checked )
					{
						/* Bypass the all filter */
						if( $app == 'all' )
						{
							continue;
						}

						/* Add to the array */
						if( $checked )
						{
							$search_app_filter[] = "'$app'";
						}
					}

					/* Add this condition to the search */
					$this->search_plugin->setCondition( 'app', 'IN', implode( ',', $search_app_filter ) );				
				}
			}
			else
			{
				foreach( $this->request['search_filter_app'] as $app => $checked )
				{
					$traditionalKey	= $app;
				}
			}
		}
		/* Check for an author filter */
		if( !empty( $this->request['search_author'] ) || !empty(intval($this->request['mid'])))
		{
			/* Query the member id */
			$mem = $this->DB->buildAndFetch( array( 
													'select' => 'member_id', 
													'from'   => 'members', 
													'where'  => "member_id = '{$this->request['mid']}' OR members_display_name='{$this->request['search_author']}' or name='{$this->request['search_author']}'" 
											)	 );
			
			$this->search_plugin->searchAuthor = true;
			
			/* Add the condition to our search */
			$this->search_plugin->setCondition( 'member_id', '=', $mem['member_id'] ? $mem['member_id'] : -1 );
		}

		/* Check for application specific filters */
		if( isset( $this->request['search_app_filters'] ) && is_array( $this->request['search_app_filters'] ) )
		{
			foreach( $this->request['search_app_filters'] as $app => $filter_data )
			{
				if( ! isset( $this->search_plugin->display_plugins[ $app ] ) )
				{
					$this->search_plugin->display_plugins[ $app ] = IPSSearchIndex::getSearchDisplayPlugin( $app );
					$this->search_plugin->display_plugins[ $app ]->search_plugin	= $this->search_plugin;
				}

				$filter_data = $this->search_plugin->display_plugins[ $app ]->buildFilterSQL( $filter_data );

				if( $filter_data )
				{
					if ( isset( $filter_data[0] ) )
					{
						foreach( $filter_data as $_data )
						{
							$this->search_plugin->setCondition( $_data['column'], $_data['operator'], $_data['value'], 'AND' );
						}
					}
					else
					{
						$this->search_plugin->setCondition( $filter_data['column'], $filter_data['operator'], $filter_data['value'], 'OR' );
					}
				}
			}
		}

		/* Check Date Range */
//		if( isset( $this->request['search_date_start'] ) && $this->request['search_date_start'] || isset( $this->request['search_date_end'] ) && $this->request['search_date_end'] )
//		{
//			/* Start Range Date */
//			$search_date_start = 0;
//
//			if( $this->request['search_date_start'] )
//			{
//				$search_date_start = strtotime( $this->request['search_date_start'] );
//			}
//
//			/* End Range Date */
//			$search_date_end = 0;
//
//			if( $this->request['search_date_end'] )
//			{
//				$search_date_end = strtotime( $this->request['search_date_end'] );
//			}
//						
//			/* Correct for timezone...hopefully */
//			$search_date_start += abs( $this->registry->class_localization->getTimeOffset() );
//			$search_date_end   += abs( $this->registry->class_localization->getTimeOffset() );
//
//			/* If the times are exactly equaly, we're going to assume they are trying to search all posts from one day */
//			if( ( $search_date_start && $search_date_end ) && $search_date_start == $search_date_end )
//			{
//				$search_date_end += 86400;
//			}
//
//			$this->search_plugin->setDateRange( $search_date_start, $search_date_end );
//		}
		
		/* If we're display results as a forum*/
		/* Count the number of results */
		$total_results = $this->search_plugin->getSearchCount( $search_term, '', $content_titles_only, array( $st, $per_page ), $this->request['search_sort_by'], $this->request['search_sort_order'] );

		/* Do Pagination Stuff */
		//$st       = isset( $this->request['st'] ) ? intval( $this->request['st'] ) : 0;
		//$per_page = $this->settings['search_per_page'] ? $this->settings['search_per_page'] : 25;
		$st = $start_num;
		$per_page = $end_num - $start_num + 1;

		/* Showing */
		$showing = array( 'start' => $st + 1, 'end' => ( $st + $per_page ) > $total_results ? $total_results : $st + $per_page );
		
		/* Loop through the search results and build the output */
		$search_entries = array();
		$search_results = array();
		$topic_ids      = array();
		
		/**
		 * If we've already run a search and it's not clear, kill it now
		 */
		if( $this->member->sessionClass()->session_data['search_thread_id'] )
		{
			$this->DB->return_die	= true;
			$this->DB->kill( $this->member->sessionClass()->session_data['search_thread_id'] );
			$this->DB->return_die	= false;
		}

		/**
		 * Store the process id
		 */
		$processId	= $this->DB->getThreadId();
		
		if( $processId )
		{
			$this->DB->update( 'sessions', array( 'search_thread_id' => $processId, 'search_thread_time' => time() ), "id='" . $this->member->session_id . "'" );
		}
		
		/* Do the search */
		foreach( $this->search_plugin->getSearchResults( $search_term, array( $st, $per_page ), $this->request['search_sort_by'], '', $content_titles_only, $this->request['search_sort_order'] ) as $r )
		{
			/* Hack Job */
			if( $r['app'] == 'forums' && $r['type_2'] == 'topic' && $r['type_id_2'] )
			{
				$topic_ids[] = $r['type_id_2'];
			}
			
			/* Add to the entries array */
			$search_entries[] = $r;
		}
		
		/**
		 * And kill that process ID
		 */
		if( $processId )
		{
			$this->DB->update( 'sessions', array( 'search_thread_id' => 0, 'search_thread_time' => 0 ), "id='" . $this->member->session_id . "'" );
		}
		/* Get dots */
		$this->_retrieveTopics( $topic_ids );
		
		$topic_array = array();
		if( ( $this->settings['cpu_watch_update'] == 1 ) and ( $this->memberData['member_id'] ) and is_array($topic_ids) and count($topic_ids))
		{
			$this->DB->build( array( 
									'select' => 'topic_id, trid as trackingTopic',
									'from'   => 'tracker',
									'where'  => 'member_id=' . $this->memberData['member_id'] . ' AND topic_id IN(' . implode( ',', $topic_ids ) . ')',
							)	);			
			$this->DB->execute();
			
			while( $p = $this->DB->fetch() )
			{
				$topic_array[ $p['topic_id'] ] = 1;
			}
		}
		
		
		/* Parse results */
		$author_info = array();
		foreach( $search_entries as $r )
		{		
			//#######################################
			//get forum name 
			//########################################	
			$forum_id = $r['forum_id'];
			$r['forum_name'] = $this->registry->class_forums->forum_by_id[ $forum_id ]['name'];
			
			//-----------------------------------------
			// Are we actually a moderator for this forum?
			//-----------------------------------------
			
			if ( ! $this->memberData['g_is_supmod'] )
			{
				$moderator = $this->memberData['forumsModeratorData'];
				
				if ( !isset($moderator[ $r['forum_id'] ]) OR !is_array( $moderator[ $r['forum_id'] ] ) )
				{
					$this->memberData['is_mod'] = 0;
				}
			}
    		
    		$can_delete = 0;
			if ($this->memberData['is_mod'] == 1 and ($this->memberData['g_is_supmod'] == 1 || $this->memberData['forumsModeratorData'][ $r['forum_id'] ]['delete_topic'])) {
				$can_delete = 1;
			}
			
			//get icon_url
    		$r['icon_url'] = get_avatar($r['last_poster_id']);
    		
    		// get last post author username
    		if (isset($author_info[$r['last_poster_id']]['username'])) {
    		    $r['last_poster_username'] = $author_info[$r['last_poster_id']]['username'];
    		} else {
        		$topic_author_name = $this->DB->buildAndFetch( array(
                                                    'select' => 'name',
                                                    'from'   => 'members',
                                                    'where'  => "member_id= {$r['last_poster_id']} "));
                $r['last_poster_username'] = $topic_author_name['name'];
                $author_info[$r['last_poster_id']]['username'] = $topic_author_name['name'];
            }
			
			//######################################
		 	// get TOPIC CONTENT and shord content.....	
		 	//######################################
		 	$queued_query_bit = ' and queued=0';
		 	if ( $this->registry->getClass('class_forums')->canQueuePosts( $r['forum_id'] ) )
			{
				$queued_query_bit = "";
			}
		  	$post_data = $this->DB->buildAndFetch( array( 
													'select' => 'post, post_htmlstate', 
													'from'   => 'posts', 
													'where'  => "topic_id= {$r['tid']} " . $queued_query_bit,
													'order' => 'pid desc')	);
			
		 	$r['short_content'] = get_short_content($post_data['post'], $post_data['post_htmlstate']);
		 		
		 	//######################################
		 	// has new since last login????.....	
		 	//######################################
//		 	if ($this->memberData['member_id']) {
//		 		if ($this->memberData['last_visit'] < $r['last_post']) {
//		 			$r['has_new'] = true;
//		 		}
//		 	}
		 	$r['has_new'] = ipsRegistry::getClass( 'classItemMarking')->isRead( array( 'forumID' => $r['forum_id'], 
																'itemID' => $r['tid'], 
																'itemLastUpdate' => $r['last_post'] 
																),  'forums' );
		 	
		 	//######################################
		 	//get POST POSITION 
		 	//##################################
			$post_position = $this->DB->buildAndFetch( array( 
												'select' => 'COUNT(*) as post_position', 
												'from'   => 'posts', 
												'where'  => "topic_id= {$r['topic_id']} and pid <= {$r['pid']} " . $queued_query_bit)	);
			$r['post_position'] = $post_position['post_position'];
		 	
		 	$r['issubscribed'] = $topic_array[ $r['tid'] ] ? true : false;
		 	$r['can_subscribe'] = ($this->settings['cpu_watch_update'] == 1 && $this->memberData['member_id']) ? true : false;
		 	
			$search_results[] = $r;
		}
		return array(
			'total_topic_num' => $total_results,
			'list'			  => $search_results,
			);
	}
	
	
	/**
	 * Wrapper function to prevent fatal errors if method does not support this function
	 *
	 * @access	private
	 * @return	boolean
	 */
	private function _getShowAsForum()
	{
		if ( method_exists( $this->search_plugin, 'getShowAsForum' ) )
		{
			return $this->search_plugin->getShowAsForum();
		}
		else
		{
			return false;
		}
	}
}
