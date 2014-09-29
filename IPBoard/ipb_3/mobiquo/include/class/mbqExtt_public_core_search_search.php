<?php
defined('IN_MOBIQUO') or exit;

require_once( IPS_ROOT_PATH . 'applications/core/modules_public/search/search.php' );

class mbqExtt_public_core_search_search extends public_core_search_search
{
	/**
	 * Class entry point
	 *
	 * @param	object		Registry reference
	 * @return	@e void		[Outputs to screen/redirects]
	 */
	public function doExecute( ipsRegistry $registry )
	{ 
		/* Load language */
		$this->registry->class_localization->loadLanguageFile( array( 'public_search' ), 'core' );
		$this->registry->class_localization->loadLanguageFile( array( 'public_forums', 'public_topic' ), 'forums' );
		
		/* Reset engine type */
		$this->settings['search_method'] = ( $this->settings['search_method'] == 'traditional' ) ? 'sql' : $this->settings['search_method'];
		
		/* Force SQL for view new content? */
		if ( ! empty( $this->settings['force_sql_vnc'] ) && $this->request['do'] == 'viewNewContent' )
		{
			$this->settings['search_method'] = 'sql';
		}
		
		$this->request['search_app_filters'] =  $this->request['search_app_filters'];
		
		/* Special consideration for contextual search */
		if ( isset( $this->request['search_app'] ) AND strstr( $this->request['search_app'], ':' ) )
		{
			list( $app, $type, $id ) = explode( ':', $this->request['search_app'] );
			
			$this->request['search_app'] = $app;
			$this->request['cType']      = $type;
			$this->request['cId']		 = $id;
		}
		else
		{
			/* Force forums as default search */
			$this->request['search_in']      = ( $this->request['search_in'] AND IPSLib::appIsSearchable( $this->request['search_in'], 'search' ) ) ? $this->request['search_in'] : 'forums';
			$this->request['search_app']     = $this->request['search_app'] ? $this->request['search_app'] : $this->request['search_in'];
		}
		
		/* Check Access */
		$this->_canSearch();		
		
		/* Start session - needs to be called before the controller is initiated */
		$this->_startSession();
		
		/* Load the controller */
		$classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH. 'sources/classes/search/controller.php', 'IPSSearch' );
		
		/* Sanitzie */
		if ( ! is_string( $this->request['search_app'] ) )
		{
			$this->request['search_app'] = 'forums';
		}
		
		try
		{
			$this->searchController = new $classToLoad( $registry, $this->settings['search_method'], $this->request['search_app'] );
		}
		catch( Exception $error )
		{
			$msg = $error->getMessage();

			/* Start session */
			$this->_endSession();
		
			switch( $msg )
			{
				case 'NO_SUCH_ENGINE':
				case 'NO_SUCH_APP':
				case 'NO_SUCH_APP_ENGINE':
					$this->registry->output->showError( sprintf( $this->lang->words['no_search_app'], ipsRegistry::$applications[ $this->request['search_app'] ]['app_title'] ), 10145.1 );
				break;
			}
		}
		
		/* Log type */
		IPSDebug::addMessage( "Search type: " . $this->settings['search_method'] );
		
		/* Set up some defaults */
		IPSSearchRegistry::set('opt.noPostPreview', false );
		IPSSearchRegistry::set('in.start', intval( $this->request['st'] ) );
		IPSSearchRegistry::set('opt.search_per_page', intval( $this->settings['search_per_page'] ) ? intval( $this->settings['search_per_page'] ) : 25 );
		
		$this->settings['search_ucontent_days']	= ( $this->settings['search_ucontent_days'] ) ? $this->settings['search_ucontent_days'] : 365;
		
		/* Contextuals */
		if ( isset( $this->request['cType'] ) )
		{
			IPSSearchRegistry::set('contextual.type', $this->request['cType'] );
			IPSSearchRegistry::set('contextual.id'  , $this->request['cId'] );
		}
		

		/* What to do */
		switch( $this->request['do'] )
		{
			case 'user_activity':
				$this->viewUserContent();
			break;
		
			case 'new_posts':
			case 'viewNewContent':
			case 'active':
				$this->viewNewContent();
			break;
			
			case 'search':
			case 'quick_search':
				$this->searchResults();
			break;
			
			case 'followed':
				$this->viewFollowedContent();
			break;
			
			case 'manageFollowed':
				$this->updateFollowedContent();
			break;
			
			default:
			case 'search_form':	
				$this->searchAdvancedForm();
			break;
		}
		
