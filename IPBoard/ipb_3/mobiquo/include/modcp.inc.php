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

$moderate = new mobi_core_modcp_modcp($registry);
$moderate->makeRegistryShortcuts($registry);
$result = $moderate->doExecute($registry);


class mobi_core_modcp_modcp extends ipsCommand
{
    protected $result;

    public function doExecute( ipsRegistry $registry )
    {
        $this->registry->class_localization->loadLanguageFile( array( 'public_modcp' ) );
        $this->registry->output->setTitle( $this->lang->words['modcp_page_title'] );

        switch( $this->request['do'] )
        {
            default:
            case 'index':
                $this->_indexPage();
            break;
        }

        return $this->result;
    }

    /**
     * Show the mod CP portal
     *
     * @return    @e void
     */
    protected function _indexPage()
    {
        ipsRegistry::getAppClass('forums');
        $this->request['tab']     = empty($this->request['tab'])     ? 'index' : trim($this->request['tab']);
        $this->request['fromapp'] = empty($this->request['fromapp']) ? 'index' : trim($this->request['fromapp']);

        $_output    = '';
        $moderator  = $this->registry->class_forums->getModerator();
        $tab        = $this->request['tab'];
        $app        = $this->request['fromapp'];

        $classToLoad = 'plugin_'.$app.'_'.$tab;
        if( $tab AND $app AND class_exists($classToLoad))
        {
            $plugin = new $classToLoad( $this->registry );
            $this->result = $plugin->executePlugin( $moderator );
        }
        else
        {
            switch ( $this->request['do'] )
            {
                case 'editmember':
                    $_output = $this->_editMember();
                    break;

                case 'doeditmember':
                    $this->_doEditMember();
                    break;

                case 'setAsSpammer':
                    $this->_setAsSpammer();
                    break;

                default:
                    $_output = $this->registry->output->getTemplate('modcp')->memberLookup();
                    break;
            }
        }
    }

    public function loadData()
    {

        //-----------------------------------------
        // Get forum libraries
        //-----------------------------------------

        ipsRegistry::getAppClass( 'forums' );

        //-----------------------------------------
        // Make sure we're a moderator...
        //-----------------------------------------

        $pass = 0;

        if( $this->memberData['member_id'] )
        {
            if( $this->memberData['g_is_supmod'] == 1 )
            {
                $pass                        = 1;
            }
            else if( $this->memberData['is_mod'] )
            {
                $other_mgroups    = array();
                $_other_mgroups    = IPSText::cleanPermString( $this->memberData['mgroup_others'] );

                if( $_other_mgroups )
                {
                    $other_mgroups    = explode( ",", $_other_mgroups );
                }

                $other_mgroups[] = $this->memberData['member_group_id'];

                $this->DB->build( array(
                                        'select' => '*',
                                        'from'   => 'moderators',
                                        'where'  => "(member_id='" . $this->memberData['member_id'] . "' OR (is_group=1 AND group_id IN(" . implode( ",", $other_mgroups ) . ")))"
                                )    );

                $this->DB->execute();

                while ( $this->moderator = $this->DB->fetch() )
                {
                    if ( $this->moderator['allow_warn'] )
                    {
                        $pass = 1;
                    }
                }
            }
        }

        if ( !$pass )
        {
            $this->registry->output->showError( 'warn_no_access', 2025, null, null, 403 );
        }

        //-----------------------------------------
        // Ensure we have a valid member
        //-----------------------------------------

        $mid    = intval($this->request['mid']);

        if ( $mid < 1 )
        {
            $this->registry->output->showError( 'warn_no_user', 10249, null, null, 404 );
        }

        $this->warn_member    = IPSMember::load( $mid, 'all' );

        if ( ! $this->warn_member['member_id'] )
        {
            $this->registry->output->showError( 'warn_no_user', 10250, null, null, 404 );
        }

        //-----------------------------------------
        // Get editor
        //-----------------------------------------

        $classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/editor/composite.php', 'classes_editor_composite' );
        $this->editor    = new $classToLoad();
    }

    protected function getModLibrary()
    {
        static $modLibrary = null;

        if( !$modLibrary )
        {
            $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . '/sources/classes/moderate.php', 'moderatorLibrary', 'forums' );
            $modLibrary = new $classToLoad( $this->registry );
            $modLibrary->init( array() );
        }

