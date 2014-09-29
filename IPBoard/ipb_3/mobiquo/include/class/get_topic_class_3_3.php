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
require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/forums/forums.php');

class forum_topic extends public_forums_forums_forums
{
    public function doExecute( ipsRegistry $registry )
    {
        $this->initForums();

        if( ! $this->forum['id'] )
        {
            //$this->registry->getClass('output')->showError( 'forums_no_id', 10333, null, null, 404 );
            get_error('forums_no_id');
        }

        $this->buildPermissions();

        if( !empty( $this->forum['redirect_on'] ) )
        {
            $redirect = $this->DB->buildAndFetch( array( 'select' => 'redirect_url', 'from' => 'forums', 'where' => "id=".$this->forum['id']) );

            if( $redirect['redirect_url'] )
            {
                get_error('Link forum');
            }
        }

        if( empty( $this->request['L'] ) )
        {
            $this->registry->getClass('class_forums')->forumsCheckAccess( $this->forum['id'], 1 );
        }

        // tapatalk add
        $this->settings['display_max_topics'] = $this->request['perpage'];

        $data = array();

        if ( $this->forum['sub_can_post'] )
        {
            $data = $this->showForum();
        }

        $this->forum['_user_can_post'] = $this->registry->class_forums->canStartTopic( $this->forum['id'] );


        // prepare output for tapatalk
        $perm_id = $this->memberData['org_perm_id'] ? $this->memberData['org_perm_id'] : $this->memberData['g_perm_id'];
        $perm_array = explode( ",", $perm_id );
        $can_upload = $this->registry->permissions->check( 'upload', $this->forum, $perm_array ) === TRUE && $this->memberData['g_attach_max'] != -1;
        $can_post = $this->forum['_user_can_post'];
        
		$read_only_forums = explode(",", ipsRegistry::$settings['tapatalk_forum_read']);
		if(empty($read_only_forums) || !is_array($read_only_forums))
		{
			$read_only_forums = array();
		}
		if(in_array($this->forum['id'], $read_only_forums))
		{
			$can_post = false;
		}
		
        $topics = array (
            'forum_id'      => $this->forum['id'],
            'forum_name'    => $this->forum['name'],
            'can_post'      => $can_post,
            'can_upload'    => $can_upload,
        );


        $topic_list = array();

        if ($this->request['topicfilter'] == 'ann')
        {
            $announce_data = $data['announce_data'];
            $total_num = count($announce_data);

            if (!empty($announce_data))
            {
                $announce_data = array_slice($announce_data, $this->request['st'], $this->settings['display_max_topics']);

                foreach($announce_data as $announce)
                {
                    $announce_id = $announce['announce_id'];
                    $post_data = $this->DB->buildAndFetch( array(
                                                        'select' => 'announce_post, announce_html_enabled',
                                                        'from'   => 'announcements',
                                                        'where'  => "announce_id='{$announce_id}'")    );
                    $short_content = get_short_content( $post_data['announce_post'], $post_data['announce_html_enabled']);

                    $xmlrpc_topic = new xmlrpcval(array(
                        'forum_id'          => new xmlrpcval($announce['forum_id'], 'string'),
                        'topic_id'          => new xmlrpcval('ann_' . $announce_id, 'string'),
                        'topic_title'       => new xmlrpcval(subject_clean($announce['announce_title']) , 'base64'),
                        'topic_author_id'   => new xmlrpcval($announce['member_id'], 'string'),
                        'topic_author_name' => new xmlrpcval(subject_clean($announce['member_name']), 'base64'),
                        'icon_url'          => new xmlrpcval($announce['pp_main_photo'], 'string'),
                        'reply_number'      => new xmlrpcval(0, 'int'),
                        'view_number'       => new xmlrpcval($announce['announce_views'], 'int'),
                        'short_content'     => new xmlrpcval($short_content, 'base64'),
                    ), 'struct');

                    $topic_list[] = $xmlrpc_topic;
                }
            }

            $topics['total_topic_num'] = $total_num;
            $topics['topics'] = $topic_list;
        }
        else
        {
            global $app_version;
            $total_num = $data['other_data']['totalItems'];
            
            if (!empty($data['topic_data']))
            {
                foreach($data['topic_data'] as $topic)
                {
                    $post = $this->registry->getClass('topics')->getPostById( $topic['topic_firstpost'] );
                    $preview = IPSText::truncate( IPSText::getTextClass( 'bbcode' )->stripAllTags( $post['post'] ), 200 );
                    $preview = preg_replace('/[\n\r\t]+/', ' ', $preview);
                    
                    $has_attach = $topic['topic_hasattach'] ? 1 : 0;
                    $new_post = $topic['_hasUnread'] ? true : false;
                    $is_subscribed = is_subscribed($topic['tid']);
                    $can_subscribe = $this->memberData['member_id'] ? true : false;
                    $is_closed = $topic['state'] == 'closed' ? true : false;
                    $is_sticky = $topic['pinned'] == 1;
                    $is_approved = $topic['approved'] > 0;
                    
                    $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = false;
                    if (!$topic['_isArchived'])
                    {
                        if ($this->memberData['g_is_supmod']) {
                            $can_rename = $can_stick = $can_close = $can_move = $can_approve = $can_delete = true;
                        } else if ($this->memberData['is_mod']) {
                            $permission = $this->memberData['forumsModeratorData'][ $topic['forum_id'] ];
                            
                            $can_rename = $permission['edit_topic'];
                            $can_move = $permission['move_topic'] && $topic['state'] != 'link';
                            $can_delete = $permission['delete_topic'];
                            
                            $can_stick = $is_sticky ? $permission['unpin_topic'] : $permission['pin_topic'];
                            $can_close = $is_closed ? $permission['open_topic'] : $permission['close_topic'];
                            
                            if (version_compare($app_version, '3.3.0', '>='))
                                $can_approve = $is_approved ? $this->forum['permissions']['TopicSoftDelete'] : $this->forum['permissions']['TopicSoftDeleteRestore']; // hide
                            else
                                $can_approve = $permission['topic_q']; // invisible
                        } else if ($this->memberData['member_id'] == $topic['starter_id'] && $this->memberData['g_edit_posts']) {
                            if ( $this->memberData['g_edit_cutoff'] > 0 )
                            {
                                if ( $topic['start_date'] > ( IPS_UNIX_TIME_NOW - ( intval($this->memberData['g_edit_cutoff']) * 60 ) ) )
                                {
                                    $can_rename = true;
                                }
                            }
                            else
                            {
                                $can_rename = true;
                            }
                        }
                        
                        if ( ( $topic['state'] != 'open' ) and ( ! $this->memberData['g_is_supmod'] AND ! $permission['edit_post'] ) )
                        {
                            if ( $this->memberData['g_post_closed'] != 1 )
                            {
                                $can_rename = false;
                            }
                        }
                    }

                    $xmlrpc_topic = array(
                        'forum_id'          => new xmlrpcval($topic['forum_id'], 'string'),
                        'topic_id'          => new xmlrpcval($topic['tid'], 'string'),
                        'topic_title'       => new xmlrpcval(subject_clean($topic['title']) , 'base64'),
                        'topic_author_id'   => new xmlrpcval($topic['starter_id'], 'string'),
                        'topic_author_name' => new xmlrpcval(subject_clean($topic['starter_name']), 'base64'),
                        'icon_url'          => new xmlrpcval($topic['_starter']['pp_main_photo'], 'string'),
                        'last_reply_time'   => new xmlrpcval(mobiquo_iso8601_encode($topic['last_post']), 'dateTime.iso8601'),
                    	'timestamp'         => new xmlrpcval(intval($topic['last_post']), 'string'),
                        'reply_number'      => new xmlrpcval(intval($topic['posts']), 'int'),
                        'view_number'       => new xmlrpcval(intval($topic['views']), 'int'),
                        'short_content'     => new xmlrpcval(subject_clean($preview), 'base64'),
                        'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
                    );
                    
                    if ($has_attach)    $xmlrpc_topic['attachment']     = new xmlrpcval('1');
                    if ($new_post)      $xmlrpc_topic['new_post']       = new xmlrpcval(true, 'boolean');
                    if ($is_subscribed) $xmlrpc_topic['is_subscribed']  = new xmlrpcval(true, 'boolean');
                    else $xmlrpc_topic['is_subscribed']  = new xmlrpcval(false, 'boolean');
                    if ($can_subscribe) $xmlrpc_topic['can_subscribe']  = new xmlrpcval(true, 'boolean');
                    else $xmlrpc_topic['can_subscribe']  = new xmlrpcval(false, 'boolean');
                    if ($is_sticky)     $xmlrpc_topic['is_sticky']      = new xmlrpcval(true, 'boolean');
                    if ($is_closed)     $xmlrpc_topic['is_closed']      = new xmlrpcval(true, 'boolean');
                    
                    if ($can_rename)    $xmlrpc_topic['can_rename']     = new xmlrpcval(true, 'boolean');
                    if ($can_stick)     $xmlrpc_topic['can_stick']      = new xmlrpcval(true, 'boolean');
                    if ($can_close)     $xmlrpc_topic['can_close']      = new xmlrpcval(true, 'boolean');
                    if ($can_move)      $xmlrpc_topic['can_move']       = new xmlrpcval(true, 'boolean');
                    if ($can_approve)   $xmlrpc_topic['can_approve']    = new xmlrpcval(true, 'boolean');
                    if ($can_delete)    $xmlrpc_topic['can_delete']     = new xmlrpcval(true, 'boolean');
                    
                    $topic_list[] = new xmlrpcval($xmlrpc_topic, 'struct');
                }
            }
        }

        $topics = array (
            'total_topic_num' => $total_num,
            'forum_id'   => $this->forum['id'],
            'forum_name' => $this->forum['name'],
            'can_post'   => $can_post,
            'can_upload' => $can_upload,
            'topics'     => $topic_list,
        );

        return $topics;
    }

