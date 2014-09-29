<?php
require_once 'class/mobi_lostpass_class.php';
$lostpass = new mobi_lostpss($registry);
$result = $lostpass->doExecute($registry);
if($result === 'verified')
{
	$result = true;
	$verified = true;
	$result_text = '';
}
else
{
	$result_text = $lostpass->tt_reulst_text;
}

