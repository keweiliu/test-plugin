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

function mobi_parse_requrest()
{
    global $request_method, $request_params, $params_num;
    
    $ver = phpversion();
    if ($ver[0] >= 5) {
        $data = file_get_contents('php://input');
    } else {
        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
    }
    
    if (count($_SERVER) == 0)
    {
        $r = new xmlrpcresp('', 15, 'XML-RPC: '.__METHOD__.': cannot parse request headers as $_SERVER is not populated');
        echo $r->serialize('UTF-8');
        exit;
    }
    
    if(isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
        $content_encoding = str_replace('x-', '', $_SERVER['HTTP_CONTENT_ENCODING']);
    } else {
        $content_encoding = '';
    }
    
    if($content_encoding != '' && strlen($data)) {
        if($content_encoding == 'deflate' || $content_encoding == 'gzip') {
            // if decoding works, use it. else assume data wasn't gzencoded
            if(function_exists('gzinflate')) {
                if ($content_encoding == 'deflate' && $degzdata = @gzuncompress($data)) {
                    $data = $degzdata;
                } elseif ($degzdata = @gzinflate(substr($data, 10))) {
                    $data = $degzdata;
                }
            } else {
                $r = new xmlrpcresp('', 106, 'Received from client compressed HTTP request and cannot decompress');
                echo $r->serialize('UTF-8');
                exit;
            }
        }
    }
    
    $parsers = php_xmlrpc_decode_xml($data);
    $request_method = $parsers->methodname;
    $request_params = php_xmlrpc_decode(new xmlrpcval($parsers->params, 'array'));
    $params_num = count($request_params);
}

function xmlrpc_shutdown()
{
    if (function_exists('error_get_last'))
    {
        $error = error_get_last();
    
        if(!empty($error)){
            switch($error['type']){
                case E_ERROR:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                case E_PARSE:
                    $xmlrpcresp = xmlresperror("Server error occurred: '{$error['message']} (".basename($error['file']).":{$error['line']})'");
                    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $xmlrpcresp->serialize('UTF-8');
                    break;
            }
        }
    }
}

function xmlresperror($error_message)
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'result_text'   => new xmlrpcval(mobiquo_encode($error_message), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($result);
}

function get_root_dir()
{
    $dir = '../';
    
    if (!empty($_SERVER['SCRIPT_FILENAME']))
    {
        $dir = dirname($_SERVER['SCRIPT_FILENAME']);
        if (!file_exists($dir.'/global.php'))
            $dir = dirname($dir);
        
        $dir = $dir.'/';
    }
    
    return $dir;
}

function get_ignore_ids($uid = 0)
{
	global $db;
	
	$userids = array();
	if(empty($uid)) return $userids;
	$users_result = $db->query_read_slave("
			SELECT user.*, userlist.type
			FROM " . TABLE_PREFIX . "userlist AS userlist
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = userlist.relationid)
			WHERE userlist.userid = " . $uid . " AND userlist.type = 'ignore'
			ORDER BY user.username
		");
	while ($user = $db->fetch_array($users_result))
	{
		$userids[] = $user['userid'];
	}
	return $userids;
}

function process_page($start, $end)
{
    $start = intval($start);
    //$end = intval($end);
    $start = empty($start) ? 0 : max($start, 0);
    $end = ((empty($end) && $end !== 0) || $end < $start) ? ($start + 19) : max($end, $start);
    if ($end - $start >= 50) {
        $end = $start + 49;
    }
    $limit = $end - $start + 1;
    $page = intval($start/$limit) + 1;
    
    return array($start, $limit, $page);
}

function parameter_to_local()
{
    global $vbulletin;
    
    if (isset($vbulletin->GPC['searchuser']))
        $vbulletin->GPC['searchuser'] = mobiquo_encode($vbulletin->GPC['searchuser'], 'to_local');
    
    if (isset($vbulletin->GPC['query']))
        $vbulletin->GPC['query'] = mobiquo_encode($vbulletin->GPC['query'], 'to_local');
}

function get_userid_by_name($name)
{
    global $db;
    
    $username = htmlspecialchars_uni($name);
    $query = "SELECT userid
          FROM " . TABLE_PREFIX . "user
          WHERE username = '" . $db->escape_string($username) . "'" ;
    
    require_once( DIR . '/includes/functions_bigthree.php');
    
    $coventry = fetch_coventry();

    $users = $db->query_read_slave($query);
    if ($db->num_rows($users))
    {
        $user = $db->fetch_array($users);
        return (in_array($user['userid'], $coventry) AND !can_moderate()) ? 0 : $user['userid'];
    }
    else
    {
        return 0;
    }
}

function get_user_by_NameorEmail($name)
{
    global $db;
    
    $name = htmlspecialchars_uni($name);

    $query = "SELECT userid, usergroupid, membergroupids, infractiongroupids, username, password, salt, email, options
      FROM " . TABLE_PREFIX . "user
      WHERE ". (preg_match('/@/',$name) ? "email" : "username")." = '" . $db->escape_string($name) . "'" ;

    require_once( DIR . '/includes/functions_bigthree.php');
    
    $coventry = fetch_coventry();

    $users = $db->query_read_slave($query);
    if ($db->num_rows($users))
    {
        $user = $db->fetch_array($users);
        return (in_array($user['userid'], $coventry) AND !can_moderate()) ? 0 : $user;
    }
    else
    {
        return 0;
    }
}
 
