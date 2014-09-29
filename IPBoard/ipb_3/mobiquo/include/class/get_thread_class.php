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
require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/forums/topics.php');

class topic_thread extends public_forums_forums_topics
{
    public function loadTopicAndForum( $topic="" )
    {
        if (isset($this->request['p']))
        {
            $pid = $this->request['p'];
            $post = $this->DB->buildAndFetch( array('select' => 'topic_id', 'from' => 'posts', 'where'  => 'pid='.$pid));
            $this->request['t'] = $topic = $post['topic_id'];
        }

        if ( ! is_array( $topic ) )
        {
            //-----------------------------------------
            // Check the input

            $this->request['t'] = intval( $this->request['t'] );

            if ( ! $this->request['t']  ) {
                get_error("No Topic ID!");
            }

            if ( $this->request['t'] < 0  ) {
                get_error("Topic ID Error!");
            }

            //-----------------------------------------
            // Get the forum info based on the forum ID,
            // get the category name, ID, and get the topic details
            //-----------------------------------------
            if ( ! isset( $this->registry->class_forums->topic_cache['tid'] ) OR ! $this->registry->class_forums->topic_cache['tid'] ) {
                $this->DB->build( array( 'select' => '*', 'from' => 'topics', 'where' => 'tid='.$this->request['t'] ) );
                $this->DB->execute();

                $this->topic = $this->DB->fetch();
            } else {
                $this->topic = $this->registry->class_forums->topic_cache;
            }
        }
        else
        {
            $this->topic = $topic;
        }

        $this->topic['forum_id'] = isset( $this->topic['forum_id'] ) ? $this->topic['forum_id'] : 0;

        $this->forum = $this->registry->class_forums->forum_by_id[ $this->topic['forum_id'] ];

        $this->request['f'] = $this->forum['id'];

        if ( ! $this->topic['tid'] )
        {
            get_error("No Such Topic!");
        }

        if ( ! $this->forum['id'] )
        {
            get_error("No Such Forum!");
        }

        //-----------------------------------------
        // Error out if the topic is not approved
        //-----------------------------------------

        if ( ! $this->registry->class_forums->canQueuePosts($this->forum['id']) )
        {
            if ($this->topic['approved'] != 1)
            {
                get_error("Topic is not approved yet!");
            }
        }

        return $this->registry->class_forums->forumsCheckAccess( $this->forum['id'], 1, 'topic', $this->topic, true );
    }

