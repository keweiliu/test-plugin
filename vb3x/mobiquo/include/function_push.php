<?php


// For tapatalk push hook only
function tt_hook_encode($str)
{
    $str = strip_tags($str);
    
    if (empty($str)) return $str;
    
    static $charset, $charset_89, $charset_AF, $charset_8F, $charset_chr, $charset_html, $support_mb, $charset_entity;
    
    if (!isset($charset))
    {
        global $vbulletin, $stylevar;
        $charset = trim($stylevar['charset']);
        
        include_once(DIR.'/'.$vbulletin->options['tapatalk_directory'].'/include/charset.php');
        
        if (preg_match('/iso-?8859-?1/i', $charset))
        {
            $charset = 'Windows-1252';
            $charset_chr = $charset_8F;
        }
        if (preg_match('/iso-?8859-?(\d+)/i', $charset, $match_iso))
        {
            $charset = 'ISO-8859-' . $match_iso[1];
            $charset_chr = $charset_AF;
        }
        else if (preg_match('/windows-?125(\d)/i', $charset, $match_win))
        {
            $charset = 'Windows-125' . $match_win[1];
            $charset_chr = $charset_8F;
        }
        else
        {
            // x-sjis is not acceptable, but sjis do
            $charset = preg_replace('/^x-/i', '', $charset);
            $support_mb = function_exists('mb_convert_encoding') && @mb_convert_encoding('test', $charset, 'UTF-8');
        }
    }
    
    
    if (preg_match('/utf-?8/i', $charset))
    {
        $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }
    else if (function_exists('mb_convert_encoding') && (strpos($charset, 'ISO-8859-') === 0 || strpos($charset, 'Windows-125') === 0) && isset($charset_html[$charset]))
    {
        if ($mode == 'to_local')
        {
            $str = @mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8');
            $str = str_replace($charset_html[$charset], $charset_chr, $str);
        }
        else
        {
            if (strpos($charset, 'ISO-8859-') === 0)
            {
                // windows-1252 issue on ios
                $str = str_replace(array(chr(129), chr(141), chr(143), chr(144), chr(157)),
                                   array('&#129;', '&#141;', '&#143;', '&#144;', '&#157;'), $str);
            }
            
            $str = str_replace($charset_chr, $charset_html[$charset], $str);
            $str = @html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        }
    }
    else if ($support_mb)
    {
        if ($mode == 'to_local')
        {
            $str = @mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8');
            $str = @mb_convert_encoding($str, $charset, 'UTF-8');
        }
        else
        {
            $str = @mb_convert_encoding($str, 'UTF-8', $charset);
            $str = @html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        }
    }
    else if (function_exists('iconv') && @iconv($charset, 'UTF-8', 'test-str'))
    {
        if ($mode == 'to_local')
        {
            $str = @htmlentities($str, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8');
            $str = @iconv('UTF-8', $charset.'//IGNORE', $str);
        }
        else
        {
            $str = @iconv($charset, 'UTF-8//IGNORE', $str);
            $str = @html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        }
    }
    else
    {
        if ($mode == 'to_local')
        {
            $str = @htmlentities($str, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8');
            if($charset == 'Windows-1252') $str = utf8_decode($str);
            $str = @html_entity_decode($str, ENT_QUOTES, $charset);
        }
        else
        {
            $str = @html_entity_decode($str, ENT_QUOTES, 'UTF-8');
            if ($charset == 'Windows-1252') $str = utf8_encode($str);
        }
    }
    
    // html entity convert
    if ($mode == 'to_local')
    {
        $str = str_replace(array_keys($charset_entity), array_values($charset_entity), $str);
    }
    
    // remove_unknown_char
    for ($i = 1; $i < 32; $i++)
    {
        if (in_array($i, array(10, 13))) continue;
        $str = str_replace(chr($i), '', $str);
    }
    
    return $str;
}

function updateSettings($key)
{
    global $vbulletin;

    $query = "UPDATE ". TABLE_PREFIX . "tapatalk_push SET title = '". ($key['push_slug']). "' WHERE userid = 0 LIMIT 1 ";
    $results = $vbulletin->db->query_write($query);
    
}

function load_push_slug()
{
    global $vbulletin;

    $query = "SELECT title as push_slug FROM ". TABLE_PREFIX . "tapatalk_push  WHERE userid = 0 LIMIT 1 ";
    $results = $vbulletin->db->query_read_slave($query);
    $ex = $vbulletin->db->fetch_row($results);
    if($ex)
        return ($ex[0]);
    else
    {
        $vbulletin->db->query_write("
            INSERT INTO " . TABLE_PREFIX . "tapatalk_push
                (userid, type, id, subid, title, author, dateline)
            VALUES
                ('0', '', '', '', '', '', ".time().")"
        );
        return array();
    }
}

function push_slug($push_v, $method = 'NEW')
{
    if(empty($push_v))
        $push_v = serialize(array());
    $push_v_data = unserialize($push_v);
    $current_time = time();
    if(!is_array($push_v_data))
        return serialize(array(2 => 0, 3 => 'Invalid v data', 5 => 0));
    if($method != 'CHECK' && $method != 'UPDATE' && $method != 'NEW')
        return serialize(array(2 => 0, 3 => 'Invalid method', 5 => 0));

    if($method != 'NEW' && !empty($push_v_data))
    {
        $push_v_data[8] = $method == 'UPDATE';
        if($push_v_data[5] == 1)
        {
            if($push_v_data[6] + $push_v_data[7] > $current_time)
                return $push_v;
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
        return serialize($push_v_data);
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

    return serialize($push_v_data);
}

function do_post_request($data, $pushTest = false)
{
    $push_url = 'http://push.tapatalk.com/push.php';
 
    if($pushTest)
        return getContentFromRemoteServer($push_url, $pushTest ? 10 : 0, $error, 'POST', $data);

    //Initial this key in modSettings
    $modSettings = load_push_slug();

    //Get push_slug from db
    $push_slug = isset($modSettings)? $modSettings : 0;
    $slug = $push_slug;
    $slug = push_slug($slug, 'CHECK');
    $check_res = unserialize($slug);

    //If it is valide(result = true) and it is not sticked, we try to send push
    if($check_res[2] && !$check_res[5])
    {
        //Slug is initialed or just be cleared
        if($check_res[8])
        {
            updateSettings(array('push_slug' => ($slug)));
        }

        //Send push
        $push_resp = getContentFromRemoteServer($push_url, 0, $error, 'POST', $data);
        if(trim($push_resp) === 'Invalid push notification key') $push_resp = 1;
        if(!is_numeric($push_resp))
        {
            //Sending push failed, try to update push_slug to db
            $slug = push_slug($slug, 'UPDATE');
            $update_res = unserialize($slug);
            if($update_res[2] && $update_res[8])
            {
                updateSettings(array('push_slug' => ($slug)));
            }
        }
    }
    
    return $response;
}

function getEmailFromScription($token, $code, $key)
{
    global $vbulletin;
    @include_once('classTTJson.php');

    $verification_url = 'http://directory.tapatalk.com/au_reg_verify.php?token='.$token.'&'.'code='.$code.'&key='.$key.'&url='.$vbulletin->options['bburl'];
    $response = getContentFromRemoteServer($verification_url, 10, $error);
    if($response)
        $result = json_decode($response, true);
    if(isset($result) && isset($result['result']))
        return $result;
    else
    {
        $data = array(
            'token' => $token,
            'code'  => $code,
            'key'   => $key,
            'url'   => $vbulletin->options['bburl'],
        );
        $response = getContentFromRemoteServer('http://directory.tapatalk.com/au_reg_verify.php', 10, $error, 'POST', $data);
        if($response)
            $result = json_decode($response, true);
        if(isset($result) && isset($result['result']))
            return $result;
        else
            return 0; //No connection to Tapatalk Server.
    }
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
function getContentFromRemoteServer($url, $holdTime = 0, &$error_msg, $method = 'GET', $data = array())
{
    //Validate input.
    $vurl = parse_url($url);
    if ($vurl['scheme'] != 'http' && $vurl['scheme'] != 'https')
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
        $opts = array(
            $vurl['scheme'] => array(
                'method' => "GET",
                'timeout' => $holdTime,
            )
        );

        $context = @stream_context_create($opts);
        $response = @file_get_contents($url,false,$context);
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
                $fp = fsockopen($host, 80, $errno, $errstr, 5);

                if(!$fp)
                {
                    $error_msg = 'Error: socket open time out or cannot connet.';
                    return false;
                }

                $data = build_query($data, '', '&');

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
                $params = array(
                    $vurl['scheme'] => array(
                        'method' => 'POST',
                        'content' => build_query($data, '', '&'),
                    )
                );
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
        $data = build_query($data, '', '&');
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

function loadAPIKey()
{
    global $mobi_api_key, $vbulletin;
    
    if(empty($mobi_api_key))
    {
        $option_key = $vbulletin->options['push_key'];
        if(isset($option_key) && !empty($option_key))
        {
            $mobi_api_key = $option_key;
        }
        else
        {
            @include_once('classTTJson.php');

            $boardurl = $vbulletin->options['bburl'];
            $boardurl = urlencode($boardurl);
            $response = getContentFromRemoteServer("http://directory.tapatalk.com/au_reg_verify.php?url=$boardurl", 10, $error);
            if($response)
                $result = json_decode($response, true);
            if(isset($result) && isset($result['result']))
                $mobi_api_key = $result['api_key'];
            else
            {
                $data = array(
                    'url'   =>  urlencode($vbulletin->options['bburl']),
                );
                $response = getContentFromRemoteServer('http://directory.tapatalk.com/au_reg_verify.php', 10, $error, 'POST', $data);
                if($response)
                    $result = json_decode($response, true);
                if(isset($result) && isset($result['result']))
                    $mobi_api_key = $result['api_key'];
                else
                    $mobi_api_key = 0;
            }
        }
    }
    return $mobi_api_key;
}

function build_query($data, $a, $b = '&')
{
    if(function_exists('http_build_query'))
    {
        return http_build_query($data, $a, $b);
    }
    else
    {
        $ret = array();
        foreach ((array)$data as $k => $v)
        {
            if (is_int($k) && $prefix != null) $k = urlencode($prefix . $k);
            if (!empty($key)) $k = $key.'['.urlencode($k).']';
            
            if (is_array($v) || is_object($v))
                array_push($ret, build_query($v, '', $sep, $k));
            else
                array_push($ret, $k.'='.urlencode($v));
        }
        
        if (empty($sep)) $sep = ini_get('arg_separator.output');
        return implode($sep, $ret);
    }
}

function is_spam($email, $ip='')
{
    if($email || checkipaddres($ip))
    {
        $params = '';
        if($email)
        {
            $params = "&email=$email";
        }

        if(checkipaddres($ip))
        {
            $params .= "&ip=$ip";
        }

        $resp = @getContentFromRemoteServer("http://www.stopforumspam.com/api?f=serial".$params, 3);
        $resp = unserialize($resp);
        if ((isset($resp['email']['confidence']) && $resp['email']['confidence'] > 50) ||
            (isset($resp['ip']['confidence']) && $resp['ip']['confidence'] > 60))
        {
            return true;
        }
    }
    
    return false;
}

function checkipaddres ($ipaddres) {
    $preg="/\A((([0-9]?[0-9])|(1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))\.){3}(([0-9]?[0-9])|(1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))\Z/";
    if(preg_match($preg,$ipaddres))
        return true;
    return false;
}