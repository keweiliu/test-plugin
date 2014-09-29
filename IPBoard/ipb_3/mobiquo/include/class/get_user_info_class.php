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
//require_once (IPS_ROOT_PATH . 'sources/base/ipsController.php');
require_once (IPS_ROOT_PATH . 'applications/members/modules_public/profile/view.php');

//class user_info extends ipsCommand
class user_info extends public_members_profile_view
{
	/*
	 * Class entry point
	 *
	 * @access	public
	 * @param	object		Registry reference
	 * @return	void		[Outputs to screen/redirects]
	 */
	public function doExecute( ipsRegistry $registry )
	{
		####################
		if (isset($this->request['user_name']))
		{
    		$user_name = to_local($this->request['user_name']);
    		$user_id = intval($this->request['id']);
    		$member = $this->DB->buildAndFetch( array( 
    						'select' => '*', 
    						'from'   => 'members', 
    						'where'  => "members_display_name= '{$user_name}' or name='{$user_name}'")	);
    		if (isset($member) and count($member)) {
    			$this->request['id'] = $member['member_id'];
    		} else {
    			get_error("No Such User!");
    		}
    	}
		
		#########################################
		
		$this->registry->class_localization->loadLanguageFile( array( 'public_profile' ), 'members' );
		$this->registry->class_localization->loadLanguageFile( array( 'public_online' ), 'members' );

		if ( !$this->memberData['g_mem_info'] )
 		{
 			get_error("Profile View Off!");
		}

		return $this->_viewModern();
		
 	}

