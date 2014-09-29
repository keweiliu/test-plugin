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

require_once (IPS_ROOT_PATH . 'applications/core/modules_public/reports/reports.php');

class mobi_public_core_reports_reports extends public_core_reports_reports
{
	public function doExecute( ipsRegistry $registry ) 
	{
		######get forum_id and topic_id
		if($this->request['rcom'] == 'post') {
			$row = $this->DB->buildAndFetch( array( 'select' => 'topic_id', 'from' => 'posts', 'where' => "pid='{$this->request['post_id']}'" ) );
			$this->request['topic_id'] = $row['topic_id'];
			if (!$this->request['topic_id']) {
				get_error("No Such Topic");
			}
			$row = $this->DB->buildAndFetch( array( 'select' => 'forum_id', 'from' => 'topics', 'where' => "tid='{$this->request['topic_id']}'" ) );
			$this->request['forum_id'] = $row['forum_id'];
			if (!$this->request['forum_id']) {
				get_error("No Such Forum");
			}
		} elseif ($this->request['rcom'] == 'messages') {
			$row = $this->DB->buildAndFetch( array( 'select' => 'msg_topic_id', 'from' => 'message_posts', 'where' => "msg_id='{$this->request['msg']}'" ) );
			$this->request['topic'] = $row['msg_topic_id'];
			$this->request['topicID'] = $row['msg_topic_id'];
			if (!$this->request['topicID']) {
				get_error("No Such Message");
			}
			
			$row = $this->DB->buildAndFetch( array( 'select' => 'mt_title', 'from' => 'message_topics', 'where' => "mt_id='{$this->request['topicID']}'" ) );
			$this->request['title'] = $row['mt_title'];
			if (!isset($this->request['title'])) {
				get_error("No Such Message");
			}
			
		}
		
		$this->request['message'] = to_local($this->request['message']);
		
		##############################
		//-----------------------------------------
		// Load basic things
		//----------------------------------------- 

		$this->registry->class_localization->loadLanguageFile( array( 'public_reports' ) );
		$this->registry->getClass('class_localization')->loadLanguageFile( array( "public_error" ), 'core' );
		$this->DB->loadCacheFile( IPSLib::getAppDir('core') . '/sql/' . ips_DBRegistry::getDriverType() . '_report_queries.php', 'report_sql_queries' );
		
		require_once( IPSLib::getAppDir('core') .'/sources/classes/reportLibrary.php' );
		$this->registry->setClass( 'reportLibrary', new reportLibrary( $this->registry ) );

		//-----------------------------------------
		// Check permissions...
		//-----------------------------------------
		
		$showReportCenter	= false;
		
		$this->member_group_ids	= array( $this->memberData['member_group_id'] );
		$this->member_group_ids	= array_diff( array_merge( $this->member_group_ids, explode( ',', $this->memberData['mgroup_others'] ) ), array('') );
		$report_center		= array_diff( explode( ',', $this->settings['report_mod_group_access'] ), array('') );

		foreach( $report_center as $groupId )
		{
			if( in_array( $groupId, $this->member_group_ids ) )
			{
				$showReportCenter	= true;
			}
		}
		
		if( ($this->request['do'] AND $this->request['do'] != 'report') AND !$showReportCenter )
		{
			get_error( $this->lang->words['no_reports_permission'] );
		}
		
		$this->registry->output->setTitle( $this->lang->words['main_title'] );

		//-----------------------------------------
		// Which road are we going to take?
		//-----------------------------------------
		switch( $this->request['do'] )
		{
			default:
			case 'report':
				$this->_initReportForm();
			break;
		}
	}
	
	
	public function _initReportForm()
	{
		//-----------------------------------------
		// Make sure we have an rcom
		//-----------------------------------------
		$rcom = IPSText::alphanumericalClean($this->request['rcom']);
		if( !$rcom )
		{
			get_error( $this->lang->words['reports_what_now'] );
		}
		
		//-----------------------------------------
		// Request plugin info from database
		//-----------------------------------------

		$row = $this->DB->buildAndFetch( array( 'select' => '*', 'from' => 'rc_classes', 'where' => "my_class='{$rcom}' AND onoff=1" ) );		
		if( !$row['com_id'] )
		{
			get_error( $this->lang->words['reports_what_now'] );
		}
		else
		{
			//-----------------------------------------
			// Can this group report this type of page?
			//-----------------------------------------
			
			if( $row['my_class'] == '' || count( array_diff($this->member_group_ids, explode(',', $row['group_can_report'])) ) >= count( $this->member_group_ids ) )
			{
				get_error( $this->lang->words['reports_cant_report'] );
			}
			
			require_once( IPSLib::getAppDir('core') . '/sources/classes/reportNotifications.php' );
			
			$notify = new reportNotifications( $this->registry );
			
			//-----------------------------------------
			// Let's get cooking! Load the plugin
			//-----------------------------------------
			
			$this->registry->getClass('reportLibrary')->loadPlugin( $row['my_class'], $row['app'] );
			
			//-----------------------------------------
			// Process 'extra data' for the plugin
			//-----------------------------------------
			
			if( $row['extra_data'] && $row['extra_data'] != 'N;' )
			{
				$this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]->_extra = unserialize( $row['extra_data'] );
			}
			else
			{
				$this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]->_extra = array();
			}
			
			$send_code = intval($this->request['send']);
			if( $send_code == 0 )
			{
				//-----------------------------------------
				// Request report form from plugin
				//-----------------------------------------
				
				$this->output .= $this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]->reportForm( $row );
			}
			else
			{
				//-----------------------------------------
				// Empty report
				//-----------------------------------------				
				if( !trim(strip_tags($this->request['message'])) )
				{
					get_error( $this->lang->words['reports_cant_empty'] );
				}
				//-----------------------------------------
				// Sending report... do necessary things
				//-----------------------------------------
				$report_data = $this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]->processReport( $row );
				$this->registry->getClass('reportLibrary')->updateCacheTime();
				
				//-----------------------------------------
				// Send out notfications...
				//-----------------------------------------
				$notify->initNotify( $this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]->getNotificationList( substr( $row['mod_group_perm'], 1, strlen($row['mod_group_perm']) - 2), $report_data ), $report_data );
				$notify->sendNotifications();				
			}
		}
	}
}