function get_usertype_by_name($name)
{
    global $vbulletin;
    
    $vbulletin->GPC['userid'] = get_userid_by_name($name);
    $userinfo = mobiquo_verify_id('user', $vbulletin->GPC['userid'], 0, 1);
    if($userinfo['usergroupid'] == 6)
        return 'admin';
    else if(!($vbulletin->usergroupcache[$userinfo['usergroupid']]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'])){
        return 'banned';
    }
    else if($userinfo['usergroupid'] == 7 ||$userinfo['usergroupid'] == 5 )
        return 'mod';
    else if($userinfo['usergroupid'] == 4)
        return 'unapproved';
    else if($userinfo['usergroupid'] == 3)
        return 'inactive';
    else if($userinfo['usergroupid'] != 1 AND $userinfo['usergroupid'] != 3 AND $userinfo['usergroupid'] != 4)
        return 'normal';
    return ' ';
}

function mobiquo_chop($string)
{
    $string = preg_replace('/\[video=(.*?)\](.*?)\[\/video\]/si','',$string);
    $string = preg_replace('/\[attach=(.*?)\](.*?)\[\/attach\]/si','',$string);
    $string = preg_replace('/<br \/\>/', ' ', $string);
    $string = preg_replace('/\n|\r|\t/', ' ', $string);
    $string = strip_quotes($string);
    $string = trim($string);
    $string = preg_replace('/ +/', ' ', $string);

    $string = fetch_censored_text(fetch_trimmed_title(strip_bbcode($string, false, true), 200));

    return $string;
}

function return_fault($errorString = '')
{
    global $vbulletin;
    
    if (is_array($errorString))
        $errorString = $errorString[1];
    elseif (empty($errorString))
    {
        if ($vbulletin->userinfo['userid']) {
            $errorString = 'You may not have permission to do this action.';
        } else {
            $errorString = 'You are not logged in or you do not have permission to do this action.';
        }
    }

    @header('Mobiquo_is_login:'.(isset($vbulletin) && $vbulletin->userinfo['userid'] != 0 ? 'true' : 'false'));
    @header('Content-Type: text/xml');
    

    if(!$vbulletin->options['bbactive'])
    {
        $response_array = array(
            'result'        => new xmlrpcval(true, 'boolean'),
            'result_text'   => new xmlrpcval(mobiquo_encode(strip_tags($errorString)), 'base64'),
        );
        require_once(CWD1.'/config/config.php');
        $mobiquo_config = new mobiquo_config();
        $tt_config = $mobiquo_config->get_config();
        foreach($tt_config as $key => $value){
            if(!$response_array[$key] && !is_array($value)){
                $response_array[$key] = new xmlrpcval(mobiquo_encode($value), 'string');
            }
        }
        $response_array['is_open'] = new xmlrpcval(false, 'boolean');
    }
    else
    {
        $response_array = array(
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval(mobiquo_encode(strip_tags($errorString)), 'base64'),
        );
    }
    $response = new xmlrpcresp(
        new xmlrpcval($response_array, 'struct')
    );
    
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$response->serialize('UTF-8');
    exit;
}

function return_mod_fault($errorString = '', $mod = true)
{
    global $vbulletin;
    
    if (is_array($errorString))
        $errorString = $errorString[1];
    elseif (empty($errorString))
    {
        if ($vbulletin->userinfo['userid']) {
            $errorString = 'You may not have permission to do this action.';
        } else {
            $errorString = 'You are not logged in or you do not have permission to do this action.';
        }
    }
    
    $response = new xmlrpcresp(new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'is_login_mod'  => new xmlrpcval($mod, 'boolean'),
        'result_text'   => new xmlrpcval(mobiquo_encode(strip_tags($errorString)), 'base64'),
    ), 'struct'));
    
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$response->serialize('UTF-8');
    exit;
}

function post_content_clean($str)
{
    global $html_content, $vbulletin;

    // custom content replacement.
    if(isset($vbulletin->options['tp_custom_content_replacement']) && !empty($vbulletin->options['tp_custom_content_replacement']))
    {
        $custom_replacement = $vbulletin->options['tp_custom_content_replacement'];
        if(!empty($custom_replacement))
        {
            $replace_arr = explode("\n", $custom_replacement);
            foreach ($replace_arr as $replace)
            {
                preg_match('/^\s*(\'|")((\#|\/|\!).+\3[ismexuADUX]*?)\1\s*,\s*(\'|")(.*?)\4\s*$/', $replace,$matches);
                if(count($matches) == 6)
                {
                    $temp_post = $str;
                    $str = @preg_replace($matches[2], $matches[5], $str);
                    if(empty($str))
                    {
                        $str = $temp_post;
                    }
                }
            }
        }
    }

    $str = preg_replace('/\[noparse\](.*?)\[\/noparse\]/sie', "'[tp_noparse]'.base64_encode('$1').'[/tp_noparse]'", $str);
    $bbcode_array = array('SIZE','FONT','LEFT','RIGHT','CENTER','NOPARSE','ATTACH','BUG','SCREENCAST','SUB','SUP');
    if (!$html_content)
        $bbcode_array = array_merge($bbcode_array, array('B','I','U','COLOR','INDENT','HIGHLIGHT'));
    
    foreach($bbcode_array as $bbcode) {
        if($bbcode == 'I' or $bbcode == 'U' or $bbcode == 'B' or $bbcode == 'SUB' or $bbcode == 'SUP'){
            $str =preg_replace("/\[\/?$bbcode\]/siU", '', $str);
        } else{
            $str =preg_replace("/\[\/?$bbcode.*\]/siU", '', $str);
        }
    }
    
    $str = preg_replace('#<iframe .*?src="(https?|//)(.*?)" .*?>.*?</iframe>#si', '[url=$2]$2[/url]', $str);
    
    // transform vb4 video to url
    $str = preg_replace('#\[video=(youtube|youtube_share|vimeo|dailymotion|metacafe|google|facebook);[^\]]*\]([^\[]+)\[/video\]#siU', "[URL=$2]$1 video[/URL]", $str);
    
    $str = preg_replace('#\[url\]([^\[]+\.(jpeg|jpg|png|gif))\[/url\]#siU', "[IMG]$1[/IMG]", $str);
    $str = preg_replace('#\[(featureimg|shot|thumb)(=[^\]]+)?\]([^\[]+)\[/\1\]#siU', "[IMG]$3[/IMG]", $str);
    
    $str = preg_replace('#\[vimeo\]([^\[]+)\[/vimeo\]#siU', "[URL]http://vimeo.com/$1[/URL]", $str);
    $str = preg_replace('#\[(youtube|yt)\]([-\w]+)\[/\1\]#siU', "[URL=http://www.youtube.com/watch?v=$2]YouTube Video[/URL]", $str);
    $str = preg_replace('#\[(youtube|yt)\]([^\[]*)\[/\1\]#siU', "[URL=$2]YouTube Video[/URL]", $str);
    $str = preg_replace('#\[(video|vedio|ame|email)([^\]]*)\]([^\[]+)\[/\1\]#siU', "[URL$2]$3[/URL]", $str);

    $str = preg_replace('/\[url\](.*?)\[\/url\]/sei', "'[url]'.trim('$1').'[/url]'", $str);
    $str = preg_replace('/\[timg\](.*?)\[\/timg\]/si', '[IMG]$1[/IMG]', $str);

    //$str = clean_quote($str);
    
    $str = preg_replace('/\[quote=(.*?)\]/sei', "process_quote_name('$1')", $str);
    $str = preg_replace('/(\[quote\])\s*/si', '$1', $str);
    $str = preg_replace('/\s*(\[\/quote\])/siU', '$1', $str);

    $str = preg_replace('/\[(CODE|PHP|HTML|code|php|html)\](.*?)\[\/\1\]/si','[quote]$2[/quote]',$str);
    $str = preg_replace('/\[(highlight|HIGHLIGHT)\](.*?)\[\/\1\]/si','[TP_LIGHT]$2[/TP_LIGHT]',$str);
    $str = preg_replace('/\[(hr|HR)\](.*?)\[\/\1\]/si',"\n--------------------------------------------\n$2",$str);
    $str = preg_replace('/<span style="color:Red">(.*?)<\/span>/','<font color="red">$1</font>', $str);
    $str = process_list_tag($str);
    $str = process_table_tag($str);

    if ($html_content)
    {
        global $color_names;
        if(!empty($color_names))
        $str = str_replace(array_keys($color_names), array_values($color_names), $str);
    }
    else
    {
        $str = htmlspecialchars_uni($str);
    }
    $str = preg_replace('/\[COLOR=\#.*?\](.*?)\[\/COLOR\]/si', '$1', $str);

    return trim($str);
}

