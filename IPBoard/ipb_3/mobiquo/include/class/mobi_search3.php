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
defined('IN_MOBIQUO') or exit;

require_once( IPS_ROOT_PATH . 'sources/classes/search/controller.php' );

class mobi_search extends ipsCommand
{
	/**
	 * Generated output
	 *
	 * @var		string
	 */		
	protected $output			= '';
	
	/**
	 * Page Title
	 *
	 * @var		string
	 */		
	protected $title			= '';
	
	/**
	 * Object to handle searches
	 *
	 * @var		string
	 */	
	protected $search_plugin	= '';
	
	/**
	 * Topics array
	 *
	 * @var		array
	 */
	protected	$_topicArray	= array();
	protected $_removedTerms  = array();
	
	/**
	 * Search controller
	 *
	 * @var		obj
	 */		
	protected $searchController;
	protected $_session;

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
		if ( ! empty( $this->settings['force_sql_vnc'] ) && ($this->request['do'] == 'viewNewContent' || $this->request['do'] == 'new_posts') )
		{
			$this->settings['search_method'] = 'sql';
		}
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
			$this->searchController = new mobi_IPSSearch( $registry, $this->settings['search_method'], $this->request['search_app'] );
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
		global $search_filter;
		IPSSearchRegistry::set('opt.noPostPreview', false );
		IPSSearchRegistry::set('in.start', intval( $this->request['st'] ) );
		IPSSearchRegistry::set('opt.search_per_page', isset( $search_filter['perpage'] ) ? intval( $search_filter['perpage'] ) : 20 );
		
		$this->settings['search_ucontent_days']	= ( $this->settings['search_ucontent_days'] ) ? $this->settings['search_ucontent_days'] : 365;
		
		/* Contextuals */
		if ( isset( $this->request['cType'] ) )
		{
			IPSSearchRegistry::set('contextual.type', $this->request['cType'] );
			IPSSearchRegistry::set('contextual.id'  , $this->request['cId'] );
		}
			
