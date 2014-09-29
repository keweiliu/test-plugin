<?php
/*******************************************
* Tapatalk
* edit-by Tapatalk team
* https://tapatalk.com
* 2014-04
*******************************************/

// If SSI.php is in the same place as this file, and SMF isn't defined, this is being run standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
// Hmm... no SSI.php and no SMF?
elseif (!defined('SMF'))
    die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

// main table
db_query("CREATE TABLE IF NOT EXISTS {$db_prefix}tapatalk_users(
            `uid` int(10) unsigned NOT NULL,
            `updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`uid`),
            KEY `updated` (`updated`)
         )", __FILE__, __LINE__);

if(SMF == 'SSI')
    echo 'Database changes are complete!';

?>