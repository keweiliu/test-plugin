<?php

header('Content-type: application/json');
include('./xmlrpc.inc');

$url = isset($_GET['url']) ? $_GET['url'] : '';
$dir = isset($_GET['dir']) ? $_GET['dir'] : 'mobiquo';
$ext = isset($_GET['ext']) ? $_GET['ext'] : '.php';

if ($url)
{
    $config = xmlrpc($url, $dir, $ext);
    if (is_array($config))
        echo json_encode($config);
    else
        echo json_encode(array('error' => $config));
}
else
    echo json_encode(array('error' => 'invalid url'));

function xmlrpc($url, $dir = 'mobiquo', $ext = '.php')
{
    $url = preg_replace('/\/$/', '', $url);
    $urldata = parse_url($url);
    
    $host = strtolower($urldata['host']);
    
    if ($urldata['scheme'] == 'https')
    {
        $http_method = 'https';
        $port = isset($urldata['port']) ? $urldata['port'] : 443;
    }
    else
    {
        $http_method = 'http11';
        $port = isset($urldata['port']) ? $urldata['port'] : 80;
    }
    
    if (substr($ext, 0, 1) != '.') $ext = '.'.$ext;
    if ($ext == '.none') $ext = '';
    $server_path = $urldata['path'].'/'.$dir.'/mobiquo'.$ext;
    
    $client = new xmlrpc_client($server_path, $host, $port);
    if(!$client) {
        $error_message = "Url not avaliable";
    }
    else
    {
        if ($urldata['scheme'] == 'https')
        {
            $client->setSSLVerifyPeer(false);
            $client->setSSLVerifyHost(0);
        }
        
        $send = new xmlrpcmsg('get_config');
        $return = $client->send($send, 3, $http_method);
        
        if($return && $return->errstr) {
            $client->setAcceptedCompression('none');
            $return = $client->send($send, 3, $http_method);
        }
        
        if($return)
        {
            if($return->errstr)
            {
                $error_message = $return->errstr.$server_path;
            }
            else
            {
                $decode_return = @php_xmlrpc_decode($return->value());
                
                if(is_array($decode_return))
                {
                    return $decode_return;
                }
                else
                {
                    $error_message = print_r($return, true);
                }
            }
        } 
        else
        {
            $error_message = "No return data";
        }
    }
    
    return $error_message;
}