 	protected function _viewModern()
 	{
		$member_id			= intval( $this->request['id'] ) ? intval( $this->request['id'] ) : intval( $this->request['MID'] );
		$member_id			= $member_id ? $member_id : $this->memberData['member_id'];
		$member				= array();
		$visitors			= array();
		$pips				= 0;
		$_positions			= array( 0 => 0 );
		$_member_ids		= array();
		$sql_extra			= '';
		$pass				= 0;
		$mod				= 0;
		$_todays_date		= getdate();
		
		$time_adjust		= $this->settings['time_adjust'] == "" ? 0 : $this->settings['time_adjust'];
		$board_posts		= $this->caches['stats']['total_topics'] + $this->caches['stats']['total_replies'];

		//-----------------------------------------
		// Grab all data...
		//-----------------------------------------		
		$member = IPSMember::load( $member_id, 'profile_portal,pfields_content,sessions,groups', 'id' );

		if ( !$member['member_id'] )
		{
			get_error("Wrong Member ID");
		}
		
		/* Build data */
		$member = IPSMember::buildDisplayData( $member, array( 'customFields' => 1, 'cfSkinGroup' => 'profile', 'checkFormat' => 1, 'cfGetGroupData' => 1, 'signature' => 1, 'spamStatus' => 1 ) );

		//-----------------------------------------
		// Recent visitor?
		//-----------------------------------------
		
		if ( $member['member_id'] != $this->memberData['member_id'] )
		{
			list( $be_anon, $loggedin ) = explode( '&', $this->memberData['login_anonymous'] );
			
			if ( ! $be_anon )
			{
				$this->_addRecentVisitor( $member, $this->memberData['member_id'] );
			}
		}

		//-----------------------------------------
		// DST?
		//-----------------------------------------
		
		if ( $member['dst_in_use'] == 1 )
		{
			$member['time_offset'] += 1;
		}


		$member['_local_time']		= $member['time_offset'] != "" ? gmstrftime( $this->settings['clock_long'], time() + ($member['time_offset']*3600) + ($time_adjust * 60) ) : '';
		
		if (version_compare($GLOBALS['app_version'], '3.2.0', '>='))
		    $member['g_title'] = IPSMember::makeNameFormatted( $member['g_title'], $member['g_id'], $member['prefix'], $member['suffix'] );
		else
		    $member['g_title'] = IPSLib::makeNameFormatted( $member['g_title'], $member['g_id'], $member['prefix'], $member['suffix'] );
		
		$member['_posts_day']		= 0;
		$member['_total_pct']		= 0;
		$member['_bday_month']		= $member['bday_month'] ? $this->lang->words['M_' . $member['bday_month'] ] : 0;
				

		$posts	= $this->DB->buildAndFetch( array(
												'select'	=> "COUNT(*) as total_posts",
												'from'		=> "posts",
												'where'		=> "author_id=" . $member['member_id'],
											)		);

		$member['posts']	= $posts['total_posts'];

		//-----------------------------------------
		// Total posts
		//-----------------------------------------
		
		if ( $member['posts'] and $board_posts  )
		{
			$member['_posts_day'] = round( $member['posts'] / ( ( time() - $member['joined']) / 86400 ), 2 );
	
			# Fix the issue when there is less than one day
			$member['_posts_day'] = ( $member['_posts_day'] > $member['posts'] ) ? $member['posts'] : $member['_posts_day'];
			$member['_total_pct'] = sprintf( '%.2f', ( $member['posts'] / $board_posts * 100 ) );
		}
		
		$member['_posts_day'] = floatval($member['_posts_day']);
			
		if( ! $this->settings['disable_profile_stats'] )
		{
			//-----------------------------------------
			// Most active in
			//-----------------------------------------
			
			/* Get list of good forum IDs */
			$forumIdsOk = $this->registry->class_forums->fetchSearchableForumIds();
			$faves		= array();
			$top		= 0;
			
			if( is_array($forumIdsOk) AND count($forumIdsOk) )
			{
				$favorite = $this->DB->buildAndFetch( array('select'	=> 'COUNT(p.author_id) as f_posts',
															'from'		=> array( 'posts' => 'p' ),
															'where'		=> 'p.author_id=' . $member['member_id'] . ' AND t.forum_id IN (' . implode( ",", $forumIdsOk ) . ") ",
															'group'		=> 't.forum_id',
															'order'		=> 'f_posts DESC',
															'limit'		=> array( 0, 1 ),
															'add_join'	=> array( array( 'select'	=> 't.forum_id',
																						 'from'		=> array( 'topics' => 't' ),
																						 'where'	=> 't.tid=p.topic_id' ) ) )	);
			}
			else
			{
				$favorite	= array( 'forum_id' => 0, 'f_posts' => 0 );
			}

			$member['favorite_id']	= $favorite['forum_id'];
			$member['_fav_posts']	= $favorite['f_posts'];
			
			if( $member['posts'] )
			{
				$member['_fav_percent']	= round( $favorite['f_posts'] / $member['posts'] * 100 );
			}
		}

		//-----------------------------------------
		// Visitors
		//-----------------------------------------
		
		if ( $member['pp_setting_count_visitors'] )
		{
			$_pp_last_visitors	= unserialize( $member['pp_last_visitors'] );
			$_visitor_info		= array();
			$_count				= 1;
		
			if ( is_array( $_pp_last_visitors ) )
			{
				krsort( $_pp_last_visitors );
			
				$_members = IPSMember::load( array_values( $_pp_last_visitors ), 'extendedProfile' );
	
				foreach( $_members as $_id => $_member )
				{ 
					$_visitor_info[ $_id ] = IPSMember::buildDisplayData( $_member, 0 );
				}
				
				foreach( $_pp_last_visitors as $_time => $_id )
				{
					if ( $_count > $member['pp_setting_count_visitors'] )
					{
						break;
					}
				
					$_count++;
				
					if( !$_visitor_info[ $_id ]['members_display_name_short'] )
					{
						$_visitor_info[ $_id ] = IPSMember::setUpGuest();
					}
					
					$_visitor_info[ $_id ]['_visited_date'] 				= ipsRegistry::getClass( 'class_localization')->getDate( $_time, 'TINY' );
					$_visitor_info[ $_id ]['members_display_name_short']	= $_visitor_info[ $_id ]['members_display_name_short'] ? $_visitor_info[ $_id ]['members_display_name_short'] : $this->lang->words['global_guestname'];

					$visitors[] = $_visitor_info[ $_id ];
				}
			}
		}
		
		//-----------------------------------------
		// Online location
		//-----------------------------------------
		
		$member = IPSMember::getLocation( $member );
		
		//-----------------------------------------
		// Add profile view
		//-----------------------------------------
		$member['icon_url'] = get_avatar($member);
		
		$this->DB->insert( 'profile_portal_views', array( 'views_member_id' => $member['member_id'] ), true );
		
		$member['display_text'] = array(
			array (
				'name' => $this->lang->words['m_group'],
				'value' => strip_tags($member['g_title']),
			),
			array (
				'name' 	=> $this->lang->words['m_active_in'],
				'value' => $this->registry->class_forums->forum_by_id[ $member['favorite_id'] ]['name'] . "({$member['_fav_posts']} {$this->lang->words['fav_posts']})",
			),
			array (
				'name' 	=> $this->lang->words['m_profile_views'],
				'value' =>  $member['members_profile_views'],
			),
			array (
				'name' 	=> $this->lang->words['m_currently'],
				'value' => subject_clean($member['_online'] ? $member['online_extra'] : $this->lang->words['online_offline']),
			),
		);
			
		if	($member['title'] != '') {
			$member['display_text'][] = array(
				'name' 	=> $this->lang->words['m_member_title'],
				'value' => $member['title'],
			);
		}
		
		return $member;
	}
	
