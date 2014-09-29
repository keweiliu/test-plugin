

<?php

$source_dir = "C:/Users/Euhow/Documents/Tencent Files/672421187/FileRecv/tapatalk-all (1)";
$target_dir = "C:/Users/Euhow/Documents/GitHub/directory.tapatalk.com/lang";
$target_lang = array(
'en','ar','de','it','es','fr'
);
$target_email_type = array(
'6Need Help Getting Started.strings'//'ttid_confirm_email.strings'//,'ttid_welcome.strings'
);
foreach($target_lang as $lang)
{
    foreach($target_email_type as $email_type)
    {
        
        $strings = @file_get_contents("$source_dir/$lang/Tapatalk email/$email_type");
        if(empty($strings))
            echo "File missing for $source_dir/$lang/Tapatalk email/$email_type<br>";
        $strings = preg_replace('/"\$/','"',$strings);
        $strings = preg_replace('/" = "/','" => "',$strings);
        $strings = preg_replace('/";/','",',$strings);
        $strings = "<?php \n \$phrase = array(\n$strings\n);";
        if (!file_exists("$target_dir/$lang")) {
            mkdir("$target_dir/$lang", 0777, true);
        }
        $email_type = preg_replace('/strings/','php',$email_type);
        //$email_type = substr($email_type, 1);
        if (file_exists("$target_dir/$lang/$email_type")) {
            unlink("$target_dir/$lang/$email_type");
        }
        file_put_contents("$target_dir/$lang/$email_type", $strings);
    }
}
