<?php

defined('IN_MOBIQUO') or exit;

class mobi_members_online extends ipsCommand
{
    public function doExecute( ipsRegistry $registry )
    {
        if ( !$this->settings['allow_online_list'] )
        {
            //$this->registry->output->showError( 'onlinelist_disabled', 10230 );
            get_error('onlinelist_disabled');
        }

        $this->request['st'] = 0;
        $this->registry->class_localization->loadLanguageFile( array( 'public_online' ), 'members' );

        return $this->_listAll();
    }
    
    
    protected function _listAll()
    {
        $final      = array();
        $_errors    = array();
        $modules    = array();
        $memberIDs  = array();
        
        if ( !$this->settings['au_cutoff'] )
        {
            $this->settings[ 'au_cutoff'] =  15 ;
        }

        $defaults = array(
            'show_mem'      => 'reg',
            'sort_order'    => 'desc',
            'sort_key'      => 'click',
        );
            
        //-----------------------------------------
        // Sort the db query
        //-----------------------------------------
        
        $cut_off  = $this->settings['au_cutoff'] * 60;
        $t_time   = time() - $cut_off;
        $wheres     = array( 'running_time > ' . $t_time );
        
        if ( ! $this->settings['spider_active'] AND ! $this->memberData['g_access_cp'] )
        {
            $wheres[]    = $this->DB->buildRight( 'id', 8 ) . " != '_session'";
        }
        
        if ( ! $this->settings['disable_anonymous'] AND !$this->memberData['g_access_cp'] )
        {
            $wheres[]    = "login_type != 1";
        }
        
        
        // get guest count
        $wheres_guest = array("member_group = " . $this->settings['guest_group']);
        $this->DB->build( array( 'select'   => '*',
                                 'from'     => 'sessions',
                                 'where'    => implode( ' AND ', array_merge($wheres, $wheres_guest) ),
                                 'calcRows' => TRUE));
                                
        $outer = $this->DB->execute();
        $max_guest = $this->DB->fetchCalculatedRows();
        
        // get member data
        $wheres[] = "member_id > 0";
        $wheres[] = "member_group != " . $this->settings['guest_group'];
        
        $this->DB->build( array( 'select'   => '*',
                                 'from'     => 'sessions',
                                 'where'    => implode( ' AND ', $wheres ),
                                 'calcRows' => TRUE,
                                 'order'    => 'running_time desc',
                                 'limit'    => array(0, 100)));

        $outer = $this->DB->execute();
        $max   = $this->DB->fetchCalculatedRows();
        
        while( $r = $this->DB->fetch($outer) )
        {
            if ( strstr( $r['id'], '_session' ) )
            {
                $r['is_bot']    = 1;
            }

            $r['where_line']    = '';
            $r['where_link']    = '';
            
            if( $this->memberData['member_id'] AND $r['member_id'] == $this->memberData['member_id'] )
            {
                $r['current_appcomponent']  = 'members';
                $r['current_module']        = 'online';
                $r['current_section']       = 'online';
            }
            
            if ( $r['member_id'] )
            {
                $memberIDs[] = $r['member_id'];
            }
            
            if( $r['in_error'] )
            {
                $_errors[ $r['id'] ] = $r;
            }
            else
            {
                $final[ $r['id'] ] = $r;
            }

            $modules[ $r['current_section'] ]  = array( 'app' => $r['current_appcomponent'] );
        }
        
        if ($this->memberData['member_id'] && !in_array($this->memberData['member_id'], $memberIDs))
        {
            $selfdata = array(
                'member_id'     => $this->memberData['member_id'],
                'member_name'   => $this->memberData['members_display_name'],
                'running_time'  => time(),
                'browser'       => 'tapatalk',
                
            );
            array_unshift($memberIDs, $this->memberData['member_id']);
            array_unshift($final, $selfdata);
            $max++;
        }
        
        
        //-----------------------------------------
        // Pass off entries to modules..
        //-----------------------------------------
        
        if ( count( $modules ) )
        {
            foreach( $modules as $module_array )
            {
                if( IPSLib::appIsInstalled( $module_array['app'] ) )
                {
                    $module_array['app'] = IPSText::alphanumericalClean($module_array['app']);
                    
                    $filename = IPSLib::getAppDir( $module_array['app'] ) . '/extensions/coreExtensions.php';
                    
                    if ( is_file( $filename ) )
                    {
                        $classToLoad = IPSLib::loadLibrary( $filename, 'publicSessions__' . $module_array['app'], $module_array['app'] );
                        $loader      = new $classToLoad();
    
                        if( method_exists( $loader, 'parseOnlineEntries' ) )
                        {
                            $final = $loader->parseOnlineEntries( $final );
                        }
                    }
                }
            }
        }

        $final    = array_merge( $final, $_errors );

        //-----------------------------------------
        // Finally, members...
        //-----------------------------------------
        
        if ( count( $memberIDs ) )
        {
            $members = IPSMember::load( $memberIDs, 'all' );
        }
        
        $newFinal = array();
        
        if( is_array($final) AND count($final) )
        {
            foreach( $final as $id => $data )
            {
                if (strpos($data['browser'], 'tapatalk') !== false)
                    $data['where_line'] = 'via Tapatalk';
                
                if ( $data['member_id'] )
                {
                    $newFinal[ 'member-' . $data['member_id'] ] = $data;
                    $newFinal[ 'member-' . $data['member_id'] ]['memberData']  = $members[ $data['member_id'] ];
                    $newFinal[ 'member-' . $data['member_id'] ]['_memberData'] = IPSMember::buildProfilePhoto( $members[ $data['member_id'] ] );
                }
                else
                {
                    $newFinal[ $data['id'] ] = $data;
                    $newFinal[ $data['id'] ]['memberData']  = array();
                    $newFinal[ $data['id'] ]['_memberData'] = IPSMember::buildProfilePhoto( 0 );
                }
            }
        }
        
        if (count($newFinal) > 0)
        {
            foreach ($newFinal as $info)
            {
                //$member = IPSMember::load( $info['member_id'], 'profile_portal,sessions', 'id' );
                //$member = IPSMember::buildDisplayData( $member, array() );
                //$member = IPSMember::getLocation( $member );
                $current_action = $info['where_line'] . (isset($info['where_line_more']) ? ': '.$info['where_line_more'] : '');
                
                $mem_list[] = new xmlrpcval(array(
                    'user_name'             => new xmlrpcval(mobi_unescape_html(to_utf8($info['member_name'])), 'base64'),
                    'username'              => new xmlrpcval(mobi_unescape_html(to_utf8($info['member_name'])), 'base64'),
					'user_type'             => new xmlrpcval(check_return_user_type($info['member_name']),'base64'),
                    'last_activity_time'    => new xmlrpcval(mobiquo_iso8601_encode($info['running_time']), 'dateTime.iso8601'),
                    'icon_url'              => new xmlrpcval($info['_memberData']['pp_small_photo']),
                    'display_text'          => new xmlrpcval(subject_clean($current_action), 'base64'),
                ), 'struct');
            }
            
            $return = array (
                'guest_count'   => $max_guest,
                'member_count'  => $max,
                'list'  => $mem_list,
            );            
        } else {
            $return = array (
                'guest_count'   => $max_guest,
                'member_count'  => 0,
                'list'  => array(),
            );
        }
        
        return $return;
    }
} 