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
require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/forums/boards.php');
require_once ('mobi_common_class.php');

class mobi_board_stat extends public_forums_forums_boards
{
	public function doExecute(ipsRegistry $registry, $mod = 'online_users')
	{
		if (! $this->memberData['member_id'] )
		{
			$this->request['last_visit'] = time();
		}
		$this->_Commonclass = new mobi_common($registry);
		
		if ($mod == 'board_stat') {		
			$active = $this->getActiveUserDetails();		
			$stats_info = $this->getTotalTextString();		
			return array_merge($active, $stats_info);
		}
		elseif ($mod == 'online_users') {
			return $this->getOnlineUserInfo();
		}
	}
	
	
	public function getOnlineUserInfo()
	{		
		$active = $this->getActiveUserDetails();
		$mem_list = array();
		
		$this->registry->class_localization->loadLanguageFile( array( 'public_profile' ), 'members' );
		$this->registry->class_localization->loadLanguageFile( array( 'public_online' ), 'members' );
		if (count($active['MEMBER']) > 0){
		    
		    $count_num = 0;
		    
			foreach ($active['MEMBER'] as $id => $info) {
			    if ($count_num < 40) {
    	    		//-----------------------------------------
    				// Grab all data...
    				//-----------------------------------------		
    				$member = IPSMember::load( $info['member_id'], 'profile_portal,sessions', 'id' );
    				$member = IPSMember::buildDisplayData( $member, array() );
    				$member = IPSMember::getLocation( $member );
    
    				$mem_list[] = new xmlrpcval(array(
    				                    'user_id'               => new xmlrpcval($info['member_id']),
                						'username'              => new xmlrpcval(mobi_unescape_html(to_utf8($info['member_name'])), 'base64'),
                						'user_name'             => new xmlrpcval(mobi_unescape_html(to_utf8($info['member_name'])), 'base64'),
                						'last_activity_time'    => new xmlrpcval(mobiquo_iso8601_encode($info['last_activity']), 'dateTime.iso8601'),
                						'icon_url'              => new xmlrpcval(get_avatar($member)),
                						'display_text'          => new xmlrpcval(subject_clean($member['_online'] ? $member['online_extra'] : $this->lang->words['online_offline']), 'base64'),
            							), 'struct');
            	} else {
    				$mem_list[] = new xmlrpcval(array(
    				                    'user_id'               => new xmlrpcval($info['member_id']),
                						'username'              => new xmlrpcval(mobi_unescape_html(to_utf8($info['member_name'])), 'base64'),
                						'user_name'             => new xmlrpcval(mobi_unescape_html(to_utf8($info['member_name'])), 'base64'),
                						'last_activity_time'    => new xmlrpcval(mobiquo_iso8601_encode($info['last_activity']), 'dateTime.iso8601'),
                						'icon_url'              => new xmlrpcval(''),
                						'display_text'          => new xmlrpcval('', 'base64'),
            							), 'struct');
            	}
            	
            	$count_num++;
			}
			
			$return = array (
				'guest_count'	=> $active['GUESTS'],
				'member_count' 	=> $active['MEMBERS'] + $active['ANON'],
				'list' 		  	=> $mem_list,
			);			
		} else {
			$return = array (
				'guest_count'	=> $active['GUESTS'],
				'member_count' 	=> $active['MEMBERS'] + $active['ANON'],
				'list' 		  	=> array(),
			);
		}
		return $return;
	}
	
	
	/**
	 * Returns an array of active users
	 *
	 * @access	public
	 * @return	array
	 **/
	public function getActiveUserDetails()
	{
		$active = array( 'TOTAL'   => 0 ,
						 'NAMES'   => array(),
						 'GUESTS'  => 0 ,
						 'MEMBERS' => 0 ,
						 'ANON'    => 0 ,
					   );
			
		if( !$this->settings['au_cutoff'] )
		{
			$this->settings['au_cutoff'] = 15;
		}
		
		//-----------------------------------------
		// Get the users from the DB
		//-----------------------------------------
		
		$cut_off = $this->settings['au_cutoff'] * 60;
		$time    = time() - $cut_off;
		$rows    = array();
		$ar_time = time();
		if ( $this->memberData['member_id'] )
		{	
			$rows = array( $ar_time.'.'.md5( microtime() ) => array( 
																	'id'           => 0,
																	'login_type'   => substr( $this->memberData['login_anonymous'], 0, 1),
																	'running_time' => $ar_time,
																	'seo_name'     => $this->memberData['members_seo_name'],
																	'member_id'    => $this->memberData['member_id'],
																	'member_name'  => $this->memberData['members_display_name'],
																	'name'          => $this->memberData['name'],
																	'member_group' => $this->memberData['member_group_id'],
																	'last_activity' => $this->memberData['last_activity'],
																	) 
						);
		}
		
		$this->DB->build( array( 
										'select' => 's.id, s.member_id, s.member_name, s.seo_name, s.login_type, s.running_time, s.member_group, s.uagent_type, m.name, m.last_activity',
										'from'   => array( 'sessions' => 's' ),
										'where'  => "running_time > $time",
										'add_join' => array(
															array( 
																	'type'	=> 'left',
																	'from'	=> array('members' => 'm'),
																	'where'	=> 'm.member_id=s.member_id'
																)
													)
							)	);
		$this->DB->execute();
		
		//-----------------------------------------
		// FETCH...
		//-----------------------------------------
		
		while ( $r = $this->DB->fetch() )
		{
			$rows[ $r['running_time'].'.'.$r['id'] ] = $r;
		}
		
		krsort( $rows );

		// cache all printed members so we
		// don't double print them
		$cached = array();
		foreach ( $rows as $result )
		{
			$last_date = $this->registry->getClass('class_localization')->getTime( $result['running_time'] );
			
			if ( isset( $result['uagent_type'] ) && $result['uagent_type'] == 'search' )
			{
				/* Skipping bot? */
				if ( ! $this->settings['spider_active'] ) {
					continue;
				}
				
				if ( ! $cached[ $result['member_name'] ] )
				{
//					if ( $this->settings['spider_anon'] )
//					{
//						if ( $this->memberData['g_access_cp'] )
//						{
//							$active['NAMES'][] = IPSLib::makeNameFormatted( $result['member_name'], $result['member_group'] );
//						}
//					}
//					else
//					{
//						$active['NAMES'][] = IPSLib::makeNameFormatted( $result['member_name'], $result['member_group'] );
//					}
					
					$cached[ $result['member_name'] ] = 1;
				}
				else
				{
					$active['GUESTS']++;
				}
			}
			else if ( ! $result['member_id'] OR ! $result['member_name'] )
			{
				$active['GUESTS']++;
			}
			//-----------------------------------------
			// Member?
			//-----------------------------------------			
			else
			{
				if ( empty( $cached[ $result['member_id'] ] ) )
				{
					$cached[ $result['member_id'] ] = 1;
					$mem_name = $result['member_name'];
					//$result['member_name'] = IPSLib::makeNameFormatted( $result['member_name'], $result['member_group'] );
					if ( ! $this->settings['disable_anonymous'] AND $result['member_id'] )
					{
						$active['MEMBERS']++;
						####################
						$member = $result;
						//$member['member_id'] = $result['member_id'];
						$member['member_name'] = $mem_name;
						$active['MEMBER'][] = $member;
						//$active['NAMES'][] = $result['member_name'];
						####################
					}
					
				}
			}
		}
		$active['TOTAL'] = $active['MEMBERS'] + $active['GUESTS'] + $active['ANON'];			
		$this->users_online = $active['TOTAL'];
		return $active;
	}
	
