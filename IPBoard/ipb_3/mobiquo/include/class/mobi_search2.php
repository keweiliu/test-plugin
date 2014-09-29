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
	 * @access	private
	 * @var		string
	 */		
	private $output			= '';
	
	/**
	 * Page Title
	 *
	 * @access	private
	 * @var		string
	 */		
	private $title			= '';
	
	/**
	 * Object to handle searches
	 *
	 * @access	private
	 * @var		string
	 */	
	private $search_plugin	= '';
	
	/**
	 * Topics array
	 *
	 * @access	private
	 * @var		array
	 */
	private	$_topicArray	= array();
	private $_removedTerms  = array();
	
	/**
	 * Search controller
	 *
	 * @access	private
	 * @var		obj
	 */		
	private $searchController;
	private $_session;
	
	private $mobi_result = array();

	/**
	 * Class entry point
	 *
	 * @access	public
	 * @param	object		Registry reference
	 * @return	void		[Outputs to screen/redirects]
	 */
	public function doExecute( ipsRegistry $registry )
	{
		/* Load language */
		$this->registry->class_localization->loadLanguageFile( array( 'public_search' ), 'core' );
		$this->registry->class_localization->loadLanguageFile( array( 'public_forums', 'public_topic' ), 'forums' );
		
		/* Reset engine type */
		$this->settings['search_method'] = ( $this->settings['search_method'] == 'traditional' ) ? 'sql' : $this->settings['search_method'];
		$this->settings['search_method'] = 'sql';
		
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
			$this->request['search_in']      = $this->request['search_in'] ? $this->request['search_in'] : 'forums';
			$this->request['search_app']     = $this->request['search_app'] ? $this->request['search_app'] : $this->request['search_in'];
		}
		
		/* Check Access */
		$this->_canSearch();		
		
		/* Start session - needs to be called before the controller is initiated */
		$this->_startSession();
		
		/* Load the controller */
		require_once( IPS_ROOT_PATH. 'sources/classes/search/controller.php' );
		
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
					get_error("Invalid Search!");
				break;
			}
		}
		
		/* Set up some defaults */
		IPSSearchRegistry::set('in.start', intval( $this->request['st'] ) );
		IPSSearchRegistry::set('opt.search_per_page', intval( $this->request['search_per_page'] ) ? intval( $this->request['search_per_page'] ) : 25 );
		
		/* Contextuals */
		if ( isset( $this->request['cType'] ) )
		{
			IPSSearchRegistry::set('contextual.type', $this->request['cType'] );
			IPSSearchRegistry::set('contextual.id'  , $this->request['cId'] );
		}
		
		/* What to do */
		switch( $this->request['do'] )
		{
			case 'active':
				$this->activeContent();
			break;
			
			case 'user_activity':
			case 'user_topic':
			case 'user_reply':
				$this->viewUserContent();
			break;
		
			case 'new_posts':
				$this->viewNewPosts();
			break;
			
			case 'search':
			case 'quick_search':
				$_GET["search_term"] = to_local($_GET["search_term"]);
				$this->request['search_term'] = $_GET["search_term"];
				$this->searchResults();
			break;
			
			case 'followed':
				$this->viewFollowedContent();
			break;
			
			default:
			case 'search_form':	
				$this->searchAdvancedForm();
			break;
		}
		
		/* Start session */
		$this->_endSession();
		
		return $this->mobi_result;
	}
	
	/**
	 * Builds the advanced search form
	 *
	 * @access	public
	 * @param	string	Message
	 * @return	void
	 */
	public function searchAdvancedForm( $msg='', $removed_search_terms=array() )
	{
		/* Set up data */
		IPSSearchRegistry::set('view.search_form', true );
		
		/* Get any application specific filters */
		$appHtml   = $this->searchController->getHtml();
		$isBoolean = $this->searchController->isBoolean();
		
		/* Output */
		$this->title   = $this->lang->words['search_form'];
		$this->registry->output->addNavigation( $this->lang->words['search_form'], '' );
		$this->output .= $this->registry->output->getTemplate( 'search' )->searchAdvancedForm( $appHtml, $msg, $this->request['search_app'], $removed_search_terms, $isBoolean );
	}
	
	/**
	 * Processes a search request
	 *
	 * @access	public
	 * @return	void
	 */
	public function searchResults()
	{
	    if ($this->request['search_author'] || !empty($this->request['mid'])) {
	        $this->request['user_name'] = to_local($this->request['user_name']);
//	        global $member;
//    		$this->request['search_author'] = $member['members_l_display_name'];
    		$user_name = $this->DB->addSlashes( strtolower($this->request['search_author']) );
    		$member = $this->DB->buildAndFetch( array( 
    													'select' => 'members_l_display_name', 
    													'from'   => 'members', 
    													'where'  => "member_id = '{$this->request['mid']}' OR members_l_display_name='{$user_name}' OR members_l_username='{$user_name}'"
    											)	 );	
    		$members_display_name = $member['members_l_display_name'];
    		$this->request['search_author'] = $members_display_name;
    	}
	    
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
		IPSSearchRegistry::set('in.search_higlight'  , str_replace( '.', '', $this->request['search_term'] ) );
		IPSSearchRegistry::set('in.search_date_end'  , ( $this->request['search_date_start'] && $this->request['search_date_end'] )  ? $this->request['search_date_end'] : 'now' );
		IPSSearchRegistry::set('in.search_date_start', ( $this->request['search_date_start']  )  ? $this->request['search_date_start'] : '' );
		IPSSearchRegistry::set('in.search_author'    , ( isset( $this->request['search_author'] ) && $this->request['search_author'] ) ? $this->request['search_author'] : '' );
		
		/* Set sort filters */
		$this->_setSortFilters();
		
		/* These can be overridden in the actual engine scripts */
	//	IPSSearchRegistry::set('set.hardLimit'        , 0 );
		IPSSearchRegistry::set('set.resultsCutToLimit', false );
		IPSSearchRegistry::set('set.resultsAsForum'   , false );
		
		/* Are we option to show titles only / search in titles only */
		IPSSearchRegistry::set('opt.searchTitleOnly', ( isset( $this->request['content_title_only'] ) && $this->request['content_title_only']  ) ? true : false );
		IPSSearchRegistry::set('display.onlyTitles' , ( ( $this->request['show_as_titles'] AND $this->settings['enable_show_as_titles'] ) OR ( IPSSearchRegistry::get('opt.searchTitleOnly') ) ) ? true : false );
		
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
		 * Ok this is an upper limit.
		 * If you needed to change this, you could do so via conf_global.php by adding:
		 * $INFO['max_search_word'] = #####;
		 */
		if ( $this->settings['min_search_word'] && ! IPSSearchRegistry::get('in.search_author') )
		{
			$_words	= explode( ' ', IPSSearchRegistry::get('in.raw_search_term') );
			$_ok	= true;
			
			foreach( $_words as $_word )
			{
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
		$_search_term = trim( preg_replace( "#(^|\s)(\+|\-|\||\~)#", " ", $search_term ) );
		
		/* Can we do this? */
		if ( IPSLib::appisSearchable( IPSSearchRegistry::get('in.search_app'), 'search' ) )
		{
			/* Perform the search */
			$this->searchController->search();
			
			/* Get count */
			$count = $this->searchController->getResultCount();
			
			/* Get results which will be array of IDs */
			$results = $this->searchController->getResultSet();
			
			$author_info = array();
			foreach ($results as $rid => $result)
			{
        		// get last post author username
        		if (isset($author_info[$result['last_poster_id']]['username'])) {
        		    $results[$rid]['last_poster_username'] = $author_info[$result['last_poster_id']]['username'];
        		} else {
	        		$topic_author_name = $this->DB->buildAndFetch( array(
                                                        'select' => 'name',
                                                        'from'   => 'members',
                                                        'where'  => "member_id= {$result['last_poster_id']} "));
                    $results[$rid]['last_poster_username'] = $topic_author_name['name'];
                    $author_info[$result['last_poster_id']]['username'] = $topic_author_name['name'];
                }
            }
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
	 * @access	protected
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
					$this->request['search_app_filters'] = $this->_session['_session_data']['search_app_filters'];
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
	 * @access	protected
	 * @return	void
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
		
		IPSSearchRegistry::set('in.search_sort_by'   , trim( $key ) );
		IPSSearchRegistry::set('in.search_sort_order', ( $dir != 'desc' ) ? 'asc' : 'desc' );
	}
	
	/**
	 * Displays the active topics screen
	 *
	 * @access	public
	 * @return	void
	 */
	public function activeContent()
	{
		IPSSearchRegistry::set('in.search_app', $this->request['search_app'] );
		IPSSearchRegistry::set('in.period'    , ( empty( $this->request['period'] ) ) ? 'today' : $this->request['period'] );
		
		/* Can we do this? */
		if ( IPSLib::appisSearchable( IPSSearchRegistry::get('in.search_app'), 'active' ) )
		{
			/* Perform the search */
			$this->searchController->viewActiveContent();
			
			/* Get count */
			$count = $this->searchController->getResultCount();
			
			/* Get results which will be array of IDs */
			$results = $this->searchController->getResultSet();
			
			/* Get templates to use */
			$template = $this->searchController->fetchTemplates();
			
			/* Fetch sort details */
			$sortIn       = $this->searchController->fetchSortIn();
			
			/* Parse result set */
			$results = $this->registry->output->getTemplate( $template['group'] )->$template['template']( $results, ( IPSSearchRegistry::get('display.onlyTitles') || IPSSearchRegistry::get( 'opt.noPostPreview') ) ? 1 : 0 );
			
			/* Build pagination */
			$links = $this->registry->output->generatePagination( array( 'totalItems'		=> $count,
																		'itemsPerPage'		=> IPSSearchRegistry::get('opt.search_per_page'),
																		'currentStartValue'	=> IPSSearchRegistry::get('in.start'),
																		'baseUrl'			=> 'app=core&amp;module=search&amp;do=active&amp;period=' . IPSSearchRegistry::get('in.period') . '&amp;search_app=' . IPSSearchRegistry::get('in.search_app') . '&amp;sid=' . $this->request['_sid'] ) );
		}
		else
		{
			$count   = 0;
			$results = array();
		}
		
		/* Output */
		$this->title   = $this->lang->words['active_posts_title'];
		$this->registry->output->addNavigation( $this->lang->words['active_posts_title'], '' );
		$this->output .= $this->registry->output->getTemplate( 'search' )->activePostsView( $results, $links, $count, $sortIn );
	}
	
	/**
	 * Displays latest user content
	 *
	 * @access	public
	 * @return	void
	 */
	public function viewUserContent()
	{
		######################find the id by username
		$this->request['user_name'] = to_local($this->request['user_name']);
		$user_name = $this->DB->addSlashes( strtolower($this->request['user_name']) );
		
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
		/* INIT */
		$id 	    = $this->request['mid'] ? intval( trim( $this->request['mid'] ) ) : $this->memberData['member_id'];
		$member	    = IPSMember::load( $id, 'core' );
		$beginStamp = 0;
		
		if ( ! $member['member_id'] )
		{
			//$this->registry->output->showError( 'search_invalid_id', 10147, null, null, 403 );
			get_error("Invalid member id");
		}
		
		IPSSearchRegistry::set('in.search_app', $this->request['search_app'] );
		IPSSearchRegistry::set('in.userMode'  , ( $this->request['userMode'] ) ? $this->request['userMode'] : 'all' );
		
		/* Can we do this? */
		if ( IPSLib::appisSearchable( IPSSearchRegistry::get('in.search_app'), 'usercontent' ) )
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
		
		$this->mobi_result = $results;
	}
	
	/**
	 * View new posts since your last visit
	 *
	 * @access	public
	 * @return	void
	 */
	public function viewNewPosts()
	{	
		IPSSearchRegistry::set('in.search_app', $this->request['search_app'] );
		
		/* Can we do this? */
		if ( IPSLib::appisSearchable( IPSSearchRegistry::get('in.search_app'), 'vnc' ) )
		{
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
    		'list'			  => $results,
		);
	}
	

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
			$results	= $this->searchController->fetchFollowedContentOutput( $results, $formatted );
	
			$pages = $this->registry->getClass('output')->generatePagination( array(  'totalItems'			=> $count,
															   					 	  'itemsPerPage'		=> IPSSearchRegistry::get('opt.search_per_page'),
																					  'currentStartValue'	=> IPSSearchRegistry::get('in.start'),
																					  'method'				=> 'nextPrevious',
																					  'baseUrl'				=> "app=core&amp;module=search&amp;do=followed&amp;search_app=" . IPSSearchRegistry::get('in.search_app')  . '&amp;sid=' . $this->request['_sid'] ) );
		}
		else
		{
			$count		= 0;
			$results	= array();
		}

		/* Output */
		$this->registry->output->addNavigation( $this->lang->words['followed_ct_title'], '' );
		$this->title	= $this->lang->words['followed_ct_title'];
		$this->output	.= $this->registry->output->getTemplate( 'search' )->followedContentView( $results, $pages, $count, $error, $contentTypes );
	}


	/**
	 * Returns a url string that will maintain search results via links
	 *
	 * @access	private
	 * @return	string
	 */
	private function _buildURLString()
	{
		/* INI */
		$url_string  = 'app=core&amp;module=search&amp;do=search&amp;andor_type=' . $this->request['andor_type'];
		$url_string .= '&amp;sid=' . $this->request['_sid'];
		
		/* Add author name */
		if( isset( $this->request['search_author'] ) AND $this->request['search_author'] )
		{
			$url_string .= "&amp;search_author=" . urlencode($this->request['search_author']);
		}
		
		/* Add titles only */
		if( isset( $this->request['show_as_titles'] ) AND $this->request['show_as_titles'] )
		{
			$url_string .= "&amp;show_as_titles={$this->request['show_as_titles']}";
		}
		
		/* Search Range */
		if( isset( $this->request['search_date_start'] ) AND $this->request['search_date_start'] )
		{
			$url_string .= "&amp;search_date_start={$this->request['search_date_start']}";
		}
		
		if( isset( $this->request['search_date_end'] ) AND $this->request['search_date_end'] )
		{
			$url_string .= "&amp;search_date_end={$this->request['search_date_end']}";
		}
		
		if ( IPSSearchRegistry::get('contextual.type') )
		{
			$url_string .= "&amp;cType=" . IPSSearchRegistry::get('contextual.type') . "&amp;cId=" . IPSSearchRegistry::get('contextual.id');
		}
			
		/* Search app filters */
		/*if( isset( $this->request['search_app_filters'] ) && count( $this->request['search_app_filters'] ) )
		{
			foreach( $this->request['search_app_filters'] as $app => $filter_data )
			{
				if( is_array( $filter_data ) )
				{					
					foreach( $filter_data as $k => $v )
					{
						if ( is_array( $v ) )
						{
							foreach( $v as $_k => $_v )
							{
								if ( $_v != '' )
								{
									$url_string .= "&amp;search_app_filters[{$app}][{$k}][$_k]={$_v}";
								}
							}
						}
						else if ( $v != '' )
						{
							$url_string .= "&amp;search_app_filters[{$app}][{$k}]={$v}";
						}
					}
				}
				else if ( $v != '' )
				{
					$url_string .= "&amp;search_app_filters[{$app}]={$v}";
				}
			}
		}*/

		if( isset( $this->request['content_title_only'] ) && $this->request['content_title_only'] )
		{
			$url_string .= "&amp;content_title_only=1";
		}
		
		if( isset( $this->request['type'] ) && isset( $this->request['type_id'] ) )
		{
			$url_string .= "&amp;type={$this->request['type']}&amp;type_id={$this->request['type_id']}";
		}
		
		if( isset( $this->request['type_2'] ) && isset( $this->request['type_id_2'] ) )
		{
			$url_string .= "&amp;type_2={$this->request['type_2']}&amp;type_id_2={$this->request['type_id_2']}";
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
	 * @access	private
	 * @return	void
	 */
	private function _canSearch()
	{
		/* Check the search setting */
		if( ! $this->settings['allow_search'] )
		{
			if( $this->xml_out )
			{
				@header( "Content-type: text/html;charset={$this->settings['gb_char_set']}" );
				print $this->lang->words['search_off'];
				exit();
			}
			else
			{
				//$this->registry->output->showError( 'search_off', 10145 );
				get_error("Search is not enabled on the forums at this time.");
			}
		}
		
		/* Check the member authorization */
		if( ! isset( $this->memberData['g_use_search'] ) || ! $this->memberData['g_use_search'] )
		{
			if( $this->xml_out )
			{
				@header( "Content-type: text/html;charset={$this->settings['gb_char_set']}" );
				print $this->lang->words['no_xml_permission'];
				exit();
			}
			else
			{
				//$this->registry->output->showError( 'no_permission_to_search', 10146, null, null, 403 );
				get_error("You do not have permission to use the search system.");
			}
		}		
	} 
}

class mobi_IPSSearch extends IPSSearch
{
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
		$this->_app       = strtolower( IPSText::alphanumericalClean( $app ) );
		
		/* Quick check */
		if ( ! file_exists( IPS_ROOT_PATH . 'sources/classes/search/engines/' . $this->_engine . '.php' ) )
		{
			/* Try SQL */
			if ( ! $this->_engine != 'sql' )
			{
				$this->_engine = 'sql';
				
				if ( ! file_exists( IPS_ROOT_PATH . 'sources/classes/search/engines/' . $this->_engine . '.php' ) )
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
		$_className = 'search_' . $this->_engine;
		
		require_once( IPS_ROOT_PATH . 'sources/classes/search/format.php' );
		
		/* Got an app specific file? Lets hope so */
		if ( file_exists( IPSLib::getAppDir( $this->_app ) . '/extensions/search/format.php' ) )
		{	
			/* We may not have sphinx specific stuff, so... */
			if ( ! file_exists( IPSLib::getAppDir( $this->_app ) . '/extensions/search/engines/' . $this->_engine . '.php' ) )
			{
				$this->_engine = 'sql';
				
				if ( ! file_exists( IPSLib::getAppDir( $this->_app ) . '/extensions/search/engines/' . $this->_engine . '.php' ) )
				{
					throw new Exception( "NO_SUCH_APP_ENGINE" );
				}
			}
			
			require_once( IPS_ROOT_PATH . 'sources/classes/search/engines/' . $this->_engine . '.php' );
			require_once( IPSLib::getAppDir( $this->_app ) . '/extensions/search/engines/' . $this->_engine . '.php' );
			
			/* SEARCH classname */
			$_className = 'search_engine_' . $this->_app;
			
			$this->SEARCH = new $_className( $registry );
			
			/* FORMAT file */
			require_once( IPSLib::getAppDir( $this->_app ) . '/extensions/search/format.php' );
			
			/* FORMAT classname */
			$_className = 'search_format_' . $this->_app;
			
			$this->FORMAT = new $_className( $registry );
			
			/* Grab config */
			require( IPSLib::getAppDir( $this->_app ) . '/extensions/search/config.php' );
			
			if ( is_array( $CONFIG ) )
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
				$this->SEARCH->setCondition( $filter_data['column'], $filter_data['operator'], $filter_data['value'], 'OR' );
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
    			
    			if ( ! $this->memberData['g_is_supmod'] )
    			{
    				$moderator = $this->memberData['forumsModeratorData'];
    				
    				if ( !isset($moderator[$forum_id]) OR !is_array( $moderator[$forum_id] ) )
    				{
    					$this->memberData['is_mod'] = 0;
    				}
    			}
        		
        		$can_delete = 0;
    			if ($this->memberData['is_mod'] == 1 and ($this->memberData['g_is_supmod'] == 1 || $this->memberData['forumsModeratorData'][$forum_id]['delete_topic'])) {
    				$results[$rid]['can_delete'] = 1;
    			}
    			
    			//######################################
    			//get icon_url
    			//######################################
        		$results[$rid]['icon_url'] = get_avatar($result['author_id']);
    			
    			//######################################
    		 	// get TOPIC CONTENT and shord content.....	
    		 	//######################################
    		 	$queued_query_bit = ' and queued=0';
    		 	if ( $this->registry->getClass('class_forums')->canQueuePosts($forum_id) )
    			{
    				$queued_query_bit = "";
    			}
    		  	$post_data = $this->DB->buildAndFetch( array( 
    													'select' => 'post, post_htmlstate', 
    													'from'   => 'posts', 
    													'where'  => "topic_id= {$result['tid']} " . $queued_query_bit,
    													'order' => 'pid desc')	);
    			
    		 	$results[$rid]['short_content'] = get_short_content($post_data['post'], $post_data['post_htmlstate']);
    		 	
    		 	//######################################
    		 	// has new since last login????.....	
    		 	//######################################
    		 	$results[$rid]['has_new'] = ipsRegistry::getClass( 'classItemMarking')->isRead( array( 'forumID' => $forum_id, 
    																'itemID' => $result['tid'], 
    																'itemLastUpdate' => $result['last_post'] 
    																),  'forums' );
    		}
    		
    		// get subscribe infor
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
    		
    		foreach ($results as $rid => $result)
    		{
    		 	$results[$rid]['issubscribed'] = $topic_array[ $result['tid'] ] ? true : false;
    		 	$results[$rid]['can_subscribe'] = ($this->settings['cpu_watch_update'] == 1 && $this->memberData['member_id']) ? true : false;
    		}
    		
    		$this->_results = $results;
		}
		
		/* Kill session */
		$this->_endSession( $processId );
	}	
    
	public function viewNewContent()
	{
		IPSSearchRegistry::set('display.onlyTitles', true);
		
		/* Hard fix mobile app users to VNC based on unread content */
		if ( $this->member->isMobileApp )
		{
			$this->memberData['bw_vnc_type'] = 1;
		}
		
		/* Run the search */
		$results = $this->SEARCH->viewNewContent();
		
		/* Set data */
		$this->_count   = intval( $results['count'] );
		$this->_results = $results['resultSet'];
		
		/* Now format results */
		if ( count( $this->_results ) )
		{
			$results = $this->FORMAT->processResults( $this->_results );
			$author_info = array();
			foreach ($results as $rid => $result)
			{
        		//get forum name 
        		$forum_id = $result['forum_id'];
        		$results[$rid]['forum_name'] = $this->registry->class_forums->forum_by_id[ $forum_id ]['name'];
                
        		//get icon_url
		        $results[$rid]['icon_url'] = get_avatar($result['last_poster_id']);
        		
        		// get last post author username
        		if (isset($author_info[$result['last_poster_id']]['username'])) {
        		    $results[$rid]['last_poster_username'] = $author_info[$result['last_poster_id']]['username'];
        		} else {
	        		$topic_author_name = $this->DB->buildAndFetch( array(
                                                        'select' => 'name',
                                                        'from'   => 'members',
                                                        'where'  => "member_id= {$result['last_poster_id']} "));
                    $results[$rid]['last_poster_username'] = $topic_author_name['name'];
                    $author_info[$result['last_poster_id']]['username'] = $topic_author_name['name'];
                }
        		
        	 	// get TOPIC CONTENT and shord content
        	  	$post_data = $this->DB->buildAndFetch( array( 
        												'select' => 'post, post_htmlstate', 
        												'from'   => 'posts', 
        												'where'  => "topic_id= {$result['tid']} and queued=0",
        												'order' => 'pid desc')	);			
        	 	$results[$rid]['short_content'] = get_short_content($post_data['post'], $post_data['post_htmlstate']);
        	}
			
			$this->_results = $results;
		}
	}
	
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
			$results = $this->FORMAT->processResults( $this->_results );
            $author_info = array();
			foreach ($results as $rid => $result)
			{
        		//get forum name 
        		$forum_id = $result['forum_id'];
        		$results[$rid]['forum_name'] = $this->registry->class_forums->forum_by_id[ $forum_id ]['name'];
        		
    			########get new post#########################			
    			$is_read = ipsRegistry::getClass('classItemMarking')->isRead( array( 'forumID' => $forum_id, 
    																'itemID' => $result['tid'], 
    																'itemLastUpdate' => $result['edit_time'] ? $result['edit_time'] : $result['last_post']
    																),  'forums' );
    			$results[$rid]['is_read'] = $is_read;
                
        		//get icon_url
		        $results[$rid]['icon_url'] = get_avatar($result['last_poster_id']);
        		
        		// get last post author username
        		if (isset($author_info[$result['last_poster_id']]['username'])) {
        		    $results[$rid]['last_poster_username'] = $author_info[$result['last_poster_id']]['username'];
        		} else {
	        		$post_author_name = $this->DB->buildAndFetch( array(
                                                        'select' => 'name',
                                                        'from'   => 'members',
                                                        'where'  => "member_id= {$result['last_poster_id']} "));
                    $results[$rid]['last_poster_username'] = $post_author_name['name'];
                    $author_info[$result['last_poster_id']]['username'] = $post_author_name['name'];
                }
        		
        	 	// get TOPIC CONTENT and shord content
        	  	$post_data = $this->DB->buildAndFetch( array( 
        												'select' => 'post, post_htmlstate', 
        												'from'   => 'posts', 
        												'where'  => "topic_id= {$result['tid']} and queued=0",
        												'order' => 'pid desc')	);			
        	 	$results[$rid]['short_content'] = get_short_content($post_data['post'], $post_data['post_htmlstate']);
        	}
        	
        	$this->_results = $results;
		}
	}
	
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
		}
 	}
 	
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

	protected function _endSession( $processId )
	{
		if ( $processId )
		{
			$this->DB->update( 'sessions', array( 'search_thread_id' => 0, 'search_thread_time' => 0 ), "id='" . $this->member->session_id . "'" );
		}
	}
}
