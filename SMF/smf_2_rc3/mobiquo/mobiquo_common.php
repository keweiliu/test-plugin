<?php

defined('IN_MOBIQUO') or exit;

function mobiquo_exit($str = '')
{
    global $request_name, $context, $topic, $board;
    
    switch ($request_name) {
        case    'authorize_user': if (preg_match('/^action=login2;sa=check;member=/', $str)) return;
        case             'login': if (preg_match('/^action=login2;sa=check;member=/', $str)) return;
        case        'login_user': if (preg_match('/^action=login2;sa=check;member=/', $str)) return;
        case       'logout_user': if (isset($_COOKIE['PHPSESSID'])) sessionDestroy($_COOKIE['PHPSESSID']); return;
        case    'delete_message': return;
        case    'create_message': if ($str == $context['current_label_redirect']) return;
        case   'subscribe_topic': if ($str == 'topic=' . $topic . '.' . $_REQUEST['start']) return;
        case 'unsubscribe_topic': if ($str == 'topic=' . $topic . '.' . $_REQUEST['start']) return;
        case   'subscribe_forum': if ($str == 'board=' . $board . '.' . $_REQUEST['start']) return;
        case 'unsubscribe_forum': if ($str == 'board=' . $board . '.' . $_REQUEST['start']) return;
        case       'report_post': return;
        case  'mark_all_as_read': return;
        case     'upload_avatar': if ('action=profile;area=forumprofile;updated' == $str) return;
    }
    
    get_error('Unknown error');
}

function to_utf8($str)
{
    global $context;
    
    if (!empty($context) && !$context['utf8'])
    {
        $charset = $context['character_set'];
        $str = function_exists('mb_convert_encoding') ? mb_convert_encoding($str, 'UTF-8', $charset) : iconv($charset, 'utf-8', $str);
    }
    
    return $str;
}

function utf8ToAscii($c){
    global $context;
    
    if (!empty($context) && $context['utf8'])
        return $c;
    
    $len = strlen($c);
    $a = 0;
    $scill = '';
    while ($a < $len){
        $ud = 0;
        if (ord($c{$a})>=0 && ord($c{$a})<=127){
            //$ud = ord($c{$a});
            $scill .= $c{$a};
            $a += 1;
            continue;
        }else if (ord($c{$a})>=192 && ord($c{$a})<=223){
            $ud = (ord($c{$a})-192)*64 + (ord($c{$a+1})-128);
            $a += 2;
        }else if (ord($c{$a})>=224 && ord($c{$a})<=239){
            $ud = (ord($c{$a})-224)*4096 + (ord($c{$a+1})-128)*64 + (ord($c{$a+2})-128);
            $a += 3;
        }else if (ord($c{$a})>=240 && ord($c{$a})<=247){
            $ud = (ord($c{$a})-240)*262144 + (ord($c{$a+1})-128)*4096 + (ord($c{$a+2})-128)*64 + (ord($c{$a+3})-128);
            $a += 4;
        }else if (ord($c{$a})>=248 && ord($c{$a})<=251){
            $ud = (ord($c{$a})-248)*16777216 + (ord($c{$a+1})-128)*262144 + (ord($c{$a+2})-128)*4096 + (ord($c{$a+3})-128)*64 + (ord($c{$a+4})-128);
            $a += 5;
        }else if (ord($c{$a})>=252 && ord($c{$a})<=253){
            $ud = (ord($c{$a})-252)*1073741824 + (ord($c{$a+1})-128)*16777216 + (ord($c{$a+2})-128)*262144 + (ord($c{$a+3})-128)*4096 + (ord($c{$a+4})-128)*64 + (ord($c{$a+5})-128);
            $a += 6;
        }else if (ord($c{$a})>=254 && ord($c{$a})<=255){ //error
            $ud = false;
        }
        $scill .= "&#$ud;";
    }
    return $scill;
}

function utf8_to_local()
{
    global $context, $request_name;
    
    if (!empty($context) && $context['utf8']) return;
    
    switch ($request_name) {
        case 'login':
            $_REQUEST['user'] = utf8ToAscii($_REQUEST['user']);
            break;
        case 'create_message':
            foreach($_POST['recipient_to'] as $index => $name) {
                $_POST['recipient_to'][$index] = utf8ToAscii($name);
            }
            $_REQUEST['subject'] = utf8ToAscii($_REQUEST['subject']);
            $_REQUEST['message'] = utf8ToAscii($_REQUEST['message']);
            break;
        case 'new_topic':
        case 'create_topic':
        case 'reply_topic':
        case 'reply_post':
        case 'save_raw_post':
            $_POST['subject'] = utf8ToAscii($_POST['subject']);
            $_POST['message'] = utf8ToAscii($_POST['message']);
            break;
        case 'get_user_info':
        case 'get_user_reply_post':
        case 'get_user_topic':
            $_REQUEST['user'] = utf8ToAscii($_REQUEST['user']);
            break;
        case 'get_participated_topic':
            if($GLOBALS['search_user'])
                $GLOBALS['search_user'] = utf8ToAscii($GLOBALS['search_user']);
            break;
        case 'search_topic':
        case 'search_post':
            $_POST['search'] = utf8ToAscii($_POST['search']);
            break;
        case 'report_post':
            $_POST['comment'] = utf8ToAscii($_POST['comment']);
            break;
    }
}

