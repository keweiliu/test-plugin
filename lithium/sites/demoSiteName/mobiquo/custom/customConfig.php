<?php

/* custom config array */
$mbqExttLithiumSiteConfig = array(
    /* required */
    'siteId'    => 1,   //used for diffrent site of cache
    'siteUrl' => 'http://tapatalk.demo.lithium.com',    //without '/' ending
    'communityName' => 'tapatalk',
    'apiVersion' => 'vc',
    /* optional */
    'authUser' => 'demo01',
    'authPass' => 'THG80*mZMi',
    'useCache'  => true,    //default is true
    'cacheTimeLimit'    => 3600,    //default is 3600
    /* for tapatalk native config */
    'tapatalk' => array(
        'user.guest_okay' => 'MbqFdtConfig.user.guest_okay.range.support'
    )
);

?>