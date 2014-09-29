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

$registry->class_localization->loadLanguageFile( array( 'public_profile' ), 'members' );
$classToLoad  = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/member/photo.php', 'classes_member_photo' );
$photo = new $classToLoad( $registry );

try
{
    $member = $registry->member()->fetchMemberData();
	$photo = $photo->save( $member, 'custom');
	$result = true;
}
catch( Exception $error )
{
	$msg = $error->getMessage();
	get_error('pp_' . $msg);
}

