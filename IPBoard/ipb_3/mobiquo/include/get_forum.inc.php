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

require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/forums/boards.php');

class boards extends public_forums_forums_boards
{
    public function doExecute( ipsRegistry $registry )
    {
        ##########
        $settings   =& $registry->fetchSettings();
        if ($settings['board_offline'] == 1 and ($this->memberData['g_access_offline'] != 1)) {
            get_error('Board Offline!');
        }

        if ($settings['force_login'] == 1 and (!$this->memberData['member_id'])) {
            get_error('Forum force login!');
        }
        ##########

        if (! $this->memberData['member_id'] )
        {
            $this->request['last_visit'] = time();
        }
        ####################
        $this->registry->class_forums->strip_invisible = 1;
        $this->registry->class_forums->forumsInit();
        ####################
        $result = $this->processAllCategories();
        //$this->processAllCategories_aa();
        return $result;
    }

    public function getForumsList(&$data, $from_array)
    {
        global $app_version;

        //change the keys first..
        $tmp =  array();
        $keys = array(
            'id' => 'forum_id',
            'name' => 'forum_name',
            'description' => 'description',
            'parent_id' => 'parent_id',
            'password' => 'password',
            'password_override' => 'password_override',

        );
        foreach($keys as $key => $value) {
            if ($from_array[$key]) {
                $tmp[$value] = $from_array[$key];
            } else {
                $tmp[$value] = '';
            }
        }
        if (isset($from_array['password']) and $from_array['password'] != '') {
            $tmp['is_protected'] = true;
        }

        if ($from_array['sub_can_post'] && (version_compare($app_version, '3.2.0', '>=') || (isset($from_array['status']) && $from_array['status']))) {
            $tmp['sub_only'] = false;
        } else {
            $tmp['sub_only'] = true;
        }
        if ($from_array['redirect_on']) {
            $tmp['url'] = $from_array['redirect_url'];
        }
        if ($from_array['parent_id'] == 'root') {
            $tmp['parent_id'] = -1;
        }
        if (isset($from_array['last_post'])) {
            $tmp['last_post'] = $from_array['last_post'];
        }

        $data[ $tmp['forum_id'] ] = $tmp;
    }

    public function insertChildForum(&$forum_list, $forum_id)
    {
        if ( is_array( $this->registry->class_forums->forum_cache[$forum_id] )
             AND count( $this->registry->class_forums->forum_cache[$forum_id]) ){
            ##If Not leaf forum
            foreach($this->registry->class_forums->forum_cache[$forum_id] as $subform_id => $data){
                self::insertChildForum($forum_list, $subform_id);
            }
        }
        ### now .... must be leaf forums...
        if (isset($forum_list[$forum_id]))
        {
            $parent_id = $forum_list[$forum_id]->structmem('parent_id')->getval();
            if ($parent_id != -1) {######## NOT ROOTS
                if( $forum_list[$parent_id]->structmem('child') ) {
                    // already have child..
                    $num = $forum_list[$parent_id]->structmem('child')->arraysize();
                    $forum_list[$parent_id]->structmem('child')->addArray(array($num => $forum_list[$forum_id]));
                } else {
                    //have no child yet...
                    $forum_list[$parent_id]->addStruct(array('child' => new xmlrpcval(array(),'array')));
                    $forum_list[$parent_id]->structmem('child')->addArray(array(0 => $forum_list[$forum_id]));
                }
                unset($forum_list[$forum_id]);
            }
        }
    }


