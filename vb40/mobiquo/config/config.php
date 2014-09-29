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

class mobiquo_config
{
    function get_config()
    {
        global $vbulletin;
        $config = array();
        $config = $this->read_config_file();

        if($config['is_open'] ==1 && $vbulletin->options['bbactive']==1){
            $config['is_open'] = 1;
        } else {
            $config['is_open'] = 0;
        }
//        if($vbulletin->options['threadmarking'] == 0)
//            $config['can_unread'] = 0;
        if(isset($vbulletin->options['reg_url']) && !empty($vbulletin->options['reg_url']))
        {
            $config['reg_url'] = $vbulletin->options['reg_url'];
        }
        if(($vbulletin->usergroupcache['1']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])){
            $config['guest_okay'] = 1;
        }else{
            $config['guest_okay'] = 0;
        }

        if(empty($vbulletin->options['allowregistration']))
        {
            $config['sign_in'] = 0;
            $config['inappreg'] = 0;
            
            $config['sso_signin'] = 0;
            $config['sso_register'] = 0;
            $config['native_register'] = 0;
        }
        if (!function_exists('curl_init') && !@ini_get('allow_url_fopen')) 
        {
            $config['sign_in'] = 0;
            $config['inappreg'] = 0;
            
            $config['sso_login'] = 0;
            $config['sso_signin'] = 0;
            $config['sso_register'] = 0;
        }
        if (isset($vbulletin->options['tapatalk_reg_type']))
        {
            if ($vbulletin->options['tapatalk_reg_type'] == 2)
            {
                $config['sign_in'] = 0;
                $config['inappreg'] = 0;
                
                $config['sso_signin'] = 0;
                $config['sso_register'] = 0;
                $config['native_register'] = 0;
            }
            else if ($vbulletin->options['tapatalk_reg_type'] == 1)
            {
                $config['sign_in'] = 0;
                $config['inappreg'] = 0;
                
                $config['sso_signin'] = 0;
                $config['sso_register'] = 0;
            }
        }
        $config['min_search_length'] = $vbulletin->options['minsearchlength'];
        $config['charset'] = vB_Template_Runtime::fetchStyleVar('charset');
        if(isset($vbulletin->options['push_key']) && !empty($vbulletin->options['push_key']))
        {
            $config['api_key'] = md5($vbulletin->options['push_key']);
        }
        if (isset($vbulletin->options['tapatalk_hide_forum']))
        {
            $config['hide_forum_id'] = unserialize($vbulletin->options['tapatalk_hide_forum']);
        }
        
        return $config;
    }
    
    function read_config_file()
    {
        $config_file = CWD1 . '/config/config.txt';
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
                $mobiquo_config[$key] = $value;
            }
            else
            {
                strlen($value) and $mobiquo_config[$key] = $value;
            }
        }
        
        if (!isset($mobiquo_config['hide_forum_id']))
            $mobiquo_config['hide_forum_id'] = array();
        
        return $mobiquo_config;
    }
}