		// tapatalk add
		if ($GLOBALS['request_name'] == 'get_participated_topic')
		    $this->request['do'] = 'user_activity';
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
		$_GET['_sid'] = $this->request['_sid'];
		/* Start session */
		return $this->mobi_result;
	}
	
	/**
	 * Moderate your list of liked content
	 *
	 * @author	bfarber
	 * @return	string	HTML to display
	 */
	public function updateFollowedContent()
	{
		IPSSearchRegistry::set( 'in.search_app', $this->request['search_app'] );
		
		//-----------------------------------------
		// Get the likes we selected
		//-----------------------------------------
		
		$_likes	= array();
		
		if( is_array($this->request['likes']) AND count($this->request['likes']) )
		{
			foreach( $this->request['likes'] as $_like )
			{
				$_thisLike	= explode( '-', $_like );
				$_likes[]	= array(
									'app'	=> $_thisLike[0],
									'area'	=> $_thisLike[1],
									'id'	=> $_thisLike[2],
									);
			}
		}
		
		//-----------------------------------------
		// Got any?
		//-----------------------------------------
		
		if( !count($_likes) OR !is_array($_likes) )
		{
			return $this->viewFollowedContent( $this->lang->words['no_likes_for_del'] );
		}
		
		//-----------------------------------------
		// Get like helper class
		//-----------------------------------------
		
		$bootstraps		= array();
		
		require_once( IPS_ROOT_PATH . 'sources/classes/like/composite.php' );/*noLibHook*/
		
		//-----------------------------------------
		// Loop over and moderate
		//-----------------------------------------
		
		foreach( $_likes as $_like )
		{
			$_bootstrap		= classes_like::bootstrap( $_like['app'], $_like['area'] );
			$_likeKey		= classes_like_registry::getKey( $_like['id'], $this->memberData['member_id'] );
			$_frequencies	= $_bootstrap->allowedFrequencies();

			//-----------------------------------------
			// What action to take?
			//-----------------------------------------
			
			switch( $this->request['modaction'] )
			{
				case 'delete':
					$_bootstrap->remove( $_like['id'], $this->memberData['member_id'] );
				break;

				case 'change-donotify':
					$this->DB->update( 'core_like', array( 'like_notify_do' => 1, 'like_notify_freq' => 'immediate' ), "like_id='" . addslashes($_likeKey) . "'" );
				break;

				case 'change-donotnotify':
					$this->DB->update( 'core_like', array( 'like_notify_do' => 0 ), "like_id='" . addslashes($_likeKey) . "'" );
				break;

				case 'change-immediate':
					if( in_array( 'immediate', $_frequencies ) )
					{
						$this->DB->update( 'core_like', array( 'like_notify_do' => 1, 'like_notify_freq' => 'immediate' ), "like_id='" . addslashes($_likeKey) . "'" );
					}
				break;

				case 'change-offline':
					if( in_array( 'offline', $_frequencies ) )
					{
						$this->DB->update( 'core_like', array( 'like_notify_do' => 1, 'like_notify_freq' => 'offline' ), "like_id='" . addslashes($_likeKey) . "'" );
					}
				break;
				
				case 'change-daily':
					if( in_array( 'daily', $_frequencies ) )
					{
						$this->DB->update( 'core_like', array( 'like_notify_do' => 1, 'like_notify_freq' => 'daily' ), "like_id='" . addslashes($_likeKey) . "'" );
					}
				break;
				
				case 'change-weekly':
					if( in_array( 'weekly', $_frequencies ) )
					{
						$this->DB->update( 'core_like', array( 'like_notify_do' => 1, 'like_notify_freq' => 'weekly' ), "like_id='" . addslashes($_likeKey) . "'" );
					}
				break;

				case 'change-anon':
					$this->DB->update( 'core_like', array( 'like_is_anon' => 1 ), "like_id='" . addslashes($_likeKey) . "'" );
				break;

				case 'change-noanon':
					$this->DB->update( 'core_like', array( 'like_is_anon' => 0 ), "like_id='" . addslashes($_likeKey) . "'" );
				break;
			}
		}

		$this->registry->getClass('output')->silentRedirect( $this->settings['base_url']."app=core&amp;module=search&amp;do=followed&amp;search_app={$this->request['search_app']}&amp;contentType={$this->request['contentType']}&amp;confirm=1" );
	}

	/**
	 * View content you are following
	 *
	 * @author	Brandon Farber
	 * @param	string	$error	Error message
	 * @return	@e void
	 */
	public function viewFollowedContent( $error='' )
	{
		IPSSearchRegistry::set( 'in.search_app', $this->request['search_app'] );
		IPSSearchRegistry::set( 'opt.searchType', 'titles' );
		IPSSearchRegistry::set( 'opt.noPostPreview', true );
		
		$results	= array();
		$formatted	= array();
		$count		= 0;

		//-----------------------------------------
		// Determine content type
		//-----------------------------------------
		
		$contentTypes	= IPSSearchRegistry::get('config.followContentTypes');

		//-----------------------------------------
		// Verify likes are available
		//-----------------------------------------
		
		if( count( IPSLib::getEnabledApplications('like') ) AND count( $contentTypes ) )
		{
			//-----------------------------------------
			// What content type?
			//-----------------------------------------
			
			$_type	= '';
			
			if( $this->request['contentType'] AND in_array( $this->request['contentType'], $contentTypes ) )
			{
				$_type	= $this->request['contentType'];
			}
			else
			{
				$_type	= $contentTypes[0];
			}
			
			$this->request['contentType']	= $_type;
			
			IPSSearchRegistry::set( 'in.followContentType', $this->request['contentType'] );

			//-----------------------------------------
			// Get like helper class
			//-----------------------------------------
			
			require_once( IPS_ROOT_PATH . 'sources/classes/like/composite.php' );/*noLibHook*/

			//-----------------------------------------
			// Get list of items user likes
			//-----------------------------------------

			$count	= $this->DB->buildAndFetch( array( 'select' => 'COUNT(*) as er',
											  		  'from'   => 'core_like',
											  		  'where'  => 'like_member_id=' . $this->memberData['member_id'] . " AND like_visible=1 AND like_app='" . IPSSearchRegistry::get('in.search_app') . "' AND like_area='" . $_type . "'" ) );
			$count	= $count['er'];
			
			$this->DB->build( array( 'select' => '*',
									 'from'   => 'core_like',
									 'where'  => 'like_member_id=' . $this->memberData['member_id'] . " AND like_visible=1 AND like_app='" . IPSSearchRegistry::get('in.search_app') . "' AND like_area='" . $_type . "'",
									 'limit'  => array( IPSSearchRegistry::get('in.start'), IPSSearchRegistry::get('opt.search_per_page') ),
									 'order'  => 'like_added DESC' ) );
			$outer	= $this->DB->execute();
			
			while( $r = $this->DB->fetch($outer) )
			{
				$results[]	= $r['like_rel_id'];

				$formatted[ $r['like_id'] ]	= $r;
			}

			/* Process */
			if( IPSSearchRegistry::get('in.followContentType') == 'topics' )
				$results = $this->searchController->fetchFollowedContentOutput( $results, $formatted );
			else
			{
			    $forums		= array();
				
				if( count($results) )
				{
					/* Get forum data */
					foreach( $results as $result )
					{
						$forums[ $result ]	= $this->registry->class_forums->forumsFormatLastinfo( $this->registry->class_forums->forumsCalcChildren( $result, $this->registry->class_forums->getForumById( $result ) ) );
					}
				}
				
				$results = $forums;
			}
		}
		else
		{
			$count		= 0;
			$results	= array();
		}
		
		$this->mobi_result = array(
			'total_item_num' => $count,
    		'items' => $results,
		);
	}
	
	/**
	 * Builds the advanced search form
	 *
	 * @param	string	Message
	 * @return	@e void
	 */
	public function searchAdvancedForm( $msg='', $removed_search_terms=array() )
	{
		/* Set up data */
		IPSSearchRegistry::set('view.search_form', true );
		
		/* Get any application specific filters */
		$appHtml   = $this->searchController->getHtml();
		$isBoolean = $this->searchController->isBoolean();
		$canTag = ( $this->settings['tags_enabled'] && IPSSearchRegistry::get( 'config.can_searchTags' ) ) ? true : false;
		
		/* Output */
		$this->title   = $this->lang->words['search_form'];
		$this->registry->output->addNavigation( $this->lang->words['search_form'], '' );
		$this->output .= $this->registry->output->getTemplate( 'search' )->searchAdvancedForm( $appHtml, $msg, $this->request['search_app'], $removed_search_terms, $isBoolean, $canTag );
	}
	
	/**
	 * Processes a search request
	 *
	 * @return	@e void
	 */
	public function searchResults()
	{
		/* Search Term */
		$_st          = $this->searchController->formatSearchTerm( $this->request['search_term'] );
		$search_term  = $_st['search_term'];
		$removedTerms = $_st['removed'];
		/* Set up some defaults */
		$this->settings['max_search_word'] = $this->settings['max_search_word'] ? $this->settings['max_search_word'] : 300;
		/* Did we come in off a post request? */
		if ( $this->request['request_method'] == 'post' )
		{
			/* Set a no-expires header */
			$this->registry->getClass('output')->setCacheExpirationSeconds( 30 * 60 );
		}
		
		/* App specific */
		if ( isset( $this->request['search_sort_by_' . $this->request['search_app'] ] ) )
		{
			$this->request['search_sort_by']    = ( $_POST[ 'search_sort_by_' . $this->request['search_app'] ] ) ? $_POST[ 'search_sort_by_' . $this->request['search_app'] ] : $this->request['search_sort_by_' . $this->request['search_app'] ];
			$this->request['search_sort_order'] = ( $_POST[ 'search_sort_order_' . $this->request['search_app'] ] ) ? $_POST[ 'search_sort_order_' . $this->request['search_app'] ] : $this->request['search_sort_order_' . $this->request['search_app'] ];
		}
		
		/* Populate the registry */
		IPSSearchRegistry::set('in.search_app'		 , $this->request['search_app'] );
		IPSSearchRegistry::set('in.raw_search_term'  , trim( $this->request['search_term'] ) );
		IPSSearchRegistry::set('in.clean_search_term', $search_term );
		IPSSearchRegistry::set('in.raw_search_tags'  , trim( IPSText::parseCleanValue( urldecode( $_REQUEST['search_tags'] ) ) ) );
		IPSSearchRegistry::set('in.search_higlight'  , str_replace( '.', '', $this->request['search_term'] ) );
		IPSSearchRegistry::set('in.search_date_end'  , ( $this->request['search_date_start'] && $this->request['search_date_end'] )  ? $this->request['search_date_end'] : 'now' );
		IPSSearchRegistry::set('in.search_date_start', ( $this->request['search_date_start']  )  ? $this->request['search_date_start'] : '' );
		IPSSearchRegistry::set('in.search_author'    , !empty( $this->request['search_author'] ) ? $this->request['search_author'] : '' );
		
		/* Set sort filters */
		$this->_setSortFilters();
		
		/* These can be overridden in the actual engine scripts */
	//	IPSSearchRegistry::set('set.hardLimit'        , 0 );
		IPSSearchRegistry::set('set.resultsCutToLimit', false );
		IPSSearchRegistry::set('set.resultsAsForum'   , false );
		
		/* Are we option to show titles only / search in titles only */
		IPSSearchRegistry::set('opt.searchType', ( !empty( $this->request['search_content'] ) AND in_array( $this->request['search_content'], array( 'both', 'titles', 'content' ) ) ) ? $this->request['search_content'] : 'both' );
		
		/* Time check */
		if ( IPSSearchRegistry::get('in.search_date_start') AND strtotime( IPSSearchRegistry::get('in.search_date_start') ) > time() )
		{
			IPSSearchRegistry::set('in.search_date_start', 'now' );
		}
		
		if ( IPSSearchRegistry::get('in.search_date_end') AND strtotime( IPSSearchRegistry::get('in.search_date_end') ) > time() )
		{
			IPSSearchRegistry::set('in.search_date_end', 'now' );
		}
		
		/* Do some date checking */
		if( IPSSearchRegistry::get('in.search_date_end') AND IPSSearchRegistry::get('in.search_date_start') AND strtotime( IPSSearchRegistry::get('in.search_date_start') ) > strtotime( IPSSearchRegistry::get('in.search_date_end') ) )
		{
			$this->searchAdvancedForm( $this->lang->words['search_invalid_date_range'] );
			return;	
		}
		
		/**
		 * Lower limit
		 */
		if ( $this->settings['min_search_word'] && ! IPSSearchRegistry::get('in.search_author') && ! IPSSearchRegistry::get('in.raw_search_tags') )
		{
			$_words	= explode( ' ', preg_replace( "#\"(.*?)\"#", '', $search_term ) );
			$_ok	= $search_term ? true : false;

			foreach( $_words as $_word )
			{
				$_word	= preg_replace( '#^\+(.+?)$#', "\\1", $_word );

				if( !$_word )
				{
					continue;
				}

				if( strlen( $_word ) < $this->settings['min_search_word'] )
				{
					$_ok	= false;
					break;
				}
			}

			if( !$_ok )
			{
				$this->searchAdvancedForm( sprintf( $this->lang->words['search_term_short'], $this->settings['min_search_word'] ), $removedTerms );
				return;
			}
		}	
		
		/**
		 * Ok this is an upper limit.
		 * If you needed to change this, you could do so via conf_global.php by adding:
		 * $INFO['max_search_word'] = #####;
		 */
		if ( $this->settings['max_search_word'] && strlen( IPSSearchRegistry::get('in.raw_search_term') ) > $this->settings['max_search_word'] )
		{
			$this->searchAdvancedForm( sprintf( $this->lang->words['search_term_long'], $this->settings['max_search_word'] ) );
			return;
		}
		
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
				if( ( time() - $last_search ) <= $this->memberData['g_search_flood'] && $last_term != IPSSearchRegistry::get('in.raw_search_term') )
				{
					$this->searchAdvancedForm( sprintf( $this->lang->words['xml_flood'], $this->memberData['g_search_flood'] ) );
					return;					
				}
				else
				{
					/* Reset the cookie */
					IPSCookie::set( 'sfc', time() );
					IPSCookie::set( 'sfct', urlencode( IPSSearchRegistry::get('in.raw_search_term') ) );
				}
			}
			/* Set the cookie */
			else
			{
				IPSCookie::set( 'sfc', time() );
				IPSCookie::set( 'sfct', urlencode( IPSSearchRegistry::get('in.raw_search_term') ) );
			}
		}
		
		/* Clean search term for results view */
		$_search_term = trim( preg_replace( '#(^|\s)(\+|\-|\||\~)#', " ", $search_term ) );
		
		/* Got tag search only but app doesn't support tags */
		if ( IPSSearchRegistry::get('in.raw_search_tags') && ! IPSSearchRegistry::get( 'config.can_searchTags' ) && ! IPSSearchRegistry::get('in.raw_search_term') )
		{
			$count   = 0;
			$results = array();
		}
		else if ( IPSLib::appIsSearchable( IPSSearchRegistry::get('in.search_app'), 'search' ) )
		{
			/* Perform the search */
			$this->searchController->search();
			
			/* Get count */
			$count = $this->searchController->getResultCount();
			
			/* Get results which will be array of IDs */
			$results = $this->searchController->getResultSet();
		}
		else
		{
			$count   = 0;
			$results = array();
		}
		$this->mobi_result = array(
    		'total_topic_num' => $count,
    		'list'			  => $results,
		);
	}
	
	/**
	 * Starts session
	 * Loads / creates a session based on activity
	 *
	 * @return
	 */
	protected function _startSession()
	{
		$session_id  = IPSText::md5Clean( $this->request['sid'] );
		$requestType = ( $this->request['request_method'] == 'post' ) ? 'post' : 'get';
		
		if ( $session_id )
		{
			/* We check on member id 'cos we can. Obviously guests will have a member ID of zero, but meh */
			$this->_session = $this->DB->buildAndFetch( array( 'select' => '*',
															   'from'   => 'search_sessions',
															   'where'  => 'session_id=\'' . $session_id . '\' AND session_member_id=' . $this->memberData['member_id'] ) );
		}
		
		/* Deflate */
		if ( $this->_session['session_id'] )
		{
			if ( $this->_session['session_data'] )
			{
				$this->_session['_session_data'] = unserialize( $this->_session['session_data'] );
				
				if ( isset( $this->_session['_session_data']['search_app_filters'] ) )
				{
					$this->request['search_app_filters'] = is_array( $this->request['search_app_filters'] ) ? array_merge( $this->_session['_session_data']['search_app_filters'], $this->request['search_app_filters'] ) : $this->_session['_session_data']['search_app_filters'];
				}
			}
			
			IPSDebug::addMessage( "Loaded search session: <pre>" . var_export( $this->_session['_session_data'], true ) . "</pre>" );
		}
		else
		{
			/* Create a session */
			$this->_session = array( 'session_id'        => md5( uniqid( microtime(), true ) ),
									 'session_created'   => time(),
									 'session_updated'   => time(),
									 'session_member_id' => $this->memberData['member_id'],
									 'session_data'      => serialize( array( 'search_app_filters' => $this->request['search_app_filters'] ) ) );
									 
			$this->DB->insert( 'search_sessions', $this->_session );
			
			$this->_session['_session_data']['search_app_filters'] = $this->request['search_app_filters'];
			
			IPSDebug::addMessage( "Created search session: <pre>" . var_export( $this->_session['_session_data'], true ) . "</pre>" );
		}
		
		/* Do we have POST infos? */
		if ( isset( $_POST['search_app_filters'] ) )
		{
			$this->_session['_session_data']['search_app_filters'] = ( is_array( $this->_session['_session_data']['search_app_filters'] ) ) ? IPSLib::arrayMergeRecursive( $this->_session['_session_data']['search_app_filters'], $_POST['search_app_filters'] ) : $_POST['search_app_filters'];
			$this->request['search_app_filters']                   = $this->_session['_session_data']['search_app_filters'];
			
			IPSDebug::addMessage( "Updated filters: <pre>" . var_export( $_POST['search_app_filters'], true ) . "</pre>" );
		}
		
		/* Globalize the session ID */
		$this->request['_sid'] = $this->_session['session_id'];
	}
	
	/**
	 * End the session
	 *
	 */
	protected function _endSession()
	{
		if ( $this->_session['session_id'] )
		{
			$sd = array( 'session_updated'   => time(),
						 'session_data'      => serialize( $this->_session['_session_data'] ) );
						 
			$this->DB->update( 'search_sessions', $sd, 'session_id=\'' . $this->_session['session_id'] . '\'' );
		}
		
		/* Delete old sessions */
		$this->DB->delete( 'search_sessions', 'session_updated < ' . ( time() - 86400 ) );
	}
	
	/**
	 * Set the search order and key
	 *
	 * @return	@e void
	 */
	protected function _setSortFilters()
	{
		$app = $this->request['search_app'];
		$key = 'date';
		$dir = 'desc';
		$dun = false;
		
		/* multi search in options? */
		if ( isset( $this->request['search_app_filters'][ $app ]['searchInKey'] ) )
		{
			$_k = $this->request['search_app_filters'][ $app ]['searchInKey'];
			
			if ( isset( $this->request['search_app_filters'][ $app ][ $_k ]['sortKey'] ) )
			{
				$dun = true;
				$key = $this->request['search_app_filters'][ $app ][ $_k ]['sortKey'];
				$dir = $this->request['search_app_filters'][ $app ][ $_k ]['sortDir'];
			}
		}
		
		/* Normal options - although sometimes used even with multiple types */
		if ( ! $dun AND isset( $this->request['search_app_filters'][$app]['sortKey'] ) )
		{
			$key = $this->request['search_app_filters'][$app]['sortKey'];
			$dir = $this->request['search_app_filters'][$app]['sortDir'];
		}
		/* Global */
		else
		{
			if ( isset( $this->request['search_sort_by'] ) )
			{
				$key = $this->request['search_sort_by'];
				$dir = $this->request['search_sort_order'];
			}
		}
		
		/* Numeric? */
		if ( is_numeric( $dir ) )
		{
			$dir = ( $dir == 0 ) ? 'desc' : 'asc';
		}
		else
		{
			$dir = 'desc';
		}
		
		IPSSearchRegistry::set('in.search_sort_by'   , trim( $key ) );
		IPSSearchRegistry::set('in.search_sort_order', ( $dir != 'desc' ) ? 'asc' : 'desc' );
	}
	
	/**
	 * Displays latest user content
	 *
	 * @return	@e void
	 */
	public function viewUserContent()
	{
		/* INIT */
		$id 	    = $this->request['mid'] ? intval( trim( $this->request['mid'] ) ) : $this->memberData['member_id'];
		/* Save query if we are viewing our own content */
		if( $this->memberData['member_id'] AND $id == $this->memberData['member_id'] )
		{
			$member	= $this->memberData;
		}
		else
		{
			$member	    = IPSMember::load( $id, 'core' );
		}
		
		$beginStamp = 0;
		
		if ( ! $member['member_id'] )
		{
			$this->registry->output->showError( 'search_invalid_id', 10147, null, null, 403 );
		}
		
		$this->request['userMode']	= !empty($this->request['userMode']) ? $this->request['userMode'] : 'all';
		
		IPSSearchRegistry::set('in.search_app', $this->request['search_app'] );
		IPSSearchRegistry::set('in.userMode'  , $this->request['userMode'] );

		/* Set sort filters */
		$this->_setSortFilters();
		
		/* Can we do this? */
		if ( IPSLib::appIsSearchable( IPSSearchRegistry::get('in.search_app'), 'usercontent' ) )
		{
			/* Perform the search */
			$this->searchController->viewUserContent( $member );
			
			/* Get count */
			$count = $this->searchController->getResultCount();
			
			/* Get results which will be array of IDs */
			$results = $this->searchController->getResultSet();
		}
		else
		{
			$count   = 0;
			$results = array();
		}
		
		$this->mobi_result = array(
			'total_topic_num' => $count,
    		'list' => $results,
		);
	}
	
	/**
	 * View new posts since your last visit
	 *
	 * @return	@e void
	 */
	public function viewNewContent()
	{	
		IPSSearchRegistry::set('in.search_app', $this->request['search_app'] );
		
		/* Fetch member cache to see if we have a value set */
		$vncPrefs = IPSMember::getFromMemberCache( $this->memberData, 'vncPrefs' );

		/* In period */
		if ( $vncPrefs === null OR ! isset( $vncPrefs[ IPSSearchRegistry::get('in.search_app') ]['view'] ) OR isset( $this->request['period'] ) )
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
		if ( isset( $this->request['period'] ) )
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
		}
		else
		{
			$count   = 0;
			$results = array();
		}
		
		$this->mobi_result = array(
			'total_topic_num' => $count,
    		'list' => $results,
		);
	}
	

	/**
	 * Returns a url string that will maintain search results via links
	 *
	 * @return	string
	 */
	protected function _buildURLString()
	{
		/* INI */
		$url_string  = 'app=core&amp;module=search&amp;do=search&amp;andor_type=' . $this->request['andor_type'];
		$url_string .= '&amp;sid=' . $this->request['_sid'];
		
		/* Add author name */
		if( !empty( $this->request['search_author'] ) )
		{
			$url_string .= "&amp;search_author=" . urlencode($this->request['search_author']);
		}

		/* Search Range */
		if( !empty( $this->request['search_date_start'] ) )
		{
			$url_string .= "&amp;search_date_start={$this->request['search_date_start']}";
		}
		
		if( !empty( $this->request['search_date_end'] ) )
		{
			$url_string .= "&amp;search_date_end={$this->request['search_date_end']}";
		}

		if( !empty( $this->request['search_app_filters'][ $this->request['search_app'] ]['sortKey'] ) )
		{
			$url_string .= "&amp;search_app_filters[{$this->request['search_app']}][sortKey]=" . $this->request['search_app_filters'][ $this->request['search_app'] ]['sortKey'];
		}

		if( !empty( $this->request['search_app_filters'][ $this->request['search_app'] ]['sortDir'] ) )
		{
			$url_string .= "&amp;search_app_filters[{$this->request['search_app']}][sortDir]=" . $this->request['search_app_filters'][ $this->request['search_app'] ]['sortDir'];
		}
		
		/* Contextual Type */
		if ( IPSSearchRegistry::get('contextual.type') )
		{
			$url_string .= "&amp;cType=" . IPSSearchRegistry::get('contextual.type') . "&amp;cId=" . IPSSearchRegistry::get('contextual.id');
		}
		
		/* Content Only */
		if( !empty( $this->request['search_content'] ) )
		{
			$url_string .= "&amp;search_content=" . $this->request['search_content'];
		}
		
		if ( IPSSearchRegistry::get('in.raw_search_tags') )
		{
			$url_string .= "&amp;search_tags=" . urlencode( IPSSearchRegistry::get('in.raw_search_tags') );
		}
		
		/* Types */
		if( isset( $this->request['type'] ) && isset( $this->request['type_id'] ) )
		{
			$url_string .= "&amp;type={$this->request['type']}&amp;type_id={$this->request['type_id']}";
		}
		
		if( isset( $this->request['type_2'] ) && isset( $this->request['type_id_2'] ) )
		{
			$url_string .= "&amp;type_2={$this->request['type_2']}&amp;type_id_2={$this->request['type_id_2']}";
		}
		
		if( isset($this->request['search_app_filters']) AND $this->request['search_app_filters'] )
		{
			foreach( $this->request['search_app_filters'] as $app => $filters )
			{
				if( $app == $this->request['search_app'] AND count($filters) )
				{
					foreach( $filters as $_filterKey => $_filterValue )
					{
						if( is_array($_filterValue) )
						{
							foreach( $_filterValue as $_filterValueKey => $_filterValueValue )
							{
								$url_string .= "&amp;search_app_filters[{$app}][{$_filterKey}][{$_filterValueKey}]={$_filterValueValue}";
							}
						}
						else
						{
							$url_string .= "&amp;search_app_filters[{$app}][{$_filterKey}]={$_filterValue}";
						}
					}
				}
			}
		}
		
		/* Fix up the search term a bit */
		$_search_term = str_replace( '&amp;', '&', $this->request['search_term'] );
		$_search_term = str_replace( '&quot;', '"', $_search_term );
		$_search_term = str_replace( '&gt;', '>', $_search_term );
		$_search_term = str_replace( '&lt;', '<', $_search_term );
		$_search_term = str_replace( '&#036;', '$', $_search_term );

		$url_string .= '&amp;search_term=' . urlencode( $_search_term );

		return $url_string;		
	}
	
	/**
	 * Checks to see if the logged in user is allowed to use the search system
	 *
	 * @return	@e void
	 */
	protected function _canSearch()
	{
		/* Check the search setting */
		if( ! $this->settings['allow_search'] )
		{
		    $msg = ( isset($this->lang->words['search_off']) ) ? $this->lang->words['search_off'] : 'search_off';
		    get_error($msg);
		}
		
		/* Check the member authorization */
		if( ! isset( $this->memberData['g_use_search'] ) || ! $this->memberData['g_use_search'] )
		{
		    $msg = ( isset($this->lang->words['no_permission_to_search']) ) ? $this->lang->words['no_permission_to_search'] : 'no_permission_to_search';
		    get_error($msg);
		}
	}
	
	/**
	 * Resets params for template
	 */
	protected function _resetRequestParameters()
	{
		$this->request['period']			= IPSSearchRegistry::get('in.period');
		$this->request['search_app']		= IPSSearchRegistry::get('in.search_app');
		$this->request['vncFollowFilterOn']	= IPSSearchRegistry::get('in.vncFollowFilterOn');
		$this->request['followedItemsOnly']	= IPSSearchRegistry::get('in.vncFollowFilterOn');
	}
	 
}