    /**
     * Topic set up ya'll
     *
     * @access    public
     * @return    void
     **/
    public function topicSetUp()
    {
        $_before = IPSDebug::getMemoryDebugFlag();

        $this->request['st'] = ! empty( $this->request['st'] ) ? intval( $this->request['st'] ) : '';

        $this->registry->class_localization->loadLanguageFile( array( 'public_boards', 'public_topic' ) );
        $this->registry->class_localization->loadLanguageFile( array( 'public_editors' ), 'core' );

        if ( ! is_array( $this->cache->getCache('ranks') ) )
        {
            $this->cache->rebuildCache( 'ranks', 'global' );
        }

        //-----------------------------------------
        // Are we actually a moderator for this forum?
        //-----------------------------------------

        if ( ! $this->memberData['g_is_supmod'] )
        {
            $moderator = $this->memberData['forumsModeratorData'];

            if ( !isset($moderator[ $this->forum['id'] ]) OR !is_array( $moderator[ $this->forum['id'] ] ) )
            {
                $this->memberData['is_mod'] = 0;
            }
        }

        $this->settings['_base_url'] = $this->settings['base_url'];
        $this->forum['FORUM_JUMP']   = $this->registry->getClass('class_forums')->buildForumJump();
        $this->first                 = intval( $this->request['st'] ) > 0 ? intval( $this->request['st'] ) : 0;
        $this->request['view']         = ! empty( $this->request['view'] ) ? $this->request['view'] : NULL ;

        //-----------------------------------------
        // Check viewing permissions, private forums,
        // password forums, etc
        //-----------------------------------------

        if ( ( ! $this->memberData['g_other_topics'] ) AND ( $this->topic['starter_id'] != $this->memberData['member_id'] ) )
        {
            get_error("Not Your Topic!");
        }
        else if( (!$this->forum['can_view_others'] AND !$this->memberData['is_mod'] ) AND ( $this->topic['starter_id'] != $this->memberData['member_id'] ) )
        {
            get_error("Not Your Topic!");
        }

        //-----------------------------------------
        // Update the topic views counter
        //-----------------------------------------

        if ( ! $this->request['view'] AND $this->topic['state'] != 'link' )
        {
            if ( $this->settings['update_topic_views_immediately'] )
            {
                $this->DB->update( 'topics', 'views=views+1', "tid=".$this->topic['tid'], true, true );
            }
            else
            {
                $this->DB->insert( 'topic_views', array( 'views_tid' => $this->topic['tid'] ), true );
            }
        }

        //-----------------------------------------
        // Need to update this topic?
        //-----------------------------------------

        if ( $this->topic['state'] == 'open' )
        {
            if( !$this->topic['topic_open_time'] OR $this->topic['topic_open_time'] < $this->topic['topic_close_time'] )
            {
                if ( $this->topic['topic_close_time'] AND ( $this->topic['topic_close_time'] <= time() AND ( time() >= $this->topic['topic_open_time'] OR !$this->topic['topic_open_time'] ) ) )
                {
                    $this->topic['state'] = 'closed';

                    $this->DB->update( 'topics', array( 'state' => 'closed' ), 'tid='.$this->topic['tid'], true );
                }
            }
            else if( $this->topic['topic_open_time'] OR $this->topic['topic_open_time'] > $this->topic['topic_close_time'] )
            {
                if ( $this->topic['topic_close_time'] AND ( $this->topic['topic_close_time'] <= time() AND time() <= $this->topic['topic_open_time'] ) )
                {
                    $this->topic['state'] = 'closed';

                    $this->DB->update( 'topics', array( 'state' => 'closed' ), 'tid='.$this->topic['tid'], true );
                }
            }
        }
        else if ( $this->topic['state'] == 'closed' )
        {
            if( !$this->topic['topic_close_time'] OR $this->topic['topic_close_time'] < $this->topic['topic_open_time'] )
            {
                if ( $this->topic['topic_open_time'] AND ( $this->topic['topic_open_time'] <= time() AND ( time() >= $this->topic['topic_close_time'] OR !$this->topic['topic_close_time'] ) ) )
                {
                    $this->topic['state'] = 'open';

                    $this->DB->update( 'topics', array( 'state' => 'open' ), 'tid='.$this->topic['tid'], true );
                }
            }
            else if( $this->topic['topic_close_time'] OR $this->topic['topic_close_time'] > $this->topic['topic_open_time'] )
            {

                if ( $this->topic['topic_open_time'] AND ( $this->topic['topic_open_time'] <= time() AND time() <= $this->topic['topic_close_time'] ) )
                {
                    $this->topic['state'] = 'open';

                    $this->DB->update( 'topics', array( 'state' => 'open' ), 'tid='.$this->topic['tid'], true );
                }
            }
        }

        //-----------------------------------------
        // Current topic rating value
        //-----------------------------------------

        $this->topic['_rate_show']  = 0;
        $this->topic['_rate_int']   = 0;
        $this->topic['_rate_img']   = '';

        if ( $this->topic['state'] != 'open' )
        {
            $this->topic['_allow_rate'] = 0;
        }
        else
        {
            $this->topic['_allow_rate'] = $this->can_rate;
        }

        if ( $this->forum['forum_allow_rating'] )
        {
            $rating = $this->DB->buildAndFetch( array( 'select' => '*', 'from' => 'topic_ratings', 'where' => "rating_tid={$this->topic['tid']} and rating_member_id=".$this->memberData['member_id'] ) );

            if ( $rating['rating_value'] AND $this->memberData['g_topic_rate_setting'] != 2 )
            {
                $this->topic['_allow_rate'] = 0;
            }

            $this->topic['_rate_id']       = 0;
            $this->topic['_rating_value']  = $rating['rating_value'] ? $rating['rating_value'] : -1;

            if ( $this->topic['topic_rating_total'] )
            {
                $this->topic['_rate_int'] = round( $this->topic['topic_rating_total'] / $this->topic['topic_rating_hits'] );
            }

            //-----------------------------------------
            // Show image?
            //-----------------------------------------

            if ( ( $this->topic['topic_rating_hits'] >= $this->settings['topic_rating_needed'] ) AND ( $this->topic['_rate_int'] ) )
            {
                $this->topic['_rate_id']   = $this->topic['_rate_int'];
                $this->topic['_rate_show'] = 1;
            }
        }
        else
        {
            $this->topic['_allow_rate'] = 0;
        }

        //-----------------------------------------
        // Update the item marker
        //-----------------------------------------
//        if ( ! $this->request['view'] )
//        {
            $this->registry->getClass('classItemMarking')->markRead( array( 'forumID' => $this->forum['id'], 'itemID' => $this->topic['tid'] ), 'forums' );
        //}
        
        $this->nav = $this->registry->class_forums->forumsBreadcrumbNav( $this->forum['id'] );

        //-----------------------------------------
        // Are we a moderator?

        if ( ($this->memberData['member_id']) and ($this->memberData['g_is_supmod'] != 1) )
        {
            $other_mgroups = array();

            if( $this->memberData['mgroup_others'] )
            {
                $other_mgroups = explode( ",", IPSText::cleanPermString( $this->memberData['mgroup_others'] ) );
            }
            $other_mgroups[] = $this->memberData['member_group_id'];

            $member_group_ids = implode( ",", $other_mgroups );

            $this->moderator = $this->DB->buildAndFetch( array(
                                                                'select' => '*',
                                                                'from'    => 'moderators',
                                                                'where'    => "forum_id LIKE '%,{$this->forum['id']},%' AND (member_id={$this->memberData['member_id']} OR (is_group=1 AND group_id IN({$member_group_ids})))"
                                                        )    );
        }


        //-----------------------------------------
        // If we can see queued topics, add count
        //-----------------------------------------

        if ( $this->registry->class_forums->canQueuePosts($this->forum['id']) )
        {
            if( isset( $this->request['modfilter'] ) AND $this->request['modfilter'] == 'invisible_posts' )
            {
                $this->topic['posts'] = intval( $this->topic['topic_queuedposts'] );
            }
            else
            {
                $this->topic['posts'] += intval( $this->topic['topic_queuedposts'] );
            }
        }


        //-----------------------------------------
        // Fix up some of the words
        //-----------------------------------------

        $this->topic['TOPIC_START_DATE'] = $this->registry->class_localization->getDate( $this->topic['start_date'], 'LONG' );

        //-----------------------------------------
        // Multi Quoting?
        //-----------------------------------------

        $this->qpids = IPSCookie::get('mqtids');

        //-----------------------------------------
        // Multi PIDS?
        //-----------------------------------------

        $this->request['selectedpids'] = ! empty( $this->request['selectedpids'] ) ? $this->request['selectedpids'] : IPSCookie::get('modpids');
        $this->request['selectedpidcount'] = 0 ;

        IPSCookie::set('modpids', '', 0);

        IPSDebug::setMemoryDebugFlag( "TOPIC: topics.php::topicSetUp", $_before );
    }