        return $modLibrary;
    }

    protected function _editMember()
    {
        $this->loadData();

        //-----------------------------------------
        // Check permissions
        //-----------------------------------------

        if ( ! $this->memberData['g_is_supmod'] )
        {
            return '';
        }

        if ( ! $this->memberData['g_access_cp'] AND $this->warn_member['g_access_cp'] )
        {
            return '';
        }

        //-----------------------------------------
        // Init
        //-----------------------------------------

        $editable    = array();

        //-----------------------------------------
        // Show about me and signature editors
        //-----------------------------------------

        $this->editor->setAllowBbcode( true );
        $this->editor->setAllowSmilies( false );
        $this->editor->setAllowHtml( $this->caches['group_cache'][ $this->warn_member['member_group_id'] ]['g_dohtml'] );
        $this->editor->setContent( IPSText::getTextClass('bbcode')->preDisplayParse( $this->warn_member['signature'] ), 'signatures' );
        $editable['signature']    = $this->editor->show( 'Post', array( 'height' => 100 ) );

        $this->editor->setAllowBbcode( true );
        $this->editor->setAllowSmilies( false );
        $this->editor->setAllowHtml( $this->caches['group_cache'][ $this->warn_member['member_group_id'] ]['g_dohtml'] );
        $this->editor->setContent( IPSText::getTextClass('bbcode')->preDisplayParse( $this->warn_member['pp_about_me'] ), 'aboutme' );
        $editable['aboutme']    = $this->editor->show( 'aboutme', array( 'noSmilies' => true, 'height' => 100 ) );

        //-----------------------------------------
        // Other fields
        //-----------------------------------------

        $editable['member_id']                = $this->warn_member['member_id'];
        $editable['members_display_name']    = $this->warn_member['members_display_name'];
        $editable['title']                    = $this->warn_member['title'];

        //-----------------------------------------
        // Profile fields
        //-----------------------------------------

        $classToLoad            = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/customfields/profileFields.php', 'customProfileFields' );
        $fields                    = new $classToLoad();
        $fields->member_data    = $this->warn_member;
        $fields->initData( 'edit' );
        $fields->parseToEdit();

        $editable['_parsedMember']    = IPSMember::buildDisplayData( $this->warn_member );

        //-----------------------------------------
        // Return the HTML
        //-----------------------------------------

        return $this->registry->getClass('output')->getTemplate('modcp')->editUserForm( $editable, $fields );
    }

    protected function _doEditMember()
    {
        $this->loadData();

        //-----------------------------------------
        // Check permissions
        //-----------------------------------------

        if ( ! $this->memberData['g_is_supmod'] )
        {
            $this->registry->output->showError( 'mod_only_supermods', 10370, true, null, 403 );
        }

        if ( ! $this->memberData['g_access_cp'] AND $this->warn_member['g_access_cp'] )
        {
            $this->registry->output->showError( 'mod_admin_edit', 3032, true, null, 403 );
        }

        if ( $this->request['auth_key'] != $this->member->form_hash )
        {
            $this->registry->output->showError( 'no_permission', 3032.1, null, null, 403 );
        }

        //-----------------------------------------
        // Init
        //-----------------------------------------

        $editable    = array();

        //-----------------------------------------
        // Signature and about me
        //-----------------------------------------

        $signature    = $this->editor->process( $_POST['Post'] );
        $aboutme    = $this->editor->process( $_POST['aboutme'] );

        //-----------------------------------------
        // Parse signature
        //-----------------------------------------

        IPSText::getTextClass('bbcode')->parse_smilies            = 0;
        IPSText::getTextClass('bbcode')->parse_html                = $this->caches['group_cache'][ $this->warn_member['member_group_id'] ]['g_dohtml'];
        IPSText::getTextClass('bbcode')->parse_bbcode            = 1;
        IPSText::getTextClass('bbcode')->parsing_section        = 'signatures';
        IPSText::getTextClass('bbcode')->parsing_mgroup            = $this->warn_member['member_group_id'];
        IPSText::getTextClass('bbcode')->parsing_mgroup_others    = $this->warn_member['mgroup_others'];

        $signature        = IPSText::getTextClass('bbcode')->preDbParse( $signature );
        $signatureCache    = IPSText::getTextClass('bbcode')->preDisplayParse( $signature );

        //-----------------------------------------
        // Parse about me
        //-----------------------------------------

        IPSText::getTextClass('bbcode')->parse_smilies            = 0;
        IPSText::getTextClass('bbcode')->parse_html                = $this->caches['group_cache'][ $this->warn_member['member_group_id'] ]['g_dohtml'];
        IPSText::getTextClass('bbcode')->parse_bbcode            = 1;
        IPSText::getTextClass('bbcode')->parsing_section        = 'aboutme';
        IPSText::getTextClass('bbcode')->parsing_mgroup            = $this->warn_member['member_group_id'];
        IPSText::getTextClass('bbcode')->parsing_mgroup_others    = $this->warn_member['mgroup_others'];

        $aboutme    = IPSText::getTextClass('bbcode')->preDbParse( $aboutme );

        //-----------------------------------------
        // Add to array to save
        //-----------------------------------------

        $save['extendedProfile']    = array( 'signature' => $signature, 'pp_about_me' => $aboutme );
        $save['members']            = array( 'title' => $this->request['title'] );

        if ( $this->request['photo'] == 1 )
        {
            $classToLoad    = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/member/photo.php', 'classes_member_photo' );
            $photos            = new $classToLoad( $this->registry );
            $photos->remove( $this->warn_member['member_id'] );
        }

        //-----------------------------------------
        // Profile fields
        //-----------------------------------------

        $classToLoad            = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/customfields/profileFields.php', 'customProfileFields' );
        $fields                    = new $classToLoad();
        $fields->member_data    = $this->warn_member;
        $fields->initData( 'edit' );
        $fields->parseToSave( $_POST );

        if ( count( $fields->out_fields ) )
        {
            $save['customFields'] = $fields->out_fields;
        }

        //-----------------------------------------
        // Bitwise
        //-----------------------------------------

        $bw = IPSBWOptions::thaw( $this->warn_member['members_bitoptions'], 'members' );
        $bw['bw_no_status_update'] = ( $this->request['status_updates'] ) ? 0 : 1;
        $save['core']['members_bitoptions'] = IPSBWOptions::freeze( $bw, 'members' );

        //-----------------------------------------
        // Write it to the DB.
        //-----------------------------------------

        IPSMember::save( $this->warn_member['member_id'], $save );

        //-----------------------------------------
        // Update signature content cache
        //-----------------------------------------

        IPSContentCache::update( $this->warn_member['member_id'], 'sig', $signatureCache );

        //-----------------------------------------
        // Add a mod log entry and redirect
        //-----------------------------------------

        $this->getModLibrary()->addModerateLog( 0, 0, 0, 0, $this->lang->words['acp_edited_profile'] . " " . $this->warn_member['members_display_name'] );

        $this->_redirect( $this->lang->words['acp_edited_profile'] . " " . $this->warn_member['members_display_name'] );
    }

    protected function _setAsSpammer()
    {
        $toSave     = array( 'core' => array( 'bw_is_spammer' => 1 ) );
        $topicId    = intval($this->request['t']);
        $topic      = array();

        if( $topicId )
        {
            $topic = $this->DB->buildAndFetch( array( 'select' => 'tid, title_seo, forum_id', 'from' => 'topics', 'where' => 'tid=' . $topicId ) );
        }
        
        if ($this->request['member_id'])
        {
            $member_id = $this->request['member_id'];
            $member = IPSMember::load( $member_id );
        }
        else
        {
            $member_name = $this->request['member_name'];
            $member = IPSMember::load( $member_name, 'all', 'displayname' );
            $member_id = $member['member_id'];
        }
        
        if ( ! $member['member_id'] )
        {
            //$this->registry->output->showError( 'moderate_no_permission', 10311900, true, null, 404 );
            get_error('moderate_no_permission');
        }

        if( !$this->memberData['g_is_supmod'] AND !$this->memberData['forumsModeratorData'][ $topic['forum_id'] ]['bw_flag_spammers'] )
        {
            //$this->registry->output->showError( 'moderate_no_permission', 103119, true, null, 403 );
            get_error('moderate_no_permission');
        }

        if ( strstr( ',' . $this->settings['warn_protected'] . ',', ',' . $member['member_group_id'] . ',' ) )
        {
            //$this->registry->output->showError( 'moderate_no_permission', 10311901, true, null, 403 );
            get_error('moderate_no_permission');
        }
        
        
        if (version_compare($GLOBALS['app_version'], '3.3.0', '>='))
            IPSMember::flagMemberAsSpammer( $member, $this->memberData );
        else {
            if ( $this->settings['spm_option'] )
            {
                switch( $this->settings['spm_option'] )
                {
                    case 'disable':
                        $toSave['core']['restrict_post']      = 1;
                        $toSave['core']['members_disable_pm'] = 2;
                    break;
        
                    case 'unapprove':
                        $toSave['core']['restrict_post']      = 1;
                        $toSave['core']['members_disable_pm'] = 2;
                        
                        //-----------------------------------------
                        // Unapprove posts and topics
                        //-----------------------------------------
                        
                        $this->getModLibrary()->toggleApproveMemberContent( $member_id, FALSE, 'all', intval( $this->settings['spm_post_days'] ) * 24 );
                    break;
        
                    case 'ban':
                        //-----------------------------------------
                        // Unapprove posts and topics
                        //-----------------------------------------
                        
                        $this->getModLibrary()->toggleApproveMemberContent( $member_id, FALSE, 'all', intval( $this->settings['spm_post_days'] ) * 24 );
                        
                        $toSave    = array(
                                        'core'                => array(
                                                                    'member_banned'        => 1,
                                                                    'title'                => '',
                                                                    'bw_is_spammer'        => 1,
                                                                    ),
                                        'extendedProfile'    => array(
                                                                    'signature'            => '',
                                                                    'pp_about_me'        => '',
                                                                    )
                                        );
        
                        //-----------------------------------------
                        // Photo
                        //-----------------------------------------
                        
                        $classToLoad    = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/member/photo.php', 'classes_member_photo' );
                        $photos            = new $classToLoad( $this->registry );
                        $photos->remove( $member['member_id'] );
        
                        //-----------------------------------------
                        // Profile fields
                        //-----------------------------------------
                
                        $classToLoad            = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/customfields/profileFields.php', 'customProfileFields' );
                        $fields                    = new $classToLoad();
                        $fields->member_data    = $member;
                        $fields->initData( 'edit' );
                        $fields->parseToSave( array() );
                        
                        if ( count( $fields->out_fields ) )
                        {
                            $toSave['customFields']    = $fields->out_fields;
                        }
        
                        //-----------------------------------------
                        // Update signature content cache
                        //-----------------------------------------
                        
                        IPSContentCache::update( $member['member_id'], 'sig', '' );
                    break;
                }
            }
            
            //-----------------------------------------
            // Shut off status imports
            //-----------------------------------------
            
            $bwOptions    = IPSBWOptions::thaw( $member['tc_bwoptions'], 'twitter' );
            $bwOptions['tc_si_status']    = 0;
            $twitter    = IPSBWOptions::freeze( $bwOptions, 'twitter' );
        
            $bwOptions = IPSBWOptions::thaw( $member['fb_bwoptions'], 'facebook' );
            $bwOptions['fbc_si_status']    = 0;            
            $facebook    = IPSBWOptions::freeze( $bwOptions, 'facebook' );
            
            $toSave['extendedProfile']['tc_bwoptions']    = $twitter;
            $toSave['extendedProfile']['fb_bwoptions']    = $facebook;
            
            //-----------------------------------------
            // Send email if configured to do so
            //-----------------------------------------
            
            if ( $this->settings['spm_notify'] AND ( $this->settings['email_in'] != $this->memberData['email'] ) )
            {
                IPSText::getTextClass('email')->getTemplate( 'possibleSpammer' );
        
                IPSText::getTextClass('email')->buildMessage( array( 'DATE'            => $this->registry->class_localization->getDate( $member['joined'], 'LONG', 1 ),
                                                                     'MEMBER_NAME'    => $member['members_display_name'],
                                                                     'IP'            => $member['ip_address'],
                                                                     'EMAIL'        => $member['email'],
                                                                     'LINK'            => $this->registry->getClass('output')->buildSEOUrl( "showuser=" . $member['member_id'], 'public', $member['members_seo_name'], 'showuser') ) );
        
                IPSText::getTextClass('email')->subject    = sprintf( $this->lang->words['new_registration_email_spammer'], $this->settings['board_name'] );
                IPSText::getTextClass('email')->to        = $this->settings['email_in'];
                IPSText::getTextClass('email')->sendMail();
            }
            
            //-----------------------------------------
            // Save member
            //-----------------------------------------
            
            IPSMember::save( $member_id, $toSave );
            
            //-----------------------------------------
            // Notify spam service
            //-----------------------------------------
            
            if( $this->settings['spam_service_send_to_ips'] )
            {
                IPSMember::querySpamService( $member['email'], $member['ip_address'], 'markspam' );
            }
            
            //-----------------------------------------
            // Member sync
            //-----------------------------------------
            
            IPSLib::runMemberSync( 'onSetAsSpammer', $member );
            
            //-----------------------------------------
            // Mod log
            //-----------------------------------------
            
            $this->getModLibrary()->addModerateLog( 0, 0, 0, 0, $this->lang->words['flag_spam_done'] . ': ' . $member['member_id'] . ' - ' . $member['email'] );
        }

        $this->result = true;
    }

    protected function _redirect( $message )
    {
        if( $this->request['pf'] )
        {
            $this->registry->output->redirectScreen( $message, $this->settings['base_url'] . "showuser=" . $this->warn_member['member_id'], $this->warn_member['members_seo_name'], 'showuser' );
        }
        else if( $this->request['t'] )
        {
            $topic    = $this->DB->buildAndFetch( array( 'select' => 'tid, title_seo', 'from' => 'topics', 'where' => 'tid=' . intval($this->request['t']) ) );

            $this->registry->output->redirectScreen( $message, $this->settings['base_url'] . "showtopic=" . $topic['tid'] . '&amp;st=' . $this->request['_st'], $topic['title_seo'], 'showtopic' );
        }
        else
        {
            $this->registry->output->redirectScreen( $message, $this->settings['base_url'] . "app=core&amp;module=modcp&amp;do=editmember&amp;mid={$this->warn_member['member_id']}" );
        }
    }
}