	/**
	 * Returns an array of board stats
	 *
	 * @access	public
	 * @return	string		Stats string
	 **/
	public function getTotalTextString()
	{
		/* INIT */
		$stats_output = array();
		
//		if ( $this->settings['show_totals'] )
//		{
		if ( ! is_array( $this->caches['stats'] ) )
		{
			$this->cache->setCache( 'stats', array(), array( 'array' => 1 ) );
		}
		
		$stats = $this->caches['stats'];
		
		//-----------------------------------------
		// We need to determine if we have the most users ever online if we aren't
		// showing active users in the stats block
		//-----------------------------------------
		
		if( !$this->users_online )
		{
			$cut_off = $this->settings['au_cutoff'] * 60;
			$time    = time() - $cut_off;
			$total	 = $this->DB->buildAndFetch( array( 'select'	=> 'count(*) as users_online', 'from' => 'sessions', 'where' => "running_time > $time" ) );

			$this->users_online = $total['users_online'];
		}
		
		//-----------------------------------------
		// Update the most active count if needed
		//-----------------------------------------
		
		if ($this->users_online > $stats['most_count'])
		{
			$stats['most_count'] = $this->users_online;
			$stats['most_date']  = time();
			
			$this->cache->setCache( 'stats', $stats, array( 'array' => 1 ) );
		}
		
//		$stats_output['most_time'] = $this->registry->getClass( 'class_localization')->getDate( $stats['most_date'], 'LONG' );
//		$stats_output['most_online'] = $this->registry->getClass('class_localization')->formatNumber($stats['most_count']);
//		
//		$this->lang->words['most_online'] = str_replace( "<#NUM#>" ,  $stats_output['most_online']	, $this->lang->words['most_online'] );
//		$this->lang->words['most_online'] = str_replace( "<#DATE#>",  $stats_output['most_time']	, $this->lang->words['most_online'] );

		$stats_output['total_posts'] = $stats['total_replies'] + $stats['total_topics'];
		$stats_output['total_topics'] = $stats['total_topics'];
		$stats_output['mem_count'] 	= $stats['mem_count'];
		//$stats_output['total_posts'] = $this->registry->getClass('class_localization')->formatNumber($stats_output['total_posts']);
		//$stats_output['mem_count'] = $this->registry->getClass('class_localization')->formatNumber($stats['mem_count']);
		//$stats_output['total_topics'] = $this->registry->getClass('class_localization')->formatNumber($stats['total_topics']);
		
		
		$this->total_posts    = $stats_output['total_posts'];
		$this->total_members  = $stats['mem_count'];
		$this->total_topics   = $stats_output['total_topics'];
		
//		$stats_output['last_mem_seo']	= $stats['last_mem_name_seo'] ? $stats['last_mem_name_seo'] : IPSText::makeSeoTitle( $stats['last_mem_name'] );
//		$stats_output['last_mem_link']	= $this->registry->output->formatUrl( $this->registry->output->buildUrl( "showuser=".$stats['last_mem_id'], 'public' ), $stats_output['last_mem_seo'], 'showuser' );
//		$stats_output['last_mem_name']	= $stats['last_mem_name'];
//		$stats_output['last_mem_id']	= $stats['last_mem_id'];

		$this->lang->words['total_word_string'] = str_replace( "<#posts#>" , $stats_output['total_posts']   , $this->lang->words['total_word_string'] );
		$this->lang->words['total_word_string'] = str_replace( "<#reg#>"   , $stats_output['mem_count']     , $this->lang->words['total_word_string'] );
		$this->lang->words['total_word_string'] = str_replace( "<#mem#>"   , $stats_output['last_mem_name'] , $this->lang->words['total_word_string'] );
		$this->lang->words['total_word_string'] = str_replace( "<#link#>"  , $stats_output['last_mem_link'] , $this->lang->words['total_word_string'] ); 
//		}

		return $stats_output;
	}	
}




?>