    public function getThreadData(ipsRegistry $registry, $start_num = 0, $end_num = 0)
    {
        $post_data = array();
        $poll_data = '';
        $function  = '';
        // INIT module
        if ( ! $this->loadTopicAndForum() ) {
            get_error("No permission!");
        }

        ##################################################
        // Topic rating: Rating
        $this->can_rate = $this->memberData['member_id'] ? intval( $this->memberData['g_topic_rate_setting'] ) : 0;
        // Reputation Cache
        if( $this->settings['reputation_enabled'] )
        {
            require_once( IPS_ROOT_PATH . 'sources/classes/class_reputation_cache.php' );
            $this->registry->setClass( 'repCache', new classReputationCache() );
            if( isset( $this->request['rep_filter'] ) && $this->request['rep_filter'] == 'update' )
            {
                $_mem_cache = IPSMember::unpackMemberCache( $this->memberData['members_cache'] );

                if( $this->request['rep_filter_set'] == '*' )
                {
                    $_mem_cache['rep_filter'] = '*';
                }
                else
                {
                    $_mem_cache['rep_filter'] = intval( $this->request['rep_filter_set'] );
                }

                IPSMember::packMemberCache( $this->memberData['member_id'], $_mem_cache );

                $this->memberData['_members_cache'] = $_mem_cache;
            }
            else
            {
                $this->memberData['_members_cache'] = IPSMember::unpackMemberCache( $this->memberData['members_cache'] );
            }
        }

        // VIEWS
        //$this->_doViewCheck();
        if ($this->request['view'] == 'getnewpost')
        {
            $st = 0;
            $pid = "";
            $markers = $this->memberData['members_markers'];
            $last_time = $this->registry->classItemMarking->fetchTimeLastMarked( array( 'forumID' => $this->forum['id'], 'itemID' => $this->topic['tid'] ), 'forums' );

            if (method_exists($this->registry->class_forums, 'fetchPostHiddenQuery'))
            {
                $query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery('visible');

                /* Can we deal with hidden posts? */
                if ( $this->registry->class_forums->canQueuePosts( $this->topic['forum_id'] ) )
                {
                    if ( $this->permissions['softDeleteSee'] )
                    {
                        /* See queued and soft deleted */
                        $query = '';
                    }
                    else
                    {
                        /* Otherwise, see queued and approved */
                        $query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array( 'visible', 'hidden' ) );
                    }
                }
                else
                {
                    /* We cannot see hidden posts */
                    if ( $this->permissions['softDeleteSee'] )
                    {
                        /* See queued and soft deleted */
                        $query = ' AND ' . $this->registry->class_forums->fetchPostHiddenQuery( array('approved', 'sdeleted') );
                    }
                }
            }
            else
                $query = ' AND queued=0';

            $this->DB->build( array(
                'select' => 'MIN(pid) as pid',
                'from'   => 'posts',
                'where'  => "topic_id={$this->topic['tid']} AND post_date > " . intval( $last_time ) . $query,
                'limit'  => array( 0,1 )
            ));
            $this->DB->execute();

            $post = $this->DB->fetch();

            if ( !$post['pid'] )
            {
                $this->DB->build( array(
                    'select' => 'MAX(pid) as pid',
                    'from'   => 'posts',
                    'where'  => "topic_id={$this->topic['tid']} " . $query,
                    'limit'  => array( 0,1 )
                ));
                $this->DB->execute();
                $post = $this->DB->fetch();
            }

            $this->request['p'] = $post['pid'];
        }

        // Process the topic
        $this->topicSetUp();
        // Which view are we using?    If mode='show' we're viewing poll results, don't change view mode
        $this->topic_view_mode = $this->_generateTopicViewMode();
        #################added by sean#######
        $this->topic_view_mode = 'linear';
        #####################################

        if (isset($this->request['p']) && $this->request['p'] > 0)
        {
            $pid = intval($this->request['p']);
            $sort_value = $pid;
            $sort_field = ($this->settings['post_order_column'] == 'pid') ? 'pid' : 'post_date';

            if($sort_field == 'post_date')
            {
                $date = $this->DB->buildAndFetch( array( 'select' => 'post_date', 'from' => 'posts', 'where' => 'pid=' . $pid ) );

                $sort_value = $date['post_date'];
            }

            $this->DB->build( array( 'select' => 'COUNT(*) as posts', 'from' => 'posts', 'where' => "topic_id={$this->topic['tid']} AND {$sort_field} <=" . intval( $sort_value ) ) );
            $this->DB->execute();

            $cposts = $this->DB->fetch();

            $position = $cposts['posts'];
            if ( (($cposts['posts']) % $this->settings['display_max_posts']) == 0 )
            {
                $pages = ($cposts['posts']) / $this->settings['display_max_posts'];
            }
            else
            {
                $number = ( ($cposts['posts']) / $this->settings['display_max_posts'] );
                $pages = ceil($number);
            }

            $st = ($pages - 1) * $this->settings['display_max_posts'];
            $this->request['st'] = $st;
        }

        // UPDATE TOPIC?
        $this->_doUpdateTopicCheck();

        // Check PPD
        $this->_ppd_ok = $this->registry->getClass('class_forums')->checkGroupPostPerDay( $this->memberData, TRUE );

        // Post ID stuff
        $find_pid      = $this->request['pid'] == "" ? (isset( $this->request['p'] ) ? $this->request['p'] : 0 ) : ( isset( $this->request['pid'] ) ? $this->request['pid'] : 0 );
        $threaded_pid = $find_pid ? '&amp;pid=' . $find_pid : '';
        $linear_pid   = $find_pid ? '&amp;view=findpost&amp;p=' . $find_pid : '';

        // Remove potential [attachmentid= tag in title
        $this->topic['title'] = IPSText::stripAttachTag( $this->topic['title'] );

        // Get posts
        $_NOW = IPSDebug::getMemoryDebugFlag();
        $post_datas = $this->_getTopicDataLinear();

        #################################################
        $post_data = $post_datas['post_data'];
        $total_post_num = $post_datas['total_post_num'];
        ##################################################
        unset( $this->cached_members );
        IPSDebug::setMemoryDebugFlag( "TOPICS: Parsed Posts - Completed", $_NOW );
        // Generate template
        $this->topic['id'] = $this->topic['forum_id'];

        /* Posting Allowed? */

        $canReply = $this->_getReplyButtonData();
        if ($canReply['image'] == 'locked') {
            $is_closed = true;
            if($canReply['url']) {
                $can_reply = 1;
            } else {
                $can_reply = 0;
            }
        } else {
            $is_closed = false;
            if($canReply['url'] and $canReply['image']) {
                $can_reply = 1;
            } else {
                $can_reply = 0;
            }
        }