class mobi_IPSSearch extends IPSSearch
{
	/**#@+
	 * Registry Object Shortcuts
	 *
	 * @var		object
	 */
	protected $registry;
	protected $DB;
	protected $settings;
	protected $request;
	protected $lang;
	protected $member;
	protected $memberData;
	protected $cache;
	protected $caches;
	/**#@-*/
	
	/**
	 * Plug in for search
	 * 
	 * @var		object
	 */
	protected $SEARCH;
	
	/**
	 * Plug in for formatting results
	 * 
	 * @var		object
	 */
	protected $FORMAT;
	
	/**
	 * App
	 * 
	 * @var		string
	 */
	protected $_app;
	
	/**
	 * Engine
	 * 
	 * @var		string
	 */
	protected $_engine;
	
	/**
	 * Result count
	 *
	 * @param	int
	 */
	protected $_count;
	
	/**
	 * Result array
	 *
	 * @param	array
	 */
	protected $_results;
	
	static public $aso;
	static public $ask;
	static public $ast;
	
	/**
	 * Setup registry objects
	 *
	 * @param	object	ipsRegistry $registry
	 * @return	@e void
	 */
	public function __construct( ipsRegistry $registry, $engine, $app )
	{
		/* Make object */
		$this->registry   =  $registry;
		$this->DB         =  $this->registry->DB();
		$this->settings   =& $this->registry->fetchSettings();
		$this->request    =& $this->registry->fetchRequest();
		$this->lang       =  $this->registry->getClass('class_localization');
		$this->member     =  $this->registry->member();
		$this->memberData =& $this->registry->member()->fetchMemberData();
		$this->cache      =  $this->registry->cache();
		$this->caches     =& $this->registry->cache()->fetchCaches();
		
		/* Set engine */
		$this->_engine    = strtolower( IPSText::alphanumericalClean( $engine ) );
		
		/* Set app */
		$this->_app       = IPSText::alphanumericalClean( $app );
		
		/* Quick check */
		if ( ! is_file( IPS_ROOT_PATH . 'sources/classes/search/engines/' . $this->_engine . '.php' ) )
		{
			/* Try SQL */
			if ( ! $this->_engine != 'sql' )
			{
				$this->_engine = 'sql';
				
				if ( ! is_file( IPS_ROOT_PATH . 'sources/classes/search/engines/' . $this->_engine . '.php' ) )
				{
					throw new Exception( "NO_SUCH_ENGINE" );
				}
			}
			else
			{
				throw new Exception( "NO_SUCH_ENGINE" );
			}
		}
		
		if ( ! isset( ipsRegistry::$applications[ $this->_app ] ) )
		{
			throw new Exception( "NO_SUCH_APP" );
		}
		
		/* Set in registry */
		IPSSearchRegistry::set( 'global.engine', $this->_engine );
		IPSSearchRegistry::set( 'global.app'   , $this->_app );
		
		/* Load up the relevant engines */
		require_once( IPS_ROOT_PATH . 'sources/classes/search/format.php' );/*noLibHook*/
		
		/* Got an app specific file? Lets hope so */
		if ( is_file( IPSLib::getAppDir( $this->_app ) . '/extensions/search/format.php' ) )
		{
			/* We may not have sphinx specific stuff, so... */
			if ( ! is_file( IPSLib::getAppDir( $this->_app ) . '/extensions/search/engines/' . $this->_engine . '.php' ) )
			{
				$this->_engine = 'sql';
				
				if ( ! is_file( IPSLib::getAppDir( $this->_app ) . '/extensions/search/engines/' . $this->_engine . '.php' ) )
				{
					throw new Exception( "NO_SUCH_APP_ENGINE" );
				}
			}
			
			/* SEARCH file */
			require_once( IPS_ROOT_PATH . 'sources/classes/search/engines/' . $this->_engine . '.php' );/*noLibHook*/
			$classToLoad  = IPSLib::loadLibrary( IPSLib::getAppDir( $this->_app ) . '/extensions/search/engines/' . $this->_engine . '.php', 'search_engine_' . $this->_app, $this->_app );
			
			$current_dir = dirname(dirname(dirname(__FILE__)));
			chdir('../');
			$this->SEARCH = new $classToLoad( $registry );
			chdir($current_dir);
			
			/* FORMAT file */
			$classToLoad  = IPSLib::loadLibrary( IPSLib::getAppDir( $this->_app ) . '/extensions/search/format.php', 'search_format_' . $this->_app, $this->_app );
			$this->FORMAT = new $classToLoad( $registry );
			
			/* Grab config */
			$CONFIG = array();
			require( IPSLib::getAppDir( $this->_app ) . '/extensions/search/config.php' );/*noLibHook*/
			
			if ( is_array( $CONFIG ) && count( $CONFIG ) )
			{
				foreach( $CONFIG as $k => $v )
				{
					IPSSearchRegistry::set( 'config.' . $k, $v );
				}
			}
		}
		else
		{
			throw new Exception( "NO_SUCH_APP_ENGINE" );
		}
		
		/* Multi content types */
		if ( IPSSearchRegistry::get( 'config.contentTypes' ) )
		{
			$c = IPSSearchRegistry::get( 'config.contentTypes' );

			if ( is_array( $c ) AND count( $c ) )
			{
				/* Set up default content type if supported */
				IPSSearchRegistry::set( $this->_app . '.searchInKey' , $c[0] );
				
				/* Filter specific search */
				if ( isset( $this->request['search_app_filters'][ $this->_app ]['searchInKey'] ) )
				{
					IPSSearchRegistry::set( $this->_app . '.searchInKey', $this->request['search_app_filters'][ $this->_app ]['searchInKey'] );
				}
			}
		}
	}
	
