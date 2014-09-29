<?php
$file = 'tapatalk_private_key.pem';
$prk = file_get_contents($file);
$privkey = openssl_pkey_get_private($prk);  //¶ÁÈ¡Ë½Ô¿
$enc_res = 'ERGNlFTJK1jz40oEKDHNvt5CXUfOrD46AsyeteS8sdKpZlKlbcIX3CHdFGVUoKru7IcktknTHTVhS5w35NTJq8W0ukK1+j4o6cG8A6/a89aJ7Y2E9mW47Gmdd/xQ7crvEaMwHsigsf1CyvgePJN0q454EF6aw6otjMP288Pyyuw=';
$result =  priv_decrypt($_GET['enc']);
echo $result;


function priv_decrypt($encrypted)  //Ë½Ô¿½âÃÜ
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