		/* Start session */
		$this->_endSession();
		
		/* If we have any HTML to print, do so... */
		if ( $this->request['do'] == 'search' && ! empty( $this->request['search_tags'] ) )
		{
			$this->registry->output->setTitle( IPSText::urldecode_furlSafe( $this->request['search_tags'] ) . ' - ' . $this->lang->words['st_tags'] . ' - ' . IPSLib::getAppTitle( $this->request['search_app'] ) . ' - ' . ipsRegistry::$settings['board_name'] );
			
			/* Add canonical tag */
			$extra = ( $this->request['st'] ) ? '&amp;st=' . $this->request['st'] : '';
			$this->registry->output->addCanonicalTag( 'app=core&amp;module=search&amp;do=search&amp;search_tags=' . IPSText::urlencode_furlSafe( $this->request['search_tags'] ) . '&amp;search_app=' . $this->request['search_app']. $extra, $this->request['search_tags'], 'tags' );
		}
		else
		{
			$this->registry->output->setTitle( $this->title . ' - ' . ipsRegistry::$settings['board_name'] );
		}
		
		$this->registry->output->addContent( $this->output );
		return MbqAppEnv::$mbqReturn;
		//$this->registry->output->sendOutput();
	}
	
	/**
	 * View new posts since your last visit
	 *
	 * @return	@e void
	 */
	public function viewNewContent()
	{		
		/* Search flood check */
		// http://community.invisionpower.com/resources/bugs.html/_/ip-board/view-new-content-view-user-content-does-not-honor-flood-checking-r40765
		//$this->_floodCheck();
		
		IPSSearchRegistry::set('in.search_app', $this->request['search_app'] );
		
		/* Fetch member cache to see if we have a value set */
		$vncPrefs = IPSMember::getFromMemberCache( $this->memberData, 'vncPrefs' );
	
		/* Guests */
		if ( ! $this->memberData['member_id'] AND ( ! $this->request['period'] OR $this->request['period'] == 'unread' ) )
		{
			$this->request['period'] = 'today';
		}
			
		/* In period */
		if ( $vncPrefs === null OR ! isset( $vncPrefs[ IPSSearchRegistry::get('in.search_app') ]['view'] ) OR ( ! empty( $this->request['period'] ) AND isset( $this->request['change'] ) ) )
		{
			$vncPrefs[ IPSSearchRegistry::get('in.search_app') ]['view'] = ( ! empty( $this->request['period'] ) ) ? $this->request['period'] : $this->settings['default_vnc_method'];
		}
		
		/* Follow filter enabled */
		if ( $vncPrefs === null OR ! isset( $vncPrefs[ IPSSearchRegistry::get('in.search_app') ]['view'] ) OR isset( $this->request['followedItemsOnly'] ) )
		{
			$vncPrefs[ IPSSearchRegistry::get('in.search_app') ]['vncFollowFilter'] = ( ! empty( $this->request['followedItemsOnly'] ) ) ? 1 : 0;
		}
		
		/* Filtering VNC by forum? */
		IPSSearchRegistry::set('forums.vncForumFilters', $vncPrefs['forums']['vnc_forum_filter'] );

		/* Set period up */
		IPSSearchRegistry::set('in.period'           , $vncPrefs[ IPSSearchRegistry::get('in.search_app') ]['view'] );
		IPSSearchRegistry::set('in.vncFollowFilterOn', $vncPrefs[ IPSSearchRegistry::get('in.search_app') ]['vncFollowFilter'] );
		
		$this->request['userMode']	= !empty($this->request['userMode']) ? $this->request['userMode'] : '';
		IPSSearchRegistry::set('in.userMode'  , $this->request['userMode'] );
		
		/* Update member cache */
		if ( isset( $this->request['period'] ) AND isset( $this->request['change'] ) )
		{
			IPSMember::setToMemberCache( $this->memberData, array( 'vncPrefs' => $vncPrefs ) );
		}
		
		IPSDebug::addMessage( var_export( $vncPrefs, true ) );
		IPSDebug::addMessage( 'Using: ' . IPSSearchRegistry::get('in.period') );
		
		/* Can we do this? */
		if ( IPSLib::appIsSearchable( IPSSearchRegistry::get('in.search_app'), 'vnc' ) || IPSLib::appIsSearchable( IPSSearchRegistry::get('in.search_app'), 'active' ) )
		{
			/* Can't do a specific unread search, so */
			if ( IPSSearchRegistry::get('in.period') == 'unread' && ! IPSLib::appIsSearchable( IPSSearchRegistry::get('in.search_app'), 'vncWithUnreadContent' ) )
			{
				IPSSearchRegistry::set( 'in.period', 'lastvisit' );
			}
			
			/* Perform the search */
			$this->searchController->viewNewContent();
			
			/* Get count */
			$count = $this->searchController->getResultCount();
						
			/* Get results which will be array of IDs */
			$results = $this->searchController->getResultSet();
			
			/* Get templates to use */
			$template = $this->searchController->fetchTemplates();
			
			/* Fetch sort details */
			$sortDropDown = $this->searchController->fetchSortDropDown();
			
			/* Fetch sort details */
			$sortIn       = $this->searchController->fetchSortIn();
			
			/* Reset for template */
			$this->_resetRequestParameters();
			
			if( IPSSearchRegistry::get('in.start') > 0 AND !count($results) )
			{
				$new_url	= 'app=core&amp;module=search&amp;do=viewNewContent&amp;period=' . IPSSearchRegistry::get('in.period') . '&amp;userMode=' . IPSSearchRegistry::get('in.userMode') . '&amp;search_app=' . IPSSearchRegistry::get('in.search_app')  . '&amp;sid=' . $this->request['_sid'];
				$new_url	.= '&amp;st=' . ( IPSSearchRegistry::get('in.start') - IPSSearchRegistry::get('opt.search_per_page') ) . '&amp;search_app_filters[' . IPSSearchRegistry::get('in.search_app') . '][searchInKey]=' . $this->request['search_app_filters'][ IPSSearchRegistry::get('in.search_app') ]['searchInKey'];
				
				$this->registry->output->silentRedirect( $this->settings['base_url'] . $new_url );
			}
			
			/* Parse result set */
			$results = $this->registry->output->getTemplate( $template['group'] )->$template['template']( $results, ( IPSSearchRegistry::get('opt.searchType') == 'titles' || IPSSearchRegistry::get('opt.noPostPreview') ) ? 1 : 0 );
			
			/* Build pagination */
			$links = $this->registry->output->generatePagination( array( 'totalItems'		 => $count,
																		 'itemsPerPage'		 => IPSSearchRegistry::get('opt.search_per_page'),
																		 'currentStartValue' => IPSSearchRegistry::get('in.start'),
																		 //'method'			 => 'nextPrevious',
																		 'baseUrl'			 => 'app=core&amp;module=search&amp;do=viewNewContent&amp;period=' . IPSSearchRegistry::get('in.period') . '&amp;userMode=' . IPSSearchRegistry::get('in.userMode') . '&amp;search_app=' . IPSSearchRegistry::get('in.search_app')  . '&amp;sid=' . $this->request['_sid'] . $this->_returnSearchAppFilters() ) );
	
			/* Showing */
			$showing = array( 'start' => IPSSearchRegistry::get('in.start') + 1, 'end' => ( IPSSearchRegistry::get('in.start') + IPSSearchRegistry::get('opt.search_per_page') ) > $count ? $count : IPSSearchRegistry::get('in.start') + IPSSearchRegistry::get('opt.search_per_page') );
		}
		else
		{
			$count   = 0;
			$results = array();
		}
		
		/* Add Debug message */
		IPSDebug::addMessage( "View New Content Matches: " . $count );
		
		/* Check for sortIn */
		if( count( $sortIn ) && !$this->request['search_app_filters'][ $this->request['search_app'] ]['searchInKey'] )
		{
			$this->request['search_app_filters'][ $this->request['search_app'] ]['searchInKey'] = $sortIn[0][0];
		}
		
		/* Output */
		$this->title   = $this->lang->words['new_posts_title'];
		$this->registry->output->addNavigation( $this->lang->words['new_posts_title'], '' );
		$this->output .= $this->registry->output->getTemplate( 'search' )->newContentView( $results, $links, $count, $sortDropDown, $sortIn, IPSSearchRegistry::get('set.resultCutToDate') );
		MbqAppEnv::$mbqReturn['subscribed_topic_unread_count'] = $count;
	}
	 
}

?>