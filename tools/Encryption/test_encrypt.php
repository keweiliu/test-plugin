<?php
  
$file = 'tapatalk_public_key.pem';
$puk = file_get_contents($file);
$pubkey = openssl_pkey_get_public($puk);  //读取公钥

$enc_res = pub_encrypt('testtesttest');
//echo urlencode($enc_res);

function pub_encrypt($data)  //公钥加密
{
    global $pubkey;
    
    if(!empty($pubkey) && $pubkey != 'default')
    {
        if(!is_string($data))
        {
            return null;
        }      
        $r = openssl_public_encrypt($data, $encrypted, $pubkey);
        if($r)
        {
            return base64_encode($encrypted);
        }
        return null;
        }
    else
    {
        return base64_encode($encrypted);
    }
}

$file = 'tapatalk_private_key.pem';
$prk = file_get_contents($file);
$privkey = openssl_pkey_get_private($prk);  //读取私钥

$result =  priv_decrypt($enc_res);
echo $result;


function priv_decrypt($encrypted)  //私钥解密
{
    global $privkey;
    $encrypted = base64_decode($encrypted);
    if(preg_match('/@/', $encrypted))
        return $encrypted;
    $r = openssl_private_decrypt($encrypted, $decrypted, $privkey);
    if($r)
    {
        return $decrypted;
    }
    return null;    
}