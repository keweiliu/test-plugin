<?php

error_reporting(E_ALL & ~E_NOTICE);

if (isset($_GET['checkip']))
{
    print do_post_request(array('ip' => 1));
}
else
{
    echo 'Tapatalk push notification test: <b>';
    $return_status = do_post_request(array('test' => 1));
    
    if ($return_status === '1')
        echo 'Success</b>';
    else
        echo 'Failed</b><br />'.$return_status;
}

function do_post_request($data)
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