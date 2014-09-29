<?php
/***************************************************************
* Subs-Mobiquo.php                                             *
* Copyright 2009 Quoord Systems Ltd. All Rights Reserved.     *
* Created by Dragooon (http://smf-media.com)                   *
****************************************************************
* This file or any content of the file should not be           *
* redistributed in any form of matter. This file is a part of  *
* Tapatalk package and should not be used and distributed      *
* in any form not approved by Quoord Systems Ltd.              *
* http://tapatalk.com | http://taptatalk.com/license.html      *
****************************************************************
* Contains sub-functions used by the main package              *
***************************************************************/

if (!defined('SMF'))
    die('Hacking attempt...');

function get_error($err_str, $status = 0)
{
    global $context;

    @ob_clean();
    
    $result = array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'result_text'   => new xmlrpcval(basic_clean($err_str), 'base64'),
    );
    
    if ($status) $result['status'] = new xmlrpcval($status, 'string');
    
    $response = new xmlrpcresp(new xmlrpcval($result,'struct'));

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$response->serialize('UTF-8');
    exit;
}


function TapatalkSsoVerification($token, $code, $key = '')
{
    global $boardurl;

    $verification_url = 'http://directory.tapatalk.com/au_reg_verify.php?token='.$token.'&'.'code='.$code.'&key='.$key.'&url='.$boardurl;
    $response = getContentFromRemoteServer($verification_url, 10, $error);
    
    if($response)
        $result = @json_decode($response);
    
    if(isset($result) && isset($result->result))
        return $result;
    else
    {
        $data = array(
            'token' => $token,
            'code'  => $code,
            'key'   => $key,
            'url'   => $boardurl,
        );
        $response = getContentFromRemoteServer('http://directory.tapatalk.com/au_reg_verify.php', 10, $error, 'POST', $data);
        if($response)
            $result = @json_decode($response);
        
        if(isset($result) && isset($result->result))
            return $result;
        else
            return 0; //No connection to Tapatalk Server.
    }
}