        //****************************************
        // can upload???
        //****************************************
        require_once( IPSLib::getAppDir( 'forums' ) . '/sources/classes/post/classPost.php' );
        require_once( IPSLib::getAppDir( 'forums' ) . '/sources/classes/post/classPostForms.php' );
        $this->_postClass = new classPostForms( $registry );
        //-----------------------------------------
        // Set up some stuff
        //-----------------------------------------
        # IDs
        $this->_postClass->setTopicID( $this->request['t'] );
        $this->_postClass->setForumID( $this->request['f'] );
        # Forum Data
        $this->_postClass->setForumData( $this->registry->getClass('class_forums')->forum_by_id[ $this->request['f'] ] );
        # Topic Data
//        $this->_postClass->setTopicData( $this->DB->buildAndFetch( array(
//                                                                            'select'   => 't.*, p.poll_only',
//                                                                            'from'     => array( 'topics' => 't' ),
//                                                                            'where'    => "t.forum_id={$this->_postClass->getForumID()} AND t.tid={$this->_postClass->getTopicID()}",
//                                                                            'add_join' => array(
//                                                                                                array(
//                                                                                                        'type'    => 'left',
//                                                                                                        'from'    => array( 'polls' => 'p' ),
//                                                                                                        'where'    => 'p.tid=t.tid'
//                                                                                                    )
//                                                                                                )
//                                    )                             )     );
        # Set Author
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

        $is_subscribed = $can_subscribe = false;

        if( ( $this->settings['cpu_watch_update'] == 1 ) and ( $this->memberData['member_id'] ) and ($this->request['t']))
        {
            $row = $this->DB->buildAndFetch( array(
                                    'select' => 'topic_id, trid as trackingTopic',
                                    'from'   => 'tracker',
                                    'where'  => 'member_id=' . $this->memberData['member_id'] . ' AND topic_id ='. $this->request['t'],
                            )    );
            $is_subscribed = (isset($row['topic_id']) ? true : false);
            $can_subscribe = true;
        }

        ########get ATTACHMENTS###############
        $post_data = $this->_parseAttachments( $post_data );

        //Update cache monitor
        IPSContentCache::updateMonitor( $this->_cacheMonitor);
        
        if (!isset($total_post_num)) {
            get_error("Error in Getting Post!");
        }

        // get breadcrumb
        $breadcrumb = array();
        if(is_array($this->nav) && count($this->nav) > 0)
        {
            foreach ($this->nav as $navigation)
            {
                $forum_id = preg_replace('/^.*?(\d+)$/', '$1', $navigation[1]);
                $forum_data = $this->registry->class_forums->forum_by_id[$forum_id];
                $breadcrumb[] = new xmlrpcval(array(
                    'forum_id'      => new xmlrpcval($forum_id, 'string'),
                    'forum_name'    => new xmlrpcval($navigation[0], 'base64'),
                    'sub_only'      => new xmlrpcval(!$forum_data['sub_can_post'], 'boolean'),
                ), 'struct');
            }
        }

        $result_post_data = array();
        ##############add by sean###################
        if (isset($post_data) AND count($post_data))
        {
            foreach ($post_data as $post_id => $data)
            {
                $attachments = array ();
                if (isset($data['post']['attachments']) and is_array($data['post']['attachments'])) {
                    foreach($data['post']['attachments'] as $aid => $tmp)
                    {
                        $thumbnail_url = $tmp['attach_is_image'] && $tmp['attach_thumb_location'] ? $this->settings['upload_url'] . '/' . $tmp['attach_thumb_location'] : '';
                        $url = $this->registry->getClass('output')->formatUrl( $this->registry->getClass('output')->buildUrl( "app=core&amp;module=attach&amp;section=attach&amp;attach_id={$aid}", "public",'' ), "", "" );
                        $xmlrpc_attachment = new xmlrpcval(array(
                            'content_type'  => new xmlrpcval($tmp['attach_is_image'] ? 'image' : $tmp['attach_ext']),
                            'thumbnail_url' => new xmlrpcval(url_encode($thumbnail_url)),
                            'url'           => new xmlrpcval(url_encode($url))
                        ), 'struct');
                        $attachments[] = $xmlrpc_attachment;
                    }
                }

                $xmlrpc_post = array(
                    'topic_id'          => new xmlrpcval($data['post']['topic_id']),
                    'post_id'           => new xmlrpcval($data['post']['pid']),
                    'post_title'        => new xmlrpcval(subject_clean($data['post']['post_title']), 'base64'),
                    'post_content'      => new xmlrpcval(post_html_clean($data['post']['post']), 'base64'),
                    'post_author_id'    => new xmlrpcval($data['post']['author_id']),
                    'can_edit'          => new xmlrpcval(($data['post']['_can_edit'] ? true : false), 'boolean'),
                    //'can_delete'      => new xmlrpcval(($data['post']['_can_delete'] ? true : false), 'boolean'),
                    'post_author_name'  => new xmlrpcval(mobi_unescape_html(to_utf8($data['author']['members_display_name'])), 'base64'),
                    'user_type'         => new xmlrpcval(check_return_user_type($data['author']['members_display_name']),'base64'),
                    'icon_url'          => new xmlrpcval(get_avatar($data['post']['author_id'])),
                    'is_online'         => new xmlrpcval(($data['author']['_online'] ? true : false), 'boolean'),
                    'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($data['post']['post_date']), 'dateTime.iso8601'),
                    'timestamp'         => new xmlrpcval(intval($data['post']['post_date']), 'string'),
                    'attachments'       => new xmlrpcval($attachments, 'array'),
                );

                $result_post_data[] = new xmlrpcval($xmlrpc_post, 'struct');
            }
        }

        $return_data = array (
            'total_post_num' => new xmlrpcval($total_post_num, 'int'),
            'forum_id'      => new xmlrpcval($this->forum['id']),
            'forum_name'    => new xmlrpcval(subject_clean($this->forum['name']), 'base64'),
            'topic_id'      => new xmlrpcval($this->topic['tid']),
            'topic_title'   => new xmlrpcval(subject_clean($this->topic['title']), 'base64'),
            'can_upload'    => new xmlrpcval($can_upload, 'boolean'),
            'can_reply'     => new xmlrpcval($can_reply, 'boolean'),
            'can_report'    => new xmlrpcval(true, 'boolean'),
            'is_closed'     => new xmlrpcval($is_closed, 'boolean'),
            'is_subscribed' => new xmlrpcval($is_subscribed, 'boolean'),
            'can_subscribe' => new xmlrpcval($can_subscribe, 'boolean'),
            
            'can_delete'    => new xmlrpcval($can_delete, 'boolean'),
            'can_approve'   => new xmlrpcval($can_approve, 'boolean'),
        );