class plugin_forums_deletedtopics
{
    protected $registry;
    protected $DB;
    protected $settings;
    protected $request;
    protected $lang;
    protected $member;
    protected $memberData;
    protected $cache;
    protected $caches;

    protected $forums;

    public function __construct( ipsRegistry $registry )
    {
        $this->registry     = $registry;
        $this->DB           = $this->registry->DB();
        $this->settings     =& $this->registry->fetchSettings();
        $this->request      =& $this->registry->fetchRequest();
        $this->member       = $this->registry->member();
        $this->memberData   =& $this->registry->member()->fetchMemberData();
        $this->cache        = $this->registry->cache();
        $this->caches       =& $this->registry->cache()->fetchCaches();
        $this->lang         = $this->registry->class_localization;

        /* Load language strings.. */
        $this->registry->class_localization->loadLanguageFile( array( 'public_forums' ), 'forums' );
    }

    public function getPrimaryTab()
    {
        return 'deleted_content';
    }

    public function getSecondaryTab()
    {
        return 'deletedtopics';
    }

    public function canView( $permissions )
    {
        if( $this->memberData['g_is_supmod'] OR $this->memberData['is_mod'] )
        {
            return true;
        }

        return false;
    }

    public function executePlugin( $permissions )
    {
        if( !$this->canView( $permissions ) )
        {
            get_error($this->lang->words['modcp_no_access']);
        }

        if ( ! $this->registry->isClassLoaded('topics') )
        {
            $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . "/sources/classes/topics.php", 'app_forums_classes_topics', 'forums' );
            $this->registry->setClass( 'topics', new $classToLoad( $this->registry ) );
        }

