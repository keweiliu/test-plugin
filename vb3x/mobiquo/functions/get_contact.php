<?php

defined('IN_MOBIQUO') or exit;
require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

function get_contact_func($xmlrpc_params)
{
    global $vbulletin, $request_params;

    include(DIR.'/'.$vbulletin->options['tapatalk_directory'].'/include/function_push.php');

    $mobi_api_key = loadAPIKey();
    $userid = $request_params[0];
    $userinfo = mobiquo_verify_id('user', $userid, 0, 1);
    if($userinfo['adminemail'])
    {
        $user = new xmlrpcval(array(
            'result' => new xmlrpcval(true, 'boolean'),
            'user_id'  => new xmlrpcval($userinfo['userid'], 'string'),
            'display_name'  => new xmlrpcval($userinfo['username'], 'base64'),
            'enc_email'  => new xmlrpcval(base64_encode(encrypt(trim($userinfo['email']), $mobi_api_key)), 'string'),
        ), 'struct');
        return new xmlrpcresp($user);
    }
    else
        return_fault('user not found');
}

function keyED($txt,$encrypt_key)
{
    $encrypt_key = md5($encrypt_key);
    $ctr=0;
    $tmp = "";
    for ($i=0;$i<strlen($txt);$i++)
    {
        if ($ctr==strlen($encrypt_key)) $ctr=0;
        $tmp.= substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1);
        $ctr++;
    }
    return $tmp;
}

function encrypt($txt,$key)
{
    srand((double)microtime()*1000000);
    $encrypt_key = md5(rand(0,32000));
    $ctr=0;
    $tmp = "";
    for ($i=0;$i<strlen($txt);$i++)
    {
        if ($ctr==strlen($encrypt_key)) $ctr=0;
        $tmp.= substr($encrypt_key,$ctr,1) .
        (substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1));
        $ctr++;
    }
    return keyED($tmp,$key);
}
