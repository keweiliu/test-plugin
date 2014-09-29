<?php

function tapatalk_push_reply($post_id)
{
    global $user_info, $context, $smcFunc, $boardurl, $modSettings;

    if(!$modSettings['tp_pushEnabled'])
        return;
    if ($context['current_topic'] && $post_id && ini_get('allow_url_fopen'))// mobi_table_exists('tapatalk_users')
    {
        $request = $smcFunc['db_query']('', '
            SELECT ts.id_member
            FROM {db_prefix}log_notify ts
            LEFT JOIN {db_prefix}tapatalk_users tu ON (ts.id_member=tu.userid)
            WHERE ts.id_topic = {int:topic_id} AND tu.subscribe=1',
            array(
                'topic_id' => $context['current_topic'],
            )
        );
        while($row = $smcFunc['db_fetch_assoc']($request))
        {
            if ($row['id_member'] == $user_info['id']) continue;
    
            $ttp_data = array(
                'userid'    => $row['id_member'],
                'type'      => 'sub',
                'id'        => $context['current_topic'],
                'subid'     => $post_id,
                'title'     => tt_push_clean($_POST['subject']),
                'author'    => tt_push_clean($user_info['name']),
                'dateline'  => time(),
            );
            $ttp_post_data = array(
                'url'  => $boardurl,
                'data' => base64_encode(serialize(array($ttp_data))),
            );

            $return_status = tt_do_post_request($ttp_post_data);
        }
    }
}

function tapatalk_push_pm()
{
    global $user_info, $smcFunc, $boardurl, $modSettings;

    if(!$modSettings['tp_pushEnabled'])
        return;
    if ($_REQUEST['recipient_to'] && $_REQUEST['subject'] && ini_get('allow_url_fopen'))// mobi_table_exists('tapatalk_users')
    {
        $timestr = time();
        $id_pm_req = $smcFunc['db_query']('', '
            SELECT p.id_pm
            FROM {db_prefix}personal_messages p
            WHERE p.msgtime > {int:msgtime_l} AND p.msgtime < {int:msgtime_h} AND p.id_member_from = {int:send_userid} ',
            array(
                'msgtime_l' => $timestr-2,
                'msgtime_h' => $timestr+2,
                'send_userid' => $user_info['id'],
            ));
        $id_pm = $smcFunc['db_fetch_assoc']($id_pm_req);
        $smcFunc['db_free_result']($request);
        if ($id_pm)
        {
            $request = $smcFunc['db_query']('', '
                SELECT tu.userid
                FROM {db_prefix}tapatalk_users tu
                WHERE tu.userid IN ({array_int:recipient_to}) AND tu.pm = 1',
                array(
                    'recipient_to' => $_POST['recipient_to'],//$recipientList['to'],
                )
            );
            while($row = $smcFunc['db_fetch_assoc']($request))
            {
                if ($row['userid'] == $user_info['id']) continue;
                
                $ttp_data = array(
                    'userid'    => $row['userid'],
                    'type'      => 'pm',
                    'id'        => $id_pm['id_pm'],
                    'title'     => tt_push_clean($_REQUEST['subject']),
                    'author'    => tt_push_clean($user_info['name']),
                    'dateline'  => time(),
                );
                $ttp_post_data = array(
                    'url'  => $boardurl,
                    'data' => base64_encode(serialize(array($ttp_data))),
                );
                $return_status = tt_do_post_request($ttp_post_data);
            }
        }
    }
}

function tt_do_post_request($data)
{
    $ch = curl_init('http://push.tapatalk.com/push.php');
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // Execute the cURL request
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function tt_push_clean($str)
{
    $str = strip_tags($str);
    $str = trim($str);
    return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
}