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

require_once( IPS_ROOT_PATH . 'applications/forums/modules_public/ajax/topics.php');

class tapatalk_topics_ajax extends public_forums_ajax_topics
{
    protected function _quote()
    {
        $pids   = explode( ',', IPSText::cleanPermString( $this->request['pids'] ) );
        $_post  = '';

        /* Load editor stuff */
        $classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/editor/composite.php', 'classes_editor_composite' );
        $this->editor = new $classToLoad();

        foreach( $pids as $pid )
        {
            $posts[]  = $this->registry->getClass('topics')->getPostById( $pid );
        }

        /* Permission ids */
        $perm_id    = $this->memberData['org_perm_id'] ? $this->memberData['org_perm_id'] : $this->memberData['g_perm_id'];
        $perm_array = explode( ",", $perm_id );

        /* Load parser */
        $classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/text/parser.php', 'classes_text_parser' );
        $parser = new $classToLoad();

        /* Naughty boy chex */
        foreach( $posts as $post )
        {
            /* Set up data */
            $this->registry->getClass('topics')->setTopicData( $post );

            if ( $this->registry->getClass('topics')->canView() !== true )
            {
                $this->registry->output->showError( 'no_permission');
            }

            if ( $this->registry->permissions->check( 'read', $this->registry->class_forums->getForumById( $post['forum_id'] ), $perm_array ) !== TRUE )
            {
                $this->registry->output->showError( 'no_permission');
            }

            /* Post visible? */
            if ( $this->registry->getClass('class_forums')->fetchHiddenType( $post ) != 'visible' )
            {
                $this->registry->output->showError( 'no_permission');
            }

            /* Phew that was a toughy wasn't it? */
            if ( $this->settings['strip_quotes'] )
            {
                $oldPost       = $post['post'];
                $post['post']  = $parser->stripQuotes( $post['post'] );

                if ( ! preg_match( '#\S+?#', trim( IPSText::stripTags( $post['post'] ) ) ) )
                {
                    $post['post']  = $oldPost;
                }

                unset( $oldPost );
            }

            /* Strip shared media in quotes */
            $post['post'] = $parser->stripSharedMedia( $post['post'] );

            /* We don't use makeQuoteSafe() here because the result is returned via AJAX and inserted as text into the editor.  + shows as &#043; as a result if we do */
            $_quoted    = rtrim( $post['post'] );
            $_name      = ( $post['members_display_name'] ? $post['members_display_name'] : $post['author_name'] );

            $_post     .= $parser->buildQuoteTag( $_quoted, $_name, $post['post_date'], 0, $post['pid'] );
        }

        $this->editor->setContent( $_post, 'topics' );
        $html = $this->registry->output->replaceMacros( $this->editor->getContent() );
        $post_content = $parser->HtmlToBBCode( $html );
        $post_content = preg_replace('/<br\s*\/?>/si', "\n", $post_content);
        $post_content = preg_replace("/(\[quote [^\]]*?\])\n+/si", '$1', $post_content);

        global $quote_post, $request_params;
        $quote_post = array(
            'post_title'   => '',
            'post_content' => $post_content,
            'post_id'      => $request_params[0],
        );
    }

