<?php

require_once 'class/mobi_usercp_class.php';
$usercp = new mobi_usercp($registry);
$result = $usercp->doExecute($registry);
$result_text = $usercp->tt_result_text;