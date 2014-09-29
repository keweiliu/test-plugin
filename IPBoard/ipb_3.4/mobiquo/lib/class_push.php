<?php

defined( 'IN_IPB' ) or exit;

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
            $postContent = preg_replace("/@\[member='(.*?)'\]/is", '@#$1#', $postContent);

            if ( preg_match_all( '/(?<=^@|\s@|@)(#(.{1,50})#|\S{1,50}(?=[,\.;!\?<]|\s|$))/U', $postContent, $tags ) )
            {
                foreach ($tags[2] as $index => $tag)
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
        $this->notifyPost($post, $touids, 'tag',$pushStatus);
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
                    'author_id' => $this->memberData['member_id'],
                    'dateline'  => $post['post_date'],
                );
                
                $push_data[] = $temp_data;
                unset($temp_data);
            }
            $this->push($push_data, $pushStatus);
        }
    }

    public function notifyConv( $conv, $touids, $type = 'conv' ,$pushStatus = true)
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
                    'author_id' => $this->memberData['member_id'],
                    'dateline'  => $conv['mt_last_post_time'],
                );
                
                $push_data[] = $temp_data;
                unset($temp_data);
            }

            $this->push($push_data,$pushStatus);
        }
    }

    protected function push($push_data,$pushStatus)
    {
        if (!empty($push_data))
        {
            $this->insertPushData($push_data);
            $data = array(
                'url'  => $this->settings['board_url'],
                'key'  => (!empty($this->settings['tapatalk_push_key']) ? $this->settings['tapatalk_push_key'] : ''),
                'data' => base64_encode(serialize($push_data)),
            );
            if($pushStatus)
                $this->do_post_request($data);
        }
    }

    static function do_post_request($data)
    {
        $push_url = 'http://push.tapatalk.com/push.php';

        if(isset($data['test']) || isset($data['ip']))
            return tapatalk_push::getContentFromRemoteServer($push_url, 10, $error, 'POST', $data);

        //Initial this key in modSettings
        $modSettings = tapatalk_push::load_push_slug();

        //Get push_slug from db
        $slug = isset($modSettings)? $modSettings : array();
        $slug = tapatalk_push::push_slug($slug, 'CHECK');

        //If it is valide(result = true) and it is not sticked, we try to send push
        if($slug[2] && !$slug[5])
        {
            //Slug is initialed or just be cleared
            if($slug[8])
            {
                tapatalk_push::updateSettings($slug);
            }

            //Send push
            $push_resp = tapatalk_push::getContentFromRemoteServer($push_url, 0, $error, 'POST', $data);

            if(trim($push_resp) === 'Invalid push notification key') $push_resp = 1;
            if(!is_numeric($push_resp) && !preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $push_resp))
            {
                //Sending push failed, try to update push_slug to db
                $slug = tapatalk_push::push_slug($slug, 'UPDATE');

                if($slug[2] && $slug[8])
                {
                    tapatalk_push::updateSettings($slug);
                }
            }
        }

        return $push_resp;
    }

    /**
     * Get content from remote server
     *
     * @param string $url      NOT NULL          the url of remote server, if the method is GET, the full url should include parameters; if the method is POST, the file direcotry should be given.
     * @param string $holdTime [default 0]       the hold time for the request, if holdtime is 0, the request would be sent and despite response.
     * @param string $error_msg                  return error message
     * @param string $method   [default GET]     the method of request.
     * @param string $data     [default array()] post data when method is POST.
     *
     * @exmaple: getContentFromRemoteServer('http://push.tapatalk.com/push.php', 0, $error_msg, 'POST', $ttp_post_data)
     * @return string when get content successfully|false when the parameter is invalid or connection failed.
    */
    static function getContentFromRemoteServer($url, $holdTime = 0, &$error_msg, $method = 'GET', $data = array())
    {
        //Validate input.
        $vurl = parse_url($url);
        if ($vurl['scheme'] != 'http')
        {
            $error_msg = 'Error: invalid url given: '.$url;
            return false;
        }
        if($method != 'GET' && $method != 'POST')
        {
            $error_msg = 'Error: invalid method: '.$method;
            return false;//Only POST/GET supported.
        }
        if($method == 'POST' && empty($data))
        {
            $error_msg = 'Error: data could not be empty when method is POST';
            return false;//POST info not enough.
        }

        if(!empty($holdTime) && function_exists('file_get_contents') && $method == 'GET')
        {
            $response = @file_get_contents($url);
        }
        else if (@ini_get('allow_url_fopen'))
        {
            if(empty($holdTime))
            {
                // extract host and path:
                $host = $vurl['host'];
                $path = $vurl['path'];

                if($method == 'POST')
                {
                    $fp = @fsockopen($host, 80, $errno, $errstr, 5);

                    if(!$fp)
                    {
                        $error_msg = 'Error: socket open time out or cannot connet.';
                        return false;
                    }

                    $data =  http_build_query($data);

                    fputs($fp, "POST $path HTTP/1.1\r\n");
                    fputs($fp, "Host: $host\r\n");
                    fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
                    fputs($fp, "Content-length: ". strlen($data) ."\r\n");
                    fputs($fp, "Connection: close\r\n\r\n");
                    fputs($fp, $data);
                    fclose($fp);
                    return 1;
                }
                else
                {
                    $error_msg = 'Error: 0 hold time for get method not supported.';
                    return false;
                }
            }
            else
            {
                if($method == 'POST')
                {
                    $params = array('http' => array(
                        'method' => 'POST',
                        'content' => http_build_query($data, '', '&'),
                    ));
                    $ctx = stream_context_create($params);
                    $old = ini_set('default_socket_timeout', $holdTime);
                    $fp = @fopen($url, 'rb', false, $ctx);
                }
                else
                {
                    $fp = @fopen($url, 'rb', false);
                }
                if (!$fp)
                {
                    $error_msg = 'Error: fopen failed.';
                    return false;
                }
                ini_set('default_socket_timeout', $old);
                stream_set_timeout($fp, $holdTime);
                stream_set_blocking($fp, 0);

                $response = @stream_get_contents($fp);
            }
        }
        elseif (function_exists('curl_init'))
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            if($method == 'POST')
            {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            if(empty($holdTime))
            {
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT,1);
            }
            $response = curl_exec($ch);
            curl_close($ch);
        }
        else
        {
            $error_msg = 'CURL is disabled and PHP option "allow_url_fopen" is OFF. You can enable CURL or turn on "allow_url_fopen" in php.ini to fix this problem.';
            return false;
        }
        return $response;
    }

    static function push_slug($push_v_data, $method = 'NEW')
    {
        if(empty($push_v_data))
            $push_v_data = array();

        $current_time = time();
        if(!is_array($push_v_data))
            return array(2 => 0, 3 => 'Invalid v data', 5 => 0);
        if($method != 'CHECK' && $method != 'UPDATE' && $method != 'NEW')
            return array(2 => 0, 3 => 'Invalid method', 5 => 0);

        if($method != 'NEW' && !empty($push_v_data))
        {
            $push_v_data[8] = $method == 'UPDATE';
            if($push_v_data[5] == 1)
            {
                if($push_v_data[6] + $push_v_data[7] > $current_time)
                    return $push_v_data;
                else
                    $method = 'NEW';
            }
        }

        if($method == 'NEW' || empty($push_v_data))
        {
            $push_v_data = array();     //Slug
            $push_v_data[0] = 3;        //        $push_v_data['max_times'] = 3;                //max push failed attempt times in period
            $push_v_data[1] = 300;      //        $push_v_data['max_times_in_period'] = 300;     //the limitation period
            $push_v_data[2] = 1;        //        $push_v_data['result'] = 1;                   //indicate if the output is valid of not
            $push_v_data[3] = '';       //        $push_v_data['result_text'] = '';             //invalid reason
            $push_v_data[4] = array();  //        $push_v_data['stick_time_queue'] = array();   //failed attempt timestamps
            $push_v_data[5] = 0;        //        $push_v_data['stick'] = 0;                    //indicate if push attempt is allowed
            $push_v_data[6] = 0;        //        $push_v_data['stick_timestamp'] = 0;          //when did push be sticked
            $push_v_data[7] = 600;      //        $push_v_data['stick_time'] = 600;             //how long will it be sticked
            $push_v_data[8] = 1;        //        $push_v_data['save'] = 1;                     //indicate if you need to save the slug into db
            return $push_v_data;
        }

        if($method == 'UPDATE')
        {
            $push_v_data[4][] = $current_time;
        }
        $sizeof_queue = count($push_v_data[4]);

        $period_queue = $sizeof_queue > 1 ? ($push_v_data[4][$sizeof_queue - 1] - $push_v_data[4][0]) : 0;

        $times_overflow = $sizeof_queue > $push_v_data[0];
        $period_overflow = $period_queue > $push_v_data[1];

        if($period_overflow)
        {
            if(!array_shift($push_v_data[4]))
                $push_v_data[4] = array();
        }

        if($times_overflow && !$period_overflow)
        {
            $push_v_data[5] = 1;
            $push_v_data[6] = $current_time;
        }

        return $push_v_data;
    }

    static function updateSettings($data)
    {
        @ipsRegistry::cache()->setCache( 'tapatalk_push_slug', $data, array( 'array' => 1 ) );
    }

    static function load_push_slug()
    {
        $cache = @ipsRegistry::cache()->getCache('tapatalk_push_slug');
        return empty($cache) ? array() : $cache;
    }

    protected function isTapatalkUser($userid)
    {
        $user = $this->DB->buildAndFetch( array( 'select' => 'userid', 'from' => 'tapatalk_users', 'where' => 'userid=' . intval($userid) ) );
        return isset($user['userid']) && $user ? true : false;
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
                if(@$this->DB->checkForField('author_id',$table))
                {
                    $insert_data['author_id'] = $data['author_id'];
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
}