	/**
	 * Determines where to put custom profile tabs
	 *
	 * @access	private
	 * @param	array 		$takenPositions		Array of positions that have been used
	 * @param	integer		$requestedPosition	Position to check
	 * @return	integer
	 */
	protected function _getTabPosition( $takenPositions, $requestedPosition )
	{
		if( in_array( $requestedPosition, $takenPositions ) )
		{
			$requestedPosition++;
			$this->_getTabPosition( $takenPositions, $requestedPosition );
		}
		
		return $requestedPosition;
	}
 
 
	protected function _addRecentVisitor( $member=array(), $member_id_to_add=0 )
    {
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$member_id_to_add	= intval( $member_id_to_add );
		$found				= 0;
		$_recent_visitors	= array();
		
		//-----------------------------------------
		// Check...
		//-----------------------------------------
		
		if ( ! $member_id_to_add )
		{
			return false;
		}
		
		//-----------------------------------------
		// Sort out data...
		//-----------------------------------------
		
		$recent_visitors = unserialize( $member['pp_last_visitors'] );
		
		if ( ! is_array( $recent_visitors ) OR ! count( $recent_visitors ) )
		{
			$recent_visitors = array();
		}
		
		foreach( $recent_visitors as $_time => $_id )
		{
			if ( $_id == $member_id_to_add )
			{
				$found  = 1;
				continue;
			}
			else
			{
				$_recent_visitors[ $_time ] = $_id;
			}
		}
		
		$recent_visitors = $_recent_visitors;
	
		krsort( $recent_visitors );
	
		//-----------------------------------------
		// No more than 10
		//-----------------------------------------
	
		if ( ! $found )
		{
			if ( count( $recent_visitors ) > 10 )
			{
				$_tmp = array_pop( $recent_visitors );
			}
		}
		
		//-----------------------------------------
		// Add the visit
		//-----------------------------------------
			
		$recent_visitors[ time() ] = $member_id_to_add;
		
		krsort( $recent_visitors );
		
		//-----------------------------------------
		// Update profile...
		//-----------------------------------------
	
		if ( $member['pp_member_id'] )
		{
			$this->DB->update( 'profile_portal ', array( 'pp_last_visitors' => serialize( $recent_visitors ) ), 'pp_member_id=' . $member['member_id'], true );
		}
		else
		{
			$this->DB->insert( 'profile_portal ', array( 'pp_member_id'		=> $member['member_id'],
															'pp_profile_update'	=> time(),
															'pp_last_visitors'	=> serialize( $recent_visitors ) 
								), true					);
		}
		
		return true;
	}

}	