    /**
     * Builds an array of forum data for use in the output template
     *
     * @return    array
     */
    public function renderForum()
    {
        //-----------------------------------------
        // INIT
        //-----------------------------------------

        $this->request['st'] = $this->request['changefilters'] ? 0 : ( isset($this->request['st']) ? intval($this->request['st']) : 0 );
        $announce_data  = array();
        $topic_data     = array();
        $other_data     = array();
        $multi_mod_data = array();
        $footer_filter  = array();
        $member_ids     = array();

        //-----------------------------------------
        // Show?
        //-----------------------------------------

        if ( isset( $this->request['show'] ) AND $this->request['show'] == 'sinceLastVisit' )
        {
            $this->request['prune_day'] = 200;
        }

        //-----------------------------------------
        // Are we actually a moderator for this forum?
        //-----------------------------------------

        $mod = $this->memberData['forumsModeratorData'];

        if ( ! $this->memberData['g_is_supmod'] )
        {
            if( ! isset( $mod[ $this->forum['id'] ] ) OR ! is_array( $mod[ $this->forum['id'] ] ) )
            {
                $this->memberData['is_mod'] = 0;
            }
        }

        //-----------------------------------------
        // Announcements
        //-----------------------------------------

        if( is_array( $this->registry->cache()->getCache('announcements') ) and count( $this->registry->cache()->getCache('announcements') ) )
        {
            $announcements = array();

            foreach( $this->registry->cache()->getCache('announcements') as $announce )
            {
                $order = $announce['announce_start'] ? $announce['announce_start'].','.$announce['announce_id'] : $announce['announce_id'];

                if(  $announce['announce_forum'] == '*' )
                {
                    $announcements[ $order ] = $announce;
                }
                else if( strstr( ','.$announce['announce_forum'].',', ','.$this->forum['id'].',' ) )
                {
                    $announcements[ $order ] = $announce;
                }
            }

            if( count( $announcements ) )
            {
                //-----------------------------------------
                // sort by start date
                //-----------------------------------------

                krsort( $announcements );

                foreach( $announcements as $announce )
                {
                    if ( $announce['announce_start'] )
                    {
                        $announce['announce_start'] = $this->lang->getDate( $announce['announce_start'], 'date' );
                    }
                    else
                    {
                        $announce['announce_start'] = '--';
                    }

                    $announce['announce_title'] = IPSText::stripslashes($announce['announce_title']);
                    $announce['forum_id']       = $this->forum['id'];
                    $announce['announce_views'] = intval($announce['announce_views']);
                    $announce_data[] = $announce;

                    $member_ids[ $announce['member_id'] ] = $announce['member_id'];
                }

                $this->forum['_showAnnouncementsBar'] = 1;
            }
        }

        //-----------------------------------------
        // Read topics
        //-----------------------------------------

        $First   = intval($this->request['st']);

        //-----------------------------------------
        // Sort options
        //-----------------------------------------

        $cookie_prune    = IPSCookie::get( $this->forum['id'] . "_prune_day" );
        $cookie_sort    = IPSCookie::get( $this->forum['id'] . "_sort_key" );
        $cookie_sortb    = IPSCookie::get( $this->forum['id'] . "_sort_by" );
        $cookie_fill    = IPSCookie::get( $this->forum['id'] . "_topicfilter" );

        $prune_value    = $this->selectVariable( array(
                                                1 => ! empty( $this->request['prune_day'] ) ? $this->request['prune_day'] : NULL,
                                                2 => !empty($cookie_prune) ? $cookie_prune : NULL,
                                                3 => $this->forum['prune'],
                                                4 => '100' )
                                        );

        $sort_key        = $this->selectVariable( array(
                                                1 => ! empty( $this->request['sort_key'] ) ? $this->request['sort_key'] : NULL,
                                                2 => !empty($cookie_sort) ? $cookie_sort : NULL,
                                                3 => $this->forum['sort_key'],
                                                4 => 'last_post' )
                                       );

        $sort_by        = $this->selectVariable( array(
                                                1 => ! empty( $this->request['sort_by'] ) ? $this->request['sort_by'] : NULL,
                                                2 => !empty($cookie_sortb) ? $cookie_sortb : NULL,
                                                3 => $this->forum['sort_order'],
                                                4 => 'Z-A' )
                                       );

        $topicfilter    = $this->selectVariable( array(
                                                1 => ! empty( $this->request['topicfilter'] ) ? $this->request['topicfilter'] : NULL,
                                                2 => !empty($cookie_fill) ? $cookie_fill : NULL,
                                                3 => $this->forum['topicfilter'],
                                                4 => 'all' )
                                       );

        if( ! empty( $this->request['remember'] ) )
        {
            if( $this->request['prune_day'] )
            {
                IPSCookie::set( $this->forum['id'] . "_prune_day", $this->request['prune_day'] );
            }

            if( $this->request['sort_key'] )
            {
                IPSCookie::set( $this->forum['id'] . "_sort_key", $this->request['sort_key'] );
            }

            if( $this->request['sort_by'] )
            {
                IPSCookie::set( $this->forum['id'] . "_sort_by", $this->request['sort_by'] );
            }

            if( $this->request['topicfilter'] )
            {
                IPSCookie::set( $this->forum['id'] . "_topicfilter", $this->request['topicfilter'] );
            }
        }

        //-----------------------------------------
        // Figure out sort order, day cut off, etc
        //-----------------------------------------

        $Prune = $prune_value < 100 ? (time() - ($prune_value * 60 * 60 * 24)) : ( ( $prune_value == 200 AND $this->memberData['member_id'] ) ? $this->memberData['last_visit'] : 0 );

        $sort_keys        =  array( 'last_post'    => 'sort_by_date',
                               'last_poster_name'  => 'sort_by_last_poster',
                               'title'             => 'sort_by_topic',
                               'starter_name'      => 'sort_by_poster',
                               'start_date'        => 'sort_by_start',
                               'topic_hasattach'   => 'sort_by_attach',
                               'posts'             => 'sort_by_replies',
                               'views'             => 'sort_by_views',

                             );

        $prune_by_day    = array( '1' => 'show_today',
                               '5'    => 'show_5_days',
                               '7'    => 'show_7_days',
                               '10'   => 'show_10_days',
                               '15'   => 'show_15_days',
                               '20'   => 'show_20_days',
                               '25'   => 'show_25_days',
                               '30'   => 'show_30_days',
                               '60'   => 'show_60_days',
                               '90'   => 'show_90_days',
                               '100'  => 'show_all',
                               '200'  => 'show_last_visit'
                             );

        $sort_by_keys = array( 'Z-A'   => 'descending_order',
                                'A-Z'  => 'ascending_order',
                             );

        $filter_keys  = array( 'all'    => 'topicfilter_all',
                               'open'   => 'topicfilter_open',
                               'hot'    => 'topicfilter_hot',
                               'poll'   => 'topicfilter_poll',
                               'locked' => 'topicfilter_locked',
                               'moved'  => 'topicfilter_moved',
                               
                               // tapatalk add
                               'ann'    => 'topicfilter_announce',
                               'top'    => 'topicfilter_pinned',
                             );

        if( $this->memberData['member_id'] )
        {
            $filter_keys['istarted'] = 'topicfilter_istarted';
            $filter_keys['ireplied'] = 'topicfilter_ireplied';
        }

        //-----------------------------------------
        // check for any form funny business by wanna-be hackers
        //-----------------------------------------

        if( ( ! isset( $filter_keys[$topicfilter] ) ) or ( ! isset( $sort_keys[$sort_key] ) ) or ( ! isset( $prune_by_day[$prune_value] ) ) or ( ! isset( $sort_by_keys[$sort_by] ) ) )
        {
            //$this->registry->getClass('output')->showError( 'forums_bad_filter', 10339 );
        }

        $r_sort_by = $sort_by == 'A-Z' ? 'ASC' : 'DESC';

        //-----------------------------------------
        // If sorting by starter, add secondary..
        //-----------------------------------------
        $sort_key_chk = $sort_key;

        if( $sort_key == 'starter_name' )
        {
            $sort_key    = "starter_name {$r_sort_by}, t.last_post DESC";
            $r_sort_by    = '';
        }

        //-----------------------------------------
        // Additional queries?
        //-----------------------------------------

        $add_query_array = array();
        $add_query = "";

        switch( $topicfilter )
        {
            case 'all':
                break;
            case 'open':
                $add_query_array[] = "t.state='open'";
                break;
            case 'hot':
                $add_query_array[] = "t.state='open' AND t.posts + 1 >= ".intval($this->settings['hot_topic']);
                break;
            case 'locked':
                $add_query_array[] = "t.state='closed'";
                break;
            case 'moved':
                $add_query_array[] = "t.state='link'";
                break;
            case 'poll':
                $add_query_array[] = "(t.poll_state='open' OR t.poll_state=1)";
                break;
            
            // tapatalk add
            case 'ann':
                $add_query_array[] = "0";
                break;
            case 'top':
                $add_query_array[] = "t.pinned='1'";
                break;
            
            default:
                $add_query_array[] = "t.pinned='0'";
                break;
        }

        if( ! $this->memberData['g_other_topics'] or $topicfilter == 'istarted' OR ( ! $this->forum['can_view_others'] AND ! $this->memberData['is_mod'] ) )
        {
            $add_query_array[] = "t.starter_id='".$this->memberData['member_id']."'";
        }

        $_SQL_EXTRA = '';
        $_SQL_APPROVED = '';
        $_SQL_AGE_PRUNE = '';

        if( count($add_query_array) )
        {
            $_SQL_EXTRA = ' AND '. implode( ' AND ', $add_query_array );
        }

        //-----------------------------------------
        // Moderator?
        //-----------------------------------------

        $this->request['modfilter'] = isset( $this->request['modfilter'] ) ? $this->request['modfilter'] : '';

        if ( $this->memberData['is_mod'] )
        {
            if ( $this->request['modfilter'] == 'unapproved' )
            {
                $_SQL_APPROVED = ' AND (' . $this->registry->class_forums->fetchTopicHiddenQuery(array('hidden'), 't.') . ' OR t.topic_queuedposts )';
            }
            elseif ( $this->permissions['TopicSoftDeleteSee'] )
            {
                if ( $this->request['modfilter'] == 'hidden' )
                {
                    $_SQL_APPROVED = ' AND (' . $this->registry->class_forums->fetchTopicHiddenQuery(array('sdeleted'), 't.') . ' OR t.topic_deleted_posts )';
                }
                else
                {
                    $_SQL_APPROVED = ' AND ' . $this->registry->class_forums->fetchTopicHiddenQuery(array('visible', 'hidden', 'sdeleted'), 't.');
                }
            }
            else
            {
                $_SQL_APPROVED = ' AND ' . $this->registry->class_forums->fetchTopicHiddenQuery(array('visible', 'hidden'), 't.');
            }
        }
        else
        {
            if ( $this->permissions['TopicSoftDeleteSee'] )
            {
                $_SQL_APPROVED = ' AND ' . $this->registry->class_forums->fetchTopicHiddenQuery(array('visible', 'sdeleted'), 't.');
            }
            else
            {
                $_SQL_APPROVED = ' AND ' . $this->registry->class_forums->fetchTopicHiddenQuery(array('visible'), 't.');
            }
        }

        if ( $Prune )
        {
            if ( $prune_value == 200 )
            {
                /* Just new content, don't show pinned, please */
                if( $this->memberData['is_mod'] AND $this->request['modfilter'] )
                {
                    $_SQL_AGE_PRUNE = " AND (t.last_post > {$Prune} OR t.approved=0)";
                }
                else
                {
                    $_SQL_AGE_PRUNE = " AND (t.last_post > {$Prune})";
                }
            }
            else
            {
                if( $this->memberData['is_mod'] AND $this->request['modfilter'] )
                {
                    $_SQL_AGE_PRUNE = " AND (t.pinned=1 or t.last_post > {$Prune} OR t.approved=0)";
                }
                else
                {
                    $_SQL_AGE_PRUNE = " AND (t.pinned=1 or t.last_post > {$Prune})";
                }
            }
        }

        //-----------------------------------------
        // Query the database to see how many topics there are in the forum
        //-----------------------------------------

        if( $topicfilter == 'ireplied' )
        {
            //-----------------------------------------
            // Checking topics we've replied to?
            //-----------------------------------------

            $this->DB->build( array( 'select'   => 'COUNT(' . $this->DB->buildDistinct( 'p.topic_id' ) . ') as max',
                                     'from'     => array( 'topics' => 't' ),
                                     'where'    => "t.forum_id={$this->forum['id']} AND p.author_id=".$this->memberData['member_id'] . " AND p.new_topic=0" . $_SQL_APPROVED . $_SQL_AGE_PRUNE,
                                     'add_join' => array( array( 'from' => array( 'posts' => 'p' ),
                                                                 'where' => 'p.topic_id=t.tid' ) ) ) );
            $this->DB->execute();

            $total_possible = $this->DB->fetch();
        }
        else if ( $_SQL_EXTRA or $_SQL_AGE_PRUNE OR $this->request['modfilter'] )
        {
            $this->DB->build( array(  'select' => 'COUNT(*) as max',
                                      'from'   => 'topics t',
                                      'where'  => "t.forum_id=" . $this->forum['id'] . $_SQL_APPROVED . $_SQL_AGE_PRUNE . $_SQL_EXTRA ) );

            $this->DB->execute();

            $total_possible = $this->DB->fetch();
        }
        else
        {
            $total_possible['max'] = $this->memberData['is_mod'] ? $this->forum['topics'] + $this->forum['queued_topics'] : $this->forum['topics'];

            if ( $this->permissions['TopicSoftDeleteSee'] AND $this->forum['deleted_topics'] )
            {
                $total_possible['max'] += intval( $this->forum['deleted_topics'] );
            }

            $Prune = 0;
        }

        //-----------------------------------------
        // Generate the forum page span links
        //-----------------------------------------

        $_extraStuff    = '';

        if( $this->request['modfilter'] )
        {
            $_extraStuff    .= "&amp;modfilter=" . $this->request['modfilter'];
        }

        $this->forum['SHOW_PAGES'] = $this->registry->getClass('output')->generatePagination( array( 'totalItems'       => $total_possible['max'],
                                                                                                     'itemsPerPage'     => $this->settings['display_max_topics'],
                                                                                                     'currentStartValue'=> $this->request['st'],
                                                                                                     'seoTitle'         => $this->forum['name_seo'],
                                                                                                     'showNumbers'      => false,
                                                                                                     'disableSinglePage'=> false,
                                                                                                     'baseUrl'          => "showforum=".$this->forum['id']."&amp;prune_day={$prune_value}&amp;sort_by={$sort_by}&amp;sort_key={$sort_key_chk}&amp;topicfilter={$topicfilter}{$_extraStuff}" )    );

        //-----------------------------------------
        // Start printing the page
        //-----------------------------------------

        $other_data = array( 'forum_data'       => $this->forum,
                             'totalItems'       => $total_possible['max'],
                             'hasMore'          => ( $this->request['st'] + $this->settings['display_max_topics'] > $total_possible['max'] ) ? false : true,
                             'can_edit_topics'  => $this->can_edit_topics,
                             'can_open_topics'  => $this->can_open_topics,
                             'can_close_topics' => $this->can_close_topics,
                             'can_move_topics'  => $this->can_move_topics );

        $total_topics_printed = 0;

        //-----------------------------------------
        // Get main topics
        //-----------------------------------------

        $topic_array = array();
        $topic_ids   = array();
        $topic_sort  = "";

        //-----------------------------------------
        // Cut off?
        //-----------------------------------------

        $parse_dots = 1;

        if( $topicfilter == 'ireplied' )
        {
            //-----------------------------------------
            // Checking topics we've replied to?
            // No point in getting dots again...
            //-----------------------------------------

            $parse_dots = 0;

            $_joins    = array( array( 'select'    => 't.*',
                                     'from'        => array( 'posts' => 'p' ),
                                     'where'    => 'p.topic_id=t.tid AND p.author_id=' . $this->memberData['member_id'] ) );

            if ( $this->settings['tags_enabled'] AND !$this->forum['bw_disable_tagging'] )
            {
                $_joins[]    = $this->registry->tags->getCacheJoin( array( 'meta_id_field' => 't.tid' ) );
            }

            // For some reason, mySQL doesn't like the distinct + t.* being in reverse order...
            $this->DB->build( array( 'select'    => $this->DB->buildDistinct( 'p.author_id' ),
                                     'from'        => array( 'topics' => 't' ),
                                     'where'    => "t.forum_id=" . $this->forum['id'] . " AND t.pinned IN (0,1)" . $_SQL_APPROVED . $_SQL_AGE_PRUNE . " AND p.new_topic=0",
                                     'order'    => "t.pinned desc,{$topic_sort} t.{$sort_key} {$r_sort_by}",
                                     'limit'    => array( intval($First), intval($this->settings['display_max_topics']) ),
                                     'add_join'    => $_joins ) );
            $this->DB->execute();
        }
        else
        {
            $this->DB->build( array( 'select'   => 't.*',
                                     'from'     => array( 'topics' =>  't' ),
                                     'where'    => "t.forum_id=" . $this->forum['id'] . " AND t.pinned IN (0,1)" . $_SQL_APPROVED . $_SQL_AGE_PRUNE . $_SQL_EXTRA,
                                     'order'    => 't.pinned DESC, '.$topic_sort.' t.'.$sort_key .' '. $r_sort_by,
                                     'limit'    => array( intval($First), $this->settings['display_max_topics'] ),
                                     'add_join' => ( $this->settings['tags_enabled'] AND !$this->forum['bw_disable_tagging'] ) ? array( $this->registry->tags->getCacheJoin( array( 'meta_id_field' => 't.tid' ) ) ) : array()
                             )        );
            $this->DB->execute();
        }

        while( $t = $this->DB->fetch() )
        {
            $topic_array[ $t['tid'] ] = $t;
            $topic_ids[ $t['tid'] ]   = $t['tid'];

            if ( $t['last_poster_id'] )
            {
                $member_ids[ $t['last_poster_id'] ]    = $t['last_poster_id'];
            }

            if ( $t['starter_id'] )
            {
                $member_ids[ $t['starter_id'] ]    = $t['starter_id'];
            }
        }

        ksort( $topic_ids );

        //-----------------------------------------
        // Are we dotty?
        //-----------------------------------------

        if( ( $this->settings['show_user_posted'] == 1 ) and ( $this->memberData['member_id'] ) and ( count($topic_ids) ) and ( $parse_dots ) )
        {
            $_queued    = $this->registry->class_forums->fetchPostHiddenQuery( array( 'visible' ), '' );

            $this->DB->build( array( 'select' => 'author_id, topic_id',
                                     'from'   => 'posts',
                                     'where'  => $_queued . ' AND author_id=' . $this->memberData['member_id'] . ' AND topic_id IN(' . implode( ',', $topic_ids ) . ')' )    );

            $this->DB->execute();

            while( $p = $this->DB->fetch() )
            {
                if ( is_array( $topic_array[ $p['topic_id'] ] ) )
                {
                    $topic_array[ $p['topic_id'] ]['author_id'] = $p['author_id'];
                }
            }
        }

        //-----------------------------------------
        // Get needed members
        //-----------------------------------------

        if( count($member_ids) )
        {
            $_members    = IPSMember::load( $member_ids );

            //-----------------------------------------
            // Add member data to announcements
            //-----------------------------------------

            $new_announces    = array();

            foreach( $announce_data as $announce )
            {
                $announce    = array_merge( $announce, IPSMember::buildDisplayData( $_members[ $announce['member_id'] ] ) );

                $new_announces[]    = $announce;
            }

            $announce_data    = $new_announces;
        }

        //-----------------------------------------
        // Show meh the topics!
        //-----------------------------------------

        $adCodeSet    = false;

        foreach( $topic_array as $topic )
        {
            /* Add member */
            if( $topic['last_poster_id'] )
            {
                $topic = array_merge( IPSMember::buildDisplayData( $_members[ $topic['last_poster_id'] ] ), $topic );
            }
            else
            {
                $topic = array_merge( IPSMember::buildProfilePhoto( array() ), $topic );
            }

            if ( $topic['starter_id'] )
            {
                $topic['_starter'] = IPSMember::buildDisplayData( $_members[ $topic['starter_id'] ] );
            }

            /* AD Code */
            if( $this->registry->getClass('IPSAdCode')->userCanViewAds() && ! $adCodeSet )
            {
                $topic['_adCode'] = $this->registry->getClass('IPSAdCode')->getAdCode('ad_code_forum_view_topic_code');
                if( $topic['_adCode'] )
                {
                    $adCodeSet = true;
                }
            }

            if ( $topic['pinned'] )
            {
                $this->pinned_topic_count++;
            }

            $topic_data[ $topic['tid'] ] = $this->renderEntry( $topic );

            $total_topics_printed++;
        }

        //-----------------------------------------
        // Finish off the rest of the page  $filter_keys[$topicfilter]))
        //-----------------------------------------

        $sort_by_html    = "";
        $sort_key_html    = "";
        $prune_day_html    = "";
        $filter_html    = "";

        foreach( $sort_by_keys as $k => $v )
        {
            $sort_by_html   .= $k == $sort_by      ? "<option value='$k' selected='selected'>{$this->lang->words[ $sort_by_keys[ $k ] ]}</option>\n"
                                                   : "<option value='$k'>{$this->lang->words[ $sort_by_keys[ $k ] ]}</option>\n";
        }

        foreach( $sort_keys as  $k => $v )
        {
            $sort_key_html  .= $k == $sort_key_chk ? "<option value='$k' selected='selected'>{$this->lang->words[ $sort_keys[ $k ] ]}</option>\n"
                                                   : "<option value='$k'>{$this->lang->words[ $sort_keys[ $k ] ]}</option>\n";
        }

        foreach( $prune_by_day as  $k => $v )
        {
            $prune_day_html .= $k == $prune_value  ? "<option value='$k' selected='selected'>{$this->lang->words[ $prune_by_day[ $k ] ]}</option>\n"
                                                   : "<option value='$k'>{$this->lang->words[ $prune_by_day[ $k ] ]}</option>\n";
        }

        foreach( $filter_keys as  $k => $v )
        {
            $filter_html    .= $k == $topicfilter  ? "<option value='$k' selected='selected'>{$this->lang->words[ $filter_keys[ $k ] ]}</option>\n"
                                                   : "<option value='$k'>{$this->lang->words[ $filter_keys[ $k ] ]}</option>\n";
        }

        $footer_filter['sort_by']      = $sort_key_html;
        $footer_filter['sort_order']   = $sort_by_html;
        $footer_filter['sort_prune']   = $prune_day_html;
        $footer_filter['topic_filter'] = $filter_html;

        if( $this->memberData['is_mod'] )
        {
            $count = 0;
            $other_pages = 0;

            if( $this->request['selectedtids'] != "" )
            {
                $tids = explode( ",",$this->request['selectedtids'] );

                if( is_array( $tids ) AND count( $tids ) )
                {
                    foreach( $tids as $tid )
                    {
                        if( $tid != '' )
                        {
                            if( ! isset($topic_array[ $tid ]) )
                            {
                                $other_pages++;
                            }

                            $count++;
                        }
                    }
                }
            }

            $this->lang->words['f_go'] .= " ({$count})";

            if( $other_pages )
            {
                $this->lang->words['f_go'] .= " ({$other_pages} " . $this->lang->words['jscript_otherpage'] . ")";
            }
        }

        //-----------------------------------------
        // Multi-moderation?
        //-----------------------------------------

        if( $this->memberData['is_mod'] )
        {
            $mm_array = $this->registry->getClass('class_forums')->getMultimod( $this->forum['id'] );

            if ( is_array( $mm_array ) and count( $mm_array ) )
            {
                foreach( $mm_array as $m )
                {
                    $multi_mod_data[] = $m;
                }
            }
        }

        //-----------------------------------------
        // Need to update topics?
        //-----------------------------------------

        if( count( $this->update_topics_open ) )
        {
            $this->DB->update( 'topics', array( 'state' => 'open' ), 'tid IN ('.implode( ",", $this->update_topics_open ) .')' );
        }

        if( count( $this->update_topics_close ) )
        {
            $this->DB->update( 'topics', array( 'state' => 'closed' ), 'tid IN ('.implode( ",", $this->update_topics_close ) .')' );
        }

        /* Got soft delete tids? */
        if ( is_array( $this->_sdTids ) AND count( $this->_sdTids ) )
        {
            $other_data['sdData'] = IPSDeleteLog::fetchEntries( $this->_sdTids, 'topic', false );
        }

        /* Fetch follow data */
        $other_data['follow_data'] = $this->_like->render( 'summary', $this->forum['id'] );

        return array( 'announce_data'    => $announce_data,
                      'topic_data'        => $topic_data,
                      'other_data'        => $other_data,
                      'multi_mod_data'    => $multi_mod_data,
                      'footer_filter'    => $footer_filter,
                      'active_users'    => ( $this->settings['no_au_forum'] || ! $this->memberData['gbw_view_online_lists'] ) ? array( '_done' => 0 ) : $this->_generateActiveUserData() );
    }
}