        if ($breadcrumb)      $return_data['breadcrumb']    = new xmlrpcval($breadcrumb, 'array');
        if (isset($position)) $return_data['position']      = new xmlrpcval($position, 'int');
        
        $return_data['posts'] = new xmlrpcval($result_post_data, 'array');

        return $return_data;
    }

    public function doExecute( ipsRegistry $registry)
    {
        $topic_id = intval($this->request['showtopic']);
        $this->settings['display_max_posts'] = $this->request['post_per_page'];

        return $this->getThreadData($registry);
    }

    private function _generateTopicViewMode()
    {
        $return = '';

        // We don't want indexed links changing the mode
        /*if ( $this->request['mode'] AND $this->request['mode'] AND $this->request['mode'] != 'show' )
        {
            $return = $this->request['mode'];
            IPSCookie::set( 'topicmode', $this->request['mode'], 1 );
        }
        else
        {*/
            $return = IPSCookie::get('topicmode');
        //}
        if ( ! $return )
        {
            $return = $this->settings['topicmode_default'] ? $this->settings['topicmode_default'] : 'linear';
        }

        if ( $return == 'threaded' )
        {
            $this->settings['display_max_posts'] = $this->settings['post_per_page'];
        }

        return $return;
    }

    /**
     * Updates the topic
     *
     * @access    private
     * @return    void
     **/
    private function _doUpdateTopicCheck()
    {
        $mode     = $this->topic_view_mode;
        $pre    = $mode != 'threaded' ? 'st' : 'start';

        if ( empty( $this->request['b'] ) )
        {
            if ( $this->topic['topic_firstpost'] < 1 )
            {

                //--------------------------------------
                // No first topic set - old topic, update
                //--------------------------------------

                $this->DB->build( array( 'select' => 'pid', 'from' => 'posts', 'where' => 'topic_id='.$this->topic['tid'].' AND new_topic=1' ) );
                $this->DB->execute();

                $post = $this->DB->fetch();

                if ( ! $post['pid'] )
                {
                    //-----------------------------------------
                    // Get first post info
                    //-----------------------------------------

                    $this->DB->build( array(
                                                    'select' => 'pid',
                                                    'from'   => 'posts',
                                                    'where'  => "topic_id={$this->topic['tid']}",
                                                    'order'  => 'pid ASC',
                                                    'limit'  => array( 0, 1 )
                                            )     );
                    $this->DB->execute();

                    $first_post  = $this->DB->fetch();
                    $post['pid'] = $first_post['pid'];
                }

                if ( $post['pid'] )
                {
                    $this->DB->update('topics', 'topic_firstpost='.$post['pid'], 'tid='.$this->topic['tid'], false, true );
                }

                //--------------------------------------
                // Reload "fixed" topic
                //--------------------------------------

                //$this->registry->output->silentRedirect($this->settings['base_url']."showtopic=".$this->topic['tid']."&b=1&{$pre}=" . $this->request['st'] . "&p=" . $this->request['p'] . ""."&#entry".$this->request['p']);
            }
        }
    }

    public function _getTopicDataLinear()
    {
        ################################################
        $this->settings['post_order_column'] = 'post_date';
        $this->settings['post_order_sort'] = 'asc';
        ################################################
        $first = intval($this->request['st']) >=0 ? intval($this->request['st']) : 0;

        $pc_join = array();

        if ( $this->settings['post_order_column'] != 'post_date' )
        {
            $this->settings['post_order_column'] = 'pid';
        }

        if ( $this->settings['post_order_sort'] != 'desc' )
        {
            $this->settings['post_order_sort'] = 'asc';
        }

        if ( $this->settings['au_cutoff'] == "" )
        {
            $this->settings['au_cutoff'] = 15;
        }

        //-----------------------------------------
        // Moderator?
        //-----------------------------------------

        $queued_query_bit = ' and queued=0';

        if ( $this->registry->class_forums->canQueuePosts($this->topic['forum_id']) )
        {
            $queued_query_bit = '';// and queued=0';

            if ( $this->request['modfilter'] AND  $this->request['modfilter'] == 'invisible_posts' )
            {
                $queued_query_bit = ' and queued=1';
            }
        }

        //-----------------------------------------
        // Run query
        //-----------------------------------------

        $this->topic_view_mode = 'linear';

        # We don't need * but if we don't use it, it won't use the correct index
        $this->DB->build( array(
                                'select' => 'pid',
                                'from'   => 'posts',
                                'where'  => 'topic_id='.$this->topic['tid']. $queued_query_bit,
                                'order'  => $this->settings['post_order_column'].' '.$this->settings['post_order_sort'],
                                'limit'  => array( $first, $this->settings['display_max_posts'] )
                    )    );

        $this->DB->execute();

        while( $p = $this->DB->fetch() )
        {
            $this->pids[ $p['pid'] ] = $p['pid'];
        }

        ##########################add by sean#############
        ##It must come here. $this->topic_view_mode was set as 'linear'.
        ##to get the total number of posts
        ##################################################
        $this->DB->build( array(
                                'select' => 'pid',
                                'from'   => 'posts',
                                'where'  => 'topic_id='.$this->topic['tid']. $queued_query_bit,
                    )    );

        $this->DB->execute();
        $total_post_num = 0;
        while( $this->DB->fetch() )
        {
            $total_post_num ++;
        }
        ###################################################
        //-----------------------------------------
        // Do we have any PIDS?
        //-----------------------------------------

        if ( ! count( $this->pids ) )
        {
            if ( $first )
            {
                //-----------------------------------------
                // Add dummy PID, AUTO FIX
                // will catch this below...
                //-----------------------------------------

                $this->pids[] = 0;
            }

            if ( $this->request['modfilter'] == 'invisible_posts' )
            {
                $this->pids[] = 0;
            }
        }

        $this->attach_pids = $this->pids;

        if ( ! is_array( $this->pids ) or ! count( $this->pids ) )
        {
            $this->pids = array( 0 => 0 );
        }

        //-----------------------------------------
        // Joins
        //-----------------------------------------
        $_post_joins = array(
                                array(
                                        'select' => 'm.member_id as mid,m.name,m.member_group_id,m.email,m.joined,m.posts, m.last_visit, m.last_activity,m.login_anonymous,m.title, m.warn_level, m.warn_lastwarn, m.members_display_name, m.members_seo_name, m.has_gallery, m.has_blog, m.members_bitoptions',
                                        'from'   => array( 'members' => 'm' ),
                                        'where'  => 'm.member_id=p.author_id',
                                        'type'   => 'left'
                                    ),
                                array(
                                        'select' => 'pp.*',
                                        'from'   => array( 'profile_portal' => 'pp' ),
                                        'where'  => 'm.member_id=pp.pp_member_id',
                                        'type'   => 'left'
                                ),
                                array(
                                        'select' => 'g.g_access_cp',
                                        'from'   => array( 'groups' => 'g' ),
                                        'where'  => 'g.g_id=m.member_group_id',
                                        'type'   => 'left'
                                )
                            );

        /* Add custom fields join? */
        if( $this->settings['custom_profile_topic'] == 1 )
        {
            $_post_joins[] = array(
                                    'select' => 'pc.*',
                                    'from'   => array( 'pfields_content' => 'pc' ),
                                    'where'  => 'pc.member_id=p.author_id',
                                    'type'   => 'left'
                                );
        }

        /* Reputation system enabled? */
        if( $this->settings['reputation_enabled'] )
        {
            /* Add the join to figure out if the user has already rated the post */
            $_post_joins[] = $this->registry->repCache->getUserHasRatedJoin( 'pid', 'p.pid', 'forums' );

            /* Add the join to figure out the total ratings for each post */
            if( $this->settings['reputation_show_content'] )
            {
                $_post_joins[] = $this->registry->repCache->getTotalRatingJoin( 'pid', 'p.pid', 'forums' );
            }
        }

        /* Cache? */
        if ( IPSContentCache::isEnabled() )
        {
            if ( IPSContentCache::fetchSettingValue('post') )
            {
                $_post_joins[] = IPSContentCache::join( 'post', 'p.pid' );
            }

            if ( IPSContentCache::fetchSettingValue('sig') )
            {
                $_post_joins[] = IPSContentCache::join( 'sig' , 'm.member_id', 'ccb', 'left', 'ccb.cache_content as cache_content_sig, ccb.cache_updated as cache_updated_sig' );
            }
        }

        /* Ignored Users */
        $ignored_users = array();

        foreach( $this->member->ignored_users as $_i )
        {
            if( $_i['ignore_topics'] )
            {
                $ignored_users[] = $_i['ignore_ignore_id'];
            }
        }
        //-----------------------------------------
        // Get posts
        //-----------------------------------------
        $this->DB->build( array(
                                'select'   => 'p.*',
                                'from'       => array( 'posts' => 'p' ),
                                'where'       => "p.pid IN(" . implode( ',', $this->pids ) . ")",
                                'order'       => $this->settings['post_order_column'] . ' ' . $this->settings['post_order_sort'],
                                'add_join' => $_post_joins
                        )    );

        $oq = $this->DB->execute();

        if ( ! $this->DB->getTotalRows() )
        {
            if ($first >= $this->settings['display_max_posts'])
            {
                //-----------------------------------------
                // AUTO FIX: Get the correct number of replies...
                //-----------------------------------------

                $this->DB->build( array(
                                        'select' => 'COUNT(*) as pcount',
                                        'from'   => 'posts',
                                        'where'  => "topic_id=".$this->topic['tid']." and queued !=1"
                            )    );

                $newq   = $this->DB->execute();

                $pcount = $this->DB->fetch($newq);

                $pcount['pcount'] = $pcount['pcount'] > 0 ? $pcount['pcount'] - 1 : 0;

                //-----------------------------------------
                // Update the post table...
                //-----------------------------------------

                if ($pcount['pcount'] > 1)
                {
                    $this->DB->update( 'topics', array( 'posts' => $pcount['pcount'] ), "tid=".$this->topic['tid'] );

                }

                $this->registry->output->silentRedirect($this->settings['base_url']."showtopic={$this->topic['tid']}&view=getlastpost");
            }
        }

        //-----------------------------------------
        // Render the page top
        //-----------------------------------------

        $this->topic['go_new'] = isset($this->topic['go_new']) ? $this->topic['go_new'] : '';

        //-----------------------------------------
        // Format and print out the topic list
        //-----------------------------------------

        $post_data = array();

        while ( $row = $this->DB->fetch( $oq ) )
        {
            $row['member_id']    = $row['mid'];
            $row['post'] = post_bbcode_clean($row['post']);
            if(isset($row['cache_content']))
            {
            	unset($row['cache_content']);
            }
            $return = $this->parsePostRow( $row );
			IPSContentCache::update($row['pid'], 'post', false);
            $poster = $return['poster'];
            $row    = $return['row'];
            $poster['member_id'] = $poster['mid'];

            /* Reputation */
            if( $this->settings['reputation_enabled'] )
            {
                $row['pp_reputation_points'] = $row['pp_reputation_points'] ? $row['pp_reputation_points'] : 0;
                $row['has_given_rep']        = $row['has_given_rep'] ? $row['has_given_rep'] : 0;
                $row['rep_points']           = $row['rep_points'] ? $row['rep_points'] : 0;
            }

            $post_data[ $row['pid'] ] = array( 'post' => $row, 'author' => $poster );

            //-----------------------------------------
            // Are we giving this bloke a good ignoring?
            //-----------------------------------------
            if( isset( $ignored_users ) && is_array( $ignored_users ) && count( $ignored_users ) )
            {
                if( in_array( $poster['member_id'], $ignored_users ) )
                {
                    if ( ! strstr( $this->settings['cannot_ignore_groups'], ','.$poster['member_group_id'].',' ) )
                    {
                        $post_data[ $row['pid'] ]['post']['_ignored'] = 1;
                        continue;
                    }
                }
            }

            //-----------------------------------------
            // What about rep, are we ignoring?
            //-----------------------------------------

            $this->memberData['_members_cache']['rep_filter'] = isset( $this->memberData['_members_cache']['rep_filter'] ) ? $this->memberData['_members_cache']['rep_filter'] : '*';

            if( $this->settings['reputation_enabled'] )
            {
                if( ! ( $this->settings['reputation_protected_groups'] &&
                        in_array( $this->memberData['member_group_id'], explode( ',', $this->settings['reputation_protected_groups'] ) )
                       ) &&
                     $this->memberData['_members_cache']['rep_filter'] !== '*'
                )
                {
                    if( $this->settings['reputation_show_content'] && $post_data[ $row['pid'] ]['post']['rep_points'] < $this->memberData['_members_cache']['rep_filter'] )
                    {
                        $post_data[ $row['pid'] ]['post']['_repignored'] = 1;
                    }
                }
            }

            //-----------------------------------------
            // Show end first post
            //-----------------------------------------

            if ( $this->topic_view_mode == 'linearplus' and $this->first_printed == 0 and $row['pid'] == $this->topic['topic_firstpost'] and $this->topic['posts'] > 0)
            {
                $post_data[ $row['pid'] ]['post']['_end_first_post'] = 1;
            }

            $post_data[ $row['pid'] ]['post']['rep_points'] = $post_data[ $row['pid'] ]['post']['rep_points'] ? $post_data[ $row['pid'] ]['post']['rep_points'] : 0;
        }
        $return = array (
            'total_post_num' => $total_post_num,
            'post_data' => $post_data,
        );

        return $return;
    }

    public function _parseAttachments( $postData )
    {
        $postHTML = array();

        foreach( $postData as $id => $post )
        {
            $postHTML[ $id ] = $post['post']['post'];
        }

        // ATTACHMENTS!!!
        if ( $this->topic['topic_hasattach'] )
        {
            if ( ! is_object( $this->class_attach ) )
            {
                require_once( 'mobi_class_attach.php' );
                $this->class_attach = new mobi_class_attach( $this->registry );
            }

            if ( $this->registry->permissions->check( 'download', $this->registry->class_forums->forum_by_id[ $this->topic['forum_id'] ] ) === FALSE )
            {
                $this->settings['show_img_upload'] =  0 ;
            }

            $this->class_attach->type  = 'post';
            $this->class_attach->init();

            # attach_pids is generated in the func_topic_xxxxx files
            $attachHTML = $this->class_attach->renderAttachments( $postHTML, $this->attach_pids );
        }

        /* Now parse back in the rendered posts */
        if( is_array($attachHTML) AND count($attachHTML) )
        {
            foreach( $attachHTML as $id => $data )
            {
                /* Get rid of any lingering attachment tags */
                if ( stristr( $data['html'], "[attachment=" ) )
                {
                    //$data['html'] = IPSText::stripAttachTag( $data['html'] );
                    $data['html'] = preg_replace( "#\[attachment=(\d+?)\:(?:[^\]]+?)\]#ies", '$this->inline_attach($1, $data)', $data['html'] );
                }
                $postData[ $id ]['post']['post']           = $data['html'];
                $postData[ $id ]['post']['attachmentHtml'] = $data['attachmentHtml'];
                $postData[ $id ]['post']['attachments']    = $data['attachments'];
            }
        }

        return $postData;
    }

    public function inline_attach($aid, &$data)
    {
        $replace = '';
        if (isset($data['attachments'][$aid]))
        {
            $url = $this->registry->getClass('output')->formatUrl( $this->registry->getClass('output')->buildUrl( "app=core&amp;module=attach&amp;section=attach&amp;attach_id={$aid}", "public",'' ), "", "" );
            if ($data['attachments'][$aid]['attach_is_image']) {
                $replace = '[img]'.$url.'[/img]';
            } else {
                $replace = '[url='.$url.']'.$data['attachments'][$aid]['attach_file'].'[/url]';
            }
            unset($data['attachments'][$aid]);
        }

        return $replace;
    }


    /**
     * Builds an array of post data for output
     *
     * @access    public
     * @param    array    $row    Array of post data
     * @return    array
     **/
    public function parsePostRow( $row = array() )
    {
        global $mobiquo_config;

        if (isset($mobiquo_config['hide_posts_from_guests']) && $mobiquo_config['hide_posts_from_guests'])
        {
            $hide = false;
            $row['cache_content'] = '';
            // Do we want to hide in this forum?
            if(!$this->member->member_id && in_array($this->request['f'], explode(',', $this->settings['hidepostsfromguestsforums'])))
            {
                $hide = true;
            }

            if(in_array($this->request['f'], explode(',', $this->settings['hidepostsfromguestsforumsfirstpost'])) && $this->topic['topic_firstpost'] == $row['pid'])
            {
                $hide = false;
            }

            if($hide === true)
            {
                $row['post'] = $this->settings['hidepostsfromguestsmsg'];
            }
        }

        $_NOW   = IPSDebug::getMemoryDebugFlag();
        $poster = array();

        //-----------------------------------------
        // Cache member
        //-----------------------------------------

        if ( $row['author_id'] != 0 )
        {
            if ( isset( $this->cached_members[ $row['author_id'] ] ) )
            {
                // Ok, it's already cached, read from it
                $poster = $this->cached_members[ $row['author_id'] ];
                $row['name_css'] = 'normalname';
            }
            else
            {
                $row['name_css'] = 'normalname';
                $poster = $row;

                if ( isset( $poster['cache_content_sig'] ) )
                {
                    $poster['cache_content'] = $poster['cache_content_sig'];
                    $poster['cache_updated'] = $poster['cache_updated_sig'];

                    /* Cache data monitor */
                    $this->_cacheMonitor['sig']['cached']++;
                }
                else
                {
                    unset( $poster['cache_content'], $poster['cache_updated'] );

                    /* Cache data monitor */
                    $this->_cacheMonitor['sig']['raw']++;
                }

                $poster = IPSMember::buildDisplayData( $poster, array( 'signature' => 1, 'customFields' => 1, 'warn' => 1, 'avatar' => 1, 'checkFormat' => 1, 'cfLocation' => 'topic' ) );
                $poster['member_id'] = $row['mid'];

                //-----------------------------------------
                // Add it to the cached list
                $this->cached_members[ $row['author_id'] ] = $poster;
            }
        }
        else
        {
            // It's definitely a guest...
            $row['author_name']    = $this->settings['guest_name_pre'] . $row['author_name'] . $this->settings['guest_name_suf'];

            $poster = IPSMember::setUpGuest( $row['author_name'] );
            $poster['members_display_name']        = $row['author_name'];
            $poster['_members_display_name']    = $row['author_name'];
            $poster['custom_fields']            = "";
            $poster['warn_img']                    = "";
            $row['name_css']                    = 'unreg';
        }

        # Memory Debug
        IPSDebug::setMemoryDebugFlag( "PID: ".$row['pid'] . " - Member Parsed", $_NOW );

        //-----------------------------------------
        // Queued
        //-----------------------------------------

        if ( $this->topic['topic_firstpost'] == $row['pid'] and $this->topic['approved'] != 1 )
        {
            $row['queued'] = 1;
        }

        //-----------------------------------------
        // Edit...
        //-----------------------------------------

        $row['edit_by'] = "";

        if ( ( $row['append_edit'] == 1 ) and ( $row['edit_time'] != "" ) and ( $row['edit_name'] != "" ) )
        {
            $e_time = $this->registry->class_localization->getDate( $row['edit_time'] , 'LONG' );

            $row['edit_by'] = sprintf( $this->lang->words['edited_by'], $row['edit_name'], $e_time );
        }

        //-----------------------------------------
        // Parse the post
        //-----------------------------------------
        if ( ! $row['cache_content'] )
        {
            $_NOW2   = IPSDebug::getMemoryDebugFlag();

            IPSText::getTextClass( 'bbcode' )->parse_smilies            = $row['use_emo'];
            IPSText::getTextClass( 'bbcode' )->parse_html                = ( $this->forum['use_html'] and $this->caches['group_cache'][ $row['member_group_id'] ]['g_dohtml'] and $row['post_htmlstate'] ) ? 1 : 0;
            IPSText::getTextClass( 'bbcode' )->parse_nl2br                = $row['post_htmlstate'] == 2 ? 1 : 0;
            ##
            IPSText::getTextClass( 'bbcode' )->parse_bbcode                = $this->forum['use_ibc'];
            //IPSText::getTextClass( 'bbcode' )->parse_bbcode                = 0;
            ##
            IPSText::getTextClass( 'bbcode' )->parsing_section            = 'topics';
            IPSText::getTextClass( 'bbcode' )->parsing_mgroup            = $row['member_group_id'];
            IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others    = $row['mgroup_others'];

            /* Work around */
            $_tmp = $this->memberData['view_img'];
            $this->memberData['view_img'] = 1;
            ########################################
            $row['raw_post'] = $row['post'];
            ########################################
            $row['post']    = IPSText::getTextClass( 'bbcode' )->preDisplayParse( $row['post'] );

            $this->memberData['view_img'] = $_tmp;

            IPSDebug::setMemoryDebugFlag( "topics::parsePostRow - bbcode parse - Completed", $_NOW2 );

            IPSContentCache::update( $row['pid'], 'post', $row['post'] );

            /* Cache data monitor */
            $this->_cacheMonitor['post']['raw']++;
        }
        else
        {
            ########################################
            $row['raw_post'] = $row['post'];
            ########################################
            $row['post'] = '<!--cached-' . gmdate( 'r', $row['cache_updated'] ) . '-->' . $row['cache_content'];

            /* Cache data monitor */
            $this->_cacheMonitor['post']['cached']++;
        }

        //-----------------------------------------
        // Capture content
        //-----------------------------------------

        if ( $this->topic['topic_firstpost'] == $row['pid'] )
        {
            $this->_firstPostContent = $row['post'];
        }

        //-----------------------------------------
        // View image...
        //-----------------------------------------
        if (method_exists('IPSLib', 'memberViewImages')) {
            $row['post'] = IPSLib::memberViewImages( $row['post'] );
        }

        //-----------------------------------------
        // Highlight...
        //-----------------------------------------

        if ( $this->request['hl'] )
        {
            $row['post'] = IPSText::searchHighlight( $row['post'], $this->request['hl'] );
        }

        //-----------------------------------------
        // Multi Quoting?
        //-----------------------------------------

        if ( $this->qpids )
        {
            if ( strstr( ','.$this->qpids.',', ','.$row['pid'].',' ) )
            {
                $row['_mq_selected'] = 1;
            }
        }

        //-----------------------------------------
        // Multi PIDS?
        //-----------------------------------------

        if ( $this->memberData['is_mod'] )
        {
            if ( $this->request['selectedpids'] )
            {
                if ( strstr( ','.$this->request['selectedpids'].',', ','.$row['pid'].',' ) )
                {
                    $row['_pid_selected'] = 1;
                }

                $this->request['selectedpidcount'] =  count( explode( ",", $this->request['selectedpids']  ) );
            }
        }

        //-----------------------------------------
        // Delete button..
        //-----------------------------------------
        $row['_can_delete'] = $row['pid'] != $this->topic['topic_firstpost']
                              ? $this->_getDeleteButtonData( $row )
                              : FALSE;
        $row['_can_edit']   = $this->_getEditButtonData( $row );
        //$row['_show_ip']    = $this->_getIPAddressData();


//        $row['signature'] = "";
//
//        if ( isset( $poster['signature'] ) AND $poster['signature'] AND $this->memberData['view_sigs'] )
//        {
//            if ($row['use_sig'] == 1)
//            {
//                $row['signature'] = $this->registry->output->getTemplate( 'global' )->signature_separator( $poster['signature'] );
//            }
//        }

        //-----------------------------------------
        // Post number
        //-----------------------------------------

        if ( $this->topic_view_mode == 'linearplus' and $this->topic['topic_firstpost'] == $row['pid'] )
        {
            $row['post_count'] = 1;

            if ( ! $this->first )
            {
                $this->post_count++;
            }
        }
        else
        {
            $this->post_count++;

            $row['post_count'] = intval($this->request['st']) + $this->post_count;
        }

        $row['forum_id'] = $this->topic['forum_id'];

        //-----------------------------------------
        // Memory Debug
        //-----------------------------------------
        IPSDebug::setMemoryDebugFlag( "PID: ".$row['pid']. " - Completed", $_NOW );

        return array( 'row' => $row, 'poster' => $poster );
    }

}
