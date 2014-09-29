<?php
/*======================================================================*\
|| #################################################################### ||
|| # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # This file is part of the Tapatalk package and should not be used # ||
|| # and distributed for any other purpose that is not approved by    # ||
|| # Quoord Systems Ltd.                                              # ||
|| # http://www.tapatalk.com | https://tapatalk.com/license.php       # ||
|| #################################################################### ||
\*======================================================================*/
defined('IN_MOBIQUO') or exit;

require_once( IPS_ROOT_PATH . 'applications/core/modules_public/search/search.php' );

class tapatalk_search extends public_core_search_search
{
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

        $this->request['search_app_filters'] = $this->_cleanInputFilters( $this->request['search_app_filters'] );

        /* Special consideration for contextual search */
        if ( isset( $this->request['search_app'] ) AND strstr( $this->request['search_app'], ':' ) )
        {
            list( $app, $type, $id ) = explode( ':', $this->request['search_app'] );

            $this->request['search_app'] = $app;
            $this->request['cType']      = $type;
            $this->request['cId']        = $id;
        }
        else
        {
            /* Force forums as default search */
            $this->request['search_in']      = ( $this->request['search_in'] AND IPSLib::appIsSearchable( $this->request['search_in'], 'search' ) ) ? $this->request['search_in'] : 'forums';
            $this->request['search_app']     = $this->request['search_app'] ? $this->request['search_app'] : $this->request['search_in'];
        }

        /* Check Access */
        $this->_canSearch();

        // tapatalk add for search by user id
        if ($this->request['search_authorid'])
        {
            $search_member = IPSMember::load($this->request['search_authorid']);
            if (isset($search_member['members_display_name']))
            {
                $this->request['search_author'] = $search_member['members_display_name'];
            }
        }

        // tapatalk add. return current user's data
        if (isset($this->request['return_mine']) && empty($this->request['search_author']) && empty($this->request['sid']) && $this->memberData['members_display_name'])
        {
            $this->request['search_author'] = $this->memberData['members_display_name'];
        }

        // tapatalk add for search by not in forum list
        if (!empty($this->request['search_app_filters']['forums']['forums_exclude']))
        {
            $forumIdsOk = $this->registry->class_forums->fetchSearchableForumIds();
            $this->request['search_app_filters']['forums']['forums'] = array_diff($forumIdsOk, $this->request['search_app_filters']['forums']['forums_exclude']);
        }


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

        $this->settings['search_ucontent_days'] = ( $this->settings['search_ucontent_days'] ) ? $this->settings['search_ucontent_days'] : 365;

        /* Contextuals */
        if ( isset( $this->request['cType'] ) )
        {
            IPSSearchRegistry::set('contextual.type', $this->request['cType'] );
            IPSSearchRegistry::set('contextual.id'  , $this->request['cId'] );
        }

        /* Register a call back for an incorrect page */
        $this->registry->getClass('output')->registerIncorrectPageCallback( array( $this, 'incorrectPageCallback' ) );  // tapatalk add

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

        // tapatalk add
        global $search_results, $show_last_post;
        $this->result['sid'] = $this->request['_sid'];
        $this->result['showtopic'] = ( IPSSearchRegistry::get('opt.searchType') == 'titles' || IPSSearchRegistry::get('opt.noPostPreview') ) ? 1 : 0;
        $this->setResultPreview($show_last_post);
        $search_results = $this->result;
        