function process_quote_name($quote_option)
{
    global $vbulletin;
    
    $str = '[QUOTE]';
    if (preg_match('/^(.+)(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});\s*(\d+)\s*$/U', $quote_option, $match))
    {
        $str .= '[url='.$vbulletin->options['bburl'].'/showthread.php?p='.$match[2].']'
                . strip_tags(vB_Template_Runtime::parsePhrase("originally_posted_by_x", $match[1]))
                . "[/url]\n";
    }
    
    return $str;
}

function process_list_tag($str)
{
    $contents = preg_split('#(\[LIST=[^\]]*?\]|\[/?LIST\])#siU', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    
    $result = '';
    $status = 'out';
    foreach($contents as $content)
    {
        if ($status == 'out')
        {
            if ($content == '[LIST]')
            {
                $status = 'inlist';
            } elseif (strpos($content, '[LIST=') !== false)
            {
                $status = 'inorder';
            } else {
                $result .= $content;
            }
        } elseif ($status == 'inlist')
        {
            if ($content == '[/LIST]')
            {
                $status = 'out';
            } else
            {
                $result .= str_replace('[*]', '  * ', ltrim($content));
            }
        } elseif ($status == 'inorder')
        {
            if ($content == '[/LIST]')
            {
                $status = 'out';
            } else
            {
                $index = 1;
                $result .= preg_replace('/\[\*\]/sie', "'  '.\$index++.'. '", ltrim($content));
            }
        }
    }
    
    return $result;
}

function process_table_tag($str)
{
    $result = '';
    $contents = preg_split('#(\[table=[^\]]*?\]|\[/?table\])#siU', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    foreach($contents as $content)
    {
        $useful = '';
        if(preg_match('/\[tr\]/si', $content,$matches))
        {
            $useful = preg_replace('/\r|\n/', '', $content);
            $useful = preg_replace('/\[(tr)(=[^\]]*?)?\](.*?)\[\/\1\]/si',"$3\n",$useful);
            $useful = preg_replace('/\[(td)(=[^\]]*?)?\](.*?)\[\/\1\]/si',"  $3  ",$useful);
        }
        else if(preg_match('/\[table=[^\]]*?\]/si', $content, $match)|| preg_match('/\[\/table\]/si', $content, $match))
        {
            $useful = '';
        }
        else
        {
            $useful = $content;
        }
        $result .= $useful;
    }
    return $result;
}

function clean_quote($text)
{
    $lowertext = strtolower($text);

    // find all [quote tags
    $start_pos = array();
    $curpos = 0;
    do
    {
        $pos = strpos($lowertext, '[quote', $curpos);
        if ($pos !== false AND ($lowertext[$pos + 6] == '=' OR $lowertext[$pos + 6] == ']'))
        {
            $start_pos["$pos"] = 'start';
        }

        $curpos = $pos + 6;
    }
    while ($pos !== false);

    if (sizeof($start_pos) == 0)
    {
        return $text;
    }

    // find all [/quote] tags
    $end_pos = array();
    $curpos = 0;
    do
    {
        $pos = strpos($lowertext, '[/quote]', $curpos);
        if ($pos !== false)
        {
            $end_pos["$pos"] = 'end';
            $curpos = $pos + 8;
        }
    }
    while ($pos !== false);

    if (sizeof($end_pos) == 0)
    {
        return $text;
    }

    // merge them together and sort based on position in string
    $pos_list = $start_pos + $end_pos;
    ksort($pos_list);

    do
    {
        // build a stack that represents when a quote tag is opened
        // and add non-quote text to the new string
        $stack = array();
        $newtext = '';
        $substr_pos = 0;
        foreach ($pos_list AS $pos => $type)
        {

            $stacksize = sizeof($stack);
            if ($type == 'start')
            {
                //
                // empty stack, so add from the last close tag or the beginning of the string
                    
                if ($stacksize == 0 or $stacksize ==1)
                {
                    $newtext .= substr($text, $substr_pos, $pos - $substr_pos);
                    $substr_pos = $pos ;


                }
                    
                array_push($stack, $pos);
            }
            else
            {
                // pop off the latest opened tag
                if ($stacksize >1)
                {
                    $substr_pos = $pos + 8;
                }
                array_pop($stack);
            }
        }

        $newtext .= substr($text, $substr_pos);


        // check to see if there's a stack remaining, remove those points
        // as key points, and repeat. Allows emulation of a non-greedy-type
        // recursion.
        if ($stack)
        {
            foreach ($stack AS $pos)
            {
                unset($pos_list["$pos"]);
            }
        }
    }
    while ($stack);
    return $newtext;
}

function mobiquo_time_encode($timet, $utc = 0)
{
    global $vbulletin;
    
    $hourdiff = $vbulletin->options['hourdiff'];
    $timezone = $vbulletin->userinfo['tzoffset'];
    $timet = $timet - $hourdiff;
    
    $timezone = preg_replace('/\+/','',$timezone);
    if(!$utc)
    {
        $t=strftime("%Y%m%dT%H:%M:%S", $timet);
        if($timezone >= 0){
            $timezone = sprintf("%02d",$timezone);
            $timezone = '+'.$timezone;
        }
        else{
            $timezone = $timezone * (-1);
            $timezone = sprintf("%02d",$timezone);
            $timezone = '-'.$timezone;
        }
        $t = $t.$timezone.':00';
    }
    else
    {
        if(function_exists('gmstrftime'))
        {
            $t=gmstrftime("%Y%m%dT%H:%M:%S", $timet);
        }
        else
        {
            $t=strftime("%Y%m%dT%H:%M:%S", $timet-date('Z'));
        }
    }
    
    return $t;
}

function get_icon_real_url($iconurl)
{
    global $vbulletin;
    
    $real_url = $iconurl;
    
    if( preg_match('/^http/', $iconurl)){
        $real_url = unhtmlspecialchars($iconurl);
    }
    else{
        if(preg_match('/^\//', $iconurl)){
            $base_url = preg_replace("/http:\/\//siU", '', $vbulletin->options[homeurl]);
            $path = explode('/', $base_url);
            $host = $path[0];
            unset($path);
            $base_host = "http://".$host;
            $real_url = $base_host.unhtmlspecialchars($iconurl);
        } else {
            $real_url = $vbulletin->options['bburl'].'/'.unhtmlspecialchars($iconurl);
        }
    }
    
    if(preg_match('/\/\//', $real_url))
        if(preg_match('/^\/\//', $iconurl))
            $real_url = 'http:'.unhtmlspecialchars($iconurl);

    return $real_url;
}

function get_forbidden_forums()
{
    global $vbulletin;

    $unwanted_forums = array();

    foreach($vbulletin->forumcache AS $forum)
    {
        $premissions = fetch_permissions($forum['forumid']);

        if (!($premissions & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forum['options'] & 16384) OR !($premissions & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
        {
            $unwanted_forums[] = $forum['forumid'];
        }
    }

    return $unwanted_forums;
}

function mobiquo_verify_id($idname, &$id, $alert = true, $selall = false, $options = 0)
{
    // verifies an id number and returns a correct one if it can be found
    // returns 0 if none found
    global $vbulletin, $threadcache, $vbphrase;

    if (empty($vbphrase["$idname"]))
    {
        $vbphrase["$idname"] = $idname;
    }
    
    $id = intval($id);
    $fault_string = fetch_error('invalidid', $vbphrase[$idname]);
    
    if (empty($id))
    {
        if ($alert)
        {
            return_fault($fault_string);
        }
        else
        {
            return 0;
        }
    }

    $selid = ($selall ? '*' : $idname . 'id');

    switch ($idname)
    {
        case 'thread':
            if(!$vbulletin->options['threadsubscribed'])
            {
                $vbulletin->options['threadsubscribed'] = 1;
                $hard_changed = true;
            }
            
        case 'forum':
        case 'post':
            $function = 'fetch_' . $idname . 'info';
            $tempcache = $function($id);
            if (!$tempcache AND $alert)
            {
                return_fault($fault_string);
            }
            $ret_rs = ($selall ? $tempcache : $tempcache[$idname . 'id']);
            if(isset($hard_changed) && $hard_changed) $vbulletin->options['threadsubscribed'] = 0; 
            return $ret_rs;

        case 'user':
            $tempcache = fetch_userinfo($id, $options);
            if (!$tempcache AND $alert)
            {
                return array();
            }
            return ($selall ? $tempcache : $tempcache[$idname . 'id']);

        default:
            if (!$check = $vbulletin->db->query_first("SELECT $selid FROM " . TABLE_PREFIX . "$idname WHERE $idname" . "id = $id"))
            {
                if ($alert)
                {
                    return_fault($fault_string);
                }
                return ($selall ? array() : 0);
            }
            else
            {
                return ($selall ? $check : $check["$selid"]);
            }
    }
}

function mobiquo_encode($str, $mode = '', $strip_tags = true)
{
    if ($strip_tags && empty($mode)) 
        $str = strip_tags($str);
    
    if (empty($str)) return $str;
    if (!class_exists('vB_Template_Runtime')) return $str;
    
    static $charset, $charset_89, $charset_AF, $charset_8F, $charset_chr, $charset_html, $support_mb, $charset_entity;
    
    if (!isset($charset))
    {
        $charset = trim(vB_Template_Runtime::fetchStyleVar('charset'));
        
        include_once(CWD1.'/include/charset.php');
        
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
/*
            $str = @htmlentities($str, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8');
            if(!isset($special_chars))
                include_once(CWD1.'/include/charset.php');
            if(isset($special_chars))
                foreach($special_chars as $find => $replace)
                    $str = str_replace($find, $replace, $str);
            $str = @iconv('UTF-8', $charset.'//IGNORE', $str);
            $str = str_replace($charset_html[$charset], $charset_chr, $str);
*/
            $str = utf8ToEntities($str);
            $str = @html_entity_decode($str, ENT_QUOTES, $charset);
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
    
    return remove_unknown_char($str);
}

function utf8ToEntities($string)
{ 
/*
* @see http://en.wikipedia.org/wiki/UTF-8#Description
* @link http://community.invisionpower.com/tracker/issue-23681-possible-addition/
*/
# Four-byte chars
$string = preg_replace( "/([\360-\364])([\200-\277])([\200-\277])([\200-\277])/e",  "'&#' . ( ( ord('\\1') - 240 ) * 262144 + ( ord('\\2') - 128 ) * 4096 + ( ord('\\3') - 128 ) * 64 + ( ord('\\4') - 128 ) ) . ';'", $string );

/* Three byte chars */
$string = preg_replace( "/([\340-\357])([\200-\277])([\200-\277])/e", "'&#'.((ord('\\1')-224)*4096 + (ord('\\2')-128)*64 + (ord('\\3')-128)).';'", $string ); 

/* Two byte chars */
$string = preg_replace("/([\300-\337])([\200-\277])/e", "'&#'.((ord('\\1')-192)*64+(ord('\\2')-128)).';'", $string); 

return $string; 
}

function mobiquo_get_user_icon($userid)
{
    global $vbulletin;
    
    static $useravatar;
    
    if(!$vbulletin->options['avatarenabled'] || empty($userid)) return '';
    if (isset($useravatar[$userid])) return $useravatar[$userid];
    
    $userinfo = fetch_userinfo($userid, FETCH_USERINFO_AVATAR);
    
    if(!is_array($userinfo) || empty($userinfo)) $userinfo = array();
    
    fetch_avatar_from_userinfo($userinfo, true, false);

    $useravatar[$userid] = $userinfo['avatarurl'] ? get_icon_real_url($userinfo['avatarurl']) : '';
    
    return $useravatar[$userid];
}

function get_vb_message($tempname)
{
    if (!function_exists('fetch_phrase'))
    {
        require_once(DIR . '/includes/functions_misc.php');
    }
    
    $phrase =fetch_phrase('redirect_friendspending', 'frontredirect', 'redirect_', true, false, $languageid, false);

    return $phrase;
}

function get_post_from_id($postid)
{
    global $vbulletin, $db, $forumperms, $permissions, $html_content, $threadinfo, $foruminfo;

    $post = $db->query_first_slave("
        SELECT
            post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
            user.*, userfield.*, usertextfield.*,
            " . iif($foruminfo['allowicons'], 'icon.title as icontitle, icon.iconpath, ') . "
            IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid,
            " . iif($vbulletin->options['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight, ') . "
            " . ((can_moderate($threadinfo['forumid'], 'canmoderateposts') OR can_moderate($threadinfo['forumid'], 'candeleteposts')) ? 'spamlog.postid AS spamlog_postid, ' : '') . "
            editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline, editlog.reason AS edit_reason, editlog.hashistory,
            postparsed.pagetext_html, postparsed.hasimages,
            sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
            sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight
            " . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
            $hook_query_fields
        FROM " . TABLE_PREFIX . "post AS post
        LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
        LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
        LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
        " . iif($foruminfo['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
        " . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
        " . ((can_moderate($threadinfo['forumid'], 'canmoderateposts') OR can_moderate($threadinfo['forumid'], 'candeleteposts')) ? "LEFT JOIN " . TABLE_PREFIX . "spamlog AS spamlog ON(spamlog.postid = post.postid)" : '') . "
        LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
        LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
        LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
        LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
        $hook_query_joins
        WHERE post.postid = $postid
    ");

    // Tachy goes to coventry
    if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
    {
        // do not show post if part of a thread from a user in Coventry and bbuser is not mod
        eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
    }
    if (in_coventry($post['userid']) AND !can_moderate($threadinfo['forumid']))
    {
        // do not show post if posted by a user in Coventry and bbuser is not mod
        eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
    }

    $postbit_factory = new vB_Postbit_Factory();
    $postbit_factory->registry =& $vbulletin;
    $postbit_factory->forum =& $foruminfo;
    $postbit_factory->thread =& $threadinfo;
    $postbit_factory->cache = array();
    $postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

    if ($tachyuser = in_coventry($post['userid']) AND !can_moderate($thread['forumid'])) {
        continue;
    }

    if ($post['visible'] == 2)
    {
        $fetchtype = 'post_deleted';
    }
    else if ($tachyuser)
    {
        $fetchtype = 'post_global_ignore';
    }
    else if ($ignore["$post[userid]"])
    {
        $fetchtype = 'post_ignore';
    }
    else 
    {
        $fetchtype = 'post';
    }

    $postbit_obj =& $postbit_factory->fetch_postbit($fetchtype);
    
    // load attachments
    $post['attachments'] = array();
    if ($post['attach'])
    {
        require_once(DIR . '/packages/vbattach/attach.php');
        $attach = new vB_Attach_Display_Content($vbulletin, 'vBForum_Post');
        $postattach = $attach->fetch_postattach(0, array($post['postid']));
        $post['attachments'] =& $postattach[$post['postid']];
    }
    
    
    $parsed_postcache = array('text' => '', 'images' => 1, 'skip' => false);

    $post['pagetext'] = mobiquo_handle_bbcode_attach($post['pagetext'],true, $post);
    $mobiquo_attachments = $post['attachments'];
    $postbits .= $postbit_obj->construct_postbit($post);
    
    if ($fetchtype == 'post_deleted')
    {
        $delete_content = '';
        if ($post['title'] OR $show['messageicon'] OR $show['inlinemod']) {
            $delete_content = vB_Template_Runtime::parsePhrase("message_deleted_by_x", vB_Template_Runtime::linkBuild("member", $post, NULL, 'del_userid', 'del_username'), $post['del_username']);
        }
        
        if ($post['del_reason']) {
            $delete_content .= "\n".vB_Template_Runtime::parsePhrase("reason")."\n".$post['del_reason'];
        }
        
        if ($show['viewpost'])
            $post['pagetext'] = strip_tags($delete_content)."\n"."[spoiler]{$post[pagetext]}[/spoiler]";
        else
            $post['pagetext'] = strip_tags($delete_content);
    }

    $return_attachments = array();

    if(is_array($mobiquo_attachments))
    {
        foreach($mobiquo_attachments as $attach)
        {
            $attachment_url = $attachment_thumbnail_url = "";
            preg_match_all('/href=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\"/',unhtmlspecialchars($post[imageattachmentlinks]), $image_attachment_matchs);
            preg_match_all('/href=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\"/',unhtmlspecialchars($post[otherattachments]), $other_attachment_matchs);
            preg_match_all('/href=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\".+img.+?src=\"(.+attachmentid='.$attach[attachmentid].'.+?)\"/s',unhtmlspecialchars($post[thumbnailattachments]), $thumbnail_attachment_matchs);
            preg_match_all('/src=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\"/',unhtmlspecialchars($post[imageattachments]), $small_image_attachment_matchs);
    
            if (in_array(strtolower(pathinfo($attach['filename'], PATHINFO_EXTENSION)), array('gif', 'jpg', 'jpeg', 'jpe', 'png', 'bmp'))) {
                $type = "image";
            } else {
                $type = "other";
            }
            
            if($image_attachment_matchs[1][0]) {
                $type = "image";
                $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$image_attachment_matchs[1][0];
            }
            if($other_attachment_matchs[1][0]){
                $type = "other";
                $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$other_attachment_matchs[1][0];
            }
            if($small_image_attachment_matchs[1][0]) {
                $type = "image";
                $attachment_thumbnail_url= $GLOBALS[vbulletin]->options[bburl].'/'.$small_image_attachment_matchs[1][0];
                $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$small_image_attachment_matchs[1][0];
            }
            if($thumbnail_attachment_matchs[1][0]){
                $type = "image";
    
                $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$thumbnail_attachment_matchs[1][0];
                $attachment_thumbnail_url = $GLOBALS[vbulletin]->options[bburl].'/'.$thumbnail_attachment_matchs[2][0];
            }
            
            if(empty($attachment_url)){
                $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'."attachment.php?attachmentid=".$attach['attachmentid'];
            }
    
            $return_attachment = new xmlrpcval(array(
                'filename'      => new xmlrpcval($attach['filename'], 'base64'),
                'filesize'      => new xmlrpcval($attach['filesize'], 'int'),
                'url'           => new xmlrpcval(unhtmlspecialchars($attachment_url), 'string'),
                'thumbnail_url' => new xmlrpcval(unhtmlspecialchars($attachment_thumbnail_url), 'string'),
                'content_type' => new xmlrpcval($type, 'string')
            ), 'struct');
            
            array_push($return_attachments, $return_attachment);
        }
    }

    if ($foruminfo['allowhtml'])
    {
        $post['pagetext']  = str_replace("\n", '<br />', $post['pagetext']);
        require_once(DIR . '/includes/class_wysiwygparser.php');
        $html_parser = new vB_WysiwygHtmlParser($vbulletin);
        
        if (method_exists($html_parser, 'parse_wysiwyg_html_to_bbcode')) {
            $post['pagetext'] = $html_parser->parse_wysiwyg_html_to_bbcode($post['pagetext'], $foruminfo['allowhtml']);
        } else if (method_exists($html_parser, 'parse')) {
            $post['pagetext'] = $html_parser->parse($post['pagetext'], $foruminfo['allowhtml']);
        }
    }
    
    if($html_content)
    {
        $a = fetch_tag_list();
        unset($a['option']['quote']);
        unset($a['no_option']['quote']);
        unset($a['option']['url']);
        unset($a['no_option']['url']);

        $vbulletin->options['wordwrap'] = 0;
        
        $post_content = post_content_clean($post['pagetext']);
        $post_content = preg_replace("/\[\/img\]/siU", '[/img1]', $post_content);
        $bbcode_parser = new vB_BbCodeParser($vbulletin, $a, false);
        $post_content = $bbcode_parser->parse( $post_content, $thread['forumid'], false);
        $post_content = preg_replace("/\[\/img1\]/siU", '[/IMG]', $post_content);

        $post_content = htmlspecialchars_uni($post_content);
    }
    else
    {
        $post_content = post_content_clean($post['pagetext']);
    }
    
    // add spoiler for user ignored post
    if ($fetchtype == 'post_ignore')
    {
        $post_content = strip_tags(construct_phrase($vbphrase['message_hidden_x_on_ignore_list'], $post['postusername']))
                        . "[spoiler]{$post_content}[/spoiler]";
    }
    
    $post_content = mobiquo_encode($post_content, '', $html_content);
    
    if(SHORTENQUOTE == 1 && preg_match('/^(.*\[quote\])(.+)(\[\/quote\].*)$/si', $post_content))
    {
        $new_content = "";
        $segments = preg_split('/(\[quote\].+\[\/quote\])/isU', $post_content,-1, PREG_SPLIT_DELIM_CAPTURE);

        foreach($segments as $segment)
        {
            $short_quote = $segment;
            if(preg_match('/^(\[quote\])(.+)(\[\/quote\])$/si', $segment, $quote_matches)){
                if(function_exists('mb_strlen') && function_exists('mb_substr')){
                    if(mb_strlen($quote_matches[2], 'UTF-8') > 170){
                        $short_quote = $quote_matches[1].mb_substr($quote_matches[2],0,150, 'UTF-8').$quote_matches[3];
                    }
                }
                else{
                    if(strlen($quote_matches[2]) > 170){
                        $short_quote = $quote_matches[1].substr($quote_matches[2],0,150).$quote_matches[3];
                    }
                }
                $new_content .= $short_quote;
            } else {
                $new_content .= $segment;
            }
        }

        $post_content = $new_content;
    }
    $mobiquo_can_edit = false;
    if(isset($post['editlink']) AND strlen($post['editlink']) > 0){
        $mobiquo_can_edit = true;
    }

    $mobiquo_user_online = (fetch_online_status($post, false)) ? true : false;

    $return_post = array(
        'result'            => new xmlrpcval(true, 'boolean'),
        'stat'              => new xmlrpcval($post['visible'] || can_moderate($foruminfo['forumid'], 'canmoderateposts') ? 0 : 1, 'int'),
        'forum_id'          => new xmlrpcval($foruminfo['forumid'], 'string'),
        'forum_name'        => new xmlrpcval(mobiquo_encode($foruminfo['title']), 'base64'),
        'topic_id'          => new xmlrpcval($post['threadid'], 'string'),
        'topic_title'       => new xmlrpcval(mobiquo_encode($threadinfo['title']), 'base64'),
        'post_id'           => new xmlrpcval($post['postid'], 'string'),
        'post_title'        => new xmlrpcval(mobiquo_encode($post['title']), 'base64'),
        'post_content'      => new xmlrpcval($post_content, 'base64'),
        'post_author_id'    => new xmlrpcval($post['userid'], 'string'),
        'post_author_name'  => new xmlrpcval(mobiquo_encode($post['postusername']), 'base64'),
        'post_time'         => new xmlrpcval(mobiquo_time_encode($post['dateline']), 'dateTime.iso8601'),
        'timestamp'         => new xmlrpcval($post['dateline'], 'string'),
        'attachments'       => new xmlrpcval($return_attachments, 'array'),
        
        'allow_smilies'     => new xmlrpcval($post['allowsmilie'], 'boolean'),
    );
    
    if ($mobiquo_can_edit)    $return_post['can_edit']      = new xmlrpcval(true, 'boolean');
    if ($mobiquo_user_online) $return_post['is_online']     = new xmlrpcval(true, 'boolean');
    if ($show['deleteposts']) $return_post['can_delete']    = new xmlrpcval(true, 'boolean');
    
    $return_post['icon_url'] = new xmlrpcval('', 'string');
    if($post['avatarurl']){
        $return_post['icon_url']=new xmlrpcval(get_icon_real_url($post['avatarurl']), 'string');
    }
    
    $return_post['attachment_authority'] = new xmlrpcval(0, 'int');
    if(!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment'])) {
        $return_post['attachment_authority'] = new xmlrpcval(4, 'int');
    }
    
    return $return_post;
}

function remove_unknown_char($str)
{
    for ($i = 1; $i < 32; $i++)
    {
        if (in_array($i, array(10, 13))) continue;
        $str = str_replace(chr($i), '', $str);
    }
    
    return $str;
}

function get_forum_icon_name($forumid)
{
    global $vbulletin, $lastpostarray;
    
    $forum = $vbulletin->forumcache[$forumid];
    $lastpostinfo = $vbulletin->forumcache[$lastpostarray[$forumid]];
    $forum['statusicon'] = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);
    
    $forumperms = $vbulletin->userinfo['forumpermissions'][$forumid];
    if ($vbulletin->options['showlocks'] // show locks to users who can't post
        AND !$forum['link'] // forum is not a link
        AND(
            !($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting']) // forum does not allow posting
            OR(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) // can't post new threads
                AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) // can't reply to own threads
                AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers']) // can't reply to others' threads
            )
        )
    ) {
        $forum['statusicon'] .= '_lock';
    }
    
    if ($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']) {
        $forum['statusicon'] = 'forum_' . $forum['statusicon'] . '-48';
    } else {
        if ($forum['statusicon'] == 'new_lock') $forum['statusicon'] = 'old_lock';
        $forum['statusicon'] = 'category_forum_' . $forum['statusicon'];
    }
    
    if ($forum['statusicon'] == 'category_forum_new_lock')
        $forum['statusicon'] = 'category_forum_old_lock';
    
    return $forum['statusicon'];
}

function get_forum_icon($forumid, $type = 'forum', $lock = false, $new = false)
{
    global $vbulletin;
    
    $icon_filename = tp_get_forum_icon($forumid, $type, $lock, $new);
    if($icon_filename)
    {
        $icon_url = $vbulletin->options['bburl'].'/'.$vbulletin->options['tapatalk_directory'].'/forum_icons/'.$icon_filename;
    }
    else
    {
        $icon_name = get_forum_icon_name($forumid);
        
        $statusicon_dir = vB_Template_Runtime::fetchStyleVar('imgdir_statusicon');
        if (preg_match('#^[a-z0-9]+://#si', $statusicon_dir))
        {
            $icon_url = $statusicon_dir.'/'.$icon_name.'.png';
        }
        else if (file_exists($statusicon_dir.'/'.$icon_name.'.png'))
        {
            $icon_url = $vbulletin->options['bburl'] . '/' . $statusicon_dir.'/'.$icon_name.'.png';
        }
        else
        {
             $icon_url = '';
        }
    }

    return $icon_url;
}

function get_read_topics_from_cookie()
{
    global $vbulletin;
    
    $cookie_name = COOKIE_PREFIX . 'thread_lastview';
    $cache_name = 'bb_cache_' . $cookiename; // name of cache variable
    global $$cache_name; // internal array for cacheing purposes
    
    $cookie =& $vbulletin->input->clean_gpc('c', $cookie_name, TYPE_STR);
    $cache =  &$$cache_name;
    if ($cookie != '' AND !isset($cache))
    {
        $cache = @unserialize(convert_bbarray_cookie($cookie));
    }
    
    return isset($cache) ? $cache : array();
}

function update_push()
{
    global $vbulletin, $db;
    
    $userid = $vbulletin->userinfo['userid'];
    
    if ($userid && $vbulletin->db->query_first("SHOW TABLES LIKE '" . TABLE_PREFIX . "tapatalk_users'"))
    {
        $db->query_write("
            INSERT IGNORE INTO " . TABLE_PREFIX . "tapatalk_users (userid) VALUES ('$userid')
        ");
        
        if ($db->affected_rows() == 0)
        {
            $db->query_write("
                UPDATE " . TABLE_PREFIX . "tapatalk_users
                SET updated = CURRENT_TIMESTAMP
                WHERE userid = '$userid'
            ");
        }
    }
}

function tp_get_forum_icon($id, $type = 'forum', $lock = false, $new = false)
{
    if ($type == 'link')
    {
        if ($filename = tp_get_forum_icon_by_name('link'))
            return $filename;
    }
    else
    {
        if ($lock && $new && $filename = tp_get_forum_icon_by_name('lock_new_'.$id))
            return $filename;
        if ($lock && $filename = tp_get_forum_icon_by_name('lock_'.$id))
            return $filename;
        if ($new && $filename = tp_get_forum_icon_by_name('new_'.$id))
            return $filename;
        if ($filename = tp_get_forum_icon_by_name($id))
            return $filename;
        
        if ($type == 'category')
        {
            if ($lock && $new && $filename = tp_get_forum_icon_by_name('category_lock_new'))
                return $filename;
            if ($lock && $filename = tp_get_forum_icon_by_name('category_lock'))
                return $filename;
            if ($new && $filename = tp_get_forum_icon_by_name('category_new'))
                return $filename;
            if ($filename = tp_get_forum_icon_by_name('category'))
                return $filename;
        }
        else
        {
            if ($lock && $new && $filename = tp_get_forum_icon_by_name('forum_lock_new'))
                return $filename;
            if ($lock && $filename = tp_get_forum_icon_by_name('forum_lock'))
                return $filename;
            if ($new && $filename = tp_get_forum_icon_by_name('forum_new'))
                return $filename;
            if ($filename = tp_get_forum_icon_by_name('forum'))
                return $filename;
        }
        
        if ($lock && $new && $filename = tp_get_forum_icon_by_name('lock_new'))
            return $filename;
        if ($lock && $filename = tp_get_forum_icon_by_name('lock'))
            return $filename;
        if ($new && $filename = tp_get_forum_icon_by_name('new'))
            return $filename;
    }
    
    return tp_get_forum_icon_by_name('default');
}

function tp_get_forum_icon_by_name($icon_name)
{
    $tapatalk_forum_icon_dir = CWD1.'/forum_icons/';
    
    if (file_exists($tapatalk_forum_icon_dir.$icon_name.'.png'))
        return $icon_name.'.png';
    
    if (file_exists($tapatalk_forum_icon_dir.$icon_name.'.jpg'))
        return $icon_name.'.jpg';
    
    return '';
}

function register_user($need_email_verification = false, $gravatar = false, $tid_profile = array())
{
    global $vbulletin, $tt_config;
    if(empty($_POST['password']))
        return 'Password cannot be empty';
    //Email verified by Tapatalk ID?
    {
        $vbulletin->input->clean_array_gpc('p', array(
            'agree'               => TYPE_BOOL,
            'options'             => TYPE_ARRAY_BOOL,
            'username'            => TYPE_STR,
            'email'               => TYPE_STR,
            'emailconfirm'        => TYPE_STR,
            'parentemail'         => TYPE_STR,
            'password'            => TYPE_STR,
            'password_md5'        => TYPE_STR,
            'passwordconfirm'     => TYPE_STR,
            'passwordconfirm_md5' => TYPE_STR,
            'referrername'        => TYPE_NOHTML,
            'coppauser'           => TYPE_BOOL,
            'day'                 => TYPE_UINT,
            'month'               => TYPE_UINT,
            'year'                => TYPE_UINT,
            'timezoneoffset'      => TYPE_NUM,
            'dst'                 => TYPE_UINT,
            'userfield'           => TYPE_ARRAY,
            'showbirthday'        => TYPE_UINT,
            'humanverify'         => TYPE_ARRAY
        ));
        if (!$vbulletin->options['allowregistration'])
        {
            eval(standard_error(fetch_error('noregister')));
        }

        // init user datamanager class
        $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
        $userdata->set_info('coppauser', false);
        $userdata->set_info('coppapassword', $vbulletin->GPC['password']);
        $userdata->set_bitfield('options', 'coppauser', false);
        $userdata->set('parentemail', $vbulletin->GPC['parentemail']);
        // check for missing fields
        if (empty($vbulletin->GPC['username'])
            OR empty($vbulletin->GPC['email'])
            OR (empty($vbulletin->GPC['password']) AND empty($vbulletin->GPC['password_md5']))
        )
        {
            $userdata->error('fieldmissing');
        }
        $userdata->set('email', $vbulletin->GPC['email']);
        $userdata->set('username', $vbulletin->GPC['username']);
        $userdata->set('password', ($vbulletin->GPC['password_md5'] ? $vbulletin->GPC['password_md5'] : $vbulletin->GPC['password']));
        // assign user to usergroup 3 if email needs verification
        if ($vbulletin->options['verifyemail'] && $need_email_verification)
        {
            $newusergroupid = 3;
        }
        else if ($vbulletin->options['moderatenewmembers'] OR $vbulletin->GPC['coppauser'])
        {
            $newusergroupid = 4;
        }
        else
        {
            $newusergroupid = isset($vbulletin->options['tapatalk_reg_ug']) && !empty($vbulletin->options['tapatalk_reg_ug']) ? $vbulletin->options['tapatalk_reg_ug'] : 2;
        }
        // set Tapatalk ID profile
        if(!empty($tid_profile))
        {
            $userdata->set('signature', $tid_profile['signature']);
            $userdata->set('homepage', $tid_profile['link']);
            if(isset($tid_profile['birthday']) && !empty($tid_profile['birthday']))
            {
                $birth_data = preg_split('/-/', $tid_profile['birthday']);
                $birthday = array(
                    'day'   => $birth_data[2],
                    'month' => $birth_data[1],
                    'year'  => $birth_data[0]
                );
                $userdata->set('birthday', $birthday);
            }
        }
        // set usergroupid
        $userdata->set('usergroupid', $newusergroupid);

        // set languageid
        $userdata->set('languageid', $vbulletin->userinfo['languageid']);
        // set user title
        $userdata->set_usertitle('', false, $vbulletin->usergroupcache["$newusergroupid"], false, false);

        // set profile fields
        $customfields = $userdata->set_userfields($vbulletin->GPC['userfield'], false, 'register');
        // set time options
        $userdata->set_dst($vbulletin->GPC['dst']);
        $userdata->set('timezoneoffset', $vbulletin->GPC['timezoneoffset']);

        // register IP address
        $userdata->set('ipaddress', IPADDRESS);

        //Stop Forum Spam
        include_once(CWD .'/'.$vbulletin->options['tapatalk_directory'].'/include/function_push.php');
        if(isset($vbulletin->options['stop_forum_spam']))
        {
            if((defined('IN_MOBIQUO') && $vbulletin->options['stop_forum_spam'] == 2 ) || $vbulletin->options['stop_forum_spam'] == 4 || (!defined('IN_MOBIQUO') && $vbulletin->options['stop_forum_spam'] == 3))
            {
                if(is_spam($vbulletin->GPC['email'], IPADDRESS))
                {
                    if (!function_exists('fetch_phrase'))
                    {
                        require_once(DIR . '/includes/functions_misc.php');
                    }
                    if(!defined('IN_MOBIQUO'))
                        eval(standard_error(fetch_phrase('email_mark_as_spam', 'error'), '', false));
                    else
                        return_fault(fetch_phrase('email_mark_as_spam', 'error'));
                }
            }
        }

        $userdata->pre_save();


        // check for errors
        if (!empty($userdata->errors))
        {
            $_REQUEST['do'] = 'register';

            $errorlist = '';
            if (!VB_API)
            {
                foreach ($userdata->errors AS $index => $error)
                {
                    $errorlist .= "<li>$error</li>";
                }
            }
            else
            {
                $errorlist = $userdata->errors;
            }
            return strip_tags($errorlist);
        }
        // save the data
        $vbulletin->userinfo['userid']
            = $userid
            = $userdata->save();
        // send new user email
        if ($vbulletin->options['newuseremail'] != '')
        {
            $username = $vbulletin->GPC['username'];
            $email = $vbulletin->GPC['email'];

            if ($userdata->fetch_field('referrerid') AND $vbulletin->GPC['referrername'])
            {
                $referrer = unhtmlspecialchars($vbulletin->GPC['referrername']);
            }
            else
            {
                $referrer = $vbphrase['n_a'];
            }
            $ipaddress = IPADDRESS;
            $memberlink = fetch_seo_url('member|nosession|bburl', array('userid' => $userid, 'username' => htmlspecialchars_uni($vbulletin->GPC['username'])));

            eval(fetch_email_phrases('newuser', 0));

            $newemails = explode(' ', $vbulletin->options['newuseremail']);
            foreach ($newemails AS $toemail)
            {
                if (trim($toemail))
                {
                    vbmail($toemail, $subject, $message);
                }
            }
        }

        $username = htmlspecialchars_uni($vbulletin->GPC['username']);
        $email = htmlspecialchars_uni($vbulletin->GPC['email']);
        // sort out emails and usergroups
        if ($vbulletin->options['verifyemail'] && $need_email_verification)
        {
            $activateid = build_user_activation_id($userid, (($vbulletin->options['moderatenewmembers'] OR $vbulletin->GPC['coppauser']) ? 4 : 2), 0);

            eval(fetch_email_phrases('activateaccount'));

            vbmail($email, $subject, $message, true);
            $result_text = 'An confirmation email has been sent, please check your email to activate your account';

        }
        else if ($newusergroupid == 2)
        {
            if ($vbulletin->options['welcomemail'])
            {
                eval(fetch_email_phrases('welcomemail'));
                vbmail($email, $subject, $message);
            }
        }
    }

    return array($userid, $result_text);
}