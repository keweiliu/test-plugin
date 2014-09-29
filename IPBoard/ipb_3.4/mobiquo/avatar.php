<?php

define('IN_MOBIQUO', true);
define( 'IPB_THIS_SCRIPT', 'public' );
define('IPS_ENFORCE_ACCESS', true);

require_once( '../initdata.php' );
require_once( IPS_ROOT_PATH . 'sources/base/ipsRegistry.php' );
require_once( IPS_ROOT_PATH . 'sources/base/ipsController.php' );

error_reporting(0);

$reg = ipsRegistry::instance();
$reg->init();


if (isset($_GET['user_id']))
{
    $member = IPSMember::load( $_GET['user_id'], 'extendedProfile' );
}
elseif (isset($_GET['username']))
{
    $member = IPSMember::load( base64_decode($_GET['username']), 'extendedProfile', 'displayname' );
    if (empty($member))
        $member = IPSMember::load( $_GET['username'], 'extendedProfile', 'displayname' );
}

if ($member)
{
    $size = isset($_GET['size']) && $_GET['size'] == 'full' ? 'full' : 'thumb';
    $url = IPSMember::buildProfilePhoto($member, $size);
    if (preg_match('/<img src=\'(.*?)\'/si', $url, $match)) {
        header("Location: ".$match[1], 0, 303);
    }
}