        $classToLoad = IPSLib::loadActionOverloader( IPSLib::getAppDir('forums') . '/modules_public/forums/forums.php', 'public_forums_forums_forums' );
        $this->forums = new $classToLoad( $this->registry );
        $this->forums->makeRegistryShortcuts( $this->registry );

        $st = intval($this->request['st']);
        $perpage = intval($this->request['perpage']);
        $_filters = $this->_getFilters();
        $_filters = array_merge( $_filters, array(
            'topicType' => array( 'pdelete', 'oktoremove' ),
            'getCount'  => true,
            'sortField' => 'tid',
            'sortOrder' => 'desc',
            'limit'     => $perpage,
            'offset'    => $st
        ) );

        $this->registry->getClass('topics')->setPermissionData();
        $topics = $this->registry->getClass('topics')->getTopics( $_filters );
        $total = $this->registry->getClass('topics')->getTopicsCount();
        $final = array();

        if( count( $topics ) )
        {
            foreach( $topics as $tid => $topic )
            {
                /* Have to preserve original forum id for linked topics */
                if( $topic['state'] == 'link' )
                {
                    $_originalForum = $topic['forum_id'];
                }

                $topic = $this->_checkPermissions( $topic );
                $topic = $this->forums->renderEntry( $topic );
                $topic['forum'] = $this->registry->class_forums->getForumById( $topic['forum_id'] );

                if( $topic['state'] == 'link' )
                {
                    $topic['_toForum'] = $this->registry->class_forums->getForumById( $_originalForum );
                }
                
                $_post = $this->registry->topics->getPosts( array(
                    'onlyViewable'      => true,
                    'sortField'         => 'pid',
                    'sortOrder'         => 'asc',
                    'topicId'           => array( $topic['tid'] ),
                    'limit'             => 1,
                ) );
                
                $data = array_pop( $_post );
                
                $data = IPSMember::buildDisplayData( $data );
    
                IPSText::getTextClass( 'bbcode' )->parse_smilies        = $data['use_emo'];
                IPSText::getTextClass( 'bbcode' )->parse_html           = ( $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_html'] and $this->caches['group_cache'][ $data['member_group_id'] ]['g_dohtml'] and $data['post_htmlstate'] ) ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_nl2br          = $data['post_htmlstate'] == 2 ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_bbcode         = $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_ibc'];
                IPSText::getTextClass( 'bbcode' )->parsing_section      = 'topics';
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup       = $data['member_group_id'];
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others= $data['mgroup_others'];
    
                $preview = IPSText::getTextClass( 'bbcode' )->stripQuotes( $data['post'] );
                $preview = IPSText::getTextClass( 'bbcode' )->preDisplayParse( $preview );
                $preview = IPSText::truncate( IPSText::getTextClass( 'bbcode' )->stripAllTags( strip_tags( $preview, '<br>' ) ), 200 );
                $topic['preview'] = $preview;
                
                $final[ $tid ] = $topic;
            }
        }