    public function processAllCategories()
    {
        global $mobiquo_config,$settings;
        
        /* INIT */
        $forum_tree      = array();
        $all_forums = $this->registry->class_forums->forum_cache;
        unset($all_forums["root"]);
        $parent_id = (!isset($_POST['parent_id']) || (intval($_POST['parent_id'])== 0)) ? 'root' : intval($_POST['parent_id']);
        $child_arr = $this->registry->class_forums->forum_cache[$parent_id];
        if( is_array( $child_arr ) AND count( $child_arr ) ) {
            foreach( $child_arr as $cat_id => $cat_data ) {
              
                    if (is_array($mobiquo_config['hide_forum_id']) && in_array($cat_id, $mobiquo_config['hide_forum_id']))
                        continue;
                    
                    ###     filter the keys to our API defined....handle the roots(categories)....
                    $this->getForumsList($forum_tree, $cat_data);
            }
        }
        if( is_array( $all_forums) AND count( $all_forums ) && ($parent_id == 'root')) {
            foreach( $all_forums as $forum_id => $sub_forums ) {
                foreach ($sub_forums as $sub_forum_id => $forum_data)
                {
                    // ignore hide forum
                    if (is_array($mobiquo_config['hide_forum_id']))
                    {
                        if (in_array($sub_forum_id, $mobiquo_config['hide_forum_id']))
                            continue;
                        else if (in_array($forum_data['parent_id'], $mobiquo_config['hide_forum_id']))
                        {
                            $mobiquo_config['hide_forum_id'][] = $sub_forum_id;
                            continue;
                        }
                    }
                    
                    ###    filter the keys to our API defined....handle the forums.....
                    $this->getForumsList($forum_tree, $forum_data);
                }
            }
        }
        #############Get the xmlprc structured list
        $xmlprc_forum_list = array();
        foreach($forum_tree as $forum_id => $forum_data) {

            $new_post = isset($forum_data['new_post']) ? $forum_data['new_post'] : false;

            if (!$new_post && isset($forum_data['last_post']))
            {
                $rtime = $this->registry->classItemMarking->fetchTimeLastMarked( array( 'forumID' => $forum_data['forum_id'] ), 'forums' );
                if( $forum_data['last_post'] > $rtime )
                {
                    $new_post = true;
                }
            }

            if ($new_post)
            {
                if (isset($xmlprc_forum_list[$forum_data['parent_id']]))
                    $xmlprc_forum_list[$forum_data['parent_id']]->addStruct(array('new_post' => new xmlrpcval(true, 'boolean')));
                else
                    $forum_tree[$forum_data['parent_id']]['new_post'] = true;
            }

            if (!$forum_data['sub_only'] && $this->memberData['member_id']) {
                $can_subscribe = true;
                $is_subscribed = ($this->settings['cpu_watch_update']
                                    AND ( is_array( $this->memberData['_cache'] )
                                         AND is_array( $this->memberData['_cache']['watchedForums'] )
                                         AND in_array( $forum_data['forum_id'], $this->memberData['_cache']['watchedForums'] )
                                        )
                                 );
            } else {
                $can_subscribe = false;
                $is_subscribed = false;
            }

            if (version_compare($GLOBALS['app_version'], '3.2.0', '>='))
            {
                $is_subscribed = is_subscribed($forum_id, 'forums');
            }
           
            if ( ! in_array( $this->memberData['member_group_id'], explode(",", $forum_data['password_override']) ) AND ( isset($forum_data['password']) AND $forum_data['password'] != "" ) AND ($forum_data['parent_id'] > 0))
            {
                $is_protected = true;
            }
            else
            {
                $is_protected = false;
            }

            $forum_type = $forum_data['url'] ? 'link' : ($forum_data['sub_only'] ? 'category' : 'forum');
            
            if ($logo_icon_name = tp_get_forum_icon($forum_id, $forum_type, false, $new_post))
                $logo_url = $settings['board_url'] . '/' . $settings['tapatalk_directory'] . '/forum_icons/'.$logo_icon_name;
            else
            {
                $logo_url = '';
            }

            $forum_data = new xmlrpcval(array(
                     'forum_id'      => new xmlrpcval($forum_data['forum_id'], 'string'),
                    'forum_name'    => new xmlrpcval(mobi_unescape_html(to_utf8($forum_data['forum_name'])), 'base64'),
                    'description'   => new xmlrpcval(mobi_unescape_html(to_utf8($forum_data['description'])), 'base64'),
                    'parent_id'     => new xmlrpcval($forum_data['parent_id'], 'string'),
                    'logo_url'      => new xmlrpcval($forum_data['logo_url'], 'string'),
                    'is_protected'  => new xmlrpcval($is_protected, 'boolean'),
                    'url'           => new xmlrpcval($logo_url, 'string'),
                    'sub_only'      => new xmlrpcval($forum_data['sub_only'] ? true : false, 'boolean'),
                    'new_post'      => new xmlrpcval($new_post, 'boolean'),
                    'can_subscribe' => new xmlrpcval($can_subscribe, 'boolean'),
                    'is_subscribed' => new xmlrpcval($is_subscribed, 'boolean'),
                 ), 'struct');
            $xmlprc_forum_list[$forum_id] = $forum_data;
        }
        //  Creat the tree structure
        if( is_array( $child_arr ) AND count( $child_arr ) && ($parent_id == 'root')) {
            foreach( $child_arr as $id => $cat_data ) {
                if( isset( $this->registry->class_forums->forum_cache[ $id ] ) AND is_array( $this->registry->class_forums->forum_cache[ $id ] ) )
                {
                    ### change to the tree structure our API defined.....
                    self::insertChildForum($xmlprc_forum_list, $id);
                }

            }
        }
        $result = array();
        foreach($xmlprc_forum_list as $id => $data) {
            if( isset( $child_arr[ $id ] )
                AND is_array( $child_arr[ $id ] ) )
            {
                $result[] = $data;
            }
        }
        return $result;
    }

}

$boards = new boards($registry);
$boards->makeRegistryShortcuts($registry);
$forum_tree = $boards->doExecute($registry);