	/**
	 * Magic __call methods
	 * Aka too lazy to create a proper function
	 */
	public function __call( $funcName, $args )
	{
 		/* Output format stuff.. */
		switch ( $funcName )
		{
			case 'isBoolean':
				return $this->SEARCH->isBoolean();
			break;
			case 'formatSearchTerm':
				return $this->SEARCH->formatSearchTerm( $args[0] );
			break;
			case 'getResultCount':
				return $this->_count;
			break;
			case 'getResultSet':
				return $this->_results;
			break;
			case 'fetchTemplates':
				return $this->FORMAT->fetchTemplates();
			break;
			case 'fetchSortDropDown':
				return $this->SEARCH->fetchSortDropDown();
			break;
			
			/* Primarily shortcuts for 'Content I follow' */
			case 'fetchFollowedContentOutput':
				IPSSearchRegistry::set('set.returnType', 'tids' );
				return $this->FORMAT->processResults( $args[0], $args[1] );
			break;
		}
 	}
 	
 	/**
	 * Generic: Return sort drop down
	 * 
	 * @param	string	App
	 * @return	array
	 */
	public function fetchSortDropDown( $app='' )
	{
		$app = ( $app ) ? $app : $this->_app;
		
		/* results page? */
		$filter = ( ! IPSSearchRegistry::get('view.search_form') AND IPSSearchRegistry::get( $app . '.searchInKey' )	) ? IPSSearchRegistry::get( $app . '.searchInKey' ) : '';
		
		if ( is_file( IPSLib::getAppDir( $app ) . '/extensions/search/form.php' ) )
		{
			$classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( $app ) . '/extensions/search/form.php', 'search_form_' . $app, $app );
			
			if ( class_exists( $classToLoad ) )
			{
				$_obj = new $classToLoad();
				$_dd  = $_obj->fetchSortDropDown();
				
				if ( $filter )
				{
					return $_dd[ $filter ];
				}
				else
				{
					return $_dd;
				}
			}
		}
		