        $other_data = array();

        if ( is_array( $topics ) AND count( $topics ) )
        {
            $other_data = IPSDeleteLog::fetchEntries( array_keys($topics), 'topic', false );
        }

        return array(
            'total' => $total,
            'topics' => $final,
            'other_data' => $other_data
        );
    }

    protected function _checkPermissions( $row )
    {
        $row['permissions'] = array();

        $row['permissions']['PostSoftDelete']           = $this->registry->getClass('class_forums')->canSoftDeletePosts( $row['forum_id'], array() );
        $row['permissions']['PostSoftDeleteRestore']    = $this->registry->getClass('class_forums')->can_Un_SoftDeletePosts( $row['forum_id'] );
        $row['permissions']['PostSoftDeleteSee']        = $this->registry->getClass('class_forums')->canSeeSoftDeletedPosts( $row['forum_id'] );
        $row['permissions']['SoftDeleteReason']         = $this->registry->getClass('class_forums')->canSeeSoftDeleteReason( $row['forum_id'] );
        $row['permissions']['SoftDeleteContent']        = $this->registry->getClass('class_forums')->canSeeSoftDeleteContent( $row['forum_id'] );
        $row['permissions']['TopicSoftDelete']          = $this->registry->getClass('class_forums')->canSoftDeleteTopics( $row['forum_id'], array() );
        $row['permissions']['TopicSoftDeleteRestore']   = $this->registry->getClass('class_forums')->can_Un_SoftDeleteTopics( $row['forum_id'] );
        $row['permissions']['TopicSoftDeleteSee']       = $this->registry->getClass('class_forums')->canSeeSoftDeletedTopics( $row['forum_id'] );
        $row['permissions']['canQueue']                 = $this->registry->getClass('class_forums')->canQueuePosts( $row['forum_id'] );

        return $row;
    }

    protected function _getFilters()
    {
        $_return = array();
        
        if( $this->memberData['g_is_supmod'] )
        {
         $_return['skipForumCheck'] = true;
        }
        else
        {
         $_return['forumId'] = array( 0 );
        
         if( count($this->memberData['forumsModeratorData']) )
         {
             foreach( $this->memberData['forumsModeratorData'] as $fid => $forum )
             {
                 if( $forum['bw_mod_soft_delete_see'] )
                 {
                     $_return['forumId'][] = $fid;
                 }
             }
         }
        }
        
        return $_return;
    }
}

class plugin_forums_deletedposts
{
    protected $registry;
    protected $DB;
    protected $settings;
    protected $request;
    protected $lang;
    protected $member;
    protected $memberData;
    protected $cache;
    protected $caches;

