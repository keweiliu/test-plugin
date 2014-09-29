<?php

/*******************************************
* Tapatalk
* edit-by Tapatalk team
* www.tapatalk.com
* 2012-07
*******************************************/
$direct_install = false;

if(file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF')){
	require_once(dirname(__FILE__) . '/SSI.php');
	$direct_install = true;
}
elseif (!defined('SMF'))
	die('tapatalk wasn\'t able to conect to smf');
	
//Anothers $smcFunc;
db_extend('packages');


$drop_tables = array(
	'tapatalk_users',
);


foreach($drop_tables AS $table)
	$smcFunc['db_drop_table']('{db_prefix}'.$table, array(), 'ignore');

 if($direct_install)
	echo 'Done....';
 
?>