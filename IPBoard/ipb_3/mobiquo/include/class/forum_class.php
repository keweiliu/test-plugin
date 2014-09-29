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
require_once (IPS_ROOT_PATH . 'applications/forums/sources/classes/forums/class_forums.php');

class forums_class extends class_forums
{
     /**
     * Hook: Watched Items.
     * Moved here so we can update with out requiring global hook changes
     *
     */
    public function hooks_watchedItems()
    {
        $this->registry->class_localization->loadLanguageFile( array( 'public_topic' ), 'forums' );

        if( !$this->memberData['member_id'] )
        {
            return;
        }

        /* INIT */
        $WatchedTopics  = array();

        /* Get watched topics */
        $this->registry->DB()->build( array(
                                'select'    => 'tr.*',
                                'from'      => array( 'tracker' => 'tr' ),
                                'where'     => 'tr.member_id=' . $this->memberData['member_id'],
                                'order'     => 'tr.last_sent DESC',
                                //'limit'       => array( 0, 50 ),
                                'limit'     => array( 0, 20 ),
                                'add_join'  => array(
                                                    array(
                                                        'select'    => 't.*',
                                                        'from'      => array( 'topics' => 't' ),
                                                        'where'     => 't.tid=tr.topic_id',
                                                        'type'      => 'left'
                                                        ),
//                                                  array(
//                                                      'select'    => 'pp.*',
//                                                      'from'      => array( 'profile_portal' => 'pp' ),
//                                                      'where'     => 't.last_poster_id=pp.pp_member_id',
//                                                      'type'      => 'left'
//                                                  ),
//                                                  array(
//                                                      'select'    => 'f.forum_name',
//                                                      'from'      => array( 'forums' => 'f' ),
//                                                      'where'     => 't.forum_id=f.id',
//                                                      'type'      => 'left'
//                                                  )
                                                )
                        )       );
        $this->registry->DB()->execute();

        while( $r = $this->registry->DB()->fetch() )
        {
            if( !$r['tid'] )
            {
                continue;
            }
            $is_read = ipsRegistry::getClass('classItemMarking')->isRead(array('forumID'=>$r['forum_id'], 'itemID'=>$r['tid'], 'itemLastUpdate' => $r['last_post']),  'forums' );
            $r['is_new_post'] = $is_read;

            $WatchedTopics[] = $r;
        }

        $return = array();
        $author_info = array();
        foreach ($WatchedTopics as $r) {
            //######################################
            // get TOPIC CONTENT and short content.....
            //######################################
            $queued_query_bit = ' and queued=0';
            if ( $this->canQueuePosts( $r['forum_id'] ) )
            {
                $queued_query_bit = "";
            }
            $post_data = $this->DB->buildAndFetch( array(
                                                    'select' => 'post, post_htmlstate',
                                                    'from'   => 'posts',
                                                    'where'  => "topic_id= {$r['tid']}". $queued_query_bit,
                                                    'order' => 'pid desc')  );
            $r['short_content'] = get_short_content($post_data['post'], $post_data['post_htmlstate']);

            $r['forum_name'] = $this->registry->class_forums->forum_by_id[ $r['forum_id'] ]['name'];

            if (!isset($author_info[$r['last_poster_id']]['username'])) {
                 $post_author_name = $this->DB->buildAndFetch( array(
                                                                'select' => 'name',
                                                                'from'   => 'members',
                                                                'where'  => "member_id= {$r['last_poster_id']} "));
                 $author_info[$r['last_poster_id']]['username'] = $post_author_name['name'];
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

            $xmlrpc_topic = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($r['forum_id'], 'string'),
                'forum_name'        => new xmlrpcval(mobi_unescape_html(to_utf8($r['forum_name'])), 'base64'),
                'topic_id'          => new xmlrpcval($r['topic_id'], 'string'),
                'topic_title'       => new xmlrpcval(subject_clean($r['title']), 'base64'),
                'reply_number'      => new xmlrpcval($r['posts'], 'int'),
                'view_number'       => new xmlrpcval($r['views'], 'int'),
                'short_content'     => new xmlrpcval($r['short_content'], 'base64'),
                'icon_url'          => new xmlrpcval(get_avatar($r['last_poster_id'])),
                'post_author_id'    => new xmlrpcval($r['last_poster_id']),
                'post_author_name'  => new xmlrpcval(mobi_unescape_html(to_utf8($r['last_poster_name'])), 'base64'),
                'user_type'         => new xmlrpcval(check_return_user_type($author_info[$r['last_poster_id']]['username']),'base64'),
                'new_post'          => new xmlrpcval($r['is_new_post'] ? false : true, 'boolean'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($r['last_post']), 'dateTime.iso8601'),
                'timestamp'    => new xmlrpcval(intval($r['last_post']), 'string'),
                //'can_delete'      => new xmlrpcval($can_delete ? true : false, 'boolean'),
                'is_closed'         => new xmlrpcval($r['state'] == 'closed' ? true : false, 'boolean'),
            ), 'struct');

            $return[] = $xmlrpc_topic;
        }