    public function saveTopicTitle()
    {
        /* INIT */
        $name      = to_local($_POST['name']);
        $tid       = intval( $this->request['tid'] );
        $can_edit  = 0;

        /* Check ID */
        if( ! $tid )
        {
            $this->registry->output->showError( 'ajax_no_topic_id');
        }

        /* Load Topic */
        $topic = $this->DB->buildAndFetch( array( 'select' => '*', 'from' => 'topics', 'where' => 'tid=' . $tid ) );

        if( ! $topic['tid'] )
        {
            $this->registry->output->showError( 'ajax_topic_not_found');
        }

        /* Check Permissions */
        if ( $this->memberData['g_is_supmod'] )
        {
            $can_edit = 1;
        }

        else if( is_array( $this->memberData['forumsModeratorData'] ) AND $this->memberData['forumsModeratorData'][ $topic['forum_id'] ]['edit_topic'] )
        {
            $can_edit = 1;
        }

        if( ! $can_edit )
        {
            $this->registry->output->showError( 'ajax_no_t_permission');
        }

        /* Make sure we have a valid name */
        if( trim( $name ) == '' || ! $name )
        {
            $this->registry->output->showError( 'ajax_no_t_name');
            exit();
        }

        /* Clean */
        if( $this->settings['etfilter_shout'] )
        {
            if( function_exists('mb_convert_case') )
            {
                if( in_array( strtolower( $this->settings['gb_char_set'] ), array_map( 'strtolower', mb_list_encodings() ) ) )
                {
                    $name = mb_convert_case( $name, MB_CASE_TITLE, $this->settings['gb_char_set'] );
                }
                else
                {
                    $name = ucwords( strtolower($name) );
                }
            }
            else
            {
                $name = ucwords( strtolower($name) );
            }
        }

        $name       = IPSText::parseCleanValue( $name );
        $name       = $this->cleanTopicTitle( $name );
        $name       = IPSText::getTextClass( 'bbcode' )->stripBadWords( $name );
        $title_seo  = IPSText::makeSeoTitle( $name, TRUE );

        /* Update the topic */
        $this->DB->update( 'topics', array( 'title' => $name, 'title_seo' => $title_seo ), 'tid='.$tid );

        $this->DB->insert( 'moderator_logs', array(
                                                    'forum_id'      => intval( $topic['forum_id'] ),
                                                    'topic_id'      => $tid,
                                                    'member_id'     => $this->memberData['member_id'],
                                                    'member_name'   => $this->memberData['members_display_name'],
                                                    'ip_address'    => $this->member->ip_address,
                                                    'http_referer'  => htmlspecialchars( my_getenv('HTTP_REFERER') ),
                                                    'ctime'         => time(),
                                                    'topic_title'   => $name,
                                                    'action'        => sprintf( $this->lang->words['ajax_topictitle'], $topic['title'], $name),
                                                    'query_string'  => htmlspecialchars( my_getenv('QUERY_STRING') ),
                                          )  );

        /* Update the last topic title? */
        if ( $topic['tid'] == $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['last_id'] )
        {
            $this->DB->update( 'forums', array( 'last_title' => $name, 'seo_last_title' => $title_seo ), 'id=' . $topic['forum_id'] );
        }

        if ( $topic['tid'] == $this->registry->class_forums->forum_by_id[ $topic['forum_id'] ]['newest_id'] )
        {
            $this->DB->update( 'forums', array( 'newest_title' => $name ), 'id=' . $topic['forum_id'] );
        }

        global $result;
        $result = true;
    }

