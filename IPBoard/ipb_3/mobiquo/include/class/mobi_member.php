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

class mobi_members_load extends ipsAjaxCommand 
{
    public function doExecute( ipsRegistry $registry )
    {
        $displayname = ipsRegistry::$request['user_name'];
        $CONFIG    = array();
        
        $tab = explode( ':', ipsRegistry::$request['tab'] );
        $app = substr( IPSText::alphanumericalClean( str_replace( '..', '', trim( $tab[0] ) ) ), 0, 20 );
        $tab = substr( IPSText::alphanumericalClean( str_replace( '..', '', trim( $tab[1] ) ) ), 0, 20 );

        $this->registry->class_localization->loadLanguageFile( array( 'public_profile' ), 'members' );

        $member = IPSMember::load( $displayname, 'all', 'displayname' );
        if(empty($member['member_id']) && !empty($_POST['mid']))
        {
        	$member = IPSMember::load( intval($_POST['mid']), 'all', 'id' );
        }
        if ( ! $member['member_id'] )
        {
        	
            get_error("Invalid member");
        }
        
        if( !is_file( IPSLib::getAppDir( $app ) . '/extensions/profileTabs/' . $tab . '.conf.php' ) )
        {
            get_error("Unknown request");
        }
        
        require( IPSLib::getAppDir( $app ) . '/extensions/profileTabs/' . $tab . '.conf.php' );
        
        if ( ! $CONFIG['plugin_enabled'] )
        {
            return array();
        }
        
        if( !is_file( IPSLib::getAppDir( $app ) . '/extensions/profileTabs/' . $tab . '.php' ) )
        {
            get_error("Unknown request.");
        }
        
        if ($tab == 'posts')
            return $this->return_posts( $member );
        else
            return array();
    }

    public function return_posts( $member=array() )
    {
        $content    = array();
        $last_x     = 50;
        $forumIdsOk = array( 0 => 0 );
        $date_cut   = '';
        
        if ( ! is_array( $member ) OR ! count( $member ) )
        {
            return $this->registry->getClass('output')->getTemplate('profile')->tabNoContent( 'err_no_posts_to_show' );
        }
        
        $this->registry->class_localization->loadLanguageFile( array( 'public_topic' ), 'forums' );
        
        if( !$this->memberData['g_other_topics'] AND $this->memberData['member_id'] != $member['member_id'] )
        {
            return $this->registry->getClass('output')->getTemplate('profile')->tabNoContent( 'err_no_posts_to_show' );
        }
        
        $forumIdsOk = $this->registry->class_forums->fetchSearchableForumIds();
        
        if( is_array($forumIdsOk) AND count($forumIdsOk) )
        {
            $_post_joins = array( array(
                                        'select'    => 't.*',
                                        'from'        => array( 'topics' => 't' ),
                                        'where'        => 't.tid=p.topic_id',
                                        'type'        => 'left' 
                                    ),
                                array(
                                        'select'    => 'm.member_group_id, m.mgroup_others',
                                        'from'        => array( 'members' => 'm' ),
                                        'where'        => 'm.member_id=p.author_id',
                                        'type'        => 'left' 
                                    ) );
            
            if ( $this->settings['search_ucontent_days'] )
            {
                $date_cut = ( $this->memberData['last_post'] ? $this->memberData['last_post'] : time() ) - 86400 * intval( $this->settings['search_ucontent_days'] );
                $date_cut = ' AND p.post_date > ' . $date_cut;
            }
            
            $_queued   = $this->registry->class_forums->fetchPostHiddenQuery( array( 'visible' ), 'p.' );
            $_approved = $this->registry->getClass('class_forums')->fetchTopicHiddenQuery( array( 'visible' ), 't.' );

            $this->DB->build( array( 
                                'select'    => 'p.*',
                                'from'      => array( 'posts' => 'p' ),
                                'where'     => $_queued . " AND " . $_approved . " AND p.author_id={$member['member_id']} AND p.new_topic=0 AND t.forum_id IN (" . implode( ",", $forumIdsOk ) . ") " . $date_cut,
                                'order'     => 'p.pid DESC',
                                'limit'     => array( 0, $last_x ),
                                'add_join'  => $_post_joins
                            ));

            $o = $this->DB->execute();
            
            while( $row = $this->DB->fetch( $o ) )
            {
                //$row  = IPSMember::buildDisplayData( $row );
                
                IPSText::getTextClass( 'bbcode' )->parse_smilies        = $row['use_emo'];
                IPSText::getTextClass( 'bbcode' )->parse_html           = ( $row['use_html'] and $this->caches['group_cache'][ $row['member_group_id'] ]['g_dohtml'] and $row['post_htmlstate'] ) ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_nl2br          = $row['post_htmlstate'] == 2 ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_bbcode         = 1;
                IPSText::getTextClass( 'bbcode' )->parsing_section      = 'topics';
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup       = $row['member_group_id'];
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others= $row['mgroup_others'];
                
                $row['post'] = IPSText::getTextClass( 'bbcode' )->stripQuotes( $row['post'] );
                $row['post'] = IPSText::getTextClass( 'bbcode' )->preDisplayParse( $row['post'] );
                $row['preview'] = IPSText::truncate( IPSText::getTextClass( 'bbcode' )->stripAllTags( strip_tags( $row['post'], '<br>' ) ), 200 );
                $row['forum_name'] = $this->registry->class_forums->forum_by_id[ $row['forum_id'] ]['name'];
                $row['is_read'] = ipsRegistry::getClass( 'classItemMarking' )->isRead( array('forumID' => $row['forum_id'], 'itemID' => $row['topic_id'], 'itemLastUpdate' => $row['last_post']), 'forums' );
                
                $content[] = $row;
            }
        }
        
        return $content;
    }
}