function emailExists($email)
{
    global $db_prefix, $txt;
    
    $email = mysql_real_escape_string($email);
    
    $request = db_query("
        SELECT ID_MEMBER
        FROM {$db_prefix}members
        WHERE emailAddress = '$email'
        LIMIT 1", __FILE__, __LINE__);
    
    if (mysql_num_rows($request))
    {
        $user = mysql_fetch_assoc($request);
        return $user['ID_MEMBER'];
    }
    else
        return false;
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

        $context = stream_context_create($opts);
        $response = file_get_contents($url,false,$context);
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

                $data = http_build_query($data, '', '&');

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
                        'content' => http_build_query($data, '', '&'),
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

function basic_clean($str, $cut = 0, $is_shortcontent = 0)
{
    if($is_shortcontent)
    {
        $str = preg_replace('/\[color=.*\](.*)\[\/color\]/U', '$1', $str);
        $str = preg_replace('/\[color=.*\](.*)/U', '$1', $str);
        $str = preg_replace('/Code: \[Select\]/', 'Code: ', $str);
        $str = preg_replace('/\[[u|i|b]\](.*)\[\/[u|i|b]\]/U', '$1', $str);
        $str = preg_replace('/-{3}/', '', $str);
        $str = preg_replace('/\[quote.*\](.*?)\[\/quote\]/', '[quote]', $str);
        $str = preg_replace('/&nbsp;/', ' ', $str);
        $str = preg_replace('/&n*b*s*p*$/','...',$str);
    }
    $str = preg_replace('/<a.*?>Quote from:.*?<\/a>/', ' ', $str);
    $str = strip_tags($str);
    $str = to_utf8($str);
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    if (function_exists('censorText')) censorText($str);
    if($is_shortcontent)
        $str = preg_replace('/[\r\n]*/', '', $str);
    if ($cut > 0)
    {
        $str = preg_replace('/\[url=.*?\].*?\[\/url\]\s*\[quote\].*?\[\/quote\]/si', '', $str);
        $str = preg_replace('/\[.*?\]/si', '', $str);
        $str = preg_replace('/[\n\r\t]+/', ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        $str = trim($str);
        $str = cutstr($str, $cut);
    }

    return trim($str);
}

function to_utf8($str)
{
    global $context;

    if (!empty($context) && !$context['utf8'])
    {
        $str = mobiquo_encode($str);
    }

    return $str;
}

function cutstr($string, $length, $dot = ' ...') {
    global $context;

    if(strlen($string) <= $length) {
        return $string;
    }

    $string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);

    $strcut = '';

    $n = $tn = $noc = 0;
    while($n < strlen($string)) {

        $t = ord($string[$n]);
        if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
            $tn = 1; $n++; $noc++;
        } elseif(194 <= $t && $t <= 223) {
            $tn = 2; $n += 2; $noc += 2;
        } elseif(224 <= $t && $t <= 239) {
            $tn = 3; $n += 3; $noc += 2;
        } elseif(240 <= $t && $t <= 247) {
            $tn = 4; $n += 4; $noc += 2;
        } elseif(248 <= $t && $t <= 251) {
            $tn = 5; $n += 5; $noc += 2;
        } elseif($t == 252 || $t == 253) {
            $tn = 6; $n += 6; $noc += 2;
        } else {
            $n++;
        }

        if($noc >= $length) {
            break;
        }

    }
    if($noc > $length) {
        $n -= $tn;
    }

    $strcut = substr($string, 0, $n);

    return $strcut.$dot;
}

function update_push()
{
    global $user_info, $db_prefix;

    if ($user_info['id'] && mobi_table_exist('tapatalk_users'))
    {
        db_query("INSERT IGNORE INTO {$db_prefix}tapatalk_users (uid) VALUES ({$user_info[id]})", __FILE__, __LINE__);
        if (db_affected_rows() == 0)
        {
            db_query("
                UPDATE {$db_prefix}tapatalk_users
                SET updated = CURRENT_TIMESTAMP
                WHERE uid = {$user_info[id]}", __FILE__, __LINE__
            );
        }
    }
}

function mobi_table_exist($table_name)
{
    global $db_prefix, $db_name;
    
    db_query("USE `$db_name`");
    $real_prefix = preg_replace('/^(`?).*?\1\./', '', $db_prefix);
    $request = db_query("SHOW TABLES LIKE '{$real_prefix}{$table_name}'", __FILE__, __LINE__);
    
    return mysql_num_rows($request) ? true : false;
}

// Loads general settings for mobiquo
function loadMobiquoSettings()
{
    global $mobsettings, $ID_MEMBER, $user_info;

    $user_info['id'] = $ID_MEMBER;

    // Load mobiquo error codes
    $mobsettings['mobiquo_error'] = array(
        1 => 'Server not available',
        2 => 'Service not available',
        3 => 'Pagination index error',
        4 => 'Invalid forum ID',
        5 => 'Forum is closed',
        6 => 'Invalid topic ID',
        7 => 'Invalid user ID or user name',
        8 => 'Configuration error',
        9 => 'Image file is too large to handle',
        10 => 'Invalid image format',
        11 => 'This thread is closed',
        19 => 'Cannot create duplicate thread/forum',
        20 => 'Access to this feature is denied',
        21 => 'Guests are not allowed',
        22 => 'Attachment size beyond server limit',
        23 => 'Mailbox is full',
        24 => 'This topic does not allow reply',
        25 => 'Cannot create new topic in this forum',
        26 => 'Unknown recepient',
        27 => 'Private message does not exist',
        28 => 'Private message is disabled',
        29 => 'You have exceeded the max. number of recepients',
        30 => 'Quote message is deleted',
    );

    // Load XMLRPC error codes
    $mobsettings['xmlrpc_error'] = array(
        'unknown_method' => 'Unknown method',
        'invalid_return' => 'Invalid return payload: enable debugging to examine incoming payload',
        'incorrect_params' => 'Incorrect parameters passed to method',
        'introspect_unknown' => "Can't introspect: method unknown",
        'http_error' => "Didn't receive 200 OK from remote server.",
        'no_data' => 'No data received from server.',
        'no_ssl' => 'No SSL support compiled in.',
        'curl_fail' => 'CURL error',
        'invalid_request' => 'Invalid request payload',
        'no_curl' => 'No CURL support compiled in.',
        'server_error' => 'Internal server error',
        'multicall_error' => 'Received from server invalid multicall response',
        'multicall_notstruct' => 'system.multicall expected struct',
        'multicall_nomethod' => 'missing methodName',
        'multicall_notstring' => 'methodName is not a string',
        'multicall_recursion' => 'recursive system.multicall forbidden',
        'multicall_noparams' => 'missing params',
        'multicall_notarray' => 'params is not an array',
        'cannot_decompress' => 'Received from server compressed HTTP and cannot decompress',
        'decompress_fail' => 'Received from server invalid compressed HTTP',
        'dechunk_fail' => 'Received from server invalid chunked HTTP',
        'server_cannot_decompress' => 'Received from client compressed HTTP request and cannot decompress',
        'server_decompress_fail' => 'Received from client invalid compressed HTTP request',
    );
}

// Parses the XML request and loads into $context['mob_request']
function parseMobRequest()
{
    global $context, $scripturl, $sourcedir, $mobsettings, $user_info, $modSettings;

    $ver = phpversion();
    if ($ver[0] >= 5) {
        $data = file_get_contents('php://input');
    } else {
        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
    }

    $data = str_replace('<param><value><boolean>false</boolean></value></param>', '<param><value><boolean>0</boolean></value></param>', $data);

    require_once($sourcedir . '/Subs-Package.php');

    $context['mob_request'] = array();
    $context['mob_response'] = array(
        'encoding' => 'none',
    );

    // We got compression buddy?
    if (isset($_SERVER['HTTP_CONTENT_ENCODING']) && function_exists('gzinflate'))
    {
        if ($_SERVER['HTTP_CONTENT_ENCODING'] == 'x-gzip' && !($data = @gzinflate(substr($data, 10))))
            createErrorResponse('server_decompress_failed', '', 'xmlrpc');
        elseif ($_SERVER['HTTP_CONTENT_ENCODING'] == 'x-deflate' && !($data = @gzuncompress($data)))
            createErrorResponse('server_decompress_failed', '', 'xmlrpc');
        else
            createErrorResponse('server_cannot_decompress', '', 'xmlrpc');
    }
    elseif (isset($_SERVER['HTTP_CONTENT_ENCODING']))
        createErrorResponse('server_cannot_decompress', '', 'xmlrpc');

    // Innitialize the SMF XML handler
    $xmlHandler = new xmlArray($data, true);

    // Get the method name
    if (!($context['mob_request']['method'] = $xmlHandler->fetch('methodCall/methodName')))
        createErrorResponse('unknown_method', '', 'xmlrpc');

    // Are we closed?
    if ($context['mob_request']['method'] != 'login' && $context['mob_request']['method'] != 'get_config')
    {
        if (!empty($context['in_maintenance']) && !$user_info['is_admin'])
            createErrorResponse(5, ' due to maintenance');
        elseif (empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && $context['mob_request']['method'] != 'sign_in')
            createErrorResponse(21);
    }

    // Get the parameters
    $context['mob_request']['params'] = array();
    if ($xmlHandler->exists('methodCall/params'))
        foreach ($xmlHandler->set('methodCall/params/param') as $parameter)
        {
            // Lame workdarround for create_message
            if (($context['mob_request']['method'] == 'create_message'
                 || $context['mob_request']['method'] == 'new_topic'
                 || $context['mob_request']['method'] == 'reply_post') && $parameter->exists('value/value'))
            {
                if (!$parameter->exists('value/value[1]'))
                {
                    $value = $parameter->to_array();
                    $values = array(
                        0 => array($value['value']['base64'], 'base64'),
                    );
                }
                else
                {
                    $values = array();
                    foreach ($parameter->set('value/value') as $value)
                    {
                        $value = $value->to_array();
                        $values[] = array($value['base64'], 'base64');
                    }
                }
                $context['mob_request']['params'][] = $values;
            }
            else
            {
                $parameter = $parameter->to_array();

                $keys = array_keys($parameter['value']);
                $values = array_values($parameter['value']);

                $context['mob_request']['params'][] = array($values[0], $keys[0]);
            }
        }
}

// Parses the time in the mobiquo way!
function mobiquo_time($timestamp, $offset = false)
{
    global $user_info, $modSettings;

    $timestamp = !is_numeric($timestamp) ? strtotime(str_replace('at', '', $timestamp)) : $timestamp;
    $timezone = $user_info['time_offset'] + $modSettings['time_offset'];
    
    if ($offset) $timestamp -= $timezone * 3600;
    
    $modSettings['todayMod'] = 0;
    $t = timeformat($timestamp, '%Y%m%dT%H:%M:%S');
    
    if($timezone >= 0){
        $timezone = sprintf("%02d", $timezone);
        $timezone = '+'.$timezone;
    } else {
        $timezone = $timezone * (-1);
        $timezone = sprintf("%02d",$timezone);
        $timezone = '-'.$timezone;
    }

    return $t.$timezone.':00';
}

// Creates an error response
function createErrorResponse($code, $append = '', $type = 'mobiquo')
{
    global $context, $scripturl, $mobsettings;

    // Error not found?
    if (empty($mobsettings[$type . '_error'][$code]))
        return createErrorResponse('server_error', '', 'xmlrpc');

    $faultString = $mobsettings[$type . '_error'][$code] . $append;

    // Get the faultCode...
    if (is_int($code) || is_numeric($code))
        $faultCode = $code;
    else
    {
        $i = 0;
        foreach ($mobsettings[$type . '_error'] as $key => $val)
        {
            $i++;
            if ($key == $code)
                $faultCode = $i;
        }
    }

    // Now that we have figurred it out, output the XML
    outputRPCResponse('
<params>
<param>
<value><struct>
<member><name>result</name>
<value><boolean>0</boolean></value>
</member>
<member><name>result_text</name>
<value><base64>' . base64_encode($faultString) . '</base64></value>
</member>
</struct></value>
</param>
</params>'
    );
}

// Outputs a standard RPC response(sort of...)
function outputRPCResponse($structure)
{
    global $context, $user_info;

    ob_end_clean();

    // Start the response....
    $buffer = '<?xml version="1.0" encoding="UTF-8"?>
<methodResponse>'.
$structure.'
</methodResponse>';

    header('Content-type: text/xml');
    header('Content-encoding: ' . (isset($context['mob_response']['encoding']) ? $context['mob_response']['encoding'] : ''));
    header('Content-length: ' . strlen($buffer));
    
    echo $buffer;
    exit;
}


// Gets unsafe tags
function getUnsafeTags()
{
    $bbc = parse_bbc(false);
    $tags = array();
    $safe_tags = array('img', 'quote', 'url');
    foreach ($bbc as $code => $info)
        if (!in_array($info['tag'], $safe_tags))
            $tags[] = $info['tag'];

    return $tags;
}

function processUsername($body)
{
    $body = mobi_unescape_html($body);
    return $body;
}

// Processes a posts's body for same viewing
function processBody($body)
{
    global $modSettings, $mobsettings;
    
    $body = str_replace(array('[IMG', '[/IMG]', '[URL', '[/URL]', '[IURL', '[/IURL]', '[iurl', '[/iurl]'), 
                        array('[img', '[/img]', '[url', '[/url]', '[url',  '[/url]',  '[url',  '[/url]'), $body);
    $body = preg_replace('/\[img.*?\]/', '[tpt-img]', $body);
    $body = str_replace('[url', '[tpt-url', $body);
    
    // convert youtube bbcode
    $body = preg_replace('/\[(youtube|yt)\](.*?)\[\/\1\]/ie', "'[url]'.youtube_url_check('$2').'[/url]'", $body);
    
    $body = preg_replace('/\[b\](.*?)\[\/b\]/', '[tpt-b]$1[/tpt-b]', $body);
    $body = preg_replace('/\[u\](.*?)\[\/u\]/', '[tpt-u]$1[/tpt-u]', $body);
    $body = preg_replace('/\[i\](.*?)\[\/i\]/', '[tpt-i]$1[/tpt-i]', $body);
    $body = preg_replace('#\[color=(\#[\da-fA-F]{3}|\#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\(\d{1,3}, ?\d{1,3}, ?\d{1,3}\))\](.*?)\[/color\]#si', '[tpt-color=$1]$2[/tpt-color]', $body);
    $body = strip_tags(parse_bbc($body, false, '', getUnsafeTags()), '<br><br />');
    $body = strip_tags($body, '<br><br />');
    $body = str_replace('<br />', "\n", $body);
    
    $body = str_replace('[tpt-img]', '[img]', $body);
    $body = str_replace('[tpt-url', '[url', $body);
    
    $body = preg_replace('/\[quote.*?\]/is', '[quote]', $body);
    $blocks = preg_split('/(\[\/?quote\])/i', $body, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

    $quote_level = 0;
    $body = '';

    foreach($blocks as $block)
    {
        if ($block == '[quote]') {
            if ($quote_level == 0) $body .= $block;
            $quote_level++;
        } else if ($block == '[/quote]') {
            if ($quote_level <= 1) $body .= $block;
            if ($quote_level >= 1) $quote_level--;
        } else {
            if ($quote_level <= 1) $body .= ltrim($block);
        }
    }

    return mobi_unescape_body_html($body);
}

function youtube_url_check($url)
{
    $url = trim($url);
    
    if (preg_match('/^\w+$/', $url)) $url = 'http://www.youtube.com/watch?v='.$url;
    
    return $url;
}

// Returns a shorter version of the body
function processShortContent($body)
{
    // Replace all but [img] tags to nowhere(Does that even make sense?)!
    //$shortened_message = preg_replace('/&#?[a-z0-9]{2,8};/i','', strip_tags(parse_bbc($body)));
    //$shortened_message = strip_tags(parse_bbc($body));
    //$body = mob_html_to_bbc(strip_tags(parse_bbc($body, true, '', getUnsafeTags(true)), '<br><br />'));
    $body = str_replace(array('[IMG', '[/IMG]', '[URL', '[/URL]'), array('[img', '[/img]', '[url', '[/url]'), $body);
    //$body = parse_bbc($body, true, '');
    $body = parse_bbc($body, true, '', getUnsafeTags());
    $body = preg_replace('/<div class="quoteheader">.*?<\/div>/is', '', $body);
    $body = preg_replace('/<br\s*\/?>/', ' ', $body);
    $body = strip_tags($body);
    $body = preg_replace('/\[url.*?\].*?\[\/url.*?\]/s', '[URL]', $body);
    $body = preg_replace('/\[img.*?\].*?\[\/img.*?\]/s', '[IMG]', $body);
    
    $body = preg_replace('/\[quote.*?\]/is', '[quote]', $body);
    $blocks = preg_split('/(\[\/?quote\])/i', $body, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

    $quote_level = 0;
    $body = '';

    foreach($blocks as $block)
    {
        if ($block == '[quote]') {
            if ($quote_level == 0) $body .= $block;
            $quote_level++;
        } else if ($block == '[/quote]') {
            if ($quote_level >= 1) $quote_level--;
        } else {
            if ($quote_level < 1) $body .= $block;
        }
    }
    
    $body = mobi_unescape_html($body);
    $body = preg_replace('/^\s*|\s*$/', '', $body);
    $body = preg_replace('/[\n\r\t]+/', ' ', $body);
    $body = preg_replace('/\s+/', ' ', $body);
    
    $shortened_message = utf8_substr($body, 0, 200);

    return $shortened_message;
}

function utf8_substr($str,$start,$end){
    $_start=0;
    $_end=0;

    for($i=0; $i < $end; $i++){
        $t = substr($str,$_end,1);
        if($t===false) break;

        if($i == $start){ $_start = $_end; }

        $byte_code=ord($t);

        if($byte_code>=0&&$byte_code<128){
            $_end++;
        }else if($byte_code>191&&$byte_code<224){
            $_end+=2;
        }else if($byte_code>223&&$byte_code<240){
            $_end+=3;
        }else if($byte_code>239&&$byte_code<248){
            $_end+=4;
        }else if($byte_code>248&&$byte_code<252){
            $_end+=5;
        }else{
            $_end+=6;
        }
    }
    return substr($str, $_start, $_end);
}

// Processes the subjects
function processSubject($subject)
{
    return mobi_unescape_html($subject);
}

// Yes, I agree with you. This is the lamest copy ever.
// Copied from SMF 2.0 Subs-Editor.php, required for compatibility.
function mob_html_to_bbc($text)
{
    global $modSettings, $smcFunc, $sourcedir, $scripturl, $context, $mobdb;

    // Replace newlines with spaces, as that's how browsers usually interpret them.
    $text = strtr($text, array("\n" => ' ', "\r" => ' '));

    // Though some of us love paragraphs, the parser will do better with breaks.
    $text = preg_replace('~</p>\s*?<p~i', '</p><br /><p', $text);
    $text = preg_replace('~</p>\s*(?!<)~i', '</p><br />', $text);

    // Safari/webkit wraps lines in Wysiwyg in <div>'s.
    if (isset($context['browser']['is_webkit']) && $context['browser']['is_webkit'])
        $text = preg_replace(array('~<div(?:\s(?:[^<>]*?))?' . '>~i', '</div>'), array('<br />', ''), $text);

    // If there's a trailing break get rid of it - Firefox tends to add one.
    $text = preg_replace('~<br\s?/?' . '>$~i', '', $text);

    // Remove any formatting within code tags.
    if (strpos($text, '[code') !== false)
    {
        $text = preg_replace('~<br\s?/?' . '>~i', '#smf_br_spec_grudge_cool!#', $text);
        $parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        // Only mess with stuff outside [code] tags.
        for ($i = 0, $n = count($parts); $i < $n; $i++)
        {
            // Value of 2 means we're inside the tag.
            if ($i % 4 == 2)
                $parts[$i] = strip_tags($parts[$i]);
        }

        $text = strtr(implode('', $parts), array('#smf_br_spec_grudge_cool!#' => '<br />'));
    }

    // Remove scripts, style and comment blocks.
    $text = preg_replace('~<script[^>]*[^/]?' . '>.*?</script>~i', '', $text);
    $text = preg_replace('~<style[^>]*[^/]?' . '>.*?</style>~i', '', $text);
    $text = preg_replace('~\\<\\!--.*?-->~i', '', $text);
    $text = preg_replace('~\\<\\!\\[CDATA\\[.*?\\]\\]\\>~i', '', $text);

    // Do the smileys ultra first!
    preg_match_all('~<img\s+[^<>]*?id="*smiley_\d+_([^<>]+?)[\s"/>]\s*[^<>]*?/*>(?:\s)?~i', $text, $matches);
    if (!empty($matches[0]))
    {
        // Easy if it's not custom.
        if (empty($modSettings['smiley_enable']))
        {
            $smileysfrom = array('>:D', ':D', '::)', '>:(', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', '0:)');
            $smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif');

            foreach ($matches[1] as $k => $file)
            {
                $found = array_search($file, $smileysto);
                // Note the weirdness here is to stop double spaces between smileys.
                if ($found)
                    $matches[1][$k] = '-[]-smf_smily_start#|#' . htmlspecialchars($smileysfrom[$found]) . '-[]-smf_smily_end#|#';
                else
                    $matches[1][$k] = '';
            }
        }
        else
        {
            // Load all the smileys.
            $names = array();
            foreach ($matches[1] as $file)
                $names[] = $file;
            $names = array_unique($names);

            if (!empty($names))
            {
                $request = $mobdb->query('
                    SELECT code, filename
                    FROM {db_prefix}smileys
                    WHERE filename IN ({array_string:smiley_filenames})',
                    array(
                        'smiley_filenames' => $names,
                    )
                );
                $mappings = array();
                while ($row = $mobdb->fetch_assoc())
                    $mappings[$row['filename']] = htmlspecialchars($row['code']);
                $mobdb->free_result();

                foreach ($matches[1] as $k => $file)
                    if (isset($mappings[$file]))
                        $matches[1][$k] = '-[]-smf_smily_start#|#' . $mappings[$file] . '-[]-smf_smily_end#|#';
            }
        }

        // Replace the tags!
        $text = str_replace($matches[0], $matches[1], $text);

        // Now sort out spaces
        $text = str_replace(array('-[]-smf_smily_end#|#-[]-smf_smily_start#|#', '-[]-smf_smily_end#|#', '-[]-smf_smily_start#|#'), ' ', $text);
    }

    // Only try to buy more time if the client didn't quit.
    if (connection_aborted() && $context['server']['is_apache'])
        @apache_reset_timeout();

    $parts = preg_split('~(<[A-Za-z]+\s*[^<>]*?style="?[^<>"]+"?[^<>]*?(?:/?)>|</[A-Za-z]+>)~', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $replacement = '';
    $stack = array();

    foreach ($parts as $part)
    {
        if (preg_match('~(<([A-Za-z]+)\s*[^<>]*?)style="?([^<>"]+)"?([^<>]*?(/?)>)~', $part, $matches) === 1)
        {
            // If it's being closed instantly, we can't deal with it...yet.
            if ($matches[5] === '/')
                continue;
            else
            {
                // Get an array of styles that apply to this element. (The strtr is there to combat HTML generated by Word.)
                $styles = explode(';', strtr($matches[3], array('&quot;' => '')));
                $curElement = $matches[2];
                $precedingStyle = $matches[1];
                $afterStyle = $matches[4];
                $curCloseTags = '';
                $extra_attr = '';

                foreach ($styles as $type_value_pair)
                {
                    // Remove spaces and convert uppercase letters.
                    $clean_type_value_pair = strtolower(strtr(trim($type_value_pair), '=', ':'));

                    // Something like 'font-weight: bold' is expected here.
                    if (strpos($clean_type_value_pair, ':') === false)
                        continue;

                    // Capture the elements of a single style item (e.g. 'font-weight' and 'bold').
                    list ($style_type, $style_value) = explode(':', $type_value_pair);

                    $style_value = trim($style_value);

                    switch (trim($style_type))
                    {
                        case 'font-weight':
                            if ($style_value === 'bold')
                            {
                                $curCloseTags .= '[/b]';
                                $replacement .= '[b]';
                            }
                        break;

                        case 'text-decoration':
                            if ($style_value == 'underline')
                            {
                                $curCloseTags .= '[/u]';
                                $replacement .= '[u]';
                            }
                            elseif ($style_value == 'line-through')
                            {
                                $curCloseTags .= '[/s]';
                                $replacement .= '[s]';
                            }
                        break;

                        case 'text-align':
                            if ($style_value == 'left')
                            {
                                $curCloseTags .= '[/left]';
                                $replacement .= '[left]';
                            }
                            elseif ($style_value == 'center')
                            {
                                $curCloseTags .= '[/center]';
                                $replacement .= '[center]';
                            }
                            elseif ($style_value == 'right')
                            {
                                $curCloseTags .= '[/right]';
                                $replacement .= '[right]';
                            }
                        break;

                        case 'font-style':
                            if ($style_value == 'italic')
                            {
                                $curCloseTags .= '[/i]';
                                $replacement .= '[i]';
                            }
                        break;

                        case 'color':
                            $curCloseTags .= '[/color]';
                            $replacement .= '[color=' . $style_value . ']';
                        break;

                        case 'font-size':
                            // Sometimes people put decimals where decimals should not be.
                            if (preg_match('~(\d)+\.\d+(p[xt])~i', $style_value, $matches) === 1)
                                $style_value = $matches[1] . $matches[2];

                            $curCloseTags .= '[/size]';
                            $replacement .= '[size=' . $style_value . ']';
                        break;

                        case 'font-family':
                            // Only get the first freaking font if there's a list!
                            if (strpos($style_value, ',') !== false)
                                $style_value = substr($style_value, 0, strpos($style_value, ','));

                            $curCloseTags .= '[/font]';
                            $replacement .= '[font=' . strtr($style_value, array("'" => '')) . ']';
                        break;

                        // This is a hack for images with dimensions embedded.
                        case 'width':
                        case 'height':
                            if (preg_match('~[1-9]\d*~i', $style_value, $dimension) === 1)
                                $extra_attr .= ' ' . $style_type . '="' . $dimension[0] . '"';
                        break;

                        case 'list-style-type':
                            if (preg_match('~none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha~i', $style_value, $listType) === 1)
                                $extra_attr .= ' listtype="' . $listType[0] . '"';
                        break;
                    }
                }

                // If there's something that still needs closing, push it to the stack.
                if (!empty($curCloseTags))
                    array_push($stack, array(
                            'element' => strtolower($curElement),
                            'closeTags' => $curCloseTags
                        )
                    );
                elseif (!empty($extra_attr))
                    $replacement .= $precedingStyle . $extra_attr . $afterStyle;
            }
        }

        elseif (preg_match('~</([A-Za-z]+)>~', $part, $matches) === 1)
        {
            // Is this the element that we've been waiting for to be closed?
            if (!empty($stack) && strtolower($matches[1]) === $stack[count($stack) - 1]['element'])
            {
                $byebyeTag = array_pop($stack);
                $replacement .= $byebyeTag['closeTags'];
            }

            // Must've been something else.
            else
                $replacement .= $part;
        }
        // In all other cases, just add the part to the replacement.
        else
            $replacement .= $part;
    }

    // Now put back the replacement in the text.
    $text = $replacement;

    // We are not finished yet, request more time.
    if (connection_aborted() && $context['server']['is_apache'])
        @apache_reset_timeout();

    // Let's pull out any legacy alignments.
    while (preg_match('~<([A-Za-z]+)\s+[^<>]*?(align="*(left|center|right)"*)[^<>]*?(/?)>~i', $text, $matches) === 1)
    {
        // Find the position in the text of this tag over again.
        $start_pos = strpos($text, $matches[0]);
        if ($start_pos === false)
            break;

        // End tag?
        if ($matches[4] != '/' && strpos($text, '</' . $matches[1] . '>', $start_pos) !== false)
        {
            $end_length = strlen('</' . $matches[1] . '>');
            $end_pos = strpos($text, '</' . $matches[1] . '>', $start_pos);

            // Remove the align from that tag so it's never checked again.
            $tag = substr($text, $start_pos, strlen($matches[0]));
            $content = substr($text, $start_pos + strlen($matches[0]), $end_pos - $start_pos - strlen($matches[0]));
            $tag = str_replace($matches[2], '', $tag);

            // Put the tags back into the body.
            $text = substr($text, 0, $start_pos) . $tag . '[' . $matches[3] . ']' . $content . '[/' . $matches[3] . ']' . substr($text, $end_pos);
        }
        else
        {
            // Just get rid of this evil tag.
            $text = substr($text, 0, $start_pos) . substr($text, $start_pos + strlen($matches[0]));
        }
    }

    // Let's do some special stuff for fonts - cause we all love fonts.
    while (preg_match('~<font\s+([^<>]*)>~i', $text, $matches) === 1)
    {
        // Find the position of this again.
        $start_pos = strpos($text, $matches[0]);
        $end_pos = false;
        if ($start_pos === false)
            break;

        // This must have an end tag - and we must find the right one.
        $lower_text = strtolower($text);

        $start_pos_test = $start_pos + 4;
        // How many starting tags must we find closing ones for first?
        $start_font_tag_stack = 0;
        while ($start_pos_test < strlen($text))
        {
            // Where is the next starting font?
            $next_start_pos = strpos($lower_text, '<font', $start_pos_test);
            $next_end_pos = strpos($lower_text, '</font>', $start_pos_test);

            // Did we past another starting tag before an end one?
            if ($next_start_pos !== false && $next_start_pos < $next_end_pos)
            {
                $start_font_tag_stack++;
                $start_pos_test = $next_start_pos + 4;
            }
            // Otherwise we have an end tag but not the right one?
            elseif ($start_font_tag_stack)
            {
                $start_font_tag_stack--;
                $start_pos_test = $next_end_pos + 4;
            }
            // Otherwise we're there!
            else
            {
                $end_pos = $next_end_pos;
                break;
            }
        }
        if ($end_pos === false)
            break;

        // Now work out what the attributes are.
        $attribs = fetchTagAttributes($matches[1]);
        $tags = array();
        foreach ($attribs as $s => $v)
        {
            if ($s == 'size')
                $tags[] = array('[size=' . (int) trim($v) . ']', '[/size]');
            elseif ($s == 'face')
                $tags[] = array('[font=' . trim(strtolower($v)) . ']', '[/font]');
            elseif ($s == 'color')
                $tags[] = array('[color=' . trim(strtolower($v)) . ']', '[/color]');
        }

        // As before add in our tags.
        $before = $after = '';
        foreach ($tags as $tag)
        {
            $before .= $tag[0];
            if (isset($tag[1]))
                $after = $tag[1] . $after;
        }

        // Remove the tag so it's never checked again.
        $content = substr($text, $start_pos + strlen($matches[0]), $end_pos - $start_pos - strlen($matches[0]));

        // Put the tags back into the body.
        $text = substr($text, 0, $start_pos) . $before . $content . $after . substr($text, $end_pos + 7);
    }

    // Almost there, just a little more time.
    if (connection_aborted() && $context['server']['is_apache'])
        @apache_reset_timeout();

    if (count($parts = preg_split('~<(/?)(li|ol|ul)([^>]*)>~i', $text, null, PREG_SPLIT_DELIM_CAPTURE)) > 1)
    {
        // A toggle that dermines whether we're directly under a <ol> or <ul>.
        $inList = false;

        // Keep track of the number of nested list levels.
        $listDepth = 0;

        // Map what we can expect from the HTML to what is supported by SMF.
        $listTypeMapping = array(
            '1' => 'decimal',
            'A' => 'upper-alpha',
            'a' => 'lower-alpha',
            'I' => 'upper-roman',
            'i' => 'lower-roman',
            'disc' => 'disc',
            'square' => 'square',
            'circle' => 'circle',
        );

        // $i: text, $i + 1: '/', $i + 2: tag, $i + 3: tail.
        for ($i = 0, $numParts = count($parts) - 1; $i < $numParts; $i += 4)
        {
            $tag = strtolower($parts[$i + 2]);
            $isOpeningTag = $parts[$i + 1] === '';

            if ($isOpeningTag)
            {
                switch ($tag)
                {
                    case 'ol':
                    case 'ul':

                        // We have a problem, we're already in a list.
                        if ($inList)
                        {
                            // Inject a list opener, we'll deal with the ol/ul next loop.
                            array_splice($parts, $i, 0, array(
                                '',
                                '',
                                str_repeat("\t", $listDepth) . '[li]',
                                '',
                            ));
                            $numParts = count($parts) - 1;

                            // The inlist status changes a bit.
                            $inList = false;
                        }

                        // Just starting a new list.
                        else
                        {
                            $inList = true;

                            if ($tag === 'ol')
                                $listType = 'decimal';
                            elseif (preg_match('~type="?(' . implode('|', array_keys($listTypeMapping)) . ')"?~', $parts[$i + 3], $match) === 1)
                                $listType = $listTypeMapping[$match[1]];
                            else
                                $listType = null;

                            $listDepth++;

                            $parts[$i + 2] = '[list' . ($listType === null ? '' : ' type=' . $listType) . ']' . "\n";
                            $parts[$i + 3] = '';
                        }
                    break;

                    case 'li':

                        // This is how it should be: a list item inside the list.
                        if ($inList)
                        {
                            $parts[$i + 2] = str_repeat("\t", $listDepth) . '[li]';
                            $parts[$i + 3] = '';

                            // Within a list item, it's almost as if you're outside.
                            $inList = false;
                        }

                        // The li is no direct child of a list.
                        else
                        {
                            // We are apparently in a list item.
                            if ($listDepth > 0)
                            {
                                $parts[$i + 2] = '[/li]' . "\n" . str_repeat("\t", $listDepth) . '[li]';
                                $parts[$i + 3] = '';
                            }

                            // We're not even near a list.
                            else
                            {
                                // Quickly create a list with an item.
                                $listDepth++;

                                $parts[$i + 2] = '[list]' . "\n\t" . '[li]';
                                $parts[$i + 3] = '';
                            }
                        }

                    break;
                }
            }

            // Handle all the closing tags.
            else
            {
                switch ($tag)
                {
                    case 'ol':
                    case 'ul':

                        // As we expected it, closing the list while we're in it.
                        if ($inList)
                        {
                            $inList = false;

                            $listDepth--;

                            $parts[$i + 1] = '';
                            $parts[$i + 2] = str_repeat("\t", $listDepth) . '[/list]';
                            $parts[$i + 3] = '';
                        }

                        else
                        {
                            // We're in a list item.
                            if ($listDepth > 0)
                            {
                                // Inject closure for this list item first.
                                // The content of $parts[$i] is left as is!
                                array_splice($parts, $i + 1, 0, array(
                                    '',                // $i + 1
                                    '[/li]' . "\n",    // $i + 2
                                    '',                // $i + 3
                                    '',                // $i + 4
                                ));
                                $numParts = count($parts) - 1;

                                // Now that we've closed the li, we're in list space.
                                $inList = true;
                            }

                            // We're not even in a list, ignore
                            else
                            {
                                $parts[$i + 1] = '';
                                $parts[$i + 2] = '';
                                $parts[$i + 3] = '';
                            }
                        }
                    break;

                    case 'li':

                        if ($inList)
                        {
                            // There's no use for a </li> after <ol> or <ul>, ignore.
                            $parts[$i + 1] = '';
                            $parts[$i + 2] = '';
                            $parts[$i + 3] = '';
                        }

                        else
                        {
                            // Remove the trailing breaks from the list item.
                            $parts[$i] = preg_replace('~\s*<br\s*' . '/?' . '>\s*$~', '', $parts[$i]);
                            $parts[$i + 1] = '';
                            $parts[$i + 2] = '[/li]' . "\n";
                            $parts[$i + 3] = '';

                            // And we're back in the [list] space.
                            $inList = true;
                        }

                    break;
                }
            }

            // If we're in the [list] space, no content is allowed.
            if ($inList && trim(preg_replace('~\s*<br\s*' . '/?' . '>\s*~', '', $parts[$i + 4])) !== '')
            {
                // Fix it by injecting an extra list item.
                array_splice($parts, $i + 4, 0, array(
                    '', // No content.
                    '', // Opening tag.
                    'li', // It's a <li>.
                    '', // No tail.
                ));
                $numParts = count($parts) - 1;
            }
        }

        $text = implode('', $parts);

        if ($inList)
        {
            $listDepth--;
            $text .= str_repeat("\t", $listDepth) . '[/list]';
        }

        for ($i = $listDepth; $i > 0; $i--)
            $text .= '[/li]' . "\n" . str_repeat("\t", $i - 1) . '[/list]';

    }

    // I love my own image...
    while (preg_match('~<img\s+([^<>]*)/*>~i', $text, $matches) === 1)
    {
        // Find the position of the image.
        $start_pos = strpos($text, $matches[0]);
        if ($start_pos === false)
            break;
        $end_pos = $start_pos + strlen($matches[0]);

        $params = '';
        $had_params = array();
        $src = '';

        $attrs = fetchTagAttributes($matches[1]);
        foreach ($attrs as $attrib => $value)
        {
            if (in_array($attrib, array('width', 'height')))
                $params .= ' ' . $attrib . '=' . (int) $value;
            elseif ($attrib == 'alt' && trim($value) != '')
                $params .= ' alt=' . trim($value);
            elseif ($attrib == 'src')
                $src = trim($value);
        }

        $tag = '';
        if (!empty($src))
        {
            // Attempt to fix the path in case it's not present.
            if (preg_match('~^https?://~i', $src) === 0 && is_array($parsedURL = parse_url($scripturl)) && isset($parsedURL['host']))
            {
                $baseURL = (isset($parsedURL['scheme']) ? $parsedURL['scheme'] : 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);

                if (substr($src, 0, 1) === '/')
                    $src = $baseURL . $src;
                else
                    $src = $baseURL . (empty($parsedURL['path']) ? '/' : preg_replace('~/(?:index\\.php)?$~', '', $parsedURL['path'])) . '/' . $src;
            }

            $tag = '[img' . $params . ']' . $src . '[/img]';
        }

        // Replace the tag
        $text = substr($text, 0, $start_pos) . $tag . substr($text, $end_pos);
    }

    // The final bits are the easy ones - tags which map to tags which map to tags - etc etc.
    $tags = array(
        '~<b(\s(.)*?)*?' . '>~i' => '[b]',
        '~</b>~i' => '[/b]',
        '~<i(\s(.)*?)*?' . '>~i' => '[i]',
        '~</i>~i' => '[/i]',
        '~<u(\s(.)*?)*?' . '>~i' => '[u]',
        '~</u>~i' => '[/u]',
        '~<strong(\s(.)*?)*?' . '>~i' => '[b]',
        '~</strong>~i' => '[/b]',
        '~<em(\s(.)*?)*?' . '>~i' => '[i]',
        '~</em>~i' => '[/i]',
        '~<s(\s(.)*?)*?' . '>~i' => "[s]",
        '~</s>~i' => "[/s]",
        '~<strike(\s(.)*?)*?' . '>~i' => '[s]',
        '~</strike>~i' => '[/s]',
        '~<del(\s(.)*?)*?' . '>~i' => '[s]',
        '~</del>~i' => '[/s]',
        '~<center(\s(.)*?)*?' . '>~i' => '[center]',
        '~</center>~i' => '[/center]',
        '~<pre(\s(.)*?)*?' . '>~i' => '[pre]',
        '~</pre>~i' => '[/pre]',
        '~<sub(\s(.)*?)*?' . '>~i' => '[sub]',
        '~</sub>~i' => '[/sub]',
        '~<sup(\s(.)*?)*?' . '>~i' => '[sup]',
        '~</sup>~i' => '[/sup]',
        '~<tt(\s(.)*?)*?' . '>~i' => '[tt]',
        '~</tt>~i' => '[/tt]',
        '~<table(\s(.)*?)*?' . '>~i' => '[table]',
        '~</table>~i' => '[/table]',
        '~<tr(\s(.)*?)*?' . '>~i' => '[tr]',
        '~</tr>~i' => '[/tr]',
        '~<(td|th)\s[^<>]*?colspan="?(\d{1,2})"?.*?' . '>~ie' => 'str_repeat(\'[td][/td]\', $2 - 1) . \'[td]\'',
        '~<(td|th)(\s(.)*?)*?' . '>~i' => '[td]',
        '~</(td|th)>~i' => '[/td]',
        '~<br(?:\s[^<>]*?)?' . '>~i' => "\n",
        '~<hr[^<>]*>(\n)?~i' => "[hr]\n$1",
        '~(\n)?\\[hr\\]~i' => "\n[hr]",
        '~^\n\\[hr\\]~i' => "[hr]",
        '~<blockquote(\s(.)*?)*?' . '>~i' => "&lt;blockquote&gt;",
        '~</blockquote>~i' => "&lt;/blockquote&gt;",
        '~<ins(\s(.)*?)*?' . '>~i' => "&lt;ins&gt;",
        '~</ins>~i' => "&lt;/ins&gt;",
    );
    $text = preg_replace(array_keys($tags), array_values($tags), $text);

    // Please give us just a little more time.
    if (connection_aborted() && $context['server']['is_apache'])
        @apache_reset_timeout();

    // What about URL's - the pain in the ass of the tag world.
    while (preg_match('~<a\s+([^<>]*)>([^<>]*)</a>~i', $text, $matches) === 1)
    {
        // Find the position of the URL.
        $start_pos = strpos($text, $matches[0]);
        if ($start_pos === false)
            break;
        $end_pos = $start_pos + strlen($matches[0]);

        $tag_type = 'url';
        $href = '';

        $attrs = fetchTagAttributes($matches[1]);
        foreach ($attrs as $attrib => $value)
        {
            if ($attrib == 'href')
            {
                $href = trim($value);

                // Are we dealing with an FTP link?
                if (preg_match('~^ftps?://~', $href) === 1)
                    $tag_type = 'ftp';

                // Or is this a link to an email address?
                elseif (substr($href, 0, 7) == 'mailto:')
                {
                    $tag_type = 'email';
                    $href = substr($href, 7);
                }

                // No http(s), so attempt to fix this potential relative URL.
                elseif (preg_match('~^https?://~i', $href) === 0 && is_array($parsedURL = parse_url($scripturl)) && isset($parsedURL['host']))
                {
                    $baseURL = (isset($parsedURL['scheme']) ? $parsedURL['scheme'] : 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);

                    if (substr($href, 0, 1) === '/')
                        $href = $baseURL . $href;
                    else
                        $href = $baseURL . (empty($parsedURL['path']) ? '/' : preg_replace('~/(?:index\\.php)?$~', '', $parsedURL['path'])) . '/' . $href;
                }
            }

            // External URL?
            if ($attrib == 'target' && $tag_type == 'url')
            {
                if (trim($value) == '_blank')
                    $tag_type == 'iurl';
            }
        }

        $tag = '';
        if ($href != '')
        {
            if ($matches[2] == $href)
                $tag = '[' . $tag_type . ']' . $href . '[/' . $tag_type . ']';
            else
                $tag = '[' . $tag_type . '=' . $href . ']' . $matches[2] . '[/' . $tag_type . ']';
        }

        // Replace the tag
        $text = substr($text, 0, $start_pos) . $tag . substr($text, $end_pos);
    }

    $text = strip_tags($text);

    // Some tags often end up as just dummy tags - remove those.
    $text = preg_replace('~\[[bisu]\]\s*\[/[bisu]\]~', '', $text);

    // Fix up entities.
    $text = preg_replace('~&#38;~i', '&#38;#38;', $text);

    $text = mob_legalise_bbc($text);

    return $text;
}
function mob_legalise_bbc($text)
{
    global $modSettings;

    // Don't care about the texts that are too short.
    if (strlen($text) < 3)
        return $text;

    // We are going to cycle through the BBC and keep track of tags as they arise - in order. If get to a block level tag we're going to make sure it's not in a non-block level tag!
    // This will keep the order of tags that are open.
    $current_tags = array();

    // This will quickly let us see if the tag is active.
    $active_tags = array();

    // A list of tags that's disabled by the admin.
    $disabled = empty($modSettings['disabledBBC']) ? array() : array_flip(explode(',', strtolower($modSettings['disabledBBC'])));

    // Add flash if it's disabled as embedded tag.
    if (empty($modSettings['enableEmbeddedFlash']))
        $disabled['flash'] = true;

    // Get a list of all the tags that are not disabled.
    $all_tags = parse_bbc(false);
    $valid_tags = array();
    $self_closing_tags = array();
    foreach ($all_tags as $tag)
    {
        if (!isset($disabled[$tag['tag']]))
            $valid_tags[$tag['tag']] = !empty($tag['block_level']);
        if (isset($tag['type']) && $tag['type'] == 'closed')
            $self_closing_tags[] = $tag['tag'];
    }

    // Don't worry if we're in a code/nobbc.
    $in_code_nobbc = false;

    // Right - we're going to start by going through the whole lot to make sure we don't have align stuff crossed as this happens load and is stupid!
    $align_tags = array('left', 'center', 'right', 'pre');

    // Remove those align tags that are not valid.
    $align_tags = array_intersect($align_tags, array_keys($valid_tags));

    // These keep track of where we are!
    if (!empty($align_tags) && count($matches = preg_split('~(\\[/?(?:' . implode('|', $align_tags) . ')\\])~', $text, -1, PREG_SPLIT_DELIM_CAPTURE)) > 1)
    {
        // The first one is never a tag.
        $isTag = false;

        // By default we're not inside a tag too.
        $insideTag = null;

        foreach ($matches as $i => $match)
        {
            // We're only interested in tags, not text.
            if ($isTag)
            {
                $isClosingTag = substr($match, 1, 1) === '/';
                $tagName = substr($match, $isClosingTag ? 2 : 1, -1);

                // We're closing the exact same tag that we opened.
                if ($isClosingTag && $insideTag === $tagName)
                    $insideTag = null;

                // We're opening a tag and we're not yet inside one either
                elseif (!$isClosingTag && $insideTag === null)
                    $insideTag = $tagName;

                // In all other cases, this tag must be invalid
                else
                    unset($matches[$i]);
            }

            // The next one is gonna be the other one.
            $isTag = !$isTag;
        }

        // We're still inside a tag and had no chance for closure?
        if ($insideTag !== null)
            $matches[] = '[/' . $insideTag . ']';

        // And a complete text string again.
        $text = implode('', $matches);
    }

    // Quickly remove any tags which are back to back.
    $backToBackPattern = '~\\[(' . implode('|', array_diff(array_keys($valid_tags), array('td'))) . ')[^<>\\[\\]]*\\]\s*\\[/\\1\\]~';
    $lastlen = 0;
    while (strlen($text) !== $lastlen)
        $lastlen = strlen($text = preg_replace($backToBackPattern, '', $text));

    // Need to sort the tags my name length.
    //uksort($valid_tags, 'sort_array_length');

    // These inline tags can compete with each other regarding style.
    $competing_tags = array(
        'color',
        'size',
    );

    // In case things changed above set these back to normal.
    $in_code_nobbc = false;
    $new_text_offset = 0;

    // These keep track of where we are!
    if (count($parts = preg_split(sprintf('~(\\[)(/?)(%1$s)((?:[\\s=][^\\]]*)?\\])~', implode('|', array_keys($valid_tags))), $text, -1, PREG_SPLIT_DELIM_CAPTURE)) > 1)
    {
        // Start with just text.
        $isTag = false;

        // Start outside [nobbc] or [code] blocks.
        $inCode = false;
        $inNoBbc = false;

        // A buffer containing all opened inline elements.
        $inlineElements = array();

        // A buffer containing all opened block elements.
        $blockElements = array();

        // A buffer containing the opened inline elements that might compete.
        $competingElements = array();

        // $i: text, $i + 1: '[', $i + 2: '/', $i + 3: tag, $i + 4: tag tail.
        for ($i = 0, $n = count($parts) - 1; $i < $n; $i += 5)
        {
            $tag = $parts[$i + 3];
            $isOpeningTag = $parts[$i + 2] === '';
            $isClosingTag = $parts[$i + 2] === '/';
            $isBlockLevelTag = isset($valid_tags[$tag]) && $valid_tags[$tag] && !in_array($tag, $self_closing_tags);
            $isCompetingTag = in_array($tag, $competing_tags);

            // Check if this might be one of those cleaned out tags.
            if ($tag === '')
                continue;

            // Special case: inside [code] blocks any code is left untouched.
            elseif ($tag === 'code')
            {
                // We're inside a code block and closing it.
                if ($inCode && $isClosingTag)
                {
                    $inCode = false;

                    // Reopen tags that were closed before the code block.
                    if (!empty($inlineElements))
                        $parts[$i + 4] .= '[' . implode('][', array_keys($inlineElements)) . ']';
                }

                // We're outside a coding and nobbc block and opening it.
                elseif (!$inCode && !$inNoBbc && $isOpeningTag)
                {
                    // If there are still inline elements left open, close them now.
                    if (!empty($inlineElements))
                    {
                        $parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';
                        //$inlineElements = array();
                    }

                    $inCode = true;
                }

                // Nothing further to do.
                continue;
            }

            // Special case: inside [nobbc] blocks any BBC is left untouched.
            elseif ($tag === 'nobbc')
            {
                // We're inside a nobbc block and closing it.
                if ($inNoBbc && $isClosingTag)
                {
                    $inNoBbc = false;

                    // Some inline elements might've been closed that need reopening.
                    if (!empty($inlineElements))
                        $parts[$i + 4] .= '[' . implode('][', array_keys($inlineElements)) . ']';
                }

                // We're outside a nobbc and coding block and opening it.
                elseif (!$inNoBbc && !$inCode && $isOpeningTag)
                {
                    // Can't have inline elements still opened.
                    if (!empty($inlineElements))
                    {
                        $parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';
                        //$inlineElements = array();
                    }

                    $inNoBbc = true;
                }

                continue;
            }

            // So, we're inside one of the special blocks: ignore any tag.
            elseif ($inCode || $inNoBbc)
                continue;

            // We're dealing with an opening tag.
            if ($isOpeningTag)
            {
                // Everyting inside the square brackets of the opening tag.
                $elementContent = $parts[$i + 3] . substr($parts[$i + 4], 0, -1);

                // A block level opening tag.
                if ($isBlockLevelTag)
                {
                    // Are there inline elements still open?
                    if (!empty($inlineElements))
                    {
                        // Close all the inline tags, a block tag is coming...
                        $parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';

                        // Now open them again, we're inside the block tag now.
                        $parts[$i + 5] = '[' . implode('][', array_keys($inlineElements)) . ']' . $parts[$i + 5];
                    }

                    $blockElements[] = $tag;
                }

                // Inline opening tag.
                elseif (!in_array($tag, $self_closing_tags))
                {
                    // Can't have two opening elements with the same contents!
                    if (isset($inlineElements[$elementContent]))
                    {
                        // Get rid of this tag.
                        $parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';

                        // Now try to find the corresponding closing tag.
                        $curLevel = 1;
                        for ($j = $i + 5, $m = count($parts) - 1; $j < $m; $j += 5)
                        {
                            // Find the tags with the same tagname
                            if ($parts[$j + 3] === $tag)
                            {
                                // If it's an opening tag, increase the level.
                                if ($parts[$j + 2] === '')
                                    $curLevel++;

                                // A closing tag, decrease the level.
                                else
                                {
                                    $curLevel--;

                                    // Gotcha! Clean out this closing tag gone rogue.
                                    if ($curLevel === 0)
                                    {
                                        $parts[$j + 1] = $parts[$j + 2] = $parts[$j + 3] = $parts[$j + 4] = '';
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    // Otherwise, add this one to the list.
                    else
                    {
                        if ($isCompetingTag)
                        {
                            if (!isset($competingElements[$tag]))
                                $competingElements[$tag] = array();

                            $competingElements[$tag][] = $parts[$i + 4];

                            if (count($competingElements[$tag]) > 1)
                                $parts[$i] .= '[/' . $tag . ']';
                        }

                        $inlineElements[$elementContent] = $tag;
                    }
                }

            }

            // Closing tag.
            else
            {
                // Closing the block tag.
                if ($isBlockLevelTag)
                {
                    // Close the elements that should've been closed by closing this tag.
                    if (!empty($blockElements))
                    {
                        $addClosingTags = array();
                        while ($element = array_pop($blockElements))
                        {
                            if ($element === $tag)
                                break;

                            // Still a block tag was open not equal to this tag.
                            $addClosingTags[] = $element['type'];
                        }

                        if (!empty($addClosingTags))
                            $parts[$i + 1] = '[/' . implode('][/', array_reverse($addClosingTags)) . ']' . $parts[$i + 1];

                        // Apparently the closing tag was not found on the stack.
                        if (!is_string($element) || $element !== $tag)
                        {
                            // Get rid of this particular closing tag, it was never opened.
                            $parts[$i + 1] = substr($parts[$i + 1], 0, -1);
                            $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
                            continue;
                        }
                    }
                    else
                    {
                        // Get rid of this closing tag!
                        $parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
                        continue;
                    }

                    // Inline elements are still left opened?
                    if (!empty($inlineElements))
                    {
                        // Close them first..
                        $parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';

                        // Then reopen them.
                        $parts[$i + 5] = '[' . implode('][', array_keys($inlineElements)) . ']' . $parts[$i + 5];
                    }
                }
                // Inline tag.
                else
                {
                    // Are we expecting this tag to end?
                    if (in_array($tag, $inlineElements))
                    {
                        foreach (array_reverse($inlineElements, true) as $tagContentToBeClosed => $tagToBeClosed)
                        {
                            // Closing it one way or the other.
                            unset($inlineElements[$tagContentToBeClosed]);

                            // Was this the tag we were looking for?
                            if ($tagToBeClosed === $tag)
                                break;

                            // Nope, close it and look further!
                            else
                                $parts[$i] .= '[/' . $tagToBeClosed . ']';
                        }

                        if ($isCompetingTag && !empty($competingElements[$tag]))
                        {
                            array_pop($competingElements[$tag]);

                            if (count($competingElements[$tag]) > 0)
                                $parts[$i + 5] = '[' . $tag . $competingElements[$tag][count($competingElements[$tag]) - 1] . $parts[$i + 5];
                        }
                    }

                    // Unexpected closing tag, ex-ter-mi-nate.
                    else
                        $parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
                }
            }
        }

        // Close the code tags.
        if ($inCode)
            $parts[$i] .= '[/code]';

        // The same for nobbc tags.
        elseif ($inNoBbc)
            $parts[$i] .= '[/nobbc]';

        // Still inline tags left unclosed? Close them now, better late than never.
        elseif (!empty($inlineElements))
            $parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';

        // Now close the block elements.
        if (!empty($blockElements))
            $parts[$i] .= '[/' . implode('][/', array_reverse($blockElements)) . ']';

        $text = implode('', $parts);
    }

    // Final clean up of back to back tags.
    $lastlen = 0;
    while (strlen($text) !== $lastlen)
        $lastlen = strlen($text = preg_replace($backToBackPattern, '', $text));

    return $text;
}

// Strtolower for arrah walk
function array_strtolower($value, $key)
{
    return strtolower($value);
}

// Gets the current online members
function getMembersOnline()
{
    global $mobdb, $user_info, $scripturl, $modSettings, $txt;

    $return = array();

    // Load the users online right now.
    $result = $mobdb->query('
        SELECT
            lo.ID_MEMBER AS id_member, lo.logTime AS log_time, mem.realName AS real_name, mem.memberName AS member_name, mem.showOnline AS show_online, lo.session, lo.url,
            mg.onlineColor AS online_color, mg.ID_GROUP AS id_group, mg.groupName AS group_name, mem.avatar as avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type
        FROM {db_prefix}log_online AS lo
            LEFT JOIN {db_prefix}members AS mem ON (mem.ID_MEMBER = lo.ID_MEMBER)
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
            LEFT JOIN {db_prefix}membergroups AS mg ON (mg.ID_GROUP = IF(mem.ID_GROUP = 0, mem.ID_POST_GROUP, mem.ID_GROUP))',
        array()
    );

    $return['users_online'] = array();
    $return['list_users_online'] = array();
    $return['online_groups'] = array();
    $return['num_guests'] = 0;
    $return['num_buddies'] = 0;
    $return['num_users_hidden'] = 0;

    $return['show_buddies'] = !empty($user_info['buddies']);

    $url_data = array();
    while ($row = $mobdb->fetch_assoc())
    {
        if (empty($row['real_name']))
        {
            $return['num_guests']++;
            continue;
        }
        elseif (empty($row['show_online']) && !allowedTo('moderate_forum'))
        {
            $return['num_users_hidden']++;
            continue;
        }

        $is_buddy = in_array($row['id_member'], $user_info['buddies']);

        $return['users_online'][$row['session']] = array(
            'id' => $row['id_member'],
            'username' => $row['member_name'],
            'name' => $row['real_name'],
            'group' => $row['id_group'],
            'is_buddy' => $is_buddy,
            'hidden' => empty($row['show_online']),
            'avatar' => get_avatar($row),
        );

        $url_data[$row['session']] = array($row['url'], $row['id_member']);
    }
    $mobdb->free_result();

    $url_data = determineActions($url_data);

    foreach ($return['users_online'] as $i => $member)
    {
        $return['users_online'][$i]['action'] = isset($url_data[$i]) ? $url_data[$i] : $txt['who_hidden'];
    }

    return $return;
}

function utf8ToAscii($str){
    return mobiquo_encode($str, 'to_local');
}

function mobi_unescape_body_html($str)
{
    $str = strip_tags($str);

    // add for bbcode
    $search = array(
        '#\[(tpt-b)\](.*?)\[/tpt-b\]#si',
        '#\[(tpt-u)\](.*?)\[/tpt-u\]#si',
        '#\[(tpt-i)\](.*?)\[/tpt-i\]#si',
        '#\[tpt-color=(.*?)\](.*?)\[/tpt-color\]#si',
    );

    if (!empty($GLOBALS['return_html'])) {
        $replace = array(
            '<b>$2</b>',
            '<u>$2</u>',
            '<i>$2</i>',
            '<font color="$1">$2</font>',
        );
        $str = str_replace(array('[tpt-quote]', '[/tpt-quote]'), array('[quote]', '[/quote]'), $str);
        $str = mobiquo_encode($str);
        $str = str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $str);
        $str = str_replace("\n", '<br />', $str);
        $str = str_replace("&nbsp;", ' ', $str);
    } else {
        $str = preg_replace('#\[tpt-quote\].*?\[/tpt-quote\]#si', '', $str);
        $str = mobi_unescape_html($str);
        $replace = '$2';
    }

    $str = preg_replace($search, $replace, $str);

    return $str;
}

function mobi_unescape_html($str)
{
    $str = strip_tags($str);
    $str = mobiquo_encode($str);

    return $str;
}

function determineActions($urls)
{
    global $txt, $db_prefix, $user_info, $ID_MEMBER, $modSettings;

    if (!allowedTo('who_view'))
        return array();
    loadLanguage('Who');

    // Actions that require a specific permission level.
    $allowedActions = array(
        'admin' => array('moderate_forum', 'manage_membergroups', 'manage_bans', 'admin_forum', 'manage_permissions', 'send_mail', 'manage_attachments', 'manage_smileys', 'manage_boards', 'edit_news'),
        'ban' => array('manage_bans'),
        'boardrecount' => array('admin_forum'),
        'calendar' => array('calendar_view'),
        'editnews' => array('edit_news'),
        'mailing' => array('send_mail'),
        'maintain' => array('admin_forum'),
        'manageattachments' => array('manage_attachments'),
        'manageboards' => array('manage_boards'),
        'mlist' => array('view_mlist'),
        'optimizetables' => array('admin_forum'),
        'repairboards' => array('admin_forum'),
        'search' => array('search_posts'),
        'search2' => array('search_posts'),
        'setcensor' => array('moderate_forum'),
        'setreserve' => array('moderate_forum'),
        'stats' => array('view_stats'),
        'viewErrorLog' => array('admin_forum'),
        'viewmembers' => array('moderate_forum'),
    );

    if (!is_array($urls))
        $url_list = array(array($urls, $ID_MEMBER));
    else
        $url_list = $urls;

    // These are done to later query these in large chunks. (instead of one by one.)
    $topic_ids = array();
    $profile_ids = array();
    $board_ids = array();

    $data = array();
    foreach ($url_list as $k => $url)
    {
        // Get the request parameters..
        $actions = @unserialize($url[0]);
        if ($actions === false)
            continue;

        // Check if there was no action or the action is display.
        if (!isset($actions['action']) || $actions['action'] == 'display')
        {
            // It's a topic!  Must be!
            if (isset($actions['topic']))
            {
                // Assume they can't view it, and queue it up for later.
                $data[$k] = $txt['who_hidden'];
                $topic_ids[(int) $actions['topic']][$k] = $txt['who_topic'];
            }
            // It's a board!
            elseif (isset($actions['board']))
            {
                // Hide first, show later.
                $data[$k] = $txt['who_hidden'];
                $board_ids[$actions['board']][$k] = $txt['who_board'];
            }
            // It's the board index!!  It must be!
            else
            {
                $data[$k] = $txt['who_index'];
                // ...or maybe it's just integrated into another system...
                if (isset($modSettings['integrate_whos_online']) && function_exists($modSettings['integrate_whos_online']))
                    $data[$k] = $modSettings['integrate_whos_online']($actions);
            }
        }
        // Probably an error or some goon?
        elseif ($actions['action'] == '')
            $data[$k] = $txt['who_index'];

        // Some other normal action...?
        else
        {
            // Viewing/editing a profile.
            if ($actions['action'] == 'profile' || $actions['action'] == 'profile2')
            {
                // Whose?  Their own?
                if (empty($actions['u']))
                    $actions['u'] = $url[1];

                $data[$k] = $txt['who_hidden'];
                $profile_ids[(int) $actions['u']][$k] = $actions['action'] == 'profile' ? $txt['who_viewprofile'] : $txt['who_profile'];
            }
            elseif (($actions['action'] == 'post' || $actions['action'] == 'post2') && empty($actions['topic']) && isset($actions['board']))
            {
                $data[$k] = $txt['who_hidden'];
                $board_ids[(int) $actions['board']][$k] = isset($actions['poll']) ? $txt['who_poll'] : $txt['who_post'];
            }
            // A subaction anyone can view... if the language string is there, show it.
            elseif (isset($actions['sa']) && isset($txt['whoall_' . $actions['action'] . '_' . $actions['sa']]))
                $data[$k] = $txt['whoall_' . $actions['action'] . '_' . $actions['sa']];
            // An action any old fellow can look at. (if ['whoall_' . $action] exists, we know everyone can see it.)
            elseif (isset($txt['whoall_' . $actions['action']]))
                $data[$k] = $txt['whoall_' . $actions['action']];
            // Viewable if and only if they can see the board...
            elseif (isset($txt['whotopic_' . $actions['action']]))
            {
                // Find out what topic they are accessing.
                $topic = (int) (isset($actions['topic']) ? $actions['topic'] : (isset($actions['from']) ? $actions['from'] : 0));

                $data[$k] = $txt['who_hidden'];
                $topic_ids[$topic][$k] = $txt['whotopic_' . $actions['action']];
            }
            elseif (isset($txt['whopost_' . $actions['action']]))
            {
                // Find out what message they are accessing.
                $msgid = (int) (isset($actions['msg']) ? $actions['msg'] : (isset($actions['quote']) ? $actions['quote'] : 0));

                $result = db_query("
                    SELECT m.ID_TOPIC, m.subject
                    FROM ({$db_prefix}boards AS b, {$db_prefix}messages AS m)
                    WHERE $user_info[query_see_board]
                        AND m.ID_MSG = $msgid
                        AND m.ID_BOARD = b.ID_BOARD
                    LIMIT 1", __FILE__, __LINE__);
                list ($ID_TOPIC, $subject) = mysql_fetch_row($result);
                $data[$k] = sprintf($txt['whopost_' . $actions['action']], $ID_TOPIC, $subject);
                mysql_free_result($result);

                if (empty($ID_TOPIC))
                    $data[$k] = $txt['who_hidden'];
            }
            // Viewable only by administrators.. (if it starts with whoadmin, it's admin only!)
            elseif (allowedTo('moderate_forum') && isset($txt['whoadmin_' . $actions['action']]))
                $data[$k] = $txt['whoadmin_' . $actions['action']];
            // Viewable by permission level.
            elseif (isset($allowedActions[$actions['action']]))
            {
                if (allowedTo($allowedActions[$actions['action']]))
                    $data[$k] = $txt['whoallow_' . $actions['action']];
                else
                    $data[$k] = $txt['who_hidden'];
            }
            // Unlisted or unknown action.
            else
                $data[$k] = $txt['who_unknown'];
        }
    }

    // Load topic names.
    if (!empty($topic_ids))
    {
        $result = db_query("
            SELECT t.ID_TOPIC, m.subject
            FROM ({$db_prefix}boards AS b, {$db_prefix}topics AS t, {$db_prefix}messages AS m)
            WHERE $user_info[query_see_board]
                AND t.ID_TOPIC IN (" . implode(', ', array_keys($topic_ids)) . ")
                AND t.ID_BOARD = b.ID_BOARD
                AND m.ID_MSG = t.ID_FIRST_MSG
            LIMIT " . count($topic_ids), __FILE__, __LINE__);
        while ($row = mysql_fetch_assoc($result))
        {
            // Show the topic's subject for each of the actions.
            foreach ($topic_ids[$row['ID_TOPIC']] as $k => $session_text)
                $data[$k] = sprintf($session_text, $row['ID_TOPIC'], censorText($row['subject']));
        }
        mysql_free_result($result);
    }

    // Load board names.
    if (!empty($board_ids))
    {
        $result = db_query("
            SELECT b.ID_BOARD, b.name
            FROM {$db_prefix}boards AS b
            WHERE $user_info[query_see_board]
                AND b.ID_BOARD IN (" . implode(', ', array_keys($board_ids)) . ")
            LIMIT " . count($board_ids), __FILE__, __LINE__);
        while ($row = mysql_fetch_assoc($result))
        {
            // Put the board name into the string for each member...
            foreach ($board_ids[$row['ID_BOARD']] as $k => $session_text)
                $data[$k] = sprintf($session_text, $row['ID_BOARD'], $row['name']);
        }
        mysql_free_result($result);
    }

    // Load member names for the profile.
    if (!empty($profile_ids) && (allowedTo('profile_view_any') || allowedTo('profile_view_own')))
    {
        $result = db_query("
            SELECT ID_MEMBER, realName
            FROM {$db_prefix}members
            WHERE ID_MEMBER IN (" . implode(', ', array_keys($profile_ids)) . ")
            LIMIT " . count($profile_ids), __FILE__, __LINE__);
        while ($row = mysql_fetch_assoc($result))
        {
            // If they aren't allowed to view this person's profile, skip it.
            if (!allowedTo('profile_view_any') && $ID_MEMBER != $row['ID_MEMBER'])
                continue;

            // Set their action on each - session/text to sprintf.
            foreach ($profile_ids[$row['ID_MEMBER']] as $k => $session_text)
                $data[$k] = sprintf($session_text, $row['ID_MEMBER'], $row['realName']);
        }
        mysql_free_result($result);
    }

    if (!is_array($urls))
        return isset($data[0]) ? $data[0] : false;
    else
        return $data;
}

function get_board_icon($board_id, $is_new = false, $children_new = false, $redirect = false)
{
    global $boardurl, $settings;

    $icon_path = 'forum_icons/';

    if (file_exists($icon_path.$board_id.'.png'))
        return $boardurl.'/mobiquo/'.$icon_path.$board_id.'.png';
    elseif (file_exists($icon_path.$board_id.'.jpg'))
        return $boardurl.'/mobiquo/'.$icon_path.$board_id.'.jpg';
    elseif (file_exists($icon_path.'default.png'))
        return $boardurl.'/mobiquo/'.$icon_path.'default.png';
    elseif (file_exists($icon_path.'default.jpg'))
        return $boardurl.'/mobiquo/'.$icon_path.'default.jpg';
    elseif ($is_new || $children_new) {
        $new_post = true;
        return $settings['images_url'].'/on'.($is_new ? '' : '2').'.gif';
    }
    elseif ($redirect)
        return $settings['images_url'].'/redirect.gif';
    else
        return $settings['images_url'].'/off.gif';

}

function parse_get($query)
{
    $get = array();

    $parts = explode('&', $query);
    foreach ($parts as $part)
    {
        if (strpos($part, ';') !== false)
        {
            $_parts = explode(';', $part);
            foreach ($_parts as $p)
            {
                list ($key, $value) = explode('=', $p);
                $get[$key] = $value;
            }
        }
        else
        {
            list ($key, $value) = explode('=', $part);
            $get[$key] = $value;
        }
    }

    return $get;
}

function process_url($url)
{
    $url = str_replace(' ', '%20', $url);
    $url = str_replace('&', '&amp;', $url);
    $url = str_replace('&amp;amp;', '&amp;', $url);

    return $url;
}

function process_page($start_num, $end)
{
    global $start, $limit;

    $start = intval($start_num);
    $end = intval($end);
    $start = empty($start) ? 0 : max($start, 0);
    $end = (empty($end) || $end < $start) ? ($start + 19) : max($end, $start);
    if ($end - $start >= 50) {
        $end = $start + 49;
    }
    $limit = $end - $start + 1;

    return array($start, $limit);
}

function get_avatar_by_ids($uids)
{
    global $db_prefix;

    if (empty($uids)) return array();

    $uids = array_unique($uids);

    $request = db_query("
        SELECT mem.ID_MEMBER, mem.avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type
        FROM {$db_prefix}members AS mem
            LEFT JOIN {$db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
        WHERE mem.ID_MEMBER IN (" . implode(',', $uids) . ")", __FILE__, __LINE__);

    $avatars = array();
    while ($row = mysql_fetch_assoc($request))
        $avatars[$row['ID_MEMBER']] = get_avatar($row);

    return $avatars;
}

function get_avatar($row)
{
    global $scripturl, $modSettings;

    if (isset($row['ID_ATTACH']))
        $row['id_attach'] = $row['ID_ATTACH'];
    if (isset($row['attachmentType']))
        $row['attachment_type'] = $row['attachmentType'];

    return str_replace(' ', '%20',
        $row['avatar'] == ''
            ? ($row['id_attach'] > 0
                ? (empty($row['attachment_type'])
                    ? $scripturl . '?action=dlattach;attach=' . $row['id_attach'] . ';type=avatar'
                    : $modSettings['custom_avatar_url'] . '/' . $row['filename'])
                : '')
            : (stristr($row['avatar'], 'http://')
                ? $row['avatar']
                : $modSettings['avatar_url'] . '/' . $row['avatar'])
    );
}

function get_subscribed_tids()
{
    global $ID_MEMBER, $db_prefix;

    $request = db_query("
        SELECT ID_TOPIC
        FROM {$db_prefix}log_notify
        WHERE ID_TOPIC > 0 AND ID_MEMBER = $ID_MEMBER", __FILE__, __LINE__);

    $tids = array();
    while ($row = mysql_fetch_assoc($request))
        $tids[] = $row['ID_TOPIC'];

    return $tids;
}


// Copy of SplitTopics.php > MergeExecute, stripped down slightly.
function do_merge($topics = array())
{
    global $db_prefix, $user_info, $txt, $context, $scripturl, $sourcedir;
    global $func, $language, $modSettings;

    // There's nothing to merge with just one topic...
    if (empty($topics) || !is_array($topics) || count($topics) == 1)
        fatal_lang_error('merge_need_more_topics');

    // Make sure every topic is numeric, or some nasty things could be done with the DB.
    foreach ($topics as $id => $topic)
        $topics[$id] = (int) $topic;

    // Get info about the topics and polls that will be merged.
    $request = db_query("
        SELECT
            t.ID_TOPIC, t.ID_BOARD, t.ID_POLL, t.numViews, t.isSticky,
            m1.subject, m1.posterTime AS time_started, IFNULL(mem1.ID_MEMBER, 0) AS ID_MEMBER_STARTED, IFNULL(mem1.realName, m1.posterName) AS name_started,
            m2.posterTime AS time_updated, IFNULL(mem2.ID_MEMBER, 0) AS ID_MEMBER_UPDATED, IFNULL(mem2.realName, m2.posterName) AS name_updated
        FROM ({$db_prefix}topics AS t, {$db_prefix}messages AS m1, {$db_prefix}messages AS m2)
            LEFT JOIN {$db_prefix}members AS mem1 ON (mem1.ID_MEMBER = m1.ID_MEMBER)
            LEFT JOIN {$db_prefix}members AS mem2 ON (mem2.ID_MEMBER = m2.ID_MEMBER)
        WHERE t.ID_TOPIC IN (" . implode(', ', $topics) . ")
            AND m1.ID_MSG = t.ID_FIRST_MSG
            AND m2.ID_MSG = t.ID_LAST_MSG
        ORDER BY t.ID_FIRST_MSG
        LIMIT " . count($topics), __FILE__, __LINE__);
    if (mysql_num_rows($request) < 2)
        fatal_lang_error('smf263');
    $num_views = 0;
    $isSticky = 0;
    $boards = array();
    $polls = array();
    while ($row = mysql_fetch_assoc($request))
    {
        $topic_data[$row['ID_TOPIC']] = array(
            'id' => $row['ID_TOPIC'],
            'board' => $row['ID_BOARD'],
            'poll' => $row['ID_POLL'],
            'numViews' => $row['numViews'],
            'subject' => $row['subject'],
            'started' => array(
                'time' => timeformat($row['time_started']),
                'timestamp' => forum_time(true, $row['time_started']),
                'href' => empty($row['ID_MEMBER_STARTED']) ? '' : $scripturl . '?action=profile;u=' . $row['ID_MEMBER_STARTED'],
                'link' => empty($row['ID_MEMBER_STARTED']) ? $row['name_started'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['ID_MEMBER_STARTED'] . '">' . $row['name_started'] . '</a>'
            ),
            'updated' => array(
                'time' => timeformat($row['time_updated']),
                'timestamp' => forum_time(true, $row['time_updated']),
                'href' => empty($row['ID_MEMBER_UPDATED']) ? '' : $scripturl . '?action=profile;u=' . $row['ID_MEMBER_UPDATED'],
                'link' => empty($row['ID_MEMBER_UPDATED']) ? $row['name_updated'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['ID_MEMBER_UPDATED'] . '">' . $row['name_updated'] . '</a>'
            )
        );
        $num_views += $row['numViews'];
        $boards[] = $row['ID_BOARD'];

        // If there's no poll, ID_POLL == 0...
        if ($row['ID_POLL'] > 0)
            $polls[] = $row['ID_POLL'];
        // Store the ID_TOPIC with the lowest ID_FIRST_MSG.
        if (empty($firstTopic))
            $firstTopic = $row['ID_TOPIC'];

        $isSticky = max($isSticky, $row['isSticky']);
    }
    mysql_free_result($request);

    $boards = array_values(array_unique($boards));

    // Get the boards a user is allowed to merge in.
    $merge_boards = boardsAllowedTo('merge_any');
    if (empty($merge_boards))
        fatal_lang_error('cannot_merge_any');

    // Make sure they can see all boards....
    $request = db_query("
        SELECT b.ID_BOARD
        FROM {$db_prefix}boards AS b
        WHERE b.ID_BOARD IN (" . implode(', ', $boards) . ")
            AND $user_info[query_see_board]" . (!in_array(0, $merge_boards) ? "
            AND b.ID_BOARD IN (" . implode(', ', $merge_boards) . ")" : '') . "
        LIMIT " . count($boards), __FILE__, __LINE__);
    // If the number of boards that's in the output isn't exactly the same as we've put in there, you're in trouble.
    if (mysql_num_rows($request) != count($boards))
        fatal_lang_error('smf232');
    mysql_free_result($request);

    // Determine target board.
    $target_board = count($boards) > 1 ? (int) $_REQUEST['board'] : $boards[0];
    if (!in_array($target_board, $boards))
        fatal_lang_error('smf232');

    // Determine which poll will survive and which polls won't.
    $target_poll = count($polls) > 1 ? (int) $_POST['poll'] : (count($polls) == 1 ? $polls[0] : 0);
    if ($target_poll > 0 && !in_array($target_poll, $polls))
        fatal_lang_error(1, false);
    $deleted_polls = empty($target_poll) ? $polls : array_diff($polls, array($target_poll));

    // Determine the subject of the newly merged topic - was a custom subject specified?\
    $target_subject = addslashes($topic_data[$firstTopic]['subject']);

    // Get the first and last message and the number of messages....
    $request = db_query("
        SELECT MIN(ID_MSG), MAX(ID_MSG), COUNT(ID_MSG) - 1
        FROM {$db_prefix}messages
        WHERE ID_TOPIC IN (" . implode(', ', $topics) . ")", __FILE__, __LINE__);
    list ($first_msg, $last_msg, $num_replies) = mysql_fetch_row($request);
    mysql_free_result($request);

    // Get the member ID of the first and last message.
    $request = db_query("
        SELECT ID_MEMBER
        FROM {$db_prefix}messages
        WHERE ID_MSG IN ($first_msg, $last_msg)
        ORDER BY ID_MSG
        LIMIT 2", __FILE__, __LINE__);
    list ($member_started) = mysql_fetch_row($request);
    list ($member_updated) = mysql_fetch_row($request);
    mysql_free_result($request);

    // Assign the first topic ID to be the merged topic.
    $ID_TOPIC = min($topics);

    // Delete the remaining topics.
    $deleted_topics = array_diff($topics, array($ID_TOPIC));
    db_query("
        DELETE FROM {$db_prefix}topics
        WHERE ID_TOPIC IN (" . implode(', ', $deleted_topics) . ")
        LIMIT " . count($deleted_topics), __FILE__, __LINE__);
    db_query("
        DELETE FROM {$db_prefix}log_search_subjects
        WHERE ID_TOPIC IN (" . implode(', ', $deleted_topics) . ")", __FILE__, __LINE__);

    // Asssign the properties of the newly merged topic.
    db_query("
        UPDATE {$db_prefix}topics
        SET
            ID_BOARD = $target_board,
            ID_MEMBER_STARTED = $member_started,
            ID_MEMBER_UPDATED = $member_updated,
            ID_FIRST_MSG = $first_msg,
            ID_LAST_MSG = $last_msg,
            ID_POLL = $target_poll,
            numReplies = $num_replies,
            numViews = $num_views,
            isSticky = $isSticky
        WHERE ID_TOPIC = $ID_TOPIC
        LIMIT 1", __FILE__, __LINE__);

    // Grab the response prefix (like 'Re: ') in the default forum language.
    if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
    {
        if ($language === $user_info['language'])
            $context['response_prefix'] = $txt['response_prefix'];
        else
        {
            loadLanguage('index', $language, false);
            $context['response_prefix'] = $txt['response_prefix'];
            loadLanguage('index');
        }
        cache_put_data('response_prefix', $context['response_prefix'], 600);
    }

    // Change the topic IDs of all messages that will be merged.  Also adjust subjects if 'enforce subject' was checked.
    db_query("
        UPDATE {$db_prefix}messages
        SET
            ID_TOPIC = $ID_TOPIC,
            ID_BOARD = $target_board" . (!empty($_POST['enforce_subject']) ? ",
            subject = '$context[response_prefix]$target_subject'" : '') . "
        WHERE ID_TOPIC IN (" . implode(', ', $topics) . ")", __FILE__, __LINE__);

    // Change the subject of the first message...
    db_query("
        UPDATE {$db_prefix}messages
        SET subject = '$target_subject'
        WHERE ID_MSG = $first_msg
        LIMIT 1", __FILE__, __LINE__);

    // Adjust all calendar events to point to the new topic.
    db_query("
        UPDATE {$db_prefix}calendar
        SET
            ID_TOPIC = $ID_TOPIC,
            ID_BOARD = $target_board
        WHERE ID_TOPIC IN (" . implode(', ', $deleted_topics) . ")", __FILE__, __LINE__);

    // Merge log topic entries.
    $request = db_query("
        SELECT ID_MEMBER, MIN(ID_MSG) AS new_ID_MSG
        FROM {$db_prefix}log_topics
        WHERE ID_TOPIC IN (" . implode(', ', $topics) . ")
        GROUP BY ID_MEMBER", __FILE__, __LINE__);
    if (mysql_num_rows($request) > 0)
    {
        $replaceEntries = array();
        while ($row = mysql_fetch_assoc($request))
            $replaceEntries[] = "($row[ID_MEMBER], $ID_TOPIC, $row[new_ID_MSG])";

        db_query("
            REPLACE INTO {$db_prefix}log_topics
                (ID_MEMBER, ID_TOPIC, ID_MSG)
            VALUES " . implode(', ', $replaceEntries), __FILE__, __LINE__);
        unset($replaceEntries);

        // Get rid of the old log entries.
        db_query("
            DELETE FROM {$db_prefix}log_topics
            WHERE ID_TOPIC IN (" . implode(', ', $deleted_topics) . ")", __FILE__, __LINE__);
    }
    mysql_free_result($request);

    // Get rid of the redundant polls.
    if (!empty($deleted_polls))
    {
        db_query("
            DELETE FROM {$db_prefix}polls
            WHERE ID_POLL IN (" . implode(', ', $deleted_polls) . ")
            LIMIT 1", __FILE__, __LINE__);
        db_query("
            DELETE FROM {$db_prefix}poll_choices
            WHERE ID_POLL IN (" . implode(', ', $deleted_polls) . ")", __FILE__, __LINE__);
        db_query("
            DELETE FROM {$db_prefix}log_polls
            WHERE ID_POLL IN (" . implode(', ', $deleted_polls) . ")", __FILE__, __LINE__);
    }

    // Fix the board totals.
    if (count($boards) > 1)
    {
        $request = db_query("
            SELECT ID_BOARD, COUNT(*) AS numTopics, SUM(numReplies) + COUNT(*) AS numPosts
            FROM {$db_prefix}topics
            WHERE ID_BOARD IN (" . implode(', ', $boards) . ")
            GROUP BY ID_BOARD
            LIMIT " . count($boards), __FILE__, __LINE__);
        while ($row = mysql_fetch_assoc($request))
            db_query("
                UPDATE {$db_prefix}boards
                SET
                    numPosts = $row[numPosts],
                    numTopics = $row[numTopics]
                WHERE ID_BOARD = $row[ID_BOARD]
                LIMIT 1", __FILE__, __LINE__);
        mysql_free_result($request);
    }
    else
        db_query("
            UPDATE {$db_prefix}boards
            SET numTopics = IF(" . (count($topics) - 1) . " > numTopics, 0, numTopics - " . (count($topics) - 1) . ")
            WHERE ID_BOARD = $target_board
            LIMIT 1", __FILE__, __LINE__);

    require_once($sourcedir . '/Subs-Post.php');

    // Update all the statistics.
    updateStats('topic');
    updateStats('subject', $ID_TOPIC, $target_subject);
    updateLastMessages($boards);

    logAction('merge', array('topic' => $ID_TOPIC));

    // Notify people that these topics have been merged?
    sendNotifications($ID_TOPIC, 'merge');
}

function get_topicinfo($id_topic)
{
    global $mobdb;

    // Get the topic's info
    $request = $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic, t.ID_BOARD AS id_board, b.name AS board_name, t.isSticky AS is_sticky,
                t.locked AS locked, t.ID_MEMBER_STARTED AS id_member_started, t.numReplies AS replies, t.locked,
                t.ID_FIRST_MSG AS id_first_msg
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
        WHERE t.ID_TOPIC = {int:topic}
            AND {query_see_board}
        LIMIT 1',
        array(
            'topic' => (int) $id_topic,
        )
    );
    if ($mobdb->num_rows($request) == 0)
        return array();
    $topic = $mobdb->fetch_assoc($request);
    $mobdb->free_result($request);

    return $topic;
}

function get_postinfo($id_msg)
{
    global $mobdb;

    // Get the post's info
    $request = $mobdb->query('
        SELECT m.ID_MSG AS id_msg, m.ID_MEMBER AS id_member, b.name AS board_name, b.ID_BOARD AS id_board,
                m.ID_TOPIC AS id_topic, t.ID_MEMBER_STARTED AS id_member_started, m.subject
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = m.ID_BOARD)
            INNER JOIN {db_prefix}topics AS t ON (t.ID_TOPIC = m.ID_TOPIC)
        WHERE m.ID_MSG = {int:msg}
            AND {query_see_board}
        LIMIT 1',
        array(
            'msg' => $id_msg,
        )
    );
    if ($mobdb->num_rows($request) == 0)
        return array();
    $post = $mobdb->fetch_assoc($request);
    $mobdb->free_result($request);

    return $post;
}

function get_boardinfo($id_board)
{
    global $mobdb;

    $request = $mobdb->query('
        SELECT b.ID_BOARD AS id_board, b.name AS board_name
        FROM {db_prefix}boards AS b
        WHERE {query_see_board}
            AND id_board = {int:board}
        LIMIT 1',
        array(
            'board' => $id_board,
        )
    );
    if ($mobdb->num_rows($request) == 0)
        return array();
    $board = $mobdb->fetch_assoc($request);
    $mobdb->free_result($request);

    return $board;
}

function move_topic($topic, $board, $newboard, $topicinfo)
{
    global $mobdb;

    // Move the topic and fix the stats
    $mobdb->query('
        UPDATE {db_prefix}topics
        SET ID_BOARD = {int:board}
        WHERE ID_TOPIC = {int:topic}',
        array(
            'board' => $newboard['id_board'],
            'topic' => $topic,
        )
    );
    $mobdb->query('
        UPDATE {db_prefix}calendar
        SET ID_BOARD = {int:board}
        WHERE ID_TOPIC = {int:topic}',
        array(
            'topic' => $topic,
            'board' => $newboard['id_board'],
        )
    );
    $mobdb->query('
        UPDATE {db_prefix}messages
        SET ID_BOARD = {int:board}
        WHERE ID_TOPIC = {int:topic}',
        array(
            'board' => $newboard['id_board'],
            'topic' => $topic,
        )
    );
    $mobdb->query('
        UPDATE {db_prefix}boards
        SET numPosts = numPosts + {int:new_posts},
            numTopics = numTopics + 1
        WHERE ID_BOARD = {int:board}',
        array(
            'new_posts' => $topicinfo['replies'] + 1,
            'board' => $newboard['id_board'],
        )
    );
    $mobdb->query('
        UPDATE {db_prefix}boards
        SET numPosts = numPosts - {int:new_posts},
            numTopics = numTopics - 1
        WHERE ID_BOARD = {int:board}',
        array(
            'new_posts' => $topicinfo['replies'] + 1,
            'board' => $board,
        )
    );

    // Update the last messages
    updateLastMessages(array($board, $newboard['id_board']));
}

function mob_error($err_str)
{
    @ob_clean();

    $response = new xmlrpcresp(
        new xmlrpcval(array(
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval(processBody($err_str), 'base64'),
        ),'struct')
    );

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$response->serialize('UTF-8');
    exit;
}

function mobiquo_encode($str, $mode = '')
{
    if (empty($str)) return $str;
    
    static $charset, $charset_89, $charset_AF, $charset_8F, $charset_chr, $charset_html, $support_mb;
    
    if (!isset($charset))
    {
        global $context;
        $charset = $context['character_set'];
        
        include_once('lib/charset.php');
        
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
            $str = @html_entity_decode($str, ENT_QUOTES, $charset);
            
            if($charset == 'ISO-8859-1') {
                $str = utf8_decode($str);
            }
        }
        else
        {
            $str = @html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        }
    }
    
    // html entity convert
    if ($mode == 'to_local')
    {
        $str = str_replace(
            array(
                // latin 1 char
                '&nbsp;',   '&iexcl;',  '&cent;',   '&pound;',  '&curren;', '&yen;',    '&brvbar;', '&sect;',   '&uml;',    '&copy;',   '&ordf;',   '&laquo;',  '&not;',    '&shy;',    '&reg;',    '&macr;',
                '&deg;',    '&plusmn;', '&sup2;',   '&sup3;',   '&acute;',  '&micro;',  '&para;',   '&middot;', '&cedil;',  '&sup1;',   '&ordm;',   '&raquo;',  '&frac14;', '&frac12;', '&frac34;', '&iquest;',
                '&Agrave;', '&Aacute;', '&Acirc;',  '&Atilde;', '&Auml;',   '&Aring;',  '&AElig;',  '&Ccedil;', '&Egrave;', '&Eacute;', '&Ecirc;',  '&Euml;',   '&Igrave;', '&Iacute;', '&Icirc;',  '&Iuml;',
                '&ETH;',    '&Ntilde;', '&Ograve;', '&Oacute;', '&Ocirc;',  '&Otilde;', '&Ouml;',   '&times;',  '&Oslash;', '&Ugrave;', '&Uacute;', '&Ucirc;',  '&Uuml;',   '&Yacute;', '&THORN;',  '&szlig;',
                '&agrave;', '&aacute;', '&acirc;',  '&atilde;', '&auml;',   '&aring;',  '&aelig;',  '&ccedil;', '&egrave;', '&eacute;', '&ecirc;',  '&euml;',   '&igrave;', '&iacute;', '&icirc;',  '&iuml;',
                '&eth;',    '&ntilde;', '&ograve;', '&oacute;', '&ocirc;',  '&otilde;', '&ouml;',   '&divide;', '&oslash;', '&ugrave;', '&uacute;', '&ucirc;',  '&uuml;',   '&yacute;', '&thorn;',  '&yuml;',
                
                // other latin char
                '&Scaron;', '&scaron;', '&OElig;',  '&oelig;',  '&Yuml;',   '&circ;',   '&tilde;',
                '&lsquo;',  '&rsquo;',  '&euro;',   '&rdquo;',  '&bdquo;',  '&ldquo;',  '&sbquo;',  '&hellip;', '&dagger;', '&Dagger;', '&permil;', '&lsaquo;', '&bull;',   '&ndash;',  '&trade;',  '&rsaquo;', '&fnof;',
                
                // greek char
                '&Alpha;',  '&Beta;',   '&Gamma;',  '&Delta;',  '&Epsilon;','&Zeta;',   '&Eta;',    '&Theta;',  '&Iota;',   '&Kappa;',  '&Lambda;', '&Mu;', '&Nu;', '&Xi;', '&Omicron;',
                '&Pi;',     '&Rho;',    '&Sigma;',  '&Tau;',    '&Upsilon;','&Phi;',    '&Chi;',    '&Psi;',    '&Omega;',
                '&alpha;',  '&beta;',   '&gamma;',  '&delta;',  '&epsilon;','&zeta;',   '&eta;',    '&theta;',  '&iota;',   '&kappa;',  '&lambda;', '&mu;', '&nu;', '&xi;', '&omicron;',
                '&pi;',     '&rho;',    '&sigmaf;', '&sigma;',  '&tau;',    '&upsilon;','&phi;',    '&chi;',    '&psi;',    '&omega;',
                // order control char
                '&lrm;',    '&rlm;',
            ),
            array(
                ' ',       '&#161;',  '&#162;',  '&#163;',  '&#164;',  '&#165;',  '&#166;',  '&#167;',  '&#168;',  '&#169;',  '&#170;',  '&#171;',  '&#172;',  '&#173;',  '&#174;',  '&#175;',
                '&#176;',  '&#177;',  '&#178;',  '&#179;',  '&#180;',  '&#181;',  '&#182;',  '&#183;',  '&#184;',  '&#185;',  '&#186;',  '&#187;',  '&#188;',  '&#189;',  '&#190;',  '&#191;',
                '&#192;',  '&#193;',  '&#194;',  '&#195;',  '&#196;',  '&#197;',  '&#198;',  '&#199;',  '&#200;',  '&#201;',  '&#202;',  '&#203;',  '&#204;',  '&#205;',  '&#206;',  '&#207;',
                '&#208;',  '&#209;',  '&#210;',  '&#211;',  '&#212;',  '&#213;',  '&#214;',  '&#215;',  '&#216;',  '&#217;',  '&#218;',  '&#219;',  '&#220;',  '&#221;',  '&#222;',  '&#223;',
                '&#224;',  '&#225;',  '&#226;',  '&#227;',  '&#228;',  '&#229;',  '&#230;',  '&#231;',  '&#232;',  '&#233;',  '&#234;',  '&#235;',  '&#236;',  '&#237;',  '&#238;',  '&#239;',
                '&#240;',  '&#241;',  '&#242;',  '&#243;',  '&#244;',  '&#245;',  '&#246;',  '&#247;',  '&#248;',  '&#249;',  '&#250;',  '&#251;',  '&#252;',  '&#253;',  '&#254;',  '&#255;',
                
                '&#352;',  '&#353;',  '&#338;',  '&#339;',  '&#376;',  '&#710;',  '&#732;',
                '&#8216;', '&#8217;', '&#8364;', '&#8221;', '&#8222;', '&#8220;', '&#8218;', '&#8230;', '&#8224;', '&#8225;', '&#8240;', '&#8249;', '&#8226;', '&#8211;', '&#8482;', '&#8250;', '&#402;',
                
                '&#913;', '&#914;', '&#915;', '&#916;', '&#917;', '&#918;', '&#919;', '&#920;', '&#921;', '&#922;', '&#923;', '&#924;', '&#925;', '&#926;', '&#927;',
                '&#928;', '&#929;', '&#931;', '&#932;', '&#933;', '&#934;', '&#935;', '&#936;', '&#937;',
                '&#945;', '&#946;', '&#947;', '&#948;', '&#949;', '&#950;', '&#951;', '&#952;', '&#953;', '&#954;', '&#955;', '&#956;', '&#957;', '&#958;', '&#959;',
                '&#960;', '&#961;', '&#962;', '&#963;', '&#964;', '&#965;', '&#966;', '&#967;', '&#968;', '&#969;',
                
                '&#8206;', '&#8207;',
            ),
            $str
        );
    }
    
    return $str;
}

if ($func == null)
{
    define(OLD_SYSTEM, true);
    mobiquo_loadfunc();
}

function mobiquo_loadfunc()
{
    global $modSettings, $func, $txt;
    
    // UTF-8 in regular expressions is unsupported on PHP(win) versions < 4.2.3.
    $utf8 = (empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8' && (strpos(strtolower(PHP_OS), 'win') === false || @version_compare(PHP_VERSION, '4.2.3') != -1);

    // Set a list of common functions.
    $ent_list = empty($modSettings['disableEntityCheck']) ? '&(#\d{1,7}|quot|amp|lt|gt|nbsp);' : '&(#021|quot|amp|lt|gt|nbsp);';
    $ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~e\', \'$func[\\\'entity_fix\\\'](\\\'\\2\\\')\', ', ')') : array('', '');

    // Preg_replace can handle complex characters only for higher PHP versions.
    $space_chars = $utf8 ? (@version_compare(PHP_VERSION, '4.3.3') != -1 ? '\x{A0}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}' : pack('C*', 0xC2, 0xA0, 0xE2, 0x80, 0x80) . '-' . pack('C*', 0xE2, 0x80, 0x8F, 0xE2, 0x80, 0x9F, 0xE2, 0x80, 0xAF, 0xE2, 0x80, 0x9F, 0xE3, 0x80, 0x80, 0xEF, 0xBB, 0xBF)) : '\xA0';
    
    $func = array(
        'entity_fix' => create_function('$string', '
            $num = substr($string, 0, 1) === \'x\' ? hexdec(substr($string, 1)) : (int) $string;
            return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) ? \'\' : \'&#\' . $num . \';\';'),
        'substr' => create_function('$string, $start, $length = null', '
            global $func;
            $ent_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\', ' . implode('$string', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            return $length === null ? implode(\'\', array_slice($ent_arr, $start)) : implode(\'\', array_slice($ent_arr, $start, $length));'),
        'strlen' => create_function('$string', '
            global $func;
            return strlen(preg_replace(\'~' . $ent_list . ($utf8 ? '|.~u' : '~') . '\', \'_\', ' . implode('$string', $ent_check) . '));'),
        'strpos' => create_function('$haystack, $needle, $offset = 0', '
            global $func;
            $haystack_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\', ' . implode('$haystack', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $haystack_size = count($haystack_arr);
            if (strlen($needle) === 1)
            {
                $result = array_search($needle, array_slice($haystack_arr, $offset));
                return is_int($result) ? $result + $offset : false;
            }
            else
            {
                $needle_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\',  ' . implode('$needle', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $needle_size = count($needle_arr);

                $result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
                while (is_int($result))
                {
                    $offset += $result;
                    if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
                        return $offset;
                    $result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
                }
                return false;
            }'),
        'htmlspecialchars' => create_function('$string, $quote_style = ENT_COMPAT, $charset = \'ISO-8859-1\'', '
            global $func;
            return ' . strtr($ent_check[0], array('&' => '&amp;'))  . 'htmlspecialchars($string, $quote_style, ' . ($utf8 ? '\'UTF-8\'' : '$charset') . ')' . $ent_check[1] . ';'),
        'htmltrim' => create_function('$string', '
            global $func;
            return preg_replace(\'~^([ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|([ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+$~' . ($utf8 ? 'u' : '') . '\', \'\', ' . implode('$string', $ent_check) . ');'),
        'truncate' => create_function('$string, $length', (empty($modSettings['disableEntityCheck']) ? '
            global $func;
            $string = ' . implode('$string', $ent_check) . ';' : '') . '
            preg_match(\'~^(' . $ent_list . '|.){\' . $func[\'strlen\'](substr($string, 0, $length)) . \'}~'.  ($utf8 ? 'u' : '') . '\', $string, $matches);
            $string = $matches[0];
            while (strlen($string) > $length)
                $string = preg_replace(\'~(' . $ent_list . '|.)$~'.  ($utf8 ? 'u' : '') . '\', \'\', $string);
            return $string;'),
        'strtolower' => $utf8 ? (function_exists('mb_strtolower') ? create_function('$string', '
            return mb_strtolower($string, \'UTF-8\');') : create_function('$string', '
            global $sourcedir;
            require_once($sourcedir . \'/Subs-Charset.php\');
            return utf8_strtolower($string);')) : 'strtolower',
        'strtoupper' => $utf8 ? (function_exists('mb_strtoupper') ? create_function('$string', '
            return mb_strtoupper($string, \'UTF-8\');') : create_function('$string', '
            global $sourcedir;
            require_once($sourcedir . \'/Subs-Charset.php\');
            return utf8_strtoupper($string);')) : 'strtoupper',
        'ucfirst' => $utf8 ? create_function('$string', '
            global $func;
            return $func[\'strtoupper\']($func[\'substr\']($string, 0, 1)) . $func[\'substr\']($string, 1);') : 'ucfirst',
        'ucwords' => $utf8 ? (function_exists('mb_convert_case') ? create_function('$string', '
            return mb_convert_case($string, MB_CASE_TITLE, \'UTF-8\');') : create_function('$string', '
            global $func;
            $words = preg_split(\'~([\s\r\n\t]+)~\', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
            for ($i = 0, $n = count($words); $i < $n; $i += 2)
                $words[$i] = $func[\'ucfirst\']($words[$i]);
            return implode(\'\', $words);')) : 'ucwords',
    );
}
