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

class mobi_members_messaging_view extends ipsCommand
{
    /**
     * Page title
     *
     * @var        string
     */
    protected $_title;
    
    /**
     * Navigation
     *
     * @var        array[ 0 => [ title, url ] ]
     */
    protected $_navigation;
    
    /**
     * Folder totals
     *
     * @var        mixed
     */
    protected $_totals;
    
    /**
     * Contains topic participant data
     *
     * @var        array
     */
    public $_topicParticipants;
    
    /**
     * Messenger library
     *
     * @var        object
     */
    public $messengerFunctions;
    
    /**
     * Error string
     *
     * @var        string
     */
    public $_errorString = '';
    
    /**
     * Class entry point
     *
     * @param    object        Registry reference
     * @return    @e void        [Outputs to screen/redirects]
     */
    public function doExecute( ipsRegistry $registry )
    {
        //-----------------------------------------
        // Check viewing permissions, etc
        //-----------------------------------------
        
        if ( ! $this->memberData['g_use_pm'] )
        {
            //$this->registry->getClass('output')->showError( 'messenger_disabled', 10226, null, null, 403 );
            get_error('messenger_disabled');
        }
        
        if ( $this->memberData['members_disable_pm'] == 2)
        {
            //$this->registry->getClass('output')->showError( 'messenger_disabled', 10227, null, null, 403 );
            get_error('messenger_disabled');
        }
        
        if ( ! $this->memberData['member_id'] )
        {
            //$this->registry->getClass('output')->showError( 'messenger_no_guests', 10228, null, null, 403 );
            get_error('messenger_no_guests');
        }
        
        if( ! IPSLib::moduleIsEnabled( 'messaging', 'members' ) )
        {
            //$this->registry->getClass('output')->showError( 'messenger_disabled', 10227, null, null, 404 );
            get_error('messenger_disabled');
        }
        
        /* Print CSS */
        //$this->registry->output->addToDocumentHead( 'raw', "<link rel='stylesheet' type='text/css' title='Main' media='print' href='{$this->settings['css_base_url']}style_css/{$this->registry->output->skin['_csscacheid']}/ipb_print.css' />" );

        //-----------------------------------------
        // Language
        //-----------------------------------------
        
        $this->registry->class_localization->loadLanguageFile( array( "public_editors" ), 'core' );
        $this->registry->class_localization->loadLanguageFile( array( 'public_messaging' ), 'members' );
        $this->registry->class_localization->loadLanguageFile( array( 'public_topic' ), 'forums' );
        
        //-----------------------------------------
        // Grab class
        //-----------------------------------------
        
        require_once 'mobi_messengerFunctions.php';
        $this->messengerFunctions = new mobi_messengerFunctions($registry);
        
        /* Messenger Totals */
        $this->_totals = $this->messengerFunctions->buildMessageTotals();

        /* Filtah */
        if ( $this->request['folderFilter'] )
        {
            $this->messengerFunctions->addFolderFilter( $this->request['folderFilter'] );
        }
        
        /* force disabled messenger into default */
        if ( $this->memberData['members_disable_pm'] && $this->request['do'] != 'enableMessenger' )
        {
            $this->request['do'] = 'inbox';
        }
        //-----------------------------------------
        // What to do?
        //-----------------------------------------
        
        switch( $this->request['do'] )
        {
            default:
            case 'inbox':
            case 'showFolder':
                $result = $this->_showFolder();
            break;
            case 'showConversation':
            case 'showMessage':
                $result = $this->showConversation();
            break;
            case 'multiFile':
                $html = $this->_multiFile();
            break;
            case 'findMessage':
                $html = $this->_findMessage();
            break;
            case 'addParticipants':
                $result = $this->_addParticipants();
            break;
            /*case 'leaveConversation':
                $html = $this->_leaveConversation();
            break;
            case 'rejoinConversation':
                $html = $this->_rejoinConversation();
            break;*/
            case 'deleteConversation':
                $result = $this->_deleteConversation();
            break;
            case 'blockParticipant':
                $html = $this->_blockParticipant();
            break;
            case 'unblockParticipant':
                $html = $this->_unblockParticipant();
            break;
            case 'toggleNotifications':
                $html = $this->_toggleNotifications();
            break;
            case 'enableMessenger':
                $this->_enableMessenger();
            break;
            case 'disableMessenger':
                $this->_disableMessenger();
            break;
        }
        
        //-----------------------------------------
        // If we have any HTML to print, do so...
        //-----------------------------------------
        /*
        $this->registry->output->addContent( $this->registry->getClass('output')->getTemplate('messaging')->messengerTemplate( $html, $this->messengerFunctions->_jumpMenu, $this->messengerFunctions->_dirData, $this->_totals, $this->_topicParticipants, $this->_errorString, $this->_deletedTopic ) );
        $this->registry->output->setTitle( $this->_title  . ' - ' . ipsRegistry::$settings['board_name']);
        
        $this->registry->output->addNavigation( $this->lang->words['messenger__nav'], 'app=members&amp;module=messaging' );
        
        if ( is_array( $this->_navigation ) AND count( $this->_navigation ) )
        {
            foreach( $this->_navigation as $idx => $data )
            {
                $this->registry->output->addNavigation( $data[0], $data[1] );
            }
        }
        */
        return $result;
     }
    