    public function __construct( ipsRegistry $registry ) 
    {
        $this->registry     = $registry;
        $this->DB           = $this->registry->DB();
        $this->settings     =& $this->registry->fetchSettings();
        $this->request      =& $this->registry->fetchRequest();
        $this->member       = $this->registry->member();
        $this->memberData   =& $this->registry->member()->fetchMemberData();
        $this->cache        = $this->registry->cache();
        $this->caches       =& $this->registry->cache()->fetchCaches();
        $this->lang         = $this->registry->class_localization;
        
        /* Load language strings.. */
        $this->registry->class_localization->loadLanguageFile( array( 'public_topic' ), 'forums' );
    }
    
    public function getPrimaryTab()
    {
        return 'deleted_content';
    }

    public function getSecondaryTab()
    {
        return 'deletedposts';
    }

    public function canView( $permissions )
    {
        if( $this->memberData['g_is_supmod'] OR $this->memberData['is_mod'] )
        {
            return true;
        }
        
        return false;
    }

    public function executePlugin( $permissions )
    {
        if( !$this->canView( $permissions ) )
        {
            get_error($this->lang->words['modcp_no_access']);
        }

        if ( ! $this->registry->isClassLoaded('topics') )
        {
            $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . "/sources/classes/topics.php", 'app_forums_classes_topics', 'forums' );
            $this->registry->setClass( 'topics', new $classToLoad( $this->registry ) );
        }

        $st = intval($this->request['st']);
        $perpage = intval($this->request['perpage']);
        $_filters = $this->_getFilters();
        $_filters = array_merge( $_filters, array(
            'postType'  => array( 'pdelete', 'oktoremove' ),
            'getCount'  => true,
            'sortField' => 'pid',
            'sortOrder' => 'desc',
            'parse'     => true,
            'limit'     => $perpage,
            'offset'    => $st
        ));

        $this->registry->getClass('topics')->setPermissionData();
        $posts = $this->registry->getClass('topics')->getPosts( $_filters );
        $total = $this->registry->getClass('topics')->getPostsCount();
        $final = array();
        
        if( count( $posts ) )
        {
            foreach( $posts as $pid => $item )
            {
                $post = $item['post'];

                $data = IPSMember::buildDisplayData( $post );
    
                IPSText::getTextClass( 'bbcode' )->parse_smilies        = $data['use_emo'];
                IPSText::getTextClass( 'bbcode' )->parse_html           = ( $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_html'] and $this->caches['group_cache'][ $data['member_group_id'] ]['g_dohtml'] and $data['post_htmlstate'] ) ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_nl2br          = $data['post_htmlstate'] == 2 ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_bbcode         = $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_ibc'];
                IPSText::getTextClass( 'bbcode' )->parsing_section      = 'topics';
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup       = $data['member_group_id'];
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others= $data['mgroup_others'];
    
                $preview = IPSText::getTextClass( 'bbcode' )->stripQuotes( $data['post'] );
                $preview = IPSText::getTextClass( 'bbcode' )->preDisplayParse( $preview );
                $preview = IPSText::truncate( IPSText::getTextClass( 'bbcode' )->stripAllTags( strip_tags( $preview, '<br>' ) ), 200 );
                $post['preview'] = $preview;
                
                $post['forum_name'] = $this->registry->class_forums->forum_by_id[ $post['forum_id'] ]['name'];
                
                $final[ $pid ]['post'] = $post;
            }
        }
        
        /* Got soft delete pids? */
        $other_data = array();
        
        if ( is_array( $posts ) AND count( $posts ) )
        {
            $other_data = IPSDeleteLog::fetchEntries( array_keys($posts), 'post', false );
        }

        return array(
            'total' => $total,
            'posts' => $final,
            'other_data' => $other_data
        );
    }

    /**
     * Retrieve forum ids we can moderate in for getTopics() call
     * 
     * @return    @e array
     */
     protected function _getFilters()
     {
         $_return    = array();
         
         if( $this->memberData['g_is_supmod'] )
         {
             $_return['skipForumCheck'] = true;
         }
         else
         {
             $_return['forumId'] = array( 0 );
             
             if( count($this->memberData['forumsModeratorData']) )
             {
                 foreach( $this->memberData['forumsModeratorData'] as $fid => $forum )
                 {
                     if( $forum['bw_mod_soft_delete_see'] )
                     {
                         $_return['forumId'][] = $fid;
                     }
                 }
             }
         }
         
         return $_return;
     }
}

class plugin_forums_unapprovedtopics
{
    protected $registry;
    protected $DB;
    protected $settings;
    protected $request;
    protected $lang;
    protected $member;
    protected $memberData;
    protected $cache;
    protected $caches;
    
    protected $forums;

    public function __construct( ipsRegistry $registry ) 
    {
        $this->registry     = $registry;
        $this->DB           = $this->registry->DB();
        $this->settings     =& $this->registry->fetchSettings();
        $this->request      =& $this->registry->fetchRequest();
        $this->member       = $this->registry->member();
        $this->memberData   =& $this->registry->member()->fetchMemberData();
        $this->cache        = $this->registry->cache();
        $this->caches       =& $this->registry->cache()->fetchCaches();
        $this->lang         = $this->registry->class_localization;
        
        /* Load language strings.. */
        $this->registry->class_localization->loadLanguageFile( array( 'public_forums' ), 'forums' );
    }
    
