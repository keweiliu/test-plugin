<?php

class tapatalk_push
{
    // user id list that already got tag push notification
    static public $_taguids = array();
    
    public function __construct( ipsRegistry $registry )
    {
        /* Make registry objects */
        $this->registry     =  $registry;
        $this->DB           =  $this->registry->DB();
        $this->settings     =& $this->registry->fetchSettings();
        $this->request      =& $this->registry->fetchRequest();
        $this->lang         =  $this->registry->getClass('class_localization');
        $this->member       =  $this->registry->member();
        $this->memberData   =& $this->registry->member()->fetchMemberData();
        $this->cache        =  $this->registry->cache();
        $this->caches       =& $this->registry->cache()->fetchCaches();
    }
    
    public function notifyTag( $post, $subscriptionSentTo = array(), $pushStatus = true)
    {
        $topic = $this->registry->getClass('topics')->getTopicById( $post['topic_id'] );
        $post['title'] = $topic['title'];
        
        // Users that need get taged push notification
        $seen = array();
        
        if ( stristr( $post['post'], '@' ) )
        {
            $postContent = preg_replace('/<br\s*\/?>/is', ' ', $post['post']);
            $postContent = str_replace('&#33;', '!', $postContent);
            
            if ( preg_match_all( '/(?<=^@|\s@|@)(#(.{1,50})#|\[member=\'(.{1,50})\'\]|\S{1,50}(?=[,\.;!\?]|\s|$))/U', $postContent, $tags ) )
            {
                foreach ($tags[2] as $index => $tag)
                {
                    if ($tag) $tags[1][$index] = $tag;
                }
                
                foreach ($tags[3] as $index => $tag)
                {
                    if ($tag) $tags[1][$index] = $tag;
                }
                
                $members = IPSMember::load( array_unique($tags[1]), 'all', 'displayname' );
                foreach( $members AS $uid => $member )
                {
                    if ( $this->registry->getClass('topics')->canView( $topic, $member ) )
                    {
                        if ( ( ! isset( $seen[ $uid ] ) ) && $uid && ( $uid != $this->memberData['member_id'] ) and ( ! in_array( $uid, $subscriptionSentTo ) ) )
                        {
                            $seen[ $uid ] = true;
                        }
                    }
                }
            }
        }
        $touids = empty($seen) ? array() : array_keys($seen);
        self::$_taguids = $touids;
        $this->notifyPost($post, $touids, 'tag', $pushStatus);
    }
    
    public function notifyPost( $post, $touids, $type , $pushStatus = true)
    {
        if (!empty($post) && is_array($touids) && !empty($touids))
        {
            foreach($touids as $userid)
            {
                if (!$this->isTapatalkUser($userid)) continue;
                
                $temp_data = array(
                    'userid'    => $userid,
                    'type'      => $type,
                    'id'        => $post['topic_id'],
                    'subid'     => $post['pid'],
                    'title'     => $this->toUtf8($post['title']),
                    'author'    => $this->toUtf8($this->memberData['members_display_name']),
                    'dateline'  => $post['post_date'],
                );
                $push_data[] = $temp_data;
                unset($temp_data);
            }
            $this->push($push_data, $pushStatus);
        }
    }
    
    public function notifyConv( $conv, $touids, $type = 'conv', $pushStatus = true)
    {
        if (!empty($conv) && is_array($touids) && !empty($touids))
        {
            foreach($touids as $userid)
            {
                if (!$this->isTapatalkUser($userid)) continue;
                
                $temp_data = array(
                    'userid'    => $userid,
                    'type'      => $type,
                    'id'        => $conv['mt_id'],
                    'subid'     => $conv['mt_replies'] + 1,
                    'title'     => $this->toUtf8($conv['mt_title']),
                    'author'    => $this->toUtf8($this->memberData['members_display_name']),
                    'dateline'  => $conv['mt_last_post_time'],
                );
                $push_data[] = $temp_data;
                unset($temp_data);
            }
            
            $this->push($push_data,$pushStatus);
        }
    }
    
    protected function push($push_data, $pushStatus)
    {
        if (!empty($push_data))
        {
            $this->insertPushData($push_data);
            $data = array(
                'url'  => $this->settings['board_url'],
                'key'  => (!empty($this->settings['tapatalk_push_key']) ? $this->settings['tapatalk_push_key'] : ''),
                'data' => base64_encode(serialize($push_data)),
            );
            
            if ($pushStatus)
                $this->do_post_request($data);
        }
    }
    
    static function do_post_request($data)
    {
        $push_url = 'http://push.tapatalk.com/push.php';
        $timeout = isset($data['test']) || isset($data['ip']) ? 10 : 1;
        $response = 'CURL is disabled and PHP option "allow_url_fopen" is OFF. You can enable CURL or turn on "allow_url_fopen" in php.ini to fix this problem.';
        if (function_exists('curl_init'))
        {
            $ch = curl_init($push_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            $response = curl_exec($ch);
            curl_close($ch);
        }
        elseif (ini_get('allow_url_fopen'))
        {
            $params = array('http' => array(
                'method' => 'POST',
                'content' => http_build_query($data, '', '&'),
            ));
            
            $ctx = stream_context_create($params);
            $fp = @fopen($push_url, 'rb', false, $ctx);
            if (!$fp) return false;
            $response = @stream_get_contents($fp);
        }
        
        return $response;
    }
    
    public function getTagUids()
    {
        return self::$_taguids;
    }
    
    public function toUtf8($str)
    {
        $str = IPSText::convertCharsets($str, IPS_DOC_CHAR_SET, 'utf-8');
        $str = preg_replace('/(&#\d+;|&\w+;)/e', "@html_entity_decode('$1', ENT_QUOTES, 'UTF-8')", $str);
        return $str;
    }
    
    protected function insertPushData($pushData)
    {
        $table = 'tapatalk_push_data';
        if(is_array($pushData))
        {
            foreach ($pushData as $data)
            {
                
                $insert_data = array(
                    'author' => $data['author'] , 
                    'user_id' => $data['userid'],
                    'data_type' => $data['type'],
                    'title' => $data['title'],
                    'data_id' => $data['subid'],
                    'create_time' => $data['dateline'],
                );
                
                if(@$this->DB->checkForField('sub_id',$table))
                {
                    $insert_data['sub_id'] = $data['id'];
                }
                if((@$this->DB->checkForField('sub_id',$table)) && ($insert_data['data_type'] == 'conv'))
                {
                    $insert_data['sub_id'] = $data['subid'];
                }
                if($insert_data['data_type'] == 'conv')
                {
                    $insert_data['data_id'] = $data['id'];
                }
                if($insert_data['data_type'] == 'like')
                {
                    $insert_data['create_time'] = time();
                }
                if($this->DB->checkForTable($table))
                {
                    $this->DB->insert( $table, $insert_data );
                }
            }
        }
        
    }
    
    protected function isTapatalkUser($userid)
    {
        $user = $this->DB->buildAndFetch( array( 'select' => 'userid', 'from' => 'tapatalk_users', 'where' => 'userid=' . intval($userid) ) );
        return isset($user['userid']) && $user ? true : false;
    }
}
