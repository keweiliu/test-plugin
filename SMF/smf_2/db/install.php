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


//Creating columns....
$columns = array(
	'userid' => array(
		'name' => 'userid',
		'type' => 'int',
		'size' => '10',
		'default' => 0,
		'null' => false,
	),
	'announcement' => array(
		'name' => 'announcement',
		'type' => 'smallint',
		'size' => '5',
		'default' => 1,
		'null' => false,
	),
		'pm' => array(
		'name' => 'pm',
		'type' => 'smallint',
		'size' => '5',
		'default' => 1,
		'null' => false,
	),
		'subscribe' => array(
		'name' => 'subscribe',
		'type' => 'smallint',
		'size' => '5',
		'default' => 1,
		'null' => false,
	),
		'updated' => array(
		'name' => 'updated',
		'type' => 'timestamp',
		'null' => false,
	),
	);


//Creating indexes...
$indexes = array(
	'userid' => array(
		'name' => 'userid',
		'type' => 'primary',
		'columns' => array(
			'userid' => 'userid'
		),
	),
	'announcement' => array(
		'name' => 'announcement',
		'type' => 'index',
		'columns' => array(
			'announcement' => 'announcement'
		),
	),
	'pm' => array(
		'name' => 'pm',
		'type' => 'index',
		'columns' => array(
			'pm' => 'pm'
		),
	),
		'subscribe' => array(
		'name' => 'subscribe',
		'type' => 'index',
		'columns' => array(
			'subscribe' => 'subscribe'
		),
	),
			'updated' => array(
		'name' => 'updated',
		'type' => 'timestamp',
		'columns' => array(
			'updated' => 'updated'
		),
	),
	);
	
	$installed = $smcFunc['db_create_table']('{db_prefix}tapatalk_users', $columns, $indexes, array(), 'update', 'ignore');

 if($direct_install)
	echo 'Done....';
 
?>
