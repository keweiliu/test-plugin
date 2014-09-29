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

$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();

require_once('./global.php');

class mobiquo_config
{
    function get_config()
    {
        global $vbulletin, $stylevar;
        
        $config = $this->read_config_file();
        
        $config['sys_version'] = FILE_VERSION;
        
        if($config['is_open'] == 1 && $vbulletin->options['bbactive'] == 1){
            $config['is_open'] = 1;
        } else {
            $config['is_open'] = 0;
        }

        if(isset($vbulletin->options['reg_url']) && !empty($vbulletin->options['reg_url']))
        {
            $config['reg_url'] = $vbulletin->options['reg_url'];
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
        if(($vbulletin->usergroupcache['1']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])){
            $config['guest_okay'] = 1;
        }else{
            $config['guest_okay'] = 0;
        }
        
        $config['support_md5'] = 1;
        $config['report_post'] = 1;
        $config['report_pm']   = 1;
        $config['goto_unread'] = 1;
        $config['goto_post']   = 1;
        $config['no_refresh_on_post'] = 1;
        $config['get_latest_topic'] = 1;
        $config['mod_approve']   = 1;
        $config['mod_delete'] = 1;
        $config['mod_report'] = 0;
        $config['delete_reason'] = 1;
        $config['subscribe_topic_mode'] = '0,1,2,3';
        $config['subscribe_forum_mode'] = '0,2,3';
        $config['charset'] = $stylevar['charset'];
        
        if (isset($vbulletin->options['tapatalk_hide_forum']))
        {
            $config['hide_forum_id'] = unserialize($vbulletin->options['tapatalk_hide_forum']);
        }
        else if(!empty($config['hide_forum_id']))
        {
            $config['hide_forum_id'] = preg_split('/\s*,\s*/', trim($tt_config['hide_forum_id']));
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

