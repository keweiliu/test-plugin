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

$mobiquo_config = get_mobiquo_config();

function get_mobiquo_config()
{
    $config_file = './config/config.txt';
    file_exists($config_file) or exit('config.txt does not exists');

    if(function_exists('file_get_contents')){
        $tmp = file_get_contents($config_file);
    }else{
        $handle = fopen($config_file, 'rb');
        $tmp = fread($handle, filesize($config_file));
        fclose($handle);
    }

    // remove comments by /*xxxx*/ or //xxxx
    $tmp = preg_replace('/\/\*.*?\*\/|\/\/.*?(\n)/si','$1',$tmp);
    $tmpData = preg_split("/\s*\n/", $tmp, -1, PREG_SPLIT_NO_EMPTY);

    $mobiquo_config = array();
    foreach ($tmpData as $d){
        list($key, $value) = preg_split("/=/", $d, 2); // value string may also have '='
        $key = trim($key);
        $value = trim($value);
        if (in_array($key, array('hide_forum_id_for_guest', 'hide_forum_id_for_ios', 'hide_forum_id_for_android')))
        {
            $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            count($value) and $mobiquo_config[$key] = $value;
        }
        else
        {
            strlen($value) and $mobiquo_config[$key] = $value;
        }
    }

    return $mobiquo_config;
}

function merge_ipb_option(&$mobiquo_config)
{
    global $settings;
    
    if (is_array($settings))
    {
        isset($settings['tapatalk_hide_forum']) && $mobiquo_config['hide_forum_id'] = $settings['tapatalk_hide_forum'];
        isset($settings['tapatalk_delete_option']) && $mobiquo_config['advanced_delete'] = $settings['tapatalk_delete_option'];
    }
    
    if (isset($mobiquo_config['hide_forum_id']))
    {
        $value = preg_split('/\s*,\s*/', $mobiquo_config['hide_forum_id'], -1, PREG_SPLIT_NO_EMPTY);
        count($value) and $mobiquo_config['hide_forum_id'] = array_map('intval', $value);
    }
}
