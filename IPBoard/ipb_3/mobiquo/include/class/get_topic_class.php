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
    public function doExecute( ipsRegistry $registry, $start_num = 0, $end_num = 0, $mode = '')
    {
        $this->request['f'] = intval($this->request['f'] );
        ######################################
        if( ! $this->request['f'] ) {
            get_error("Forum ID Error!");
        }

        $pinned = 0;
        if($mode == 'TOP') {
            $start_num = 0;
            $end_num = 19;
            $pinned = 1;
        } else {
            $pinned = 0;
        }
        $this->request['st'] = $start_num;
        $this->settings['display_max_topics'] = $end_num - $start_num + 1;
        $this->request['pinned'] = $pinned;
        ####################################
        $this->initForums();

        $this->forum = $this->registry->getClass('class_forums')->forum_by_id[ $this->request['f'] ];
        if( ! $this->forum['id'] ) {
            get_error("No Such Forum!");
        }

        if( isset( $this->forum['redirect_on'] ) AND $this->forum['redirect_on'] ) {
            get_error("Forum Redirect On!");
        }

        // Check forum access perms
        if( empty( $this->request['L'] ) ) {
            $result = $this->registry->getClass('class_forums')->forumsCheckAccess( $this->forum['id'], 1, 'forum', array(), true);
            if (!$result) {
                get_error("No Permission!");
            }
        }
        $this->forum['_user_can_post'] = 1;

        if( ! $this->registry->permissions->check( 'start', $this->forum ) )
        {
            $this->forum['_user_can_post'] = 0;
        }

        if( ! $this->forum['sub_can_post'] )
        {
            $this->forum['_user_can_post'] = 0;
        }

        if( $this->forum['min_posts_post'] && $this->forum['min_posts_post'] > $this->memberData['posts'] )
        {
            $this->forum['_user_can_post'] = 0;
        }

        if( ! $this->forum['status'] )
        {
            $this->forum['_user_can_post'] = 0;
        }

        if( ! $this->memberData['g_post_new_topics'] )
        {
            $this->forum['_user_can_post'] = 0;
        }


        // Are we viewing the forum, or viewing the forum rules?
        $data = array();
        if ( $this->forum['sub_can_post'] )
        {
            $data = $this->renderForum($mode);
        }
        return $data;
    }

    public function renderForum($mode)
    {
        //$this->request['st'] =  $this->request['changefilters'] ? 0 : ( isset($this->request['st']) ? intval($this->request['st']) : 0 );
        $announce_data  = array();
        $topic_data     = array();
        $other_data     = array();
        $multi_mod_data = array();
        $footer_filter  = array();

        // Show?
        /*
        if ( isset(  $this->request['show'] ) AND $this->request['show'] == 'sinceLastVisit' )
        {
            $this->request['prune_day'] = 200;
        }
        */
        // Are we actually a moderator for this forum?
        $mod = $this->memberData['forumsModeratorData'];
        if ( ! $this->memberData['g_is_supmod'] )
        {
            if( ! isset( $mod[ $this->forum['id'] ] ) OR ! is_array( $mod[ $this->forum['id'] ] ) )
            {
                $this->memberData['is_mod'] = 0;
            }
        }


        if ($mode == 'ANN') {
            if( is_array( $this->registry->cache()->getCache('announcements') ) and count( $this->registry->cache()->getCache('announcements') ) )            {
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
                            $announce['announce_start'] = gmstrftime( '%x', $announce['announce_start'] );
                        }
                        else
                        {
                            $announce['announce_start'] = '--';
                        }

                        $announce['announce_title'] = IPSText::stripslashes($announce['announce_title']);
                        $announce['forum_id']       = $this->forum['id'];
                        $announce['announce_views'] = intval($announce['announce_views']);
                        $announce_data[] = $announce;
                    }

                    $this->forum['_showAnnouncementsBar'] = 1;
                }
            }

             if (isset($announce_data) and count($announce_data)) {
                  $announce_data = array_slice($announce_data, $this->request['st'], $this->settings['display_max_topics']);
                 foreach($announce_data as $thread) {
                     // get short content.....
                      $topic_id = $thread['announce_id'];
                      $post_data = $this->DB->buildAndFetch( array(
                                                            'select' => 'announce_post, announce_html_enabled',
                                                            'from'   => 'announcements',
                                                            'where'  => "announce_id='{$topic_id}'")    );
                     $short_content = get_short_content( $post_data['announce_post'], $post_data['announce_html_enabled']);

                    $xmlrpc_topic = new xmlrpcval(array(
                        'forum_id'          => new xmlrpcval($thread['forum_id'], 'string'),
                        'topic_id'          => new xmlrpcval('ann_' . $thread['announce_id'], 'string'),
                        'topic_title'       => new xmlrpcval(subject_clean($thread['announce_title']) , 'base64'),
                        'topic_author_id'   => new xmlrpcval($thread['member_id'], 'string'),
                        'topic_author_name' => new xmlrpcval(mobi_unescape_html(to_utf8($thread['member_name'])), 'base64'),
                        'icon_url'          => new xmlrpcval(get_avatar($thread['member_id']), 'string'),
                        //'last_reply_time' => new xmlrpcval(mobiquo_iso8601_encode(), 'dateTime.iso8601'),
                        'reply_number'      => new xmlrpcval(0, 'int'),
                        'new_post'          => new xmlrpcval(false, 'boolean'),
                        'view_number'       => new xmlrpcval($thread['announce_views'], 'int'),
                        'short_content'     => new xmlrpcval($short_content, 'base64'),
                        //'can_delete'      => new xmlrpcval(false, 'boolean'),
                    ), 'struct');
                    $topic_list[] = $xmlrpc_topic;
                }

                $topics = array (
                    'total_topic_num' => $total_topic_num,
                    'topics' => $topic_list,
                );
            } else {
                $topics = array (
                    'total_topic_num' => $total_topic_num,
                    'topics' => array(),
                );
            }

            return $topics;
        }


        // Read topics
        $First   = intval($this->request['st']);
        // Sort options
        $cookie_prune = IPSCookie::get( $this->forum['id']."_prune_day" );
        $cookie_sort  = IPSCookie::get( $this->forum['id']."_sort_key" );
        $cookie_sortb = IPSCookie::get( $this->forum['id']."_sort_by" );
        $cookie_fill  = IPSCookie::get( $this->forum['id']."_topicfilter" );
        $prune_value = $this->selectVariable( array(
            1 => ! empty( $this->request['prune_day'] ) ? $this->request['prune_day'] : NULL,
            2 => !empty($cookie_prune) ? $cookie_prune : NULL,
            3 => $this->forum['prune'],
            4 => '100' )
        );

        $sort_key = $this->selectVariable( array(
            1 => ! empty( $this->request['sort_key'] ) ? $this->request['sort_key'] : NULL,
            2 => !empty($cookie_sort) ? $cookie_sort : NULL,
            3 => $this->forum['sort_key'],
            4 => 'last_post')
        );

        $sort_by = $this->selectVariable( array(
            1 => ! empty( $this->request['sort_by'] ) ? $this->request['sort_by'] : NULL,
            2 => !empty($cookie_sortb) ? $cookie_sortb : NULL,
            3 => $this->forum['sort_order'] ,
            4 => 'Z-A' )
        );

        $topicfilter = $this->selectVariable( array(
            1 => ! empty( $this->request['topicfilter'] ) ? $this->request['topicfilter'] : NULL,
            2 => !empty($cookie_fill) ? $cookie_fill : NULL,
            3 => $this->forum['topicfilter'] ,
            4 => 'all' )
        );
        
        // Figure out sort order, day cut off, etc
        $Prune = $prune_value < 100 ? (time() - ($prune_value * 60 * 60 * 24)) : ( ( $prune_value == 200 AND $this->memberData['member_id'] ) ? $this->memberData['last_visit'] : 0 );
        $sort_keys = array( 'last_post'         => 'sort_by_date',
                               'last_poster_name'  => 'sort_by_last_poster',
                               'title'             => 'sort_by_topic',
                               'starter_name'      => 'sort_by_poster',
                               'start_date'        => 'sort_by_start',
                               'topic_hasattach'   => 'sort_by_attach',
                               'posts'             => 'sort_by_replies',
                               'views'             => 'sort_by_views',
                             );

        $prune_by_day = array( '1'    => 'show_today',
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

        $sort_by_keys = array( 'Z-A'  => 'descending_order',
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

        // check for any form funny business by wanna-be hackers
        if( ( ! isset( $filter_keys[$topicfilter] ) ) or ( ! isset( $sort_keys[$sort_key] ) ) or ( ! isset( $prune_by_day[$prune_value] ) ) or ( ! isset( $sort_by_keys[$sort_by] ) ) )
        {
            //$this->registry->getClass('output')->showError( 'forums_bad_filter', 10339 );
        }

        $r_sort_by = $sort_by == 'A-Z' ? 'ASC' : 'DESC';

        // If sorting by starter, add secondary..
        $sort_key_chk = $sort_key;
        if( $sort_key == 'starter_name' )
        {
            $sort_key    = "starter_name {$r_sort_by}, t.last_post DESC";
            $r_sort_by    = '';
        }

        // Additional queries?
        $add_query_array = array();
        $add_query       = "";

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

        $_SQL_EXTRA     = '';
        $_SQL_APPROVED  = '';
        $_SQL_AGE_PRUNE = '';

        if( count($add_query_array) )
        {
            $_SQL_EXTRA = ' AND '. implode( ' AND ', $add_query_array );
        }

        // Moderator?
        if( ! $this->memberData['is_mod'] )
        {
            $_SQL_APPROVED = ' AND t.approved=1';
        }
        else
        {
            $_SQL_APPROVED = ''; //' AND t.approved IN (0,1)'; If you are an admin, it's not needed and eliminates a filesort in some cases
        }

        // Query the database to see how many topics there are in the forum
        if( $topicfilter == 'ireplied' )
        {
            // Checking topics we've replied to?
            $this->DB->build( array(
                'select'    => 'COUNT(' . $this->DB->buildDistinct( 'p.topic_id' ) . ') as max',
                'from'      => array( 'topics' => 't' ),
                'where'     => " t.forum_id={$this->forum['id']} AND p.author_id=".$this->memberData['member_id'] . " AND p.new_topic=0" . $_SQL_APPROVED . $_SQL_AGE_PRUNE,
                'add_join'  => array(
                                    array(
                                        'from'  => array( 'posts' => 'p' ),
                                        'where' => 'p.topic_id=t.tid',
                                        )
                                    )
            ) );
            $this->DB->execute();

            $total_possible = $this->DB->fetch();
        }
        else if ( ( $_SQL_EXTRA or $_SQL_AGE_PRUNE ) and ! $this->request['modfilter'] )
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
            $Prune = 0;
        }

        $total_topics_printed = 0;

        // Get main topics
        $topic_array = array();
        $topic_ids   = array();
        $topic_sort  = "";

        // Cut off?
        $parse_dots = 1;
        if( $topicfilter == 'ireplied' )
        {
            // Checking topics we've replied to? No point in getting dots again...
            $parse_dots = 0;
            // For some reason, mySQL doesn't like the distinct + t.* being in reverse order...
            $this->DB->build( array(
                'select'    => $this->DB->buildDistinct( 'p.author_id' ),
                'from'      => array( 'topics' => 't' ),
                'where'     => "t.forum_id=" . $this->forum['id'] . " AND t.pinned IN (0,1)" . $_SQL_APPROVED . $_SQL_AGE_PRUNE . " AND p.new_topic=0",
                'order'     => "t.pinned desc,{$topic_sort} t.{$sort_key} {$r_sort_by}",
                'limit'     => array( intval($First), intval($this->settings['display_max_topics']) ),
                'add_join'  => array(
                                    array(
                                        'select'    => 't.*',
                                        'from'      => array( 'posts' => 'p' ),
                                        'where'     => 'p.topic_id=t.tid AND p.author_id=' . $this->memberData['member_id'],
                                        )
                                    )
            ));
            $this->DB->execute();
        }
        else
        {
            $this->DB->build( array(
                'select' => '*',
                'from'   => 'topics t',
                'where'  =>  "t.forum_id=" . $this->forum['id'] . " AND t.pinned IN (0,1)" . $_SQL_APPROVED . $_SQL_AGE_PRUNE . $_SQL_EXTRA,
                'order'  => 't.pinned DESC, '.$topic_sort.' t.'.$sort_key .' '. $r_sort_by,
                'limit'  => array( intval($First), $this->settings['display_max_topics'] )
            ));
            $this->DB->execute();
        }

        while( $t = $this->DB->fetch() )
        {
            $topic_array[ $t['tid'] ] = $t;
            $topic_ids[ $t['tid'] ]   = $t['tid'];
        }

        ksort( $topic_ids );

        // Are we dotty
        if( ( $this->settings['show_user_posted'] == 1 ) and ( $this->memberData['member_id'] ) and ( count($topic_ids) ) and ( $parse_dots ) )
        {
            $this->DB->build( array(
                                    'select' => 'author_id, topic_id',
                                    'from'   => 'posts',
                                    'where'  => 'author_id=' . $this->memberData['member_id'] . ' AND topic_id IN(' . implode( ',', $topic_ids ) . ')',
                            ) );

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
        // Are we tracking watched stuff
        //-----------------------------------------

        if( ( $this->settings['cpu_watch_update'] == 1 ) and ( $this->memberData['member_id'] ) and ( count($topic_ids) ) and ( $parse_dots ) )
        {
            $this->DB->build( array(
                                    'select' => 'topic_id, trid as trackingTopic',
                                    'from'   => 'tracker',
                                    'where'  => 'member_id=' . $this->memberData['member_id'] . ' AND topic_id IN(' . implode( ',', $topic_ids ) . ')',
                            )    );

            $this->DB->execute();

            while( $p = $this->DB->fetch() )
            {
                if ( is_array( $topic_array[ $p['topic_id'] ] ) )
                {
                    $topic_array[ $p['topic_id'] ]['trackingTopic'] = 1;
                }
            }
        }

        foreach( $topic_array as $topic )
        {
            $topic_data[ $topic['tid'] ] = $this->renderEntry( $topic );
        }

        #################################################################################################
        $forum_data = $this->forum;

        if (version_compare($GLOBALS['app_version'], '3.2.0', '>='))
        {
            $can_post = $this->registry->getClass('class_forums')->canStartTopic( $this->forum['id'] );
        }
        else
        {
            $can_post = ($this->forum['_user_can_post'] && $this->forum['sub_can_post'] && $this->memberData['member_id']);
        }
    	$read_only_forums = explode(",", ipsRegistry::$settings['tapatalk_forum_read']);
		if(empty($read_only_forums) || !is_array($read_only_forums))
		{
			$read_only_forums = array();
		}
		if(in_array($this->forum['id'], $read_only_forums))
		{
			$can_post = false;
		}
        //****************************************
        // can upload???
        //****************************************
        require_once( IPSLib::getAppDir( 'forums' ) . '/sources/classes/post/classPost.php' );
        require_once( IPSLib::getAppDir( 'forums' ) . '/sources/classes/post/classPostForms.php' );
        $this->_postClass = new classPostForms( $this->registry );

        $this->_postClass->setForumID( $this->request['f'] );
        $this->_postClass->setForumData( $this->registry->getClass('class_forums')->forum_by_id[ $this->request['f'] ] );
        $this->_postClass->setAuthor( $this->memberData['member_id'] );
        try
        {
            $this->_postClass->globalSetUp();
        }
        catch( Exception $error )
        {
            get_error('Global Setup Error!');
        }
        $can_upload = $this->_postClass->can_upload;

        $total_topic_num =  isset($total_possible['max']) ? $total_possible['max'] : 0;
        if ($total_topic_num == 0) {
            $topics = array (
                'total_topic_num' => 0,
                'can_post'    => $can_post,
                'can_upload'    => $can_upload,
                'topics' => array(),
            );
        }
        else {

            //-----------------------------------------
            // Are we actually a moderator for this forum?
            //-----------------------------------------

            if ( ! $this->memberData['g_is_supmod'] )
            {
                $moderator = $this->memberData['forumsModeratorData'];

                if ( !isset($moderator[ $forum_data['id'] ]) OR !is_array( $moderator[ $forum_data['id'] ] ) )
                {
                    $this->memberData['is_mod'] = 0;
                }
            }

            $can_delete = 0;
            if ($this->memberData['is_mod'] == 1 and ($this->memberData['g_is_supmod'] == 1 || $this->memberData['forumsModeratorData'][ $forum_data['id'] ]['delete_topic'])) {
                $can_delete = 1;
            }
            if (isset($topic_data) and count($topic_data)) {
                 foreach($topic_data as $thread) {
                     // get TOPIC CONTENT and short content.....
                      $topic_id = $thread['tid'];
                      $post_data = $this->DB->buildAndFetch( array(
                                                            'select' => 'post, post_htmlstate',
                                                            'from'   => 'posts',
                                                            'where'  => "topic_id='{$topic_id}'"));
                     $short_content = get_short_content( $post_data['post'], $post_data['post_htmlstate']);

                     //NEW POST?
                    if ($this->memberData['member_id']) {
                        $is_read = ipsRegistry::getClass( 'classItemMarking')->isRead( array( 'forumID' => $thread['forum_id'],
                                                                'itemID' => $thread['tid'],
                                                                'itemLastUpdate' => $thread['_last_post']
                                                                ),  'forums' );
                    }

                    $issubscribed = ($this->settings['cpu_watch_update'] AND $thread['trackingTopic']);
                    $can_subscribe = ($this->settings['cpu_watch_update'] == 1 && $this->memberData['member_id']) ? true : false;
                    $is_closed = ($thread['state'] == 'closed' ? true : false);

                    $xmlrpc_topic = new xmlrpcval(array(
                        'forum_id'          => new xmlrpcval($thread['forum_id'], 'string'),
                        'topic_id'          => new xmlrpcval($thread['tid'], 'string'),
                        'topic_title'       => new xmlrpcval(subject_clean($thread['title']) , 'base64'),
                        'topic_author_id'   => new xmlrpcval($thread['starter_id'], 'string'),
                        'topic_author_name' => new xmlrpcval(mobi_unescape_html(to_utf8($thread['starter_name'])), 'base64'),
                        'icon_url'          => new xmlrpcval(get_avatar($thread['starter_id']), 'string'),
                        'last_reply_time'   => new xmlrpcval(mobiquo_iso8601_encode(isset($thread['_last_post']) ? $thread['_last_post'] : $thread['last_post']), 'dateTime.iso8601'),
                    	'timestamp'         => new xmlrpcval(intval(isset($thread['_last_post']) ? $thread['_last_post'] : $thread['last_post']), 'string'),
                        'reply_number'      => new xmlrpcval(isset($thread['__posts']) ? $thread['__posts'] : $thread['posts'], 'int'),
                        'new_post'          => new xmlrpcval($is_read ? false : true, 'boolean'),
                        'view_number'       => new xmlrpcval($thread['views'], 'int'),
                        'short_content'     => new xmlrpcval($short_content, 'base64'),
                        //'can_delete'        => new xmlrpcval($can_delete ? true : false, 'boolean'),
                        'is_subscribed'     => new xmlrpcval($issubscribed, 'boolean'),
                        'can_subscribe'     => new xmlrpcval($can_subscribe, 'boolean'),
                        'is_closed'         => new xmlrpcval($is_closed, 'boolean'),

                        'attachment'        => new xmlrpcval($thread['topic_hasattach'] ? 1 : 0, 'string'),
                    ), 'struct');
                    $topic_list[] = $xmlrpc_topic;
                }

                $topics = array (
                    'total_topic_num' => $total_topic_num,
                    'can_post'    => $can_post,
                    'can_upload'    => $can_upload,
                    'topics' => $topic_list,
                );
            } else {
                $topics = array (
                    'total_topic_num' => $total_topic_num,
                    'can_post'    => $can_post,
                    'can_upload'    => $can_upload,
                    'topics' => array(),
                );
            }
        }

        $topics['forum_id'] = $forum_data['id'];
        $topics['forum_name'] = $forum_data['name'];

        return $topics;
    }
}