		return array( 'date' => $this->lang->words['s_search_type_0'] );
	}
	
	/**
	 * Generic: Return sort in
	 * 
	 * @param	string	[App]
	 * @return	array
	 */
	public function fetchSortIn( $app='' )
	{
		$app = ( $app ) ? $app : $this->_app;
		
		if ( is_file( IPSLib::getAppDir( $app ) . '/extensions/search/form.php' ) )
		{
			$classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( $app ) . '/extensions/search/form.php', 'search_form_' . $app, $app );
			
			if ( class_exists( $classToLoad ) )
			{
				$_obj = new $classToLoad();
				
				if ( method_exists( $_obj, 'fetchSortIn' ) )
				{			
					return $_obj->fetchSortIn();
				}
			}
		}
		
		return FALSE;
	}
 	
	/**
	 * Returns boxes for the search form
	 *
	 * @param	boolean		Grab all apps or just the current one
	 * @return	array
	 */	
	public function getHtml( $allApps = TRUE )
	{
		/* INIT */
		$filtersHtml = '';
		
		/* Loop through apps */		
		foreach( ipsRegistry::$applications as $app )
		{
			/* Not all? */
			if ( ! $allApps and $app['app_directory'] != $this->_app )
			{
				continue;
			}
			
			if( IPSLib::appIsSearchable( $app['app_directory'] ) )
			{
				/* got custom filter? */
				if ( is_file( IPSLib::getAppDir( $app['app_directory'] ) . '/extensions/search/form.php' ) )
				{
					$classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( $app['app_directory'] ) . '/extensions/search/form.php', 'search_form_' . $app['app_directory'], $app['app_directory'] );
					
					if ( class_exists( $classToLoad ) and method_exists( $classToLoad, 'getHtml' ) )
					{
						$_obj = new $classToLoad();
						
						$filtersHtml[ $app['app_directory'] ] = $_obj->getHtml();
					}
					else
					{
						$filtersHtml[ $app['app_directory'] ] = array( 'title' => IPSLib::getAppTitle( $app['app_directory'] ), 'html' => '' );
					}
				}
				else
				{
					$filtersHtml[ $app['app_directory'] ] = array( 'title' => IPSLib::getAppTitle( $app['app_directory'] ), 'html' => '' );
				}
				
				$filtersHtml[ $app['app_directory'] ]['sortDropIn']   = $this->fetchSortIn( $app['app_directory'] );
				$filtersHtml[ $app['app_directory'] ]['sortDropDown'] = $this->fetchSortDropDown( $app['app_directory'] );
			}
		}
	
		return $filtersHtml;
	}
	
	/**
	 * Perform the search
	 * Populates $this->_count and $this->_results
	 *
	 * @return	nothin'
	 */		
	public function search()
	{
		$APP        = IPSSearchRegistry::get('in.search_app');
		$filterData = $this->request['search_app_filters'][ $APP ];
		
		/* Check for an author filter */
		if ( IPSSearchRegistry::get('in.search_author') )
		{
			/* Query the member id */
			$mem = $this->DB->buildAndFetch( array( 'select' => 'member_id', 
													'from'   => 'members', 
													'where'  => "members_display_name='" . $this->DB->addSlashes( IPSSearchRegistry::get('in.search_author') ) . "'"  )	 );
			
			IPSSearchRegistry::set('opt.searchAuthor', true );
			
			/* Add the condition to our search */
			$this->SEARCH->setCondition( 'member_id', '=', $mem['member_id'] ? $mem['member_id'] : -1 );
		}

		/* Check for application specific filters - just need to do active app here */
		$filterData = $this->SEARCH->buildFilterSQL( $filterData );

		if( $filterData )
		{
			if ( isset( $filterData[0] ) )
			{
				foreach( $filterData as $_data )
				{
					$this->SEARCH->setCondition( $_data['column'], $_data['operator'], $_data['value'], 'AND' );
				}
			}
			else
			{
				$this->SEARCH->setCondition( $filterData['column'], $filterData['operator'], $filterData['value'], 'OR' );
			}
		}
		
		/* Check Date Range */
		if( IPSSearchRegistry::get('in.search_date_start') || IPSSearchRegistry::get('in.search_date_end') )
		{
			/* Start Range Date */
			$search_date_start = 0;

			if( IPSSearchRegistry::get('in.search_date_start') )
			{
				$search_date_start = strtotime( IPSSearchRegistry::get('in.search_date_start') );
				/* Correct for timezone...hopefully */
				$search_date_start += abs( $this->registry->class_localization->getTimeOffset() );
			}

			/* End Range Date */
			$search_date_end = 0;

			if( IPSSearchRegistry::get('in.search_date_end') AND IPSSearchRegistry::get('in.search_date_end') != 'now' )
			{
				$search_date_end = strtotime( IPSSearchRegistry::get('in.search_date_end') );
				/* Correct for timezone...hopefully */
				$search_date_end   += abs( $this->registry->class_localization->getTimeOffset() );
			}
						
			/* If the times are exactly equaly, we're going to assume they are trying to search all posts from one day */
			if( ( $search_date_start && $search_date_end ) && $search_date_start == $search_date_end )
			{
				$search_date_end += 86400;
			}

			$this->SEARCH->setDateRange( $search_date_start, $search_date_end );
		}
		
		/* Init session */
		$processId = $this->_startSession();
		
		/* Run the search */
		$results = $this->SEARCH->search();
		
		/* Set data */
		$this->_count   = intval( $results['count'] );
		$this->_results = $results['resultSet'];
		
		/* Now format results */
		if ( count( $this->_results ) )
		{
			$results = $this->FORMAT->processResults( $this->_results );
			
			$topic_ids = array();
            foreach ($results as $rid => $result)
    		{
    		    $topic_ids[] = $result['tid'];
    		    
    			//#######################################
    			//get forum name 
    			//########################################	
    			$forum_id = $result['forum_id'];
    			$results[$rid]['forum_name'] = $this->registry->class_forums->forum_by_id[ $forum_id ]['name'];
    			
    			//-----------------------------------------
    			// Are we actually a moderator for this forum?
    			//-----------------------------------------
    			
//    			if ( ! $this->memberData['g_is_supmod'] )
//    			{
//    				$moderator = $this->memberData['forumsModeratorData'];
//    				
//    				if ( !isset($moderator[$forum_id]) OR !is_array( $moderator[$forum_id] ) )
//    				{
//    					$this->memberData['is_mod'] = 0;
//    				}
//    			}
        		
//        		$can_delete = 0;
//    			if ($this->memberData['is_mod'] == 1 and ($this->memberData['g_is_supmod'] == 1 || $this->memberData['forumsModeratorData'][$forum_id]['delete_topic'])) {
//    				$results[$rid]['can_delete'] = 1;
//    			}
    			
    			//######################################
    			//get icon_url
    			//######################################
        		$results[$rid]['icon_url'] = get_avatar($result['author_id']);
    			
    			//######################################
    		 	// get TOPIC CONTENT and shord content.....	
    		 	//######################################
    		 	$results[$rid]['short_content'] = get_short_content($result['post']);
    		 	
    		 	//######################################
    		 	// has new since last login????.....	
    		 	//######################################
    		 	$results[$rid]['has_new'] = ipsRegistry::getClass( 'classItemMarking')->isRead( array( 'forumID' => $forum_id, 
    																'itemID' => $result['tid'], 
    																'itemLastUpdate' => $result['last_post'] 
    																),  'forums' );
                
                // get subscribe infor
    		 	$results[$rid]['issubscribed'] = is_subscribed($result['tid']);
    		 	$results[$rid]['can_subscribe'] = $this->memberData['member_id'] ? true : false;
    		 	
    		 	// get post position
    		 	$this->DB->build( array( 'select' => 'COUNT(*) as posts', 'from' => 'posts', 'where' => "topic_id={$result['tid']} AND pid <=" . intval( $result['pid'] ) ) );
			    $this->DB->execute();
			    $cposts = $this->DB->fetch();
			    $results[$rid]['post_position'] = $cposts['posts'];
    		}

    		$this->_results = $results;
		}
		
		/* Kill session */
		$this->_endSession( $processId );
	}	
	
	/**
	 * Perform the search
	 * Populates $this->_count and $this->_results
	 *
	 * @return	nothin'
	 */
	public function viewNewContent()
	{
		IPSSearchRegistry::set('opt.searchTitleOnly', true);
		IPSSearchRegistry::set('in.period_in_seconds', false );
		
		/* Hard fix mobile app users to VNC based on ACP default VNC method */
		if ( $this->member->isMobileApp )
		{
			IPSSearchRegistry::set( 'in.period', $this->settings['default_vnc_method'] );
		}
		
		/* Do we have a period? */
		switch( IPSSearchRegistry::get('in.period') )
		{
			case 'today':
			default:
				$date	= 86400;		// 24 hours
			break;
			
			case 'week':
				$date	= 604800;		// 1 week
			break;
			
			case 'weeks':
				$date	= 1209600;		// 2 weeks
			break;
			
			case 'month':
				$date	= 2592000;		// 30 days
			break;
			
			case 'months':
				$date	= 15552000;		// 6 months
			break;
			
			case 'year':
				$date	= 31536000;		// 365 days
			break;
			case 'lastvisit':
				$date   = time() - intval( $this->memberData['last_visit'] );
			break;
			case 'unread':
				//$date   = false;
				$date = 2592000;
			break;
		}
		
		/* Set date up */
		IPSSearchRegistry::set('in.period_in_seconds', $date );
		
		/* Run the search */
		$results = $this->SEARCH->viewNewContent();
		
		/* Set data */
		$this->_count   = intval( $results['count'] );
		$this->_results = $results['resultSet'];
		
		/* Now format results */
		if ( count( $this->_results ) )
		{
			//$this->_results = $this->FORMAT->processResults( $this->_results );
			foreach($this->_results as $index => $tid)
			{
				$this->_results[$index] = $this->_topicPreview($tid);
			}

			/* Now generate HTML */
			//$this->_results = $this->FORMAT->parseAndFetchHtmlBlocks( $this->_results );
		}
	}
	
	/**
	 * Perform the search
	 * Populates $this->_count and $this->_results
	 *
	 * @return	nothin'
	 */
	public function viewUserContent( $member )
	{
		/* Run the search */
		$results = $this->SEARCH->viewUserContent( $member );
		
		/* Set data */
		$this->_count   = intval( $results['count'] );
		$this->_results = $results['resultSet'];
		
		/* Now format results */
		if ( count( $this->_results ) )
		{
			//$this->_results = $this->FORMAT->processResults( $this->_results );
			foreach($this->_results as $index => $tid)
			{
				$this->_results[$index] = $this->_topicPreview($tid);
			}

			/* Now generate HTML */
			//$this->_results = $this->FORMAT->parseAndFetchHtmlBlocks( $this->_results );
		}
	}
	
	protected function _topicPreview($tid, $pid = 0, $sTerm = '')
    {
        /* Load topic class */
        if ( ! $this->registry->isClassLoaded('topics') )
        {
            $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . "/sources/classes/topics.php", 'app_forums_classes_topics', 'forums' );
            $this->registry->setClass( 'topics', new $classToLoad( $this->registry ) );
        }
        
        $this->lang->loadLanguageFile( array( 'public_topic', 'public_mod' ), 'forums' );
        
        /* INIT */
        $topic            = array();
        $posts            = array();
        $permissions    = array();
        $query            = '';

        /* Topic visibility */
    
        $_perms = array( 'visible' );
        
        if ( $this->registry->getClass('class_forums')->canSeeSoftDeletedTopics( false ) )
        {
            $_perms[] = 'sdelete';
        }
        
        if ( $this->registry->getClass('class_forums')->canQueuePosts( false ) )
        {
            $_perms[] = 'hidden';
        }

        /* Grab topic data and first post */
        $topic = $this->DB->buildAndFetch( array( 'select'   => '*, title as topic_title, posts as topic_posts, last_post as topic_last_post',
                                                  'from'     => 'topics',
                                                  'where'    => $this->registry->class_forums->fetchTopicHiddenQuery( $_perms ) . ' AND tid=' . $tid ) );