     /**
      * Enable messenger
      */
    protected function _enableMessenger()
    {
        $authKey = $this->request['authKey'];
        
        /* Auth key */
        if ( $this->request['authKey'] != $this->member->form_hash )
        {
            $this->registry->getClass('output')->showError( 'messenger_bad_key', 2024, null, null, 403 );
        }
        
        /* Toggle */
        if ( $this->memberData['members_disable_pm'] != 2 )
        {
            IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'members_disable_pm' => 0 ) ) );
        }
        
        $this->registry->getClass('output')->silentRedirect( $this->settings['base_url'] . 'app=members&amp;module=messaging' );
    }
    
    /**
      * Enable messenger
      */
    protected function _disableMessenger()
    {
        $authKey = $this->request['authKey'];
        
        /* Auth key */
        if ( $this->request['authKey'] != $this->member->form_hash )
        {
            $this->registry->getClass('output')->showError( 'messenger_bad_key', 2024, null, null, 403 );
        }
        
        /* Toggle */
        if ( $this->memberData['members_disable_pm'] != 2 )
        {
            IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'members_disable_pm' => 1 ) ) );
        }
        
        $this->registry->getClass('output')->silentRedirect( $this->settings['base_url'] . 'app=members&amp;module=messaging' );
    }
    
    /**
     * Block a participant
     *
     * @return    mixed    void, or HTML
     */
    protected function _toggleNotifications()
    {
        //-----------------------------------------
        // INIT
        //-----------------------------------------

        $authKey     = $this->request['authKey'];
        $topicID     = intval( $this->request['topicID'] );

        //-----------------------------------------
        // Auth check
        //-----------------------------------------

        if ( $this->request['authKey'] != $this->member->form_hash )
        {
            $this->registry->getClass('output')->showError( 'messenger_bad_key', 2024, null, null, 403 );
        }

        //-----------------------------------------
        // Do it
        //-----------------------------------------

        try
        {
            $this->messengerFunctions->toggleNotificationStatus( $this->memberData['member_id'], array( $topicID ), 'toggle' );

            $this->registry->getClass('output')->silentRedirect( $this->settings['base_url'] . 'app=members&amp;module=messaging&amp;section=view&amp;do=showConversation&amp;topicID=' . $topicID );
        }
        catch( Exception $error )
        {
            $msg = $error->getMessage();
            $this->_errorString = $msg;

            return $this->showConversation();
        }
    }
        
    /**
     * Block a participant
     *
     * @return    mixed    void, or HTML
     */
    protected function _blockParticipant()
    {
        //-----------------------------------------
        // INIT
        //-----------------------------------------
        
        $authKey     = $this->request['authKey'];
        $memberID    = intval( $this->request['memberID'] );
        $topicID     = intval( $this->request['topicID'] );
        
        //-----------------------------------------
        // Auth check
        //-----------------------------------------
        
        if ( $this->request['authKey'] != $this->member->form_hash )
        {
            $this->registry->getClass('output')->showError( 'messenger_bad_key', 2024, null, null, 403 );
        }
        
        //-----------------------------------------
        // Do it
        //-----------------------------------------
        
        try
        {
            $this->messengerFunctions->toggleTopicBlock( $memberID, $this->memberData['member_id'], $topicID, TRUE );
            
            $this->registry->getClass('output')->silentRedirect( $this->settings['base_url'] . 'app=members&amp;module=messaging&amp;section=view&amp;do=showConversation&amp;topicID=' . $topicID );
        }
        catch( Exception $error )
        {
            $msg = $error->getMessage();
            $this->_errorString = $msg;
            
            return $this->showConversation();
        }
    }
    
    /**
     * Leave a conversation (non topic starter)
     *
     * @return    mixed    void, or HTML
     */
    protected function _unblockParticipant()
    {
        //-----------------------------------------
        // INIT
        //-----------------------------------------
        
        $authKey     = $this->request['authKey'];
        $memberID    = intval( $this->request['memberID'] );
        $topicID     = intval( $this->request['topicID'] );
        
        //-----------------------------------------
        // Auth check
        //-----------------------------------------
        
        if ( $this->request['authKey'] != $this->member->form_hash )
        {
            $this->registry->getClass('output')->showError( 'messenger_bad_key', 2024, null, null, 403 );
        }
        
        //-----------------------------------------
        // Do it
        //-----------------------------------------
        
        try
        {
            $this->messengerFunctions->toggleTopicBlock( $memberID, $this->memberData['member_id'], $topicID, FALSE );
            
            $this->registry->getClass('output')->silentRedirect( $this->settings['base_url'] . 'app=members&amp;module=messaging&amp;section=view&amp;do=showConversation&amp;topicID=' . $topicID );
        }
        catch( Exception $error )
        {
            $msg = $error->getMessage();
            $this->_errorString = $msg;
            
            return $this->showConversation();
        }
    }
    
    /**
     * Leave a conversation (non topic starter)
     *
     * @return    mixed    void, or HTML
     */
    protected function _deleteConversation()
    {
        $topicID = intval( $this->request['topicID'] );

        try
        {
            $this->messengerFunctions->deleteTopics( $this->memberData['member_id'], array( $topicID )  );
        }
        catch( Exception $error )
        {
            $msg = $error->getMessage();
            $this->_errorString = $msg;
            
            get_error($msg);
        }
        
        return true;
    }
    
    /**
     * Deletes a reply
     *
     * @return    mixed    void, or HTML
     */
    protected function _addParticipants()
    {
        $topicID     = intval( $this->request['topicID'] );
        $inviteUsers = array();
        $start       = intval( $this->request['st'] );

        if ( $this->memberData['g_max_mass_pm'] AND $this->request['inviteNames'] )
        {
            $inviteUsers = $this->request['inviteNames'];
        }

        try
        {
            $this->messengerFunctions->addTopicParticipants( $topicID, $inviteUsers, $this->memberData['member_id'] );
            //$this->registry->getClass('output')->silentRedirect( $this->settings['base_url'] . 'app=members&amp;module=messaging&amp;section=view&amp;do=showConversation&amp;topicID=' . $topicID . '&amp;st=' . $start );
        }
        catch( Exception $error )
        {
            $msg = $error->getMessage();
            
            if ( isset($this->lang->words[ 'err_' . $msg ]) )
            {
                $_msgString = $this->lang->words[ 'err_' . $msg ];
                $_msgString = str_replace( '#NAMES#'   , implode( ",", $this->messengerFunctions->exceptionData ), $_msgString );
                $_msgString = str_replace( '#TONAME#'  , implode( ",", $inviteUsers), $_msgString );
                $_msgString = str_replace( '#FROMNAME#', $this->memberData['members_display_name'], $_msgString );
                $_msgString = str_replace( '#LIMIT#'   , $this->memberData['g_max_mass_pm'], $_msgString );
            }
            else
            {
                $_msgString = $this->lang->words['err_UNKNOWN'] . ' ' . $msg;
            }
            
            //$this->_errorString = $_msgString;
            
            //return $this->showConversation();
            get_error($_msgString);
        }
        
        return true;
    }
    
    /**
     * Redirects the user to the correct page in a conversation based on the incoming msg ID
     *
     * @return    string        returns HTML
     */
    protected function _findMessage()
    {
        $msgID   = ( $this->request['msgID'] == '__firstUnread__' ) ? '__firstUnread__' : intval( $this->request['msgID'] );
        $topicID = intval( $this->request['topicID'] );
        
        /* Fetch topic data */
        $topicData   = $this->messengerFunctions->fetchTopicData( $topicID );
        
        /* Figure out the MSG id */
        if ( $msgID == '__firstUnread__' )
        {
            /* Grab mah 'pants */
            $participants = $this->messengerFunctions->fetchTopicParticipants( $topicID );
            
            if ( $participants[ $this->memberData['member_id'] ] )
            {
                $_msgID = $this->DB->buildAndFetch( array( 'select' => 'msg_id',
                                                           'from'   => 'message_posts',
                                                           'where'  => 'msg_topic_id=' . $topicID . ' AND msg_date > ' . intval( $participants[ $this->memberData['member_id'] ]['map_read_time'] ),
                                                           'order'  => 'msg_date ASC',
                                                           'limit'  => array( 0, 1 ) ) );
    
                $msgID = $_msgID['msg_id'];
            }
        }
        
        $msgID   = ( $msgID ) ? $msgID : $topicData['mt_last_msg_id'];
        
        /* Figure it out */
        $replies   = $topicData['mt_replies'] + 1;
        $perPage   = $this->messengerFunctions->messagesPerPage;
        $page      = 0;
        
        $_count = $this->DB->buildAndFetch( array( 'select' => 'COUNT(*) as count',
                                                   'from'   => 'message_posts',
                                                   'where'  => "msg_topic_id=" . $topicID . " AND msg_id <=" . intval( $msgID ) ) );                                        
        
        
        if ( (($_count['count']) % $perPage) == 0 )
        {
            $pages = ($_count['count']) / $perPage;
        }
        else
        {
            $pages = ceil( ( ( $_count['count'] ) / $perPage ) );
        }
        
        $st = ($pages - 1) * $perPage;
        
        $this->registry->getClass('output')->silentRedirect( $this->settings['base_url'] . 'app=members&amp;module=messaging&amp;section=view&amp;do=showConversation&amp;topicID=' . $topicID . '&amp;st=' . $st . '#msg' . $msgID );
    }
    
    /**
     * Multi Files Messages
     *
     * @return    string        returns HTML
     */
    protected function _multiFile()
    {
        //-----------------------------------------
        // INIT
        //-----------------------------------------
        
        $method    = $this->request['method'];
        $folderID  = $this->request['folderID'];
        $cfolderID = $this->request['cFolderID'];
        $sort      = $this->request['sort'];
        $start     = intval( $this->request['st'] );
        $ids       = array();
        
        //-----------------------------------------
        // Auth OK?
        //-----------------------------------------
        
        if ( $this->request['auth_key'] != $this->member->form_hash )
        {
            $this->messengerFunctions->_currentFolderID = $cfolderID;
            return $this->_showFolder( $this->lang->words['err_auth'] );
        }
        
        //-----------------------------------------
        // Grab IDs
        //-----------------------------------------
        
        if ( is_array( $_POST['msgid'] ) )
        {
            foreach( $_POST['msgid'] as $id => $value )
            {
                $id = intval( $id );
                $ids[ $id ] = $id;
            }
        }

        //-----------------------------------------
        // What are we doing?
        //-----------------------------------------

        try
        {
            if ( $method == 'delete' )
            {
                $this->messengerFunctions->deleteTopics( $this->memberData['member_id'], $ids );
                
                $p_end = $this->settings['show_max_msg_list'] > 0 ? $this->settings['show_max_msg_list'] : 50;
                $start = ( count( $ids ) >= $p_end ) ? $start-$p_end : $start;
            }
            else if ( $method == 'move' )
            {
                $this->messengerFunctions->moveTopics( $this->memberData['member_id'], $ids, $folderID );
                
                $p_end = $this->settings['show_max_msg_list'] > 0 ? $this->settings['show_max_msg_list'] : 50;
                $start = ( count( $ids ) >= $p_end ) ? $start-$p_end : $start;
            }
            else if ( $method == 'markread' OR $method == 'markunread' )
            {
                $_method = ( $method == 'markread' ) ? TRUE : FALSE;
                
                $this->messengerFunctions->toggleReadStatus( $this->memberData['member_id'], $ids, $_method );
            }
            else if ( $method == 'notifyon' OR $method == 'notifyoff' )
            {
                $_method = ( $method == 'notifyon' ) ? TRUE : FALSE;

                $this->messengerFunctions->toggleNotificationStatus( $this->memberData['member_id'], $ids, $_method );
            }
            
            $this->registry->getClass('output')->silentRedirect( $this->settings['base_url'] . 'app=members&amp;module=messaging&amp;section=view&amp;do=showFolder&amp;folderID=' . $cfolderID . '&amp;sort=' . $sort . '&amp;st=' . $start );
        }
        catch( Exception $error )
        {
            $msg   = $error->getMessage();
            $error = '';

            switch( $msg )
            {
                default:
                    $error =  $this->lang->words['err_unspecifed'];
                break;
                case 'NO_IDS_SELECTED':
                    $error = $this->lang->words['err_NO_IDS_SELECTED'];
                break;
                /* Move exceptions */
                case 'NO_SUCH_FOLDER':
                    $error = $this->lang->words['err_NO_SUCH_FOLDER'];
                break;
                case 'NO_IDS_TO_MOVE':
                    $error = $this->lang->words['err_NO_IDS_TO_MOVE'];
                break;
                /* Delete exceptions */
                case 'NO_IDS_TO_DELETE':
                    $error = $this->lang->words['err_NO_IDS_TO_DELETE'];
                break;
            }
                
            $this->messengerFunctions->_currentFolderID = $cfolderID;
            return $this->_showFolder( $error );
        }
    }
    
    /**
     * Show a message
     *
     * @return    string        returns HTML
     */
    public function showConversation()
    {
        $topicID = intval( $this->request['topicID'] );
        $start   = intval( $this->request['st'] );
        $end     = intval($this->request['perpage']) > 0 ? intval($this->request['perpage']) : 20;
        
        if ( ! $topicID )
        {
            $this->registry->getClass('output')->showError( 'messenger_no_msgid', 10225, null, null, 404 );
            get_error('messenger_no_msgid');
        }
        
        try
        {
            $conversationData = $this->messengerFunctions->fetchConversation( $topicID, $this->memberData['member_id'], array( 'offsetStart' => $start, 'offsetEnd' => $end ) );
        }
        catch( Exception $error )
        {
            $_msg = $error->getMessage();
            
            if ( $_msg == 'NO_READ_PERMISSION' )
            {
                //$this->registry->getClass('output')->showError( 'messenger_no_msgid', 10229, null, null, 403 );
                get_error('messenger_no_msgid');
            }
            else if ( $_msg == 'YOU_ARE_BANNED' )
            {
                //$this->registry->getClass('output')->showError( 'messenger_you_be_banned_yo', 10275, null, null, 403 );
                get_error('messenger_you_be_banned_yo');
            }
        }
        
        $conversationData['topicData']['_canReply'] = $this->messengerFunctions->canReplyTopic( $this->memberData['member_id'], $conversationData['topicData'], $conversationData['memberData'] );
        
        $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir('core') . '/sources/classes/reportLibrary.php', 'reportLibrary' );
        $reports = new $classToLoad( $this->registry );
        $conversationData['topicData']['_canReport'] = $reports->canReport( 'messages' );

        return $conversationData;
    }
    
    /**
     * Show the folder list
     *
     * @param    string        Any error text
     * @return    string        returns HTML
     */
    protected function _showFolder( $error='' )
    {
        $sort   = $this->request['sort'];
        $start  = intval($this->request['st']);
        $p_end  = intval($this->request['perpage']) > 0 ? intval($this->request['perpage']) : 50;
        $sort_key = '';

        $totalMsg = $this->messengerFunctions->getPersonalTopicsCount( $this->memberData['member_id'], $this->messengerFunctions->_currentFolderID );
        $totalUnreadMsg = $this->messengerFunctions->getPersonalTopicsCount( $this->memberData['member_id'], 'new' );
        
        /* Only update if we're not using a filter */
        if ( ( ! $this->request['folderFilter'] ) AND $totalMsg != $this->messengerFunctions->_dirData[ $this->messengerFunctions->_currentFolderID ]['count'] )
        {
            $this->messengerFunctions->rebuildFolderCount( $this->memberData['member_id'], array( $this->messengerFunctions->_currentFolderID => $totalMsg ) );
        }
        
        if ( $start >= $totalMsg )
        {
            $start = 0;
        }
        
        $messages = $this->messengerFunctions->getPersonalTopicsList( $this->memberData['member_id'], $this->messengerFunctions->_currentFolderID, array( 'sort' => $sort, 'offsetStart' => $start, 'offsetEnd' => $p_end ) );
        
        $conversations = array(
            'total'  => $totalMsg,
            'unread' => $totalUnreadMsg,
            'data'   => $messages
        );
        
        return $conversations;
    }
}