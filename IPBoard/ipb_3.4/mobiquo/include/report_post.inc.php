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
require_once ('class/reports.php');
$mobi_public_core_reports_reports = new mobi_public_core_reports_reports($registry);
$mobi_public_core_reports_reports->makeRegistryShortcuts($registry);
$result = $mobi_public_core_reports_reports->doExecute($registry);
