<?php

if (!defined('SMF'))
    die('Hacking attempt...');

error_reporting(0);

function tapatalk_push_post($post_id, $newTopic = false)
{
    global $user_info, $db_prefix, $boardurl, $modSettings, $topic, $board, $ID_MEMBER;

    //subscribe push
    $pushed_user_ids = array();
    if ($topic && $post_id && (function_exists('curl_init') || ini_get('allow_url_fopen')))
    {
        if ($newTopic)
            $request = db_query("
                SELECT ln.id_member
                FROM {$db_prefix}log_notify ln, {$db_prefix}tapatalk_users tu
                WHERE ln.id_member=tu.uid AND ln.id_board = ".intval($board), __FILE__, __LINE__);
        else
            $request = db_query("
                SELECT ln.id_member
                FROM {$db_prefix}log_notify ln, {$db_prefix}tapatalk_users tu
                WHERE ln.id_member=tu.uid AND ln.id_topic = ".intval($topic), __FILE__, __LINE__);
        while($row = mysql_fetch_assoc($request))
        {
            if ($row['id_member'] == $ID_MEMBER) continue;
    
            $ttp_data = array(
                'userid'    => $row['id_member'],
                'type'      => $newTopic ? 'newtopic' : 'sub',
                'id'        => $topic,
                'subid'     => $post_id,
                'title'     => tt_push_clean($_POST['subject']),
                'author'    => tt_push_clean($user_info['name']),
                'authorid'  => $ID_MEMBER,
                'dateline'  => time(),
            );
            $pushed_user_ids[] = $row['id_member'];
            //store_as_alert($ttp_data);
            $ttp_post_data = array(
                'url'  => $boardurl,
                'data' => base64_encode(serialize(array($ttp_data))),
            );
            //if(isset($modSettings['tp_push_key']) && !empty($modSettings['tp_push_key']))
                //$ttp_post_data['key'] = $modSettings['tp_push_key'];

            $return_status = tt_do_post_request($ttp_post_data);
        }
    }
    tapatalk_push_quote_tag($post_id, false, $pushed_user_ids);
}

function tapatalk_push_quote_tag($post_id, $newtopic = false, $pushed_user_ids = array())
{
    global $user_info, $context, $boardurl, $modSettings, $topic, $db_prefix, $ID_MEMBER;
    
    if ($topic && isset($_POST['message']) && $post_id && (function_exists('curl_init') || ini_get('allow_url_fopen')))
    {
        $message = $_POST['message'];
        //quote push
        $quotedUsers = array();
        
        if(preg_match_all('/\[quote author=(.*?) link=.*?\]/si', $message, $quote_matches))
        {
            $quotedUsers = $quote_matches[1];
            $quote_ids = verify_smf_userids_from_names($quotedUsers);
            if(!empty($quote_ids))
            {
                $quote_ids_str = implode(',', $quote_ids);
                $request = db_query("
                    SELECT tu.uid
                    FROM {$db_prefix}tapatalk_users tu
                    WHERE tu.uid IN ($quote_ids_str)", __FILE__, __LINE__);
                while($row = mysql_fetch_assoc($request))
                {
                    if ($row['uid'] == $ID_MEMBER) continue;
                    if (in_array($row['uid'], $pushed_user_ids)) continue;
                    
                    $ttp_data = array(
                        'userid'    => $row['uid'],
                        'type'      => 'quote',
                        'id'        => ($newtopic ? $topic : $context['current_topic']),
                        'subid'     => $post_id,
                        'title'     => tt_push_clean($_POST['subject']),
                        'author'    => tt_push_clean($user_info['name']),
                        'authorid'  => $ID_MEMBER,
                        'dateline'  => time(),
                    );
                    $pushed_user_ids[] = $row['uid'];
                    //store_as_alert($ttp_data);
                    $ttp_post_data = array(
                        'url'  => $boardurl,
                        'data' => base64_encode(serialize(array($ttp_data))),
                    );
                    //if(isset($modSettings['tp_push_key']) && !empty($modSettings['tp_push_key']))
                        //$ttp_post_data['key'] = $modSettings['tp_push_key'];
                    
                    $return_status = tt_do_post_request($ttp_post_data);
                }
            }
        }
        
        //@ push
        if (preg_match_all( '/(?<=^@|\s@)(#(.{1,50})#|\S{1,50}(?=[<,\.;!\?]|\s|$))/U', $message, $tags ) )
        {
            foreach ($tags[2] as $index => $tag)
            {
                if ($tag) $tags[1][$index] = $tag;
            }
            $tagged_usernames =  array_unique($tags[1]);
            $tag_ids = verify_smf_userids_from_names($tagged_usernames);
            if(!empty($tag_ids))
            {
                $tag_ids_str = implode(',', $tag_ids);
                $request = db_query("
                    SELECT tu.uid
                    FROM {$db_prefix}tapatalk_users tu
                    WHERE tu.uid IN ($tag_ids_str)", __FILE__, __LINE__);
                while($row = mysql_fetch_assoc($request))
                {
                    if ($row['uid'] == $ID_MEMBER) continue;
                    if (in_array($row['uid'], $pushed_user_ids)) continue;
                    
                    $ttp_data = array(
                        'userid'    => $row['uid'],
                        'type'      => 'tag',
                        'id'        => ($newtopic ? $topic : $context['current_topic']),
                        'subid'     => $post_id,
                        'title'     => tt_push_clean($_POST['subject']),
                        'author'    => tt_push_clean($user_info['name']),
                        'authorid'  => $ID_MEMBER,
                        'dateline'  => time(),
                    );
                    $pushed_user_ids[] = $row['uid'];
                    //store_as_alert($ttp_data);
                    $ttp_post_data = array(
                        'url'  => $boardurl,
                        'data' => base64_encode(serialize(array($ttp_data))),
                    );
                    //if(isset($modSettings['tp_push_key']) && !empty($modSettings['tp_push_key']))
                        //$ttp_post_data['key'] = $modSettings['tp_push_key'];

                    $return_status = tt_do_post_request($ttp_post_data);
                }
            }
        }
    }
}

function tapatalk_push_pm()
{
    global $user_info, $db_prefix, $boardurl, $modSettings, $context, $ID_MEMBER;

    $sent_logs = !empty($context['send_log']) && !empty($context['send_log']['sent']) ? $context['send_log']['sent'] : array();
    
    $sent_recipients = array();
    foreach($sent_logs as $sent_log)
    {
        if (preg_match('/ \'(.*?)\'/', $sent_log, $match))
            $sent_recipients[] = $match[1];
    }
    
    $sent_recipients = verify_smf_userids_from_names($sent_recipients);

    if (isset($sent_recipients) && !empty($sent_recipients) && isset($_REQUEST['subject']))
    {
        $timestr = time();
        $id_pm_req = db_query("
            SELECT p.id_pm
            FROM {$db_prefix}personal_messages p
            WHERE p.id_member_from = '$ID_MEMBER' ORDER BY id_pm DESC LIMIT 1", __FILE__, __LINE__);
        if ($id_pm_req && $id_pm = mysql_fetch_assoc($id_pm_req))
        {
            $sent_recipients_str = implode(',', $sent_recipients);
            $request = db_query("
                SELECT tu.uid
                FROM {$db_prefix}tapatalk_users tu
                WHERE tu.uid IN ({$sent_recipients_str})", __FILE__, __LINE__);
            while($row = mysql_fetch_assoc($request))
            {
                if ($row['uid'] == $ID_MEMBER) continue;
                
                $ttp_data = array(
                    'userid'    => $row['uid'],
                    'type'      => 'pm',
                    'id'        => $id_pm['id_pm'],
                    'title'     => tt_push_clean($_REQUEST['subject']),
                    'author'    => tt_push_clean($user_info['name']),
                    'authorid'  => $ID_MEMBER,
                    'dateline'  => time(),
                );
                //store_as_alert($ttp_data);
                $ttp_post_data = array(
                    'url'  => $boardurl,
                    'data' => base64_encode(serialize(array($ttp_data))),
                );
                //if(isset($modSettings['tp_push_key']) && !empty($modSettings['tp_push_key']))
                    //$ttp_post_data['key'] = $modSettings['tp_push_key'];
                
                tt_do_post_request($ttp_post_data);
            }
        }
    }
}

function tt_do_post_request($data)
{
    global $boardurl, $modSettings;
    $push_url = 'http://push.tapatalk.com/push.php';

    if(!function_exists('updateSettings'))
        require_once($sourcedir . '/Subs.php');

    //Initial this key in modSettings
    if(!isset($modSettings['push_slug']))
        updateSettings(array('push_slug' => 0));

    //Get push_slug from db
    $push_slug = isset($modSettings['push_slug'])? $modSettings['push_slug'] : 0;
    $slug = base64_decode($push_slug);
    $slug = push_slug($slug, 'CHECK');
    $check_res = unserialize($slug);

    //If it is valide(result = true) and it is not sticked, we try to send push
    if($check_res['result'] && !$check_res['stick'])
    {
        //Slug is initialed or just be cleared
        if($check_res['save'])
        {
            updateSettings(array('push_slug' => base64_encode($slug)));
        }

        //Send push
        $push_resp = getPushContentFromRemoteServer($push_url, 0, $error, 'POST', $data);
        if(trim($push_resp) === 'Invalid push notification key') $push_resp = 1;
        if(!is_numeric($push_resp))
        {
            //Sending push failed, try to update push_slug to db
            $slug = push_slug($slug, 'UPDATE');
            $update_res = unserialize($slug);
            if($update_res['result'] && $update_res['save'])
            {
                updateSettings(array('push_slug' => base64_encode($slug)));
            }
        }
    }
}

function push_slug($push_v, $method = 'NEW')
{
    if(empty($push_v))
        $push_v = serialize(array());
    $push_v_data = unserialize($push_v);
    $current_time = time();
    if(!is_array($push_v_data))
        return serialize(array('result' => 0, 'result_text' => 'Invalid v data', 'stick' => 0));
    if($method != 'CHECK' && $method != 'UPDATE' && $method != 'NEW')
        return serialize(array('result' => 0, 'result_text' => 'Invalid method', 'stick' => 0));

    if($method != 'NEW' && !empty($push_v_data))
    {
        $push_v_data['save'] = $method == 'UPDATE';
        if($push_v_data['stick'] == 1)
        {
            if($push_v_data['stick_timestamp'] + $push_v_data['stick_time'] > $current_time)
                return $push_v;
            else
                $method = 'NEW';
        }
    }

    if($method == 'NEW' || empty($push_v_data))
    {
        $push_v_data = array();                       //Slug
        $push_v_data['max_times'] = 3;                //max push failed attempt times in period
        $push_v_data['max_times_in_period'] = 300;      //the limitation period
        $push_v_data['result'] = 1;                   //indicate if the output is valid of not
        $push_v_data['result_text'] = '';             //invalid reason
        $push_v_data['stick_time_queue'] = array();   //failed attempt timestamps
        $push_v_data['stick'] = 0;                    //indicate if push attempt is allowed
        $push_v_data['stick_timestamp'] = 0;          //when did push be sticked
        $push_v_data['stick_time'] = 600;             //how long will it be sticked
        $push_v_data['save'] = 1;                     //indicate if you need to save the slug into db
        return serialize($push_v_data);
    }

    if($method == 'UPDATE')
    {
        $push_v_data['stick_time_queue'][] = $current_time;
    }
    $sizeof_queue = count($push_v_data['stick_time_queue']);
    
    $period_queue = $sizeof_queue > 1 && isset($push_v_data['stick_time_queue'][$sizeof_queue - 1]) && isset($push_v_data['stick_time_queue'][0]) ? ($push_v_data['stick_time_queue'][$sizeof_queue - 1] - $push_v_data['stick_time_queue'][0]) : 0;

    $times_overflow = $sizeof_queue > $push_v_data['max_times'];
    $period_overflow = $period_queue > $push_v_data['max_times_in_period'];

    if($period_overflow)
    {
        if(!array_shift($push_v_data['stick_time_queue']))
            $push_v_data['stick_time_queue'] = array();
    }
    
    if($times_overflow && !$period_overflow)
    {
        $push_v_data['stick'] = 1;
        $push_v_data['stick_timestamp'] = $current_time;
    }

    return serialize($push_v_data);
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
function getPushContentFromRemoteServer($url, $holdTime = 0, &$error_msg, $method = 'GET', $data = array())
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

function tt_push_clean($str)
{
    $str = strip_tags($str);
    $str = trim($str);
    return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
}

function verify_smf_userids_from_names($names)
{
    $direct_ids = array();
    $valid_names = array();
    $verified_ids = array();
    foreach($names as $index => $user)
    {
        if(is_numeric($user) && $user == intval($user))
            $direct_ids[] = $user;
        else
            $valid_names[] = $user;
    }
    if(!empty($valid_names))
    {
        $loaded_ids = loadMemberData($valid_names, true);
        //make sure tids only contains integer values
        if(is_array($loaded_ids))
        {
            foreach($loaded_ids as $idx => $loaded_id)
                if(is_numeric($loaded_id) && $loaded_id == intval($loaded_id))
                    $verified_ids[] = $loaded_id;
        }
        else
            if(is_numeric($loaded_ids) && $loaded_ids == intval($loaded_ids))
                    $verified_ids[] = $loaded_ids;
    }
    $verified_ids = array_unique(array_merge($direct_ids, $verified_ids));
    return $verified_ids;
}

if (!function_exists('http_build_query')) {

    function http_build_query($data, $prefix = null, $sep = '', $key = '')
    {
        $ret = array();
        foreach ((array )$data as $k => $v) {
            $k = urlencode($k);
            if (is_int($k) && $prefix != null) {
                $k = $prefix . $k;
            }
 
            if (!empty($key)) {
                $k = $key . "[" . $k . "]";
            }
 
            if (is_array($v) || is_object($v)) {
                array_push($ret, http_build_query($v, "", $sep, $k));
            } else {
                array_push($ret, $k . "=" . urlencode($v));
            }
        }
 
        if (empty($sep)) {
            $sep = ini_get("arg_separator.output");
        }
 
        return implode($sep, $ret);
    }
}