    public function getPrimaryTab()
    {
        return 'unapproved_content';
    }
    
    public function getSecondaryTab()
    {
        return 'unapprovedtopics';
    }

    public function canView( $permissions )
    {
        if( $this->memberData['g_is_supmod'] OR $this->memberData['is_mod'] )
        {
            return true;
        }
        
        return false;
    }

    public function executePlugin( $permissions )
    {
        if( !$this->canView( $permissions ) )
        {
            get_error($this->lang->words['modcp_no_access']);
        }
        
        if ( ! $this->registry->isClassLoaded('topics') )
        {
            $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . "/sources/classes/topics.php", 'app_forums_classes_topics', 'forums' );
            $this->registry->setClass( 'topics', new $classToLoad( $this->registry ) );
        }

        $classToLoad = IPSLib::loadActionOverloader( IPSLib::getAppDir('forums') . '/modules_public/forums/forums.php', 'public_forums_forums_forums' );
        $this->forums = new $classToLoad( $this->registry );
        $this->forums->makeRegistryShortcuts( $this->registry );

        $st = intval($this->request['st']);
        $perpage = intval($this->request['perpage']);
        $_filters = $this->_getFilters();
        $_filters = array_merge( $_filters, array(
            'topicType' => array( 'sdelete', 'hidden' ),
            'getCount'  => true,
            'sortField' => 'tid',
            'sortOrder' => 'desc',
            'limit'     => $perpage,
            'offset'    => $st
        ));

        $this->registry->getClass('topics')->setPermissionData();
        $topics = $this->registry->getClass('topics')->getTopics( $_filters );
        $total = $this->registry->getClass('topics')->getTopicsCount();
        $final = array();
        
        if( count( $topics ) )
        {
            foreach( $topics as $tid => $topic )
            {
                $topic = $this->_checkPermissions( $topic );
                $topic = $this->forums->renderEntry( $topic );
                $topic['forum'] = $this->registry->class_forums->getForumById( $topic['forum_id'] );
                
                $_post = $this->registry->topics->getPosts( array(
                    'onlyViewable'      => true,
                    'sortField'         => 'pid',
                    'sortOrder'         => 'asc',
                    'topicId'           => array( $topic['tid'] ),
                    'limit'             => 1,
                ) );
                
                $data = array_pop( $_post );
                
                $data = IPSMember::buildDisplayData( $data );
    
                IPSText::getTextClass( 'bbcode' )->parse_smilies        = $data['use_emo'];
                IPSText::getTextClass( 'bbcode' )->parse_html           = ( $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_html'] and $this->caches['group_cache'][ $data['member_group_id'] ]['g_dohtml'] and $data['post_htmlstate'] ) ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_nl2br          = $data['post_htmlstate'] == 2 ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_bbcode         = $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_ibc'];
                IPSText::getTextClass( 'bbcode' )->parsing_section      = 'topics';
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup       = $data['member_group_id'];
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others= $data['mgroup_others'];
    
                $preview = IPSText::getTextClass( 'bbcode' )->stripQuotes( $data['post'] );
                $preview = IPSText::getTextClass( 'bbcode' )->preDisplayParse( $preview );
                $preview = IPSText::truncate( IPSText::getTextClass( 'bbcode' )->stripAllTags( strip_tags( $preview, '<br>' ) ), 200 );
                $topic['preview'] = $preview;
                
                $final[ $tid ] = $topic;
            }
        }
        
        $other_data = array();
        
        if ( is_array( $topics ) AND count( $topics ) )
        {
            $other_data = IPSDeleteLog::fetchEntries( array_keys($topics), 'topic', false );
        }
        
        return array(
            'total' => $total,
            'topics' => $final,
            'other_data' => $other_data
        );
    }
    
    protected function _checkPermissions( $row )
    {
        $row['permissions'] = array();

        $row['permissions']['PostSoftDelete']           = $this->registry->getClass('class_forums')->canSoftDeletePosts( $row['forum_id'], array() );
        $row['permissions']['PostSoftDeleteRestore']    = $this->registry->getClass('class_forums')->can_Un_SoftDeletePosts( $row['forum_id'] );
        $row['permissions']['PostSoftDeleteSee']        = $this->registry->getClass('class_forums')->canSeeSoftDeletedPosts( $row['forum_id'] );
        $row['permissions']['SoftDeleteReason']         = $this->registry->getClass('class_forums')->canSeeSoftDeleteReason( $row['forum_id'] );
        $row['permissions']['SoftDeleteContent']        = $this->registry->getClass('class_forums')->canSeeSoftDeleteContent( $row['forum_id'] );
        $row['permissions']['TopicSoftDelete']          = $this->registry->getClass('class_forums')->canSoftDeleteTopics( $row['forum_id'], array() );
        $row['permissions']['TopicSoftDeleteRestore']   = $this->registry->getClass('class_forums')->can_Un_SoftDeleteTopics( $row['forum_id'] );
        $row['permissions']['TopicSoftDeleteSee']       = $this->registry->getClass('class_forums')->canSeeSoftDeletedTopics( $row['forum_id'] );
        $row['permissions']['canQueue']                 = $this->registry->getClass('class_forums')->canQueuePosts( $row['forum_id'] );
        
        return $row;
    }

     protected function _getFilters()
     {
         $_return    = array();
         
         if( $this->memberData['g_is_supmod'] )
         {
             $_return['skipForumCheck'] = true;
         }
         else
         {
             $_return['forumId'] = array( 0 );
             
             if( count($this->memberData['forumsModeratorData']) )
             {
                 foreach( $this->memberData['forumsModeratorData'] as $fid => $forum )
                 {
                     if( $forum['topic_q'] )
                     {
                         $_return['forumId'][] = $fid;
                     }
                 }
             }
         }
         
         return $_return;
     }
}