//        /* Grab topic data and first post */
//        $topic = $this->DB->buildAndFetch( array( 'select'   => 't.*, t.title as topic_title, t.posts as topic_posts, t.last_post as topic_last_post',
//                                                  'from'     => array( 'topics' => 't' ),
//                                                  'where'    => $this->registry->class_forums->fetchTopicHiddenQuery( $_perms, 't.' ) . ' AND t.tid=' . $tid,
//                                                  'add_join' => array( array( 'select' => 'p.*',
//                                                                                'from'   => array( 'posts' => 'p' ),
//                                                                                'where'  => 'p.pid=t.topic_firstpost',
//                                                                                'type'   => 'left' ),
//                                                                         array( 'select' => 'm.*',
//                                                                                   'from'   => array( 'members' => 'm' ),
//                                                                                   'where'  => 'm.member_id=p.author_id',
//                                                                                   'type'   => 'left' ),
//                                                                         array( 'select' => 'pp.*',
//                                                                                   'from'   => array( 'profile_portal' => 'pp' ),
//                                                                                   'where'  => 'm.member_id=pp.pp_member_id',
//                                                                                   'type'   => 'left' ) ) ) );
        if ( ! $topic['tid'] )
        {
            return array();
            //return $this->returnString( 'no_topic' );
        }
        /* Permission check */
        if ( $this->registry->class_forums->forumsCheckAccess( $topic['forum_id'], 0, 'topic', $topic, true ) !== true )
        {
            return array();
            //return $this->returnString( 'no_permission' );
            //return $this->returnHtml( $this->registry->output->getTemplate('global_other')->ajaxPopUpNoPermission() );
        }
        
        /* Build permissions */
    
        $permissions['PostSoftDeleteSee']      = $this->registry->getClass('class_forums')->canSeeSoftDeletedPosts( $topic['forum_id'] );
        $permissions['SoftDeleteContent']      = $this->registry->getClass('class_forums')->canSeeSoftDeleteContent( $topic['forum_id'] );
        $permissions['TopicSoftDeleteSee']     = $this->registry->getClass('class_forums')->canSeeSoftDeletedTopics( $topic['forum_id'] );
        $permissions['canQueue']               = $this->registry->getClass('class_forums')->canQueuePosts( $topic['forum_id'] );
        
        /* Boring old boringness */
        if ( $permissions['canQueue'] )
        {
            if ( $permissions['PostSoftDeleteSee'] )
            {
                $query    = $this->registry->class_forums->fetchPostHiddenQuery(array('visible', 'hidden', 'sdeleted') ) . ' AND ';
            }
            else
            {
                $query    = $this->registry->class_forums->fetchPostHiddenQuery(array('visible', 'hidden') ) . ' AND ';
            }
        }
        else
        {
            if ( $permissions['PostSoftDeleteSee'] )
            {
                $query    = $this->registry->class_forums->fetchPostHiddenQuery(array('visible', 'sdeleted') ) . ' AND ';
            }
            else
            {
                $query    = $this->registry->class_forums->fetchPostHiddenQuery(array('visible') ) . ' AND ';
            }
        }
        
        
        /* Get last post */
        $_post = $this->registry->topics->getPosts( array( 'onlyViewable'    => true,
                                                           'sortField'         => 'pid',
                                                           'sortOrder'         => 'desc', // tapatalk change from asc to desc
                                                           'topicId'         => array( $topic['tid'] ),
                                                           'limit'             => 1,
                                                           'archiveToNative' => true,
                                                           /*'isArchivedTopic' => $this->registry->topics->isArchived( $topic )*/ ) );

        $posts['last'] = array_pop( $_post );
        
        /* Assign */
        //$posts['first'] = $topic;
        
        
        /* Any more for any more? */
