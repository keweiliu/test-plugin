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
require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/forums/announcements.php');

class mobi_announcements extends public_forums_forums_announcements
{
    public function doExecute( ipsRegistry $registry )
    {
        $announceID = intval( $this->request['announce_id'] );

        if ( ! $announceID )
        {
            //$this->registry->getClass('output')->showError( 'announcement_id_missing', 10327, null, null, 404 );
            get_error('announcement_id_missing');
        }

        $this->registry->getClass( 'class_localization')->loadLanguageFile( array( 'public_topic' ) );

        $_post_joins = array(
                            array( 'select'    => 'm.*',
                                    'from'    => array( 'members' => 'm' ),
                                    'where'    => 'm.member_id=a.announce_member_id',
                                    'type'    => 'left'
                                ),
                            array( 'select'    => 'pp.*',
                                    'from'    => array( 'profile_portal' => 'pp' ),
                                    'where'    => 'm.member_id=pp.pp_member_id',
                                    'type'    => 'left'
                                ),
                            );

        /* Add custom fields join? */
        if( $this->settings['custom_profile_topic'] == 1 )
        {
            $_post_joins[] = array(
                                    'select' => 'pc.*',
                                    'from'   => array( 'pfields_content' => 'pc' ),
                                    'where'  => 'pc.member_id=m.member_id',
                                    'type'   => 'left'
                                );
        }

        $announce = $this->DB->buildAndFetch( array( 'select' => 'a.*',
                                                            'from'      => array( 'announcements' => 'a' ),
                                                            'where'     => 'a.announce_id=' . $announceID,
                                                            'add_join'  => $_post_joins
                                                    ) );
        $announce['announce_post'] = post_bbcode_clean($announce['announce_post']);

        if ( ! $announce['announce_id'] or ! $announce['announce_forum'] )
        {
            //$this->registry->getClass('output')->showError( 'announcement_id_missing', 10328, null, null, 404 );
            get_error('announcement_id_missing');
        }

        $pass = 0;

        if ( $announce['announce_forum'] == '*' )
        {
            $pass = 1;
        }
        else
        {
            $tmp = explode( ",", $announce['announce_forum'] );

            if ( ! is_array( $tmp ) and ! ( count( $tmp ) ) )
            {
                $pass = 0;
            }
            else
            {
                foreach( $tmp as $id )
                {
                    if ( $this->registry->getClass('class_forums')->forum_by_id[ $id ]['id'] )
                    {
                        if ( IPSMember::checkPermissions( 'read', $id ) )
                        {
                            $pass = 1;
                            break;
                        }
                    }
                }
            }
        }

        if ( $pass != 1 )
        {
            //$this->registry->getClass('output')->showError( 'announcement_no_perms', 2035, true, null, 403 );
            get_error('announcement_no_perms');
        }

        if( ! $announce['announce_active'] AND ! $this->memberData['g_is_supmod'] )
        {
            //$this->registry->getClass('output')->showError( 'announcement_no_perms', 2036, true, null, 403 );
            get_error('announcement_no_perms');
        }

        IPSText::getTextClass( 'bbcode' )->parse_smilies        = 1;
        IPSText::getTextClass( 'bbcode' )->parse_html           = $announce['announce_html_enabled'] ? 1 : 0;
        IPSText::getTextClass( 'bbcode' )->parse_nl2br          = $announce['announce_html_enabled'] ? $announce['announce_nlbr_enabled'] : 1;
        IPSText::getTextClass( 'bbcode' )->parse_bbcode         = 1;
        IPSText::getTextClass( 'bbcode' )->parsing_section      = 'announcements';
        IPSText::getTextClass( 'bbcode' )->parsing_mgroup       = $announce['member_group_id'];
        IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others= $announce['mgroup_others'];

        $announce['announce_post'] = IPSText::getTextClass( 'bbcode' )->preDisplayParse( $announce['announce_post'] );
        
        $this->DB->build( array( 'update' => 'announcements', 'set' => 'announce_views=announce_views+1', 'where' => "announce_id=".$announceID ) );
        $this->DB->execute();
        
        $display_time = $announce['announce_start'] ? $announce['announce_start'] : time();

        // prepare xmlrpc return
        $xmlrpc_post = new xmlrpcval(array(
            'topic_id'          => new xmlrpcval('ann_' . $announce['announce_id']),
            'post_title'        => new xmlrpcval(subject_clean($announce['announce_title']), 'base64'),
            'post_content'      => new xmlrpcval(post_html_clean($announce['announce_post']), 'base64'),
            'post_author_id'    => new xmlrpcval($announce['announce_member_id']),
            'post_author_name'  => new xmlrpcval(mobi_unescape_html(to_utf8($announce['members_display_name'])), 'base64'),
			'user_type'         => new xmlrpcval(check_return_user_type($announce['members_display_name']),'base64'),
            'icon_url'          => new xmlrpcval(get_avatar($announce['announce_member_id'])),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($display_time), 'dateTime.iso8601'),
			'timestamp'    => new xmlrpcval(intval($display_time), 'string'),
        ), 'struct');

        return array (
            'total_post_num'=> new xmlrpcval(1, 'int'),
            'can_reply'     => new xmlrpcval(false, 'boolean'),
            'can_subscribe' => new xmlrpcval(false, 'boolean'),
            'posts'         => new xmlrpcval(array($xmlrpc_post), 'array'),
        );
    }
}
