<?php

error_reporting(0);

$board_url = isset($_GET['board_url']) ? $_GET['board_url'] : '';
$referer = isset($_GET['referer']) ? $_GET['referer'] : '';
$redirect_url = $referer ? $referer : ($board_url ? $board_url : dirname(dirname(dirname($_SERVER['REQUEST_URI']))));
$code = isset($_GET['code']) ? $_GET['code'] : '';
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://';

$byo = '';
if (isset($_GET['app_ios_id']))
{
    $app_ios_id = intval($_GET['app_ios_id']);
    if ($app_ios_id && $app_ios_id != '-1') $byo = "&app_ios_id=$app_ios_id";
}
else if (isset($_GET['app_android_id']))
{
    $app_android_id = urlencode($_GET['app_android_id']);
    if ($app_android_id && $app_android_id != '-1') $byo = "&app_android_id=$app_android_id";
}

$ads_url = $protocol.'tapatalk.com/welcome_screen.php'
    .'?referer='.urlencode($referer)
    .'&code='.urlencode($code)
    .'&board_url='.urlencode($board_url)
    .'&lang='.urlencode($lang)
    .$byo
    .'&callback=?';

?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="format-detection" content="telephone=no" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="white" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no" />
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
</head>
<body>
    <script>$.getJSON("<?php echo $ads_url; ?>",function(data){
        if (!data.html) window.location.href = "<?php echo addslashes(htmlspecialchars($redirect_url)); ?>";
        $("body").append(data.html);
    });
    </script>
</body>
</html>