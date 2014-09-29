<?php

defined('MBQ_IN_IT') or exit;
/**
 * This file is not needed by default!
 * Run this first before call MbqMain::initAppEnv() when you need!
 * 
 * @since  2012-11-19
 * @author Wu ZeTao <578014287@qq.com>
 */
/* Please write any codes you need in the following area before call MbqMain::initAppEnv()! */

/* vb3's code begin */
define('THIS_SCRIPT', '');
define('CSRF_PROTECTION', true);
define('CSRF_SKIP_LIST', '');

$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();

require_once('./global.php');

require_once(DIR . '/includes/functions.php');
require_once(DIR . '/includes/functions_forumlist.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumdisplay.php');
require_once(DIR . '/includes/functions_prefix.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/class_postbit.php');
require_once(DIR . '/includes/class_bbcode.php');
/* vb3's code end */

?>