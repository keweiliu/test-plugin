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

require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/forums/boards.php');

class boards extends public_forums_forums_boards
{
    public function doExecute( ipsRegistry $registry )
    {
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

        if ($from_array['sub_can_post']) {
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
        $forum_tree = array();
        $all_forums = $this->registry->class_forums->forum_cache;
        unset($all_forums["root"]);
        $parent_id = (!isset($_POST['parent_id']) || (intval($_POST['parent_id'])== 0)) ? 'root' : intval($_POST['parent_id']);
        $child_arr = $this->registry->class_forums->forum_cache[$parent_id];
        if( is_array( $child_arr ) AND count( $child_arr ) )
        {
            foreach( $child_arr as $cat_id => $cat_data )
            {
                if (is_array($mobiquo_config['hide_forum_id']) && in_array($cat_id, $mobiquo_config['hide_forum_id']))
                    continue;
                
                if (!$this->memberData['member_id'] && is_array($mobiquo_config['hide_forum_id_for_guest']) && in_array($cat_id, $mobiquo_config['hide_forum_id_for_guest']))
                    continue;
                
                if (tapatalk_is_ios() && is_array($mobiquo_config['hide_forum_id_for_ios']) && in_array($cat_id, $mobiquo_config['hide_forum_id_for_ios']))
                    continue;
                
                if (tapatalk_is_android() && is_array($mobiquo_config['hide_forum_id_for_android']) && in_array($cat_id, $mobiquo_config['hide_forum_id_for_android']))
                    continue;
                
                ###     filter the keys to our API defined....handle the roots(categories)....
                $this->getForumsList($forum_tree, $cat_data);
            }
        }
        
        if( is_array( $all_forums) AND count( $all_forums ) && ($parent_id == 'root'))
        {
            foreach( $all_forums as $forum_id => $sub_forums )
            {
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
                    
                    // ignore hide forum for guest only
                    if (!$this->memberData['member_id'] && is_array($mobiquo_config['hide_forum_id_for_guest']))
                    {
                        if (in_array($sub_forum_id, $mobiquo_config['hide_forum_id_for_guest']))
                            continue;
                        else if (in_array($forum_data['parent_id'], $mobiquo_config['hide_forum_id_for_guest']))
                        {
                            $mobiquo_config['hide_forum_id_for_guest'][] = $sub_forum_id;
                            continue;
                        }
                    }
                    
                    // ignore hide forum for ios
                    if (tapatalk_is_ios() && is_array($mobiquo_config['hide_forum_id_for_ios']))
                    {
                        if (in_array($sub_forum_id, $mobiquo_config['hide_forum_id_for_ios']))
                            continue;
                        else if (in_array($forum_data['parent_id'], $mobiquo_config['hide_forum_id_for_ios']))
                        {
                            $mobiquo_config['hide_forum_id_for_ios'][] = $sub_forum_id;
                            continue;
                        }
                    }
                    
                    // ignore hide forum for ios
                    if (tapatalk_is_android() && is_array($mobiquo_config['hide_forum_id_for_android']))
                    {
                        if (in_array($sub_forum_id, $mobiquo_config['hide_forum_id_for_android']))
                            continue;
                        else if (in_array($forum_data['parent_id'], $mobiquo_config['hide_forum_id_for_android']))
                        {
                            $mobiquo_config['hide_forum_id_for_android'][] = $sub_forum_id;
                            continue;
                        }
                    }
                    
                    ###    filter the keys to our API defined....handle the forums.....
                    $this->getForumsList($forum_tree, $forum_data);
                }
            }
        }
        
        #############Get the xmlprc structured list
        $origin_forum_tree = $forum_tree;
        $i = 1;
        $xmlprc_forum_list = array();
        while(!empty($forum_tree) && $i < 100)
        {
            $i++;
            $parent_ids = array();
            foreach($forum_tree as $forum_data)
            {
                $parent_ids[$forum_data['parent_id']] = 1;
            }
            
            foreach($forum_tree as $forum_id => $forum_data)
            {
                if (in_array($forum_id, array_keys($parent_ids))) continue;
                
                $new_post = isset($forum_data['new_post']) ? $forum_data['new_post'] : false;
                
                if (!$new_post && isset($forum_data['last_post']))
                {
                    $rtime = $this->registry->classItemMarking->fetchTimeLastMarked( array( 'forumID' => $forum_data['forum_id'] ), 'forums' );
                    if( $forum_data['last_post'] > $rtime )
                    {
                        $new_post = true;
                    }
                }
                
                if ($new_post && isset($forum_tree[$forum_data['parent_id']]))
                {
                    $forum_tree[$forum_data['parent_id']]['new_post'] = true;
                }
                
                $can_subscribe = !$forum_data['sub_only'] && $this->memberData['member_id'];
                $is_subscribed = is_subscribed($forum_id, 'forums');
               
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
                    if ($forum_data['url'])
                        $logo_url = $settings['img_url'].'/f_redirect.png';
                    else if ($new_post)
                        $logo_url = $settings['img_url'].'/f_icon.png';
                    else
                        $logo_url = $settings['img_url'].'/f_icon_read.png';
                }
                
                $xmlprc_forum_list[$forum_id] = new xmlrpcval(array(
                    'forum_id'      => new xmlrpcval($forum_data['forum_id'], 'string'),
                    'forum_name'    => new xmlrpcval(subject_clean($forum_data['forum_name']), 'base64'),
                    'description'   => new xmlrpcval(subject_clean($forum_data['description']), 'base64'),
                    'parent_id'     => new xmlrpcval($forum_data['parent_id'], 'string'),
                    'logo_url'      => new xmlrpcval($logo_url, 'string'),
                    'is_protected'  => new xmlrpcval($is_protected, 'boolean'),
                    'url'           => new xmlrpcval($forum_data['url'], 'string'),
                    'sub_only'      => new xmlrpcval($forum_data['sub_only'] ? true : false, 'boolean'),
                    'new_post'      => new xmlrpcval($new_post, 'boolean'),
                    'can_subscribe' => new xmlrpcval($can_subscribe, 'boolean'),
                    'is_subscribed' => new xmlrpcval($is_subscribed, 'boolean'),
                ), 'struct');
                
                unset($forum_tree[$forum_id]);
            }
        }
        
        // keep original forum order
        $xmlprc_forum_list_order = array();
        foreach($origin_forum_tree as $id => $data)
        {
            if (isset($xmlprc_forum_list[$id]))
            {
                $xmlprc_forum_list_order[$id] = $xmlprc_forum_list[$id];
            }
        }
        
        //  Creat the tree structure
        if( is_array( $child_arr ) AND count( $child_arr ) && ($parent_id == 'root')) {
            foreach( $child_arr as $id => $cat_data ) {
                if( isset( $this->registry->class_forums->forum_cache[ $id ] ) AND is_array( $this->registry->class_forums->forum_cache[ $id ] ) )
                {
                    ### change to the tree structure our API defined.....
                    self::insertChildForum($xmlprc_forum_list_order, $id);
                }

            }
        }
        
        $result = array();
        foreach($xmlprc_forum_list_order as $id => $data) {
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