    public function editBoxShow()
    {
        $pid = $attach_rel_id = intval( $this->request['p'] );
        $attach_rel_module = 'post';
        
        if ( ! $pid )
        {
            $this->registry->output->showError( 'error');
        }

        if ($post = $this->registry->getClass('topics')->getPostById( $pid ))
        {
            $fid = $post['forum_id'];
            $tid = $post['tid'] ? $post['tid'] : $post['topic_id'];
        }

        if ( ! $pid OR ! $tid OR ! $fid )
        {
            $this->registry->output->showError( 'error');
        }

        if ( $this->memberData['member_id'] )
        {
            if ( IPSMember::isOnModQueue( $this->memberData ) === NULL )
            {
                $this->registry->output->showError( 'ajax_reply_noperm');
            }
        }

        //-----------------------------------------
        // Get classes
        //-----------------------------------------

        if ( ! is_object( $this->postClass ) )
        {
            $this->registry->getClass( 'class_localization')->loadLanguageFile( array( 'public_editors' ), 'core' );

            require_once( IPSLib::getAppDir( 'forums' ) . "/sources/classes/post/classPost.php" );/*noLibHook*/
            $classToLoad     = IPSLib::loadLibrary( IPSLib::getAppDir( 'forums' ) . '/sources/classes/post/classPostForms.php', 'classPostForms', 'forums' );
            $this->postClass = new $classToLoad( $this->registry );
        }

        /* Set post class data */
        $this->postClass->setForumData( $this->registry->getClass('class_forums')->getForumById( $fid ) );
        $this->postClass->setTopicID( $tid );
        $this->postClass->setForumID( $fid );
        $this->postClass->setPostID( $pid );

        /* Topic Data */
        $this->postClass->setTopicData( $this->registry->getClass('topics')->getTopicById( $tid ) );

        # Set Author
        $this->postClass->setAuthor( $this->member->fetchMemberData() );

        # Get Edit form
        try
        {
            $html = $this->postClass->displayAjaxEditForm();
            $html = $this->registry->output->replaceMacros( $html );
            $post_content = IPSText::UNhtmlspecialchars( $html );
            $post_content = preg_replace("/^.*?<textarea [^>]*?>(.*?)<\/textarea>.*?$/si", '$1', $post_content);
            
            $attachments = array();
            $attach_post_key = $this->postClass->post_key;
            
            //-----------------------------------------
            // Load attachments so we get some stats
            //-----------------------------------------
            
            $classToLoad  = IPSLib::loadLibrary( IPSLib::getAppDir( 'core' ) . '/sources/classes/attach/class_attach.php', 'class_attach' );
            $class_attach = new $classToLoad( $this->registry );
            $class_attach->type             = 'post';
            $class_attach->attach_post_key  = $attach_post_key;
            $class_attach->init();
            $class_attach->getUploadFormSettings();
            
            
            /* Load Language Bits */
            $this->registry->getClass( 'class_localization')->loadLanguageFile( array( 'lang_post' ) );
            
            /* Generate current items... */
            $_more = ( $attach_rel_id ) ? ' OR c.attach_rel_id=' . $attach_rel_id : '';
            
            $this->DB->build( array( 
                                            'select'   => 'c.*',
                                            'from'     => array( 'attachments' => 'c' ),
                                            'where'    => "c.attach_rel_module='{$attach_rel_module}' AND c.attach_post_key='{$attach_post_key}'{$_more}",
                                            'add_join' => array( array(
                                                                        'select' => 't.*',
                                                                        'from'   => array( 'attachments_type' => 't' ),
                                                                        'where'  => 't.atype_extension=c.attach_ext',
                                                                        'type'   => 'left' 
                                                                )   )
                                                
                                    )   );
                                        
            $this->DB->execute();
            
            while( $row = $this->DB->fetch() )
            {
                if ( $attach_rel_module != $row['attach_rel_module'] )
                {
                    continue;
                }
                
                $thumbnail_url = $row['attach_is_image'] && $row['attach_thumb_location'] ? $this->settings['upload_url'] . '/' . $row['attach_thumb_location'] : '';
                $url = $this->registry->getClass('output')->formatUrl( $this->registry->getClass('output')->buildUrl( "app=core&amp;module=attach&amp;section=attach&amp;attach_id={$row[attach_id]}", "public",'' ), "", "" );
                $attachments[] = array(
                    'attach_id'     => $row['attach_id'],
                    'filename'      => str_replace( array( '[', ']' ), '', $row['attach_file'] ),
                    'filesize'      => $row['attach_filesize'],
                    'content_type'  => $row['attach_is_image'] ? 'image' : $row['attach_ext'],
                    'url'           => $url,
                    'thumbnail_url' => $thumbnail_url,
                );
            }
            
            $edit_reason = method_exists($this->postClass, 'getPostEditReason') ? $this->postClass->getPostEditReason() : false;
            
            global $postinfo;
            $postinfo = array(
                'post_title'   => '',
                'post_content' => $post_content,
                'post_id'      => $pid,
                'show_reason'  => $edit_reason !== false,
                'edit_reason'  => $edit_reason,
                'group_id'     => $attach_post_key,
                'attachments'  => $attachments,
            );
        }
        catch ( Exception $error )
        {
            $this->registry->output->showError( $error->getMessage() );
        }
    }
}

ipsRegistry::$request['secure_key'] = $registry->member()->form_hash;