        //##############################
        //get the total_topic_num
        //##############################
        $this->registry->DB()->build( array(
                                'select'    => 'tr.*',
                                'from'      => array( 'tracker' => 'tr' ),
                                'where'     => 'tr.member_id=' . $this->memberData['member_id'],
                                'order'     => 'tr.last_sent DESC',
                                'add_join'  => array(
                                                    array(
                                                        'select'    => 't.*',
                                                        'from'      => array( 'topics' => 't' ),
                                                        'where'     => 't.tid=tr.topic_id',
                                                        'type'      => 'left'
                                                        )
                                                    )
                        )       );
        $this->registry->DB()->execute();
        $total_topic_num = 0;
        while( $r = $this->registry->DB()->fetch() )
        {
            $total_topic_num ++;
        }

        return array(
            'total_topic_num' => $total_topic_num,
            'topics' => $return
        );
    }


    /**
     * Hook: Recent topics
     * Moved here so we can update with out requiring global hook changes
     *
     */
    public function hooks_recentTopics($start_num= 0, $end_num= 0)
    {
        /* INIT */
        $topicIDs   = array();
        $timesUsed  = array();
        $bvnp       = explode( ',', $this->settings['vnp_block_forums'] );

        $this->registry->class_localization->loadLanguageFile( array( 'public_topic' ), 'forums' );
        ##############
        $this->strip_invisible = true;
        $this->forumsInit();
        ##############

        /* Grab last X data */
        foreach( $this->forum_by_id as $forumID => $forumData )
        {
            if ( ! $forumData['can_view_others'] ) {
                continue;
            }

            if ( $forumData['password'] ) {
                continue;
            }

            if ( ! $this->registry->permissions->check( 'read', $forumData ) )  {
                continue;
            }

            if ( is_array( $bvnp ) AND count( $bvnp ) ) {
                if ( in_array( $forumID, $bvnp ) ) {
                    continue;
                }
            }

            if ( $this->settings['forum_trash_can_id'] AND $forumID == $this->settings['forum_trash_can_id'] )
            {
                continue;
            }

            /* Still here? */
            $_topics = $this->lastXThaw( $forumData['last_x_topic_ids'] );

            if ( is_array( $_topics ) )
            {
                foreach( $_topics as $id => $time )
                {
                    if( in_array( $time, $timesUsed ) )
                    {
                        while( in_array( $time, $timesUsed ) )
                        {
                            $time +=1;
                        }
                    }

                    $topicIDs[ $time ] = $id;
                }
            }
        }

        global $total_recent_num;
        $total_recent_num = count($topicIDs);

        $timesUsed  = array();
        $topics_rows = array();
        if ( is_array( $topicIDs ) )
        {
            krsort( $topicIDs );

            $_topics = array_slice( $topicIDs, $start_num, $end_num-$start_num+1 );

            if ( is_array( $_topics ) && count( $_topics ) )
            {
                /* Query Topics */
                $this->registry->DB()->build( array(
                                                'select'   => 't.*',
                                                'from'     => array( 'topics' => 't' ),
                                                'where'    => 't.tid IN (' . implode( ',', array_values( $_topics ) ) . ')',
                                                'add_join' => array(
                                                                    array(
                                                                            'select'    => 'f.name as forum_name',
                                                                            'from'      => array( 'forums' => 'f' ),
                                                                            'where'     => 'f.id=t.forum_id',
                                                                            'type'      => 'left',
                                                                        ),
                                                                    array(
                                                                        'select'    => 'pp.*',
                                                                        'from'      => array( 'profile_portal' => 'pp' ),
                                                                        'where'     => 't.last_poster_id=pp.pp_member_id',
                                                                        'type'      => 'left'
                                                                    )
                                                                )
                                            )    );

                $this->registry->DB()->execute();

                $topic_rows = array();

                while( $r = $this->registry->DB()->fetch() )
                {
                    $time   = $r['start_date'];

                    if( in_array( $time, $timesUsed ) )
                    {
                        while( in_array( $time, $timesUsed ) )
                        {
                            $time +=1;
                        }
                    }

                    $topics_rows[ $time ] = $r;
                }

                krsort( $topics_rows );
            }
        }
        $return = array();


        $topic_array = array();
        if( ( $this->settings['cpu_watch_update'] == 1 ) and ( $this->memberData['member_id'] ) and is_array($topicIDs) and count($topicIDs))
        {
            $_topics = array_slice( $topicIDs, $start_num, $end_num-$start_num+1 );
            $this->DB->build( array(
                                    'select' => 'topic_id, trid as trackingTopic',
                                    'from'   => 'tracker',
                                    'where'  => 'member_id=' . $this->memberData['member_id'] . ' AND topic_id IN(' . implode( ',', array_values( $_topics ) ) . ')',
                            )   );
            $this->DB->execute();

            while( $p = $this->DB->fetch() )
            {
                $topic_array[ $p['topic_id'] ] = 1;
            }
        }
        $author_info = array();
        foreach ($topics_rows as $r) {
            ########if topic is moved###############
            if ($r['state'] == 'link') {
                if ( preg_match('/(\d)+&(\d)+/', $r['moved_to']) ) {
                    $tmp = preg_split('/&/', $r['moved_to']);
                    $topic_id = $tmp[0];
                    $forum_id = $tmp[1];
                    $topic_data = $this->DB->buildAndFetch( array(
                                                        'select'   => 't.*',
                                                        'from'     => array( 'topics' => 't' ),
                                                        'where'     => "t.tid= {$topic_id} and t.forum_id={$forum_id}",
                                                        )   );
                    if (count($topic_data)) {
                        $r['forum_id'] = $topic_data['forum_id'];
                        $r['tid'] = $topic_data['tid'];
                        $r['posts'] = $topic_data['posts'];
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }
            }
            //######################################
            // get TOPIC CONTENT and shord content.....
            //######################################
            $queued_query_bit = ' and queued=0';
            if ( $this->canQueuePosts( $r['forum_id'] ) )
            {
                $queued_query_bit = "";
            }

            $post_data = $this->DB->buildAndFetch( array(
                                                    'select' => 'post, post_htmlstate',
                                                    'from'   => 'posts',
                                                    'where'  => "topic_id= {$r['tid']} " . $queued_query_bit,
                                                    'order' => 'pid desc')  );

            $r['short_content'] = get_short_content($post_data['post'], $post_data['post_htmlstate']);

            if (!isset($author_info[$r['last_poster_id']]['username'])) {
                 $post_author_name = $this->DB->buildAndFetch( array(
                                                                'select' => 'name',
                                                                'from'   => 'members',
                                                                'where'  => "member_id= {$r['last_poster_id']} "));
                 $author_info[$r['last_poster_id']]['username'] = $post_author_name['name'];
            }

            //######################################
            //is new post?
            //######################################
            $is_read = ipsRegistry::getClass( 'classItemMarking')->isRead( array('forumID' => $r['forum_id'],
                                                                                 'itemID' => $r['tid'],
                                                                                 'itemLastUpdate' => $r['last_post']
                                                                ),  'forums' );
            $r['is_new_post'] = $is_read;

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

            $r['issubscribed'] = $topic_array[ $r['tid'] ] ? true : false;
            $r['can_subscribe'] = ($this->settings['cpu_watch_update'] == 1 && $this->memberData['member_id']) ? true : false;
            $is_closed = ($r['state'] == 'closed' ? true : false);

            $xmlrpc_topic = new xmlrpcval( array(
                'forum_id'          => new xmlrpcval($r['forum_id']),
                'forum_name'        => new xmlrpcval(mobi_unescape_html(to_utf8($r['forum_name'])), 'base64'),
                'topic_id'          => new xmlrpcval($r['tid']),
                'topic_title'       => new xmlrpcval(subject_clean($r['title']), 'base64'),
                'reply_number'      => new xmlrpcval($r['posts'], 'int'),
                'view_number'       => new xmlrpcval($r['views'], 'int'),
                'short_content'     => new xmlrpcval($r['short_content'], 'base64'),
                'icon_url'          => new xmlrpcval(get_avatar($r['last_poster_id'])),
                'post_author_id'    => new xmlrpcval($r['last_poster_id']),
                'post_author_name'  => new xmlrpcval(mobi_unescape_html(to_utf8($r['last_poster_name'])), 'base64'),
                'user_type'         => new xmlrpcval(check_return_user_type($author_info[$r['last_poster_id']]['username']),'base64'),
                'new_post'          => new xmlrpcval($r['is_new_post'] ? false : true, 'boolean'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($r['last_post']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval(intval($r['last_post']), 'string'),
                //'can_delete'      => new xmlrpcval($can_delete ? true : false, 'boolean'),
                'is_subscribed'     => new xmlrpcval($r['issubscribed'], 'boolean'),
                'can_subscribe'     => new xmlrpcval($r['can_subscribe'], 'boolean'),
                'is_closed'         => new xmlrpcval($is_closed, 'boolean'),
            ), 'struct');

            $return[] = $xmlrpc_topic;
        }

        return $return;
    }
}
