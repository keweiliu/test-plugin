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

if (!defined('IN_MOBIQUO'))
	die('Hacking Attempt...');

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
        if ($key == 'hide_forum_id')
        {
            $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            foreach($value as $forum_id)
                if (intval($forum_id))
                    $mobiquo_config[$key][] = intval($forum_id);
        }
        elseif ($key == 'mod_function')
        {
            $mobiquo_config[$key] = array();
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
