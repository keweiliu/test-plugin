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

$mobiquo_config['guest_okay'] = !$settings['force_login'];

if ($settings['board_offline'] == 1) {
    $mobiquo_config['is_open'] = 0;
}

if (isset($settings['tapatalk_reg_url']) && $settings['tapatalk_reg_url'])
{
    $mobiquo_config['reg_url'] = $settings['tapatalk_reg_url'];
}

if($settings['no_reg'] > 0)
{
    $mobiquo_config['sign_in'] = 0;
    $mobiquo_config['inappreg'] = 0;
    
    $mobiquo_config['sso_signin'] = 0;
    $mobiquo_config['sso_register'] = 0;
    $mobiquo_config['native_register'] = 0;
}

if (!function_exists('curl_init') && !@ini_get('allow_url_fopen'))
{
    $mobiquo_config['sign_in'] = 0;
    $mobiquo_config['inappreg'] = 0;
    
    $mobiquo_config['sso_login'] = 0;
    $mobiquo_config['sso_signin'] = 0;
    $mobiquo_config['sso_register'] = 0;
}

if (isset($settings['tapatalk_reg_type']))
{
    if ($settings['tapatalk_reg_type'] == 2)
    {
        $mobiquo_config['sign_in'] = 0;
        $mobiquo_config['inappreg'] = 0;
        
        $mobiquo_config['sso_signin'] = 0;
        $mobiquo_config['sso_register'] = 0;
        $mobiquo_config['native_register'] = 0;
    }
    else if ($settings['tapatalk_reg_type'] == 1)
    {
        $mobiquo_config['sign_in'] = 0;
        $mobiquo_config['inappreg'] = 0;
        
        $mobiquo_config['sso_signin'] = 0;
        $mobiquo_config['sso_register'] = 0;
    }
}