//        if ( $topic['topic_posts'] )
//        {
//            /* Grab number of unread posts? */
//            $last_time = $this->registry->classItemMarking->fetchTimeLastMarked( array( 'forumID' => $topic['forum_id'], 'itemID' => $tid ), 'forums');
//            
//            if ( $last_time AND $last_time < $topic['topic_last_post'] )
//            {
//                $count = $this->DB->buildAndFetch( array( 'select' => 'COUNT(*) as count, MAX(pid) as max, MIN(pid) as min',
//                                                            'from'   => 'posts',
//                                                          'where'  => $query . "topic_id={$tid} AND post_date > " . intval( $last_time ) )    );
//            }
//            else
//            {
//                $count = $this->DB->buildAndFetch( array( 'select' => 'MAX(pid) as max',
//                                                            'from'   => 'posts',
//                                                          'where'  => $query . "topic_id={$tid}" ) );
//                $count['min']   = 0;
//                $count['count'] = 0;
//            }
//                                              
//            $topic['_lastRead']    = $last_time;
//            $topic['_unreadPosts'] = intval( $count['count'] );
//            
//            /* Got a max and min */
//            if ( $count['max'] )
//            {
//                $this->DB->build( array(  'select'   => 'p.*',
//                                          'from'     => array( 'posts' => 'p' ),
//                                          'where'    => 'p.pid IN (' . intval( $count['min'] ) . ',' . intval( $count['max'] ) . ')',
//                                          'add_join' => array( array( 'select' => 'm.*',
//                                                                           'from'   => array( 'members' => 'm' ),
//                                                                           'where'  => 'm.member_id=p.author_id',
//                                                                           'type'   => 'left' ),
//                                                                 array( 'select' => 'pp.*',
//                                                                           'from'   => array( 'profile_portal' => 'pp' ),
//                                                                           'where'  => 'm.member_id=pp.pp_member_id',
//                                                                           'type'   => 'left' ) ) ) );
//                
//                $this->DB->execute();
//                
//                while( $r = $this->DB->fetch() )
//                {
//                    $r['tid']        = $topic['tid'];
//                    $r['title_seo']    = $topic['title_seo'];
//                    
//                    if ( $r['pid'] == $count['max'] )
//                    {
//                        $posts['last'] = $r;
//                    }
//                    else
//                    {
//                        $posts['unread'] = $r;
//                    }
//                }
//            }
//            
//            if ( is_array( $posts['unread'] ) AND is_array( $posts['last'] ) )
//            {
//                if ( $posts['unread']['pid'] == $posts['last']['pid'] )
//                {
//                    unset( $posts['unread'] );
//                }
//                else if ( $posts['unread']['pid'] == $posts['first']['pid'] )
//                {
//                    unset( $posts['unread'] );
//                }
//                
//            }
//        }
        
        
        /* Search? */
        if ( $pid AND $sTerm )
        {
            $this->DB->build( array(  'select'   => 'p.*',
                                      'from'     => array( 'posts' => 'p' ),
                                      'where'    => 'p.pid=' . $pid,
                                      'add_join' => array( array( 'select' => 'm.*',
                                                                       'from'   => array( 'members' => 'm' ),
                                                                       'where'  => 'm.member_id=p.author_id',
                                                                       'type'   => 'left' ),
                                                             array( 'select' => 'pp.*',
                                                                       'from'   => array( 'profile_portal' => 'pp' ),
                                                                       'where'  => 'm.member_id=pp.pp_member_id',
                                                                       'type'   => 'left' ) ) ) );
                                                                           
            $this->DB->execute();
            
            $posts['search'] = $this->DB->fetch();
        }
        
        /* Still here? */
        foreach( $posts as $k => $data )
        {
            $data  = IPSMember::buildDisplayData( $data );
            
            IPSText::getTextClass( 'bbcode' )->parse_smilies            = $data['use_emo'];
            IPSText::getTextClass( 'bbcode' )->parse_html                = ( $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_html'] and $this->caches['group_cache'][ $data['member_group_id'] ]['g_dohtml'] and $data['post_htmlstate'] ) ? 1 : 0;
            IPSText::getTextClass( 'bbcode' )->parse_nl2br                = $data['post_htmlstate'] == 2 ? 1 : 0;
            IPSText::getTextClass( 'bbcode' )->parse_bbcode                = $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_ibc'];
            IPSText::getTextClass( 'bbcode' )->parsing_section            = 'topics';
            IPSText::getTextClass( 'bbcode' )->parsing_mgroup            = $data['member_group_id'];
            IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others    = $data['mgroup_others'];
            
            $data['post']    = IPSText::getTextClass( 'bbcode' )->stripQuotes( $data['post'] );
            $data['post']    = IPSText::getTextClass( 'bbcode' )->preDisplayParse( $data['post'] );
            
            /* Search term? */
            if ( $k == 'search' AND $pid AND $sTerm )
            {
                $data['post'] = IPSText::truncateTextAroundPhrase( IPSText::getTextClass( 'bbcode' )->stripAllTags( str_replace( '<br />', ' ', strip_tags( $data['post'], '<br>' ) ) ), $sTerm );
                $data['post'] = IPSText::searchHighlight( $data['post'], $sTerm );
            }
            else
            {
                $data['post'] = IPSText::truncate( IPSText::getTextClass( 'bbcode' )->stripAllTags( strip_tags( $data['post'] ) ), 200 );
            }
            
            $data['_isVisible']           = ( $this->registry->getClass('class_forums')->fetchHiddenType( $data ) == 'visible' ) ? true : false;
            $data['_isHidden']           = ( $this->registry->getClass('class_forums')->fetchHiddenType( $data ) == 'hidden' ) ? true : false;
            $data['_isDeleted']           = ( $this->registry->getClass('class_forums')->fetchHiddenType( $data ) == 'sdelete' ) ? true : false;

            $posts[ $k ] = $data;
        }
        
        $topic['_key'] = uniqid(microtime());
        
        $topic['preview'] = $posts;
        
        return $topic;
    }
	
	/**
	 * Flag a search session
	 *
	 * @return int			Process ID
	 */
	protected function _startSession()
	{
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
		
		if ( $processId )
		{
			$this->DB->update( 'sessions', array( 'search_thread_id' => $processId, 'search_thread_time' => time() ), "id='" . $this->member->session_id . "'" );
		}
		
		return $processId;
	}
	
	/**
	 * End a search session
	 *
	 * @return void
	 */
	protected function _endSession( $processId )
	{
		if ( $processId )
		{
			$this->DB->update( 'sessions', array( 'search_thread_id' => 0, 'search_thread_time' => 0 ), "id='" . $this->member->session_id . "'" );
		}
	}
	
	/**
	 * Custom sort function to avoid filesorts in the system
	 *
	 * @param	array 		A
	 * @param	array		B
	 * @return	boolean
	 */
	static function usort( $a, $b )
	{
		switch ( self::$ast )
		{
			case 'numeric':
			case 'numerical':
				if ( self::$aso == 'asc' )
				{
					return ($a[ self::$ask ] > $b[ self::$ask ]) ? +1 : -1;
				}
				else
				{
					return ($a[ self::$ask ] < $b[ self::$ask ]) ? +1 : -1;
				}
			break;
			case 'string':
				if ( self::$aso == 'asc' )
				{
					return strcasecmp($a[ self::$ask ], $b[ self::$ask ]) <= 0 ? -1 : +1;
				}
				else
				{
					return strcasecmp($a[ self::$ask ], $b[ self::$ask ]) <= 0 ? +1 : -1;
				}
			break;
		}
	}
}