function get_short_content($message, $length = 100)
{
    $message = preg_replace('/\[url.*?\].*?\[\/url\]/si', '###url###', $message);
    $message = preg_replace('/\[img.*?\].*?\[\/img\]/si', '###img###', $message);
    $message = preg_replace('/\[attach.*?\].*?\[\/attach\]/si', '###attach###', $message);
    $message = preg_replace('/\[(i|code|quote|free|media|audio|flash|hide|swf).*?\].*?\[\/\\1\]/si', '', $message);
    $message = preg_replace('/\[.*?\]/si', '', $message);
    $message = preg_replace('/###(url|img|attach)###/si', '[$1]', $message);
    $message = preg_replace('/^\s*|\s*$/', '', $message);
    $message = preg_replace('/[\n\r\t]+/', ' ', $message);
    $message = preg_replace('/\s+/', ' ', $message);
    if (function_exists('censorText')) censorText($message);
    $message = cutstr($message, $length);
    $message = basic_clean($message);

    return $message;
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

function get_error($err_str)
{
    global $context;
    
    @ob_clean();
    
    if(!headers_sent())
    {
        header('Mobiquo_is_login:'.($context['user']['is_logged'] ? 'true' : 'false'));
        header('Content-Type: text/xml');
    }
    
    $response = new xmlrpcresp(
        new xmlrpcval(array(
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval(basic_clean($err_str), 'base64'),
        ),'struct')
    );
    
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$response->serialize('UTF-8');
    exit;
}

function log_it($log_data)
{
    global $mobiquo_config;

    if(!$mobiquo_config['keep_log'] || !$log_data)
    {
        return;
    }

    $log_file = './log/'.date('Ymd_H').'.log';

    file_put_contents($log_file, print_r($log_data, true), FILE_APPEND);
}

function post_html_clean($str)
{
    $search = array(
        '/<a .*?href="(.*?)".*?>(.*?)<\/a>/si',
        '/<img .*?src="(.*?)".*?\/?>/si',
        '/<br\s*\/?>|<\/cite>|<li>|<\/em>|<em.*?>/si',
    );

    $replace = array(
        '[url=$1]$2[/url]',
        '[img]$1[/img]',
        "\n"
    );

    $str = preg_replace('/\n|\r/si', '', $str);
    $str = parse_quote($str);
    $str = preg_replace('/<i class="pstatus".*?>.*?<\/i>(<br\s*\/>){0,2}/', '', $str);
    $str = preg_replace('/<script.*?>.*?<\/script>/', '', $str);
    $str = preg_replace($search, $replace, $str);
    // remove link on img
    $str = preg_replace('/\[url=.*?\](\[img\].*?\[\/img\])\[\/url\]/', '$1', $str);
    
    $str = basic_clean($str);
    $str = parse_bbcode($str);

    return $str;
}

function parse_quote($str)
{
    $blocks = preg_split('/(<blockquote.*?>|<\/blockquote>)/i', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    
    $quote_level = 0;
    $message = '';
        
    foreach($blocks as $block)
    {
        if (preg_match('/<blockquote.*?>/i', $block)) {
            if ($quote_level == 0) $message .= '[quote]';
            $quote_level++;
        } else if (preg_match('/<\/blockquote>/i', $block)) {
            if ($quote_level <= 1) $message .= '[/quote]';
            if ($quote_level >= 1) {
                $quote_level--;
                $message .= "\n";
            }
        } else {
            if ($quote_level <= 1) $message .= $block;
        }
    }
    
    return $message;
}

function parse_bbcode($str)
{
    $search = array(
        '#\[(b)\](.*?)\[/b\]#si',
        '#\[(u)\](.*?)\[/u\]#si',
        '#\[(i)\](.*?)\[/i\]#si',
        '#\[color=(\#[\da-fA-F]{3}|\#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\(\d{1,3}, ?\d{1,3}, ?\d{1,3}\))\](.*?)\[/color\]#si',
    );
    
    if ($GLOBALS['return_html']) {
        $replace = array(
            '<$1>$2</$1>',
            '<$1>$2</$1>',
            '<$1>$2</$1>',
            '<font color="$1">$2</font>',
        );
        $str = str_replace("\n", '<br />', $str);
    } else {
        $replace = '$2';
    }
    
    return preg_replace($search, $replace, $str);
}

function basic_clean($str, $cut = 0)
{
    $str = preg_replace('/<a.*?>Quote from:.*?<\/a>/', ' ', $str);
    $str = strip_tags($str);
    $str = to_utf8($str);
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    if (function_exists('censorText')) censorText($str);
    
    if ($cut > 0)
    {
        $str = preg_replace('/\[url=.*?\].*?\[\/url\]\s*\[quote\].*?\[\/quote\]/si', '', $str);
        $str = preg_replace('/\[.*?\]/si', '', $str);
        $str = preg_replace('/[\n\r\t]+/', ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        $str = trim($str);
        $str = cutstr($str, $cut);
    }
    
    $str = parse_bbcode($str);
    
    return trim($str);
}

function get_topic_info($fid, $tid)
{
    global $smcFunc, $user_info, $modSettings, $scripturl, $settings;
    
    if(!$tid) return array();
    
    $request = $smcFunc['db_query']('substring', '
        SELECT
            t.id_topic, t.num_replies, t.locked, t.num_views, t.is_sticky, t.id_poll, t.id_previous_board,
            ' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from,
            ln.id_topic AS is_marked_notify,
            t.id_last_msg, t.approved, t.unapproved_posts, ml.poster_time AS last_poster_time,
            ml.id_msg_modified, ml.subject AS last_subject, ml.icon AS last_icon,
            ml.poster_name AS last_member_name, ml.id_member AS last_id_member,
            IFNULL(meml.real_name, ml.poster_name) AS last_display_name, t.id_first_msg,
            mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
            mf.poster_name AS first_member_name, mf.id_member AS first_id_member,
            IFNULL(memf.real_name, mf.poster_name) AS first_display_name, SUBSTRING(ml.body, 1, 385) AS last_body,
            SUBSTRING(mf.body, 1, 385) AS first_body, ml.smileys_enabled AS last_smileys, mf.smileys_enabled AS first_smileys,
            IFNULL(al.id_attach, 0) AS id_attach_last, al.filename as filename_last, al.attachment_type as attachment_type_last, meml.avatar as avatar_last,
            IFNULL(af.id_attach, 0) AS id_attach_first, af.filename as filename_first, af.attachment_type as attachment_type_first, memf.avatar as avatar_first
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
            INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
            LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
            LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
            LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)
            LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = memf.id_member)' . ($user_info['is_guest'] ? '' : '
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})
            LEFT JOIN {db_prefix}log_notify AS ln ON (ln.id_topic = t.id_topic AND ln.id_member = {int:current_member})'). '
        WHERE t.id_topic = {int:current_topic}',
        array(
            'current_board' => intval($fid),
            'current_topic' => intval($tid),
            'current_member' => $user_info['id'],
        )
    );
    $topic = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    
    // Check for notifications on this topic
    $request = $smcFunc['db_query']('', '
        SELECT sent, id_topic
        FROM {db_prefix}log_notify
        WHERE id_topic = {int:current_topic} AND id_member = {int:current_member}
        LIMIT 1',
        array(
            'current_member' => $user_info['id'],
            'current_topic' => $topic['id_topic'],
        )
    );
    $topic['is_marked_notify'] = $smcFunc['db_num_rows']($request) ? true : false;
    $smcFunc['db_free_result']($request);
    
    $started = $topic['first_id_member'] == $user_info['id'];
    $topic['can_lock']        = allowedTo('lock_any') || ($started && allowedTo('lock_own'));
    $topic['can_sticky']      = allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']);
    $topic['can_move']        = allowedTo('move_any') || ($started && allowedTo('move_own'));
    $topic['can_modify']      = allowedTo('modify_any') || ($started && allowedTo('modify_own'));
    $topic['can_remove']      = allowedTo('remove_any') || ($started && allowedTo('remove_own'));
    $topic['can_approve']     = allowedTo('approve_posts');
    $topic['can_mark_notify'] = allowedTo('mark_notify') && !$user_info['is_guest'];
    
    
    $topic['is_sticky'] = !empty($modSettings['enableStickyTopics']) && !empty($topic['is_sticky']);
    $topic['is_locked'] = !empty($topic['locked']);
    $topic['is_approved'] = !empty($topic['approved']);
    $topic['new']       = $topic['new_from'] <= $topic['id_msg_modified'];
    $topic['last_poster_time'] = timeformat($topic['last_poster_time']);
    $topic['first_poster_time'] = timeformat($topic['first_poster_time']);
    
    if (!empty($settings['message_index_preview']))
    {
        // Limit them to 100 characters - do this FIRST because it's a lot of wasted censoring otherwise.
        $topic['first_body'] = preg_replace('/\[quote.*?\].*\[\/quote\]/si', '', $topic['first_body']);
        $topic['first_body'] = preg_replace('/\[img.*?\].*?\[\/img\]/si', '###img###', $topic['first_body']);
        $topic['first_body'] = strip_tags(strtr(mobiquo_parse_bbc($topic['first_body'], false, $topic['id_first_msg']), array('<br />' => ' ')));
        $topic['first_body'] = preg_replace('/###img###/si', '[img]', $topic['first_body']);
        if ($smcFunc['strlen']($topic['first_body']) > 100)
            $topic['first_body'] = $smcFunc['substr']($topic['first_body'], 0, 100);
        
        $topic['last_body'] = preg_replace('/\[quote.*?\].*\[\/quote\]/si', '', $topic['last_body']);
        $topic['last_body'] = preg_replace('/\[img.*?\].*?\[\/img\]/si', '###img###', $topic['last_body']);
        $topic['last_body'] = strip_tags(strtr(mobiquo_parse_bbc($topic['last_body'], false, $topic['id_last_msg']), array('<br />' => ' ')));
        $topic['last_body'] = preg_replace('/###img###/si', '[img]', $topic['last_body']);
        if ($smcFunc['strlen']($topic['last_body']) > 100)
            $topic['last_body'] = $smcFunc['substr']($topic['last_body'], 0, 100);
        
        // Censor the subject and message preview.
        censorText($topic['first_subject']);
        censorText($topic['first_body']);
        
        // Don't censor them twice!
        if ($topic['id_first_msg'] == $topic['id_last_msg'])
        {
            $topic['last_subject'] = $topic['first_subject'];
            $topic['last_body'] = $topic['first_body'];
        }
        else
        {
            censorText($topic['last_subject']);
            censorText($topic['last_body']);
        }
    }
    else
    {
        $topic['first_body'] = '';
        $topic['last_body'] = '';
        censorText($topic['first_subject']);
        
        if ($topic['id_first_msg'] == $topic['id_last_msg'])
            $topic['last_subject'] = $topic['first_subject'];
        else
            censorText($topic['last_subject']);
    }
    
    if (!empty($settings['show_user_images']))
    {
        $topic['first_poster_avatar'] = $topic['avatar_first'] == '' ? ($topic['id_attach_first'] > 0 ? (empty($topic['attachment_type_first']) ? $scripturl . '?action=dlattach;attach=' . $topic['id_attach_first'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $topic['filename_first']) : '') : (stristr($topic['avatar_first'], 'http://') ? $topic['avatar_first'] : $modSettings['avatar_url'] . '/' . $topic['avatar_first']);
        $topic['last_poster_avatar'] = $topic['avatar_last'] == '' ? ($topic['id_attach_last'] > 0 ? (empty($topic['attachment_type_last']) ? $scripturl . '?action=dlattach;attach=' . $topic['id_attach_last'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $topic['filename_last']) : '') : (stristr($topic['avatar_last'], 'http://') ? $topic['avatar_last'] : $modSettings['avatar_url'] . '/' . $topic['avatar_last']);
    }
    
    $topic['num_views'] = intval($topic['num_views']);
    $topic['num_replies'] = intval($topic['num_replies']);
    
    return $topic;
}

function mobiquo_parse_bbc($message, $smileys = true, $cache_id = '', $parse_tags = array())
{
    global $user_info, $modSettings;
    
    $user_info['time_format'] = $user_info['user_time_format'];
    $modSettings['todayMod'] = $modSettings['todayMod_bak'];
    $message = str_replace('[spoiler]', "\nSpoiler for Hiden:\n[quote]", $message);
    $message = str_replace('[/spoiler]', '[/quote]', $message);
    $message = parse_bbc($message, $smileys, $cache_id, $parse_tags);
    $user_info['time_format'] = '%Y%m%dT%H:%M:%S+00:00';
    $modSettings['todayMod'] = 0;
    
    return $message;
}

function mobi_parse_requrest()
{
    global $request_name, $request_params, $params_num;
    
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
    $request_name = $parsers->methodname;
    $request_params = php_xmlrpc_decode(new xmlrpcval($parsers->params, 'array'));
    $params_num = count($request_params);
}