        /* If we have any HTML to print, do so...
        if ( $this->request['do'] == 'search' && ! empty( $this->request['search_tags'] ) )
        {
            $this->registry->output->setTitle( IPSText::urldecode_furlSafe( $this->request['search_tags'] ) . ' - ' . $this->lang->words['st_tags'] . ' - ' . IPSLib::getAppTitle( $this->request['search_app'] ) . ' - ' . ipsRegistry::$settings['board_name'] );

            // Add canonical tag
            $extra = ( $this->request['st'] ) ? '&amp;st=' . $this->request['st'] : '';
            $this->registry->output->addCanonicalTag( 'app=core&amp;module=search&amp;do=search&amp;search_tags=' . IPSText::urlencode_furlSafe( $this->request['search_tags'] ) . '&amp;search_app=' . $this->request['search_app']. $extra, $this->request['search_tags'], 'tags' );
        }
        else
        {
            $this->registry->output->setTitle( $this->title . ' - ' . ipsRegistry::$settings['board_name'] );
        }

        $this->registry->output->addContent( $this->output );
        $this->registry->output->sendOutput();
        */
    }

    public function searchAdvancedForm( $msg='Search Error', $removed_search_terms=array() )
    {
        $this->registry->output->showError( $msg );
    }

    public function searchResults()
    {
        // localization search keys
        $this->request['search_term'] = to_local($this->request['search_term']);
        $this->request['search_author'] = to_local($this->request['search_author']);

        /* Search Term */
        if( isset( $this->request['search_term'] ) && !is_string( $this->request['search_term'] ) )
        {
            $this->registry->getClass('output')->showError( 'invalid_search_term', 10312564 );
        }

        $_st          = $this->searchController->formatSearchTerm( trim( $this->request['search_term'] ) );
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

        if ( is_array( $this->request['search_app_filters'] ) )
        {
            array_walk_recursive( $this->request['search_app_filters'], create_function('&$item, $key', '$item = IPSText::htmlspecialchars($item);' ) );
        }

        /* App specific */
        if ( isset( $this->request['search_sort_by_' . $this->request['search_app'] ] ) )
        {
            $this->request['search_sort_by']    = ( $_POST[ 'search_sort_by_' . $this->request['search_app'] ] ) ? htmlspecialchars( $_POST[ 'search_sort_by_' . $this->request['search_app'] ] ) : $this->request['search_sort_by_' . $this->request['search_app'] ];
            $this->request['search_sort_order'] = ( $_POST[ 'search_sort_order_' . $this->request['search_app'] ] ) ? htmlspecialchars( $_POST[ 'search_sort_order_' . $this->request['search_app'] ] ) : $this->request['search_sort_order_' . $this->request['search_app'] ];
        }

        /* Populate the registry */
        IPSSearchRegistry::set('in.search_app'       , $this->request['search_app'] );
        IPSSearchRegistry::set('in.raw_search_term'  , trim( $this->request['search_term'] ) );
        IPSSearchRegistry::set('in.clean_search_term', $search_term );
        IPSSearchRegistry::set('in.raw_search_tags'  , str_replace( '&amp;', '&', trim( IPSText::parseCleanValue( IPSText::urldecode_furlSafe( $_REQUEST['search_tags'] ) ) ) ) );
        IPSSearchRegistry::set('in.search_higlight'  , str_replace( '.', '', $this->request['search_term'] ) );
        IPSSearchRegistry::set('in.search_date_end'  , ( $this->request['search_date_start'] && $this->request['search_date_end'] )  ? $this->request['search_date_end'] : 'now' );
        IPSSearchRegistry::set('in.search_date_start', ( $this->request['search_date_start']  )  ? $this->request['search_date_start'] : '' );
        IPSSearchRegistry::set('in.search_author'    , !empty( $this->request['search_author'] ) ? $this->request['search_author'] : '' );

        /* Set sort filters */
        $this->_setSortFilters();

        /* These can be overridden in the actual engine scripts */
    //  IPSSearchRegistry::set('set.hardLimit'        , 0 );
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
            if ( $this->settings['search_method'] == 'sphinx' && substr_count( $search_term, '"' ) >= 2 )
            {
                $_ok = true;
            }
            else
            {
                $_words = explode( ' ', preg_replace( "#\"(.*?)\"#", '', $search_term ) );
                $_ok    = $search_term ? true : false;

                foreach( $_words as $_word )
                {
                    $_word  = preg_replace( '#^\+(.+?)$#', "\\1", $_word );

                    if ( ! $_word OR $_word == '|' )
                    {
                        continue;
                    }

                    if( strlen( $_word ) < $this->settings['min_search_word'] )
                    {
                        $_ok    = false;
                        break;
                    }
                }
            }

            if( ! $_ok )
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
            $last_term  = str_replace( "&quot;", '"', IPSCookie::get( 'sfct' ) );
            $last_term  = str_replace( "&amp;", '&',  $last_term );

            /* If we have a last search time, check it */
            if( $last_search && $last_term )
            {
                if( ( time() - $last_search ) <= $this->memberData['g_search_flood'] && $last_term != IPSSearchRegistry::get('in.raw_search_term') )
                {
                    $this->searchAdvancedForm( sprintf( $this->lang->words['xml_flood'], $this->memberData['g_search_flood'] - ( time() - $last_search ) ) );
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

            /* Get results which will be array of IDs
            $results = $this->searchController->getResultSet();
            */

            /* Get templates to use
            $template = $this->searchController->fetchTemplates();
            */

            /* Fetch sort details */
            $sortDropDown = $this->searchController->fetchSortDropDown();

            /* Set default sort option */
            $_a = IPSSearchRegistry::get('in.search_app');
            $_k = IPSSearchRegistry::get( $_a . '.searchInKey') ? IPSSearchRegistry::get( $_a . '.searchInKey') : '';

            if( $_k AND !$this->request['search_app_filters'][ $_a ][ $_k ]['sortKey'] AND is_array($sortDropDown) AND count($sortDropDown) )
            {
                $this->request['search_app_filters'][ $_a ][ $_k ]['sortKey']   = key( $sortDropDown );
            }
            else if( !$_k AND !$this->request['search_app_filters'][ $_a ]['sortKey'] AND is_array($sortDropDown) AND count($sortDropDown) )
            {
                $this->request['search_app_filters'][ $_a ]['sortKey']  = key( $sortDropDown );
            }

            /* Fetch sort details */
            $sortIn       = $this->searchController->fetchSortIn();

            /* Build pagination */
            $links = $this->registry->output->generatePagination( array( 'totalItems'       => $count,
                                                                         'itemsPerPage'     => IPSSearchRegistry::get('opt.search_per_page'),
                                                                         'currentStartValue'=> IPSSearchRegistry::get('in.start'),
                                                                         'baseUrl'          => $this->_buildURLString() . '&amp;search_app=' . IPSSearchRegistry::get('in.search_app') )    );

            /* Showing */
            $showing = array( 'start' => IPSSearchRegistry::get('in.start') + 1, 'end' => ( IPSSearchRegistry::get('in.start') + IPSSearchRegistry::get('opt.search_per_page') ) > $count ? $count : IPSSearchRegistry::get('in.start') + IPSSearchRegistry::get('opt.search_per_page') );

            /* Parse result set
            $results = $this->registry->output->getTemplate( $template['group'] )->$template['template']( $results, ( IPSSearchRegistry::get('opt.searchType') == 'titles' || IPSSearchRegistry::get('opt.noPostPreview') ) ? 1 : 0 );
            */

            /* Check for sortIn */
            if( count( $sortIn ) && !$this->request['search_app_filters'][ $this->request['search_app'] ]['searchInKey'] )
            {
                $this->request['search_app_filters'][ $this->request['search_app'] ]['searchInKey'] = $sortIn[0][0];
            }

            // tapatalk add
            $results = $this->searchController->getRawResultSet();
        }
        else
        {
            $count   = 0;
            $results = array();
        }

        $this->result = array(
            'count' => $count,
            'list'  => $results,
        );

        /* Output
        $this->title   = $this->lang->words['search_results'];
        $this->output .= $this->registry->output->getTemplate( 'search' )->searchResultsWrapper( $results, $sortDropDown, $sortIn, $links, $count, $showing, $_search_term, $this->_buildURLString(), $this->request['search_app'], $removedTerms, IPSSearchRegistry::get('set.hardLimit'), IPSSearchRegistry::get('set.resultsCutToLimit'), IPSSearchRegistry::get('in.raw_search_tags') );
        */
    }

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

                /*
                if ( isset( $this->_session['_session_data']['search_app_filters'] ) )
                {
                    $this->request['search_app_filters'] = is_array( $this->request['search_app_filters'] ) ? array_merge( $this->_session['_session_data']['search_app_filters'], $this->request['search_app_filters'] ) : $this->_session['_session_data']['search_app_filters'];
                }
                */
                // tapatalk udpated
                if ( is_array($this->_session['_session_data']) )
                {
                    $this->request = array_merge( $this->request, $this->_session['_session_data'] );
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
                                     'session_data'      => serialize( $this->request ) // tapatalk updated
                                   );

            $this->DB->insert( 'search_sessions', $this->_session );

            $this->_session['_session_data'] = $this->request;  // tapatalk updated
            unset($this->_session['_session_data']['st']);      // tapatalk add

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

    public function viewUserContent()
    {

        // tapatalk add for search by user name
        if ($this->request['search_author'] && empty($this->request['mid']))
        {
            $search_member = IPSMember::load( $this->request['search_author'], 'core', 'displayname' );
            if (isset($search_member['member_id']))
            {
                $this->request['mid'] = $search_member['member_id'];
            }
        }

        /* INIT */
        $id         = $this->request['mid'] ? intval( trim( $this->request['mid'] ) ) : $this->memberData['member_id'];

        /* Save query if we are viewing our own content */
        if( $this->memberData['member_id'] AND $id == $this->memberData['member_id'] )
        {
            $member = $this->memberData;
        }
        else
        {
            $member     = IPSMember::load( $id, 'core' );
        }

        $beginStamp = 0;

        if ( ! $member['member_id'] )
        {
            $this->registry->output->showError( 'search_invalid_id', 10147, null, null, 403 );
        }

        $this->request['userMode']  = !empty($this->request['userMode']) ? $this->request['userMode'] : 'all';

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

            /* Get templates to use
            $template = $this->searchController->fetchTemplates();
            */

            /* Fetch sort details */
            $sortDropDown = $this->searchController->fetchSortDropDown();

            /* Set default sort option */
            $_a = IPSSearchRegistry::get('in.search_app');
            $_k = IPSSearchRegistry::get( $_a . '.searchInKey') ? IPSSearchRegistry::get( $_a . '.searchInKey') : '';

            if( $_k AND !$this->request['search_app_filters'][ $_a ][ $_k ]['sortKey'] AND is_array($sortDropDown) )
            {
                $this->request['search_app_filters'][ $_a ][ $_k ]['sortKey']   = key( $sortDropDown );
            }
            else if( !$_k AND !$this->request['search_app_filters'][ $_a ]['sortKey'] AND is_array($sortDropDown) )
            {
                $this->request['search_app_filters'][ $_a ]['sortKey']  = key( $sortDropDown );
            }

            /* Fetch sort details */
            $sortIn       = $this->searchController->fetchSortIn();

            /* Reset for template */
            $this->_resetRequestParameters();

            /* Parse result set 
            $results = $this->registry->output->getTemplate( $template['group'] )->$template['template']( $results, ( IPSSearchRegistry::get('opt.searchType') == 'titles' || IPSSearchRegistry::get('opt.noPostPreview') ) ? 1 : 0 );
            */

            /* Build pagination */
            $links = $this->registry->output->generatePagination( array( 'totalItems'       => $count,
                                                                        'itemsPerPage'      => IPSSearchRegistry::get('opt.search_per_page'),
                                                                        'currentStartValue' => IPSSearchRegistry::get('in.start'),
                                                                        'baseUrl'           => 'app=core&amp;module=search&amp;do=user_activity&amp;mid=' . $id . '&amp;search_app=' . IPSSearchRegistry::get('in.search_app') . '&amp;userMode=' . IPSSearchRegistry::get('in.userMode')  . '&amp;sid=' . $this->request['_sid'] . $this->_returnSearchAppFilters() ) );
        
            // tapatalk add
            $results = $this->searchController->getRawResultSet();
        }
        else
        {
            $count   = 0;
            $results = array();
        }

        $this->result = array(
            'count' => $count,
            'list'  => $results,
        );
        
        /*
        $this->title   = sprintf( $this->lang->words['s_participation_title'], $member['members_display_name'] );
        $this->registry->output->addNavigation( $this->title, '' );
        $this->output .= $this->registry->output->getTemplate( 'search' )->userPostsView( $results, $links, $count, $member, IPSSearchRegistry::get('set.hardLimit'), IPSSearchRegistry::get('set.resultsCutToLimit'), $beginStamp, $sortIn, $sortDropDown );
        */
    }

    public function viewNewContent()
    {

        IPSSearchRegistry::set('in.search_app', $this->request['search_app'] );

        /* Fetch member cache to see if we have a value set */
        $vncPrefs = IPSMember::getFromMemberCache( $this->memberData, 'vncPrefs' );
        $vncPrefs = null;

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
        //IPSSearchRegistry::set('forums.vncForumFilters', $vncPrefs['forums']['vnc_forum_filter'] );
        IPSSearchRegistry::set('forums.vncForumFilters', $this->request['search_app_filters']['forums']['forums'] );

        /* Set period up */
        IPSSearchRegistry::set('in.period'           , $vncPrefs[ IPSSearchRegistry::get('in.search_app') ]['view'] );
        IPSSearchRegistry::set('in.vncFollowFilterOn', $vncPrefs[ IPSSearchRegistry::get('in.search_app') ]['vncFollowFilter'] );

        $this->request['userMode']  = !empty($this->request['userMode']) ? $this->request['userMode'] : '';
        IPSSearchRegistry::set('in.userMode'  , $this->request['userMode'] );

        /* Update member cache
        if ( isset( $this->request['period'] ) AND isset( $this->request['change'] ) )
        {
            IPSMember::setToMemberCache( $this->memberData, array( 'vncPrefs' => $vncPrefs ) );
        }
        */

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

            /* Get templates to use
            $template = $this->searchController->fetchTemplates();
            */

            /* Fetch sort details */
            $sortDropDown = $this->searchController->fetchSortDropDown();

            /* Fetch sort details */
            $sortIn       = $this->searchController->fetchSortIn();

            /* Reset for template */
            $this->_resetRequestParameters();

            if( IPSSearchRegistry::get('in.start') > 0 AND !count($results) )
            {
                $new_url    = 'app=core&amp;module=search&amp;do=viewNewContent&amp;period=' . IPSSearchRegistry::get('in.period') . '&amp;userMode=' . IPSSearchRegistry::get('in.userMode') . '&amp;search_app=' . IPSSearchRegistry::get('in.search_app')  . '&amp;sid=' . $this->request['_sid'];
                $new_url    .= '&amp;st=' . ( IPSSearchRegistry::get('in.start') - IPSSearchRegistry::get('opt.search_per_page') ) . '&amp;search_app_filters[' . IPSSearchRegistry::get('in.search_app') . '][searchInKey]=' . $this->request['search_app_filters'][ IPSSearchRegistry::get('in.search_app') ]['searchInKey'];

                //$this->registry->output->silentRedirect( $this->settings['base_url'] . $new_url );
            }

            /* Parse result set
            $results = $this->registry->output->getTemplate( $template['group'] )->$template['template']( $results, ( IPSSearchRegistry::get('opt.searchType') == 'titles' || IPSSearchRegistry::get('opt.noPostPreview') ) ? 1 : 0 );
            */

            /* Build pagination */
            $links = $this->registry->output->generatePagination( array( 'totalItems'        => $count,
                                                                         'itemsPerPage'      => IPSSearchRegistry::get('opt.search_per_page'),
                                                                         'currentStartValue' => IPSSearchRegistry::get('in.start'),
                                                                         //'method'          => 'nextPrevious',
                                                                         'baseUrl'           => 'app=core&amp;module=search&amp;do=viewNewContent&amp;period=' . IPSSearchRegistry::get('in.period') . '&amp;userMode=' . IPSSearchRegistry::get('in.userMode') . '&amp;search_app=' . IPSSearchRegistry::get('in.search_app')  . '&amp;sid=' . $this->request['_sid'] . $this->_returnSearchAppFilters() ) );

            /* Showing */
            $showing = array( 'start' => IPSSearchRegistry::get('in.start') + 1, 'end' => ( IPSSearchRegistry::get('in.start') + IPSSearchRegistry::get('opt.search_per_page') ) > $count ? $count : IPSSearchRegistry::get('in.start') + IPSSearchRegistry::get('opt.search_per_page') );

            // tapatalk add
            $results = $this->searchController->getRawResultSet();
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

        $this->result = array(
            'count' => $count,
            'list'  => $results,
        );

        /* Output
        $this->title   = $this->lang->words['new_posts_title'];
        $this->registry->output->addNavigation( $this->lang->words['new_posts_title'], '' );
        $this->output .= $this->registry->output->getTemplate( 'search' )->newContentView( $results, $links, $count, $sortDropDown, $sortIn, IPSSearchRegistry::get('set.resultCutToDate') );
        */
    }

    public function incorrectPageCallback( $paginationData )
    {
        $this->registry->output->showError( 'page_doesnt_exist', 'acf-ipc-1', null, null, 404 );
    }
    
    // tapatalk add
    public function setResultPreview( $showLastPost = true )
    {
        if (isset($this->result['list']) && !empty($this->result['list']) && is_array($this->result['list']))
        {
            /* Init */
            if ( ! $this->registry->isClassLoaded('topics') && $this->result['showtopic'])
            {
                $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . "/sources/classes/topics.php", 'app_forums_classes_topics', 'forums' );
                $this->registry->setClass( 'topics', new $classToLoad( $this->registry ) );
            }
            
            // parser for post preview
            $classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/text/parser.php', 'classes_text_parser' );
            $parser = new $classToLoad();
            $parser->set( array( 'parseArea'      => 'topics',
                                 'memberData'     => $this->memberData,
                                 'parseBBCode'    => 1,
                                 'parseHtml'      => 0,
                                 'parseEmoticons' => 1 ) );
            
            foreach($this->result['list'] as &$result)
            {
                if ( $this->result['showtopic'] && $this->registry->class_forums->forumsCheckAccess( $result['forum_id'], 0, 'topic', $result, true ) !== true )
                {
                    $preview = '';
                }
                else if ($this->result['showtopic'] && $showLastPost && $result['start_date'] != $result['last_post'])
                {
                    /* Get last post */
                    $_post = $this->registry->topics->getPosts( array( 'onlyViewable'    => true,
                                                                       'skipForumCheck'  => true,
                                                                       'sortField'       => 'post_date',
                                                                       'sortOrder'       => 'desc',
                                                                       'topicId'         => array( $result['tid'] ),
                                                                       'limit'           => 1,
                                                                       'archiveToNative' => true,
                                                                       'isArchivedTopic' => $this->registry->topics->isArchived( $result ) ) );
                    
                    $last_post = array_pop( $_post );
                    $preview = $last_post['post'];
                }
                else
                {
                    $preview = $result['post'];
                }
                
                $preview = $parser->stripQuotes( $preview );
                $preview = $parser->display( $preview );
                $preview = IPSText::truncate( IPSText::getTextClass( 'bbcode' )->stripAllTags( strip_tags( $preview, '<br>' ) ), 500 );
                $result['preview'] = $preview;
            }
        }
    }
}
