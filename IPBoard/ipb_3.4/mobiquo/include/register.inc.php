<?php

defined('IN_MOBIQUO') or exit;

require_once 'class/mobi_register_class.php';
$register = new mobi_register($registry);
$result = $register->doExecute($registry);
$result_text = $register->tt_result_text;
