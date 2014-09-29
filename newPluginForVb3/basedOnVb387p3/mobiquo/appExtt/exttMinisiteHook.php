<?php

/* hook codes for minisite redirection */
/* common hook code in xml
if (is_file(CWD . '/' . $vbulletin->options['tapatalk_directory'] . '/appExtt/exttMinisiteHook.php')) {
    include(CWD . '/' . $vbulletin->options['tapatalk_directory'] . '/appExtt/exttMinisiteHook.php'); 
}
*/
/* hook points
Tapatalk: Minisite List Forum -> forumhome_start
Tapatalk: Minisite List Forum Topics -> forumdisplay_start
Tapatalk: Minisite List Thread Posts -> showthread_start
*/

if (FILE_VERSION >= '3.8.7') {  //only support >= vb387p3 version
} else {
    return false;
}

define('ExttMbqMinisiteRedirectYes', 2);
define('ExttMbqMinisiteRedirectNo', 1);

/* judge no mobile value to control whether or not to do minisite redirection */
if (array_key_exists('exttMbqNoMobile', $_GET)) {
    if ($_GET['exttMbqNoMobile'] == 1) {
        vbsetcookie('exttMbqMinisiteRedirectFlag', ExttMbqMinisiteRedirectNo);  //disable redirection
        return false;   //do not execute the following redirection code
    } else {
        vbsetcookie('exttMbqMinisiteRedirectFlag', ExttMbqMinisiteRedirectYes); //enable redirection
        //!!! cookie has been sent but behind code do not know this,so set the var first for the following code.
        $exttMbqMinisiteRedirectFlag = ExttMbqMinisiteRedirectYes;
    }
}

//set flag to control redirection
if (array_key_exists(COOKIE_PREFIX . 'exttMbqMinisiteRedirectFlag', $_COOKIE)) {    //!!!
    if (!isset($exttMbqMinisiteRedirectFlag))
    $exttMbqMinisiteRedirectFlag = $_COOKIE[COOKIE_PREFIX . 'exttMbqMinisiteRedirectFlag'];
} else {
    $exttMbqMinisiteRedirectFlag = ExttMbqMinisiteRedirectYes;
    vbsetcookie('exttMbqMinisiteRedirectFlag', $exttMbqMinisiteRedirectFlag);
}

//judge mobile accessing
//refer vb422/includes/init.php -> Test mobile browser
$exttMbqIsMobile = false;
if (stripos($_SERVER['HTTP_USER_AGENT'], 'windows') === false OR preg_match('/(Windows Phone OS|htc)/i', strtolower($_SERVER['HTTP_USER_AGENT'])))
{
	if (
		preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|Windows Phone OS|htc|ipad)/i', strtolower($_SERVER['HTTP_USER_AGENT']))
		OR
		stripos($_SERVER['HTTP_ACCEPT'],'application/vnd.wap.xhtml+xml') !== false
		OR
		((isset($_SERVER['HTTP_X_WAP_PROFILE']) OR isset($_SERVER['HTTP_PROFILE'])))
		OR
		stripos($_SERVER['ALL_HTTP'],'OperaMini') !== false
	)
	{
		$exttMbqIsMobile = true;
	}
	// This array is big and may be bigger later on. So we move it to a second if.
	else if (in_array(
				strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4)),
				array(
				'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
				'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
				'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
				'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
				'newt','noki','oper','palm','pana','pant','phil','play','port','prox',
				'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
				'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
				'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
				'wapr','webc','winw','winw','xda ','xda-')
			)
		)
	{
		$exttMbqIsMobile = true;
		if(strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4)) == 'oper' AND  preg_match('/(linux|mac)/i', $_SERVER['HTTP_USER_AGENT']))
		{
			$exttMbqIsMobile = false;
		}
	}
}

//make redirect url
$exttMbqMiniSiteBaseUrl = $vbulletin->options['bburl'].'/'.$vbulletin->options['tapatalk_directory'].'/minisite/site';
if (!$vbulletin->GPC['forumid'] && !$vbulletin->GPC['threadid']) {    //list forum
    $exttMbqMiniSiteRedirectUrl = $exttMbqMiniSiteBaseUrl.'/MainForum.php';
} elseif ($vbulletin->GPC['forumid'] && !$vbulletin->GPC['threadid']) { //list forum topics
    $exttMbqMiniSiteRedirectUrl = $exttMbqMiniSiteBaseUrl.'/MainTopic.php?cmd=threadList&fid='.$vbulletin->GPC['forumid'];
} elseif ($vbulletin->GPC['forumid'] && $vbulletin->GPC['threadid']) {   //list thread posts
    $exttMbqMiniSiteRedirectUrl = $exttMbqMiniSiteBaseUrl.'/MainTopic.php?cmd=getThread&tid='.$vbulletin->GPC['threadid'];
}

//redirection
if ($exttMbqIsMobile && ($exttMbqMinisiteRedirectFlag == ExttMbqMinisiteRedirectYes) && $exttMbqMiniSiteRedirectUrl && $vbulletin->options['minisite_auto_redirect']) {
//if (($exttMbqMinisiteRedirectFlag == ExttMbqMinisiteRedirectYes) && $exttMbqMiniSiteRedirectUrl && $vbulletin->options['minisite_auto_redirect']) {
    header( "HTTP/1.1 301 Moved Permanently" ) ;
    header("Location:$exttMbqMiniSiteRedirectUrl");
    exit();
}

?>