class plugin_forums_unapprovedposts
{
    protected $registry;
    protected $DB;
    protected $settings;
    protected $request;
    protected $lang;
    protected $member;
    protected $memberData;
    protected $cache;
    protected $caches;

    public function __construct( ipsRegistry $registry ) 
    {
        $this->registry     = $registry;
        $this->DB           = $this->registry->DB();
        $this->settings     =& $this->registry->fetchSettings();
        $this->request      =& $this->registry->fetchRequest();
        $this->member       = $this->registry->member();
        $this->memberData   =& $this->registry->member()->fetchMemberData();
        $this->cache        = $this->registry->cache();
        $this->caches       =& $this->registry->cache()->fetchCaches();
        $this->lang         = $this->registry->class_localization;
        
        /* Load language strings.. */
        $this->registry->class_localization->loadLanguageFile( array( 'public_topic' ), 'forums' );
    }
    
    public function getPrimaryTab()
    {
        return 'unapproved_content';
    }
    
    public function getSecondaryTab()
    {
        return 'unapprovedposts';
    }
    
    public function canView( $permissions )
    {
        if( $this->memberData['g_is_supmod'] OR $this->memberData['is_mod'] )
        {
            return true;
        }
        
        return false;
    }
    
    public function executePlugin( $permissions )
    {
        if( !$this->canView( $permissions ) )
        {
            return '';
        }
        
        if ( ! $this->registry->isClassLoaded('topics') )
        {
            $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . "/sources/classes/topics.php", 'app_forums_classes_topics', 'forums' );
            $this->registry->setClass( 'topics', new $classToLoad( $this->registry ) );
        }

        $st = intval($this->request['st']);
        $perpage = intval($this->request['perpage']);
        $_filters = $this->_getFilters();
        $_filters = array_merge( $_filters, array(
            'postType'  => array( 'sdelete', 'hidden' ),
            'getCount'  => true,
            'sortField' => 'pid',
            'sortOrder' => 'desc',
            'parse'     => true,
            'limit'     => $perpage,
            'offset'    => $st
        ));
        
        $this->registry->getClass('topics')->setPermissionData();
        $posts = $this->registry->getClass('topics')->getPosts( $_filters );
        $total = $this->registry->getClass('topics')->getPostsCount();
        $final = array();
        
        if( count( $posts ) )
        {
            foreach( $posts as $pid => $item )
            {
                $post = $item['post'];

                $data = IPSMember::buildDisplayData( $post );
    
                IPSText::getTextClass( 'bbcode' )->parse_smilies        = $data['use_emo'];
                IPSText::getTextClass( 'bbcode' )->parse_html           = ( $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_html'] and $this->caches['group_cache'][ $data['member_group_id'] ]['g_dohtml'] and $data['post_htmlstate'] ) ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_nl2br          = $data['post_htmlstate'] == 2 ? 1 : 0;
                IPSText::getTextClass( 'bbcode' )->parse_bbcode         = $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['use_ibc'];
                IPSText::getTextClass( 'bbcode' )->parsing_section      = 'topics';
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup       = $data['member_group_id'];
                IPSText::getTextClass( 'bbcode' )->parsing_mgroup_others= $data['mgroup_others'];
    
                $preview = IPSText::getTextClass( 'bbcode' )->stripQuotes( $data['post'] );
                $preview = IPSText::getTextClass( 'bbcode' )->preDisplayParse( $preview );
                $preview = IPSText::truncate( IPSText::getTextClass( 'bbcode' )->stripAllTags( strip_tags( $preview, '<br>' ) ), 200 );
                $post['preview'] = $preview;
                
                $post['forum_name'] = $this->registry->class_forums->forum_by_id[ $post['forum_id'] ]['name'];
                
                $final[ $pid ]['post'] = $post;
            }
        }
        
        return array(
            'total' => $total,
            'posts' => $final,
        );
    }

    /**
     * Retrieve forum ids we can moderate in for getTopics() call
     * 
     * @return    @e array
     */
     protected function _getFilters()
     {
         $_return = array();
         
         if( $this->memberData['g_is_supmod'] )
         {
             $_return['skipForumCheck'] = true;
         }
         else
         {
             $_return['forumId'] = array( 0 );
             
             if( count($this->memberData['forumsModeratorData']) )
             {
                 foreach( $this->memberData['forumsModeratorData'] as $fid => $forum )
                 {
                     if( $forum['post_q'] )
                     {
                         $_return['forumId'][] = $fid;
                     }
                 }
             }
         }
         
         return $_return;
     }
}
