<?php
/**
 * @version $Id: controller.php 11 2009-03-17 09:17:49Z ggiunta $
 * @author Gaetano Giunta
 * @copyright (C) 2005-2009 G. Giunta
 * @license code licensed under the BSD License: http://phpxmlrpc.sourceforge.net/license.txt
 *
 * @todo add links to documentation from every option caption
 * @todo switch params for http compression from 0,1,2 to values to be used directly
 * @todo add a little bit more CSS formatting: we broke IE box model getting a width > 100%...
 * @todo add support for more options, such as ntlm auth to proxy, or request charset encoding
 *
 * @todo parse content of payload textarea to be fed to visual editor
 * @todo add http no-cache headers
 **/
error_reporting(E_ERROR);
  include(getcwd().'/common.php');
  if ($action == '')
    $action = 'list';

  // relative path to the visual xmlrpc editing dialog
  $editorpath = '../../javascript/';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>XMLRPC Debugger</title>
<meta name="robots" content="index,nofollow" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<script type="text/javascript" src="jquery.js"></script>
<script type="text/javascript">
$(document).ready(function(){
	$("#execute").click(function(){
		verifyserver();
		$("#forum_action").submit();
		setTimeout(function(){
			$.ajax({
			   type: "GET",
			   url: "action.php?cookie_action=1",
			   success: function(msg){
			     $("#clientcookies").val(msg);
			   }
			});
		}, 3000);
		
	})
})
</script>
<script type="text/javascript" language="Javascript">
  if (window.name!='frmcontroller')
    top.location.replace('index.php?run='+escape(self.location));
</script>
<!-- xmlrpc/jsonrpc base library -->

<style type="text/css">
<!--
html {overflow: -moz-scrollbars-vertical;}
body {padding: 0.5em; background-color: #EEEEEE; font-family: Verdana, Arial, Helvetica; font-size: 8pt;}
h1 {font-size: 12pt; margin: 0.5em;}
h2 {font-size: 10pt; display: inline; vertical-align: top;}
table {border: 1px solid gray; margin-bottom: 0.5em; padding: 0.25em; width: 100%;}

td {vertical-align: top; font-family: Verdana, Arial, Helvetica; font-size: 8pt;}
.labelcell {text-align: right;}
-->
</style>
<script language="JavaScript" type="text/javascript">
<!--
  function verifyserver()
  {
    if (document.frmaction.host.value == '')
    {
      alert('Please insert a server name or address');
      return false;
    }
//    if (document.frmaction.path.value == '')
//      document.frmaction.path.value = '/';
    var action = '';
    for (counter = 0; counter < document.frmaction.action.length; counter++)
      if (document.frmaction.action[counter].checked)
      {
        action = document.frmaction.action[counter].value;
      }
    if (document.frmaction.method.value == '' && (action == 'execute' || action == 'wrap' || action == 'describe'))
    {
      alert('Please insert a method name');
      return false;
    }
    if (document.frmaction.authtype.value != '1' && document.frmaction.username.value == '')
    {
      alert('No username for authenticating to server: authentication disabled');
    }
    return true;
  }

  function switchaction()
  {
    // reset html layout depending on action to be taken
    var action = '';
    for (counter = 0; counter < document.frmaction.action.length; counter++)
      if (document.frmaction.action[counter].checked)
      {
        action = document.frmaction.action[counter].value;
      }
    if (action == 'execute')
    {
      document.frmaction.methodpayload.disabled = false;
      displaydialogeditorbtn(true);//if (document.getElementById('methodpayloadbtn') != undefined) document.getElementById('methodpayloadbtn').disabled = false;
      document.frmaction.method.disabled = false;
      if (navigator.userAgent.indexOf("Firefox")>0) {
        document.frmaction.methodpayload.rows = 6;
        document.frmaction.methodpayload.cols = 79;
      }
    }
    else
    {
      document.frmaction.methodpayload.rows = 1;
      if (action == 'describe' || action == 'wrap')
      {
        document.frmaction.methodpayload.disabled = true;
        displaydialogeditorbtn(false); //if (document.getElementById('methodpayloadbtn') != undefined) document.getElementById('methodpayloadbtn').disabled = true;
        document.frmaction.method.disabled = false;
      }
      else // list
      {
        document.frmaction.methodpayload.disabled = true;
        displaydialogeditorbtn(false); //if (document.getElementById('methodpayloadbtn') != undefined) document.getElementById('methodpayloadbtn').disabled = false;
        document.frmaction.method.disabled = true;
      }
    }
  }

    function switchpath(select_path)
    {
        document.getElementById('regular_method').style.display = 'none';
        document.getElementById('dir_method').style.display = 'none';
        document.getElementById('mod_method').style.display = 'none';
        
        if (select_path == 'dir') {
            document.getElementById('dir_method').style.display = 'block';
            document.getElementById('path').value = '';
            document.getElementById('get_nested_category').checked = true;
            document.getElementById('host').value = 'http://localhost/directory/sxmlrpc.php';
        } else {
            if (select_path == 'mod') {
                document.getElementById('mod_method').style.display = 'block';
            } else {
                document.getElementById('regular_method').style.display = 'block';
                document.getElementById('get_config').checked = true;
            }
            
            if (select_path == 'dz') {
               document.getElementById('path').value = '/plugins/dabandeng/mobiquo/mobiquo.php';
            } else if (select_path == 'dzx') {
               document.getElementById('path').value = '/source/plugin/dabandeng/mobiquo/mobiquo.php';
            } else if (select_path == 'pw') {
               document.getElementById('path').value = '/hack/dabandeng/mobiquo/mobiquo.php';
            } else {
               document.getElementById('path').value = '/mobiquo/mobiquo.php';
            }
        }
    }
    
    function more_checked()
    {
        if (document.frmaction.more.checked){
            document.getElementById('hide_more1').style.display = 'block';
            document.getElementById('hide_more2').style.display = 'block';
        } else {
            document.getElementById('hide_more1').style.display = 'none';
            document.getElementById('hide_more2').style.display = 'none';
        }
    }
    
  function switchssl()
  {
    if (document.frmaction.protocol.value != '2')
    {
      document.frmaction.verifypeer.disabled = true;
      document.frmaction.verifyhost.disabled = true;
      document.frmaction.cainfo.disabled = true;
      document.getElementById('port').value = 80;
    }
    else
    {
      document.frmaction.verifypeer.disabled = false;
      document.frmaction.verifyhost.disabled = false;
      document.frmaction.cainfo.disabled = false;
      document.getElementById('port').value = 443;
    }
  }

  function switchauth()
  {
    if (document.frmaction.protocol.value != '0')
    {
      document.frmaction.authtype.disabled = false;
    }
    else
    {
      document.frmaction.authtype.disabled = true;
      document.frmaction.authtype.value = 1;
    }
  }

  function swicthcainfo()
  {
    if (document.frmaction.verifypeer.checked == true)
    {
      document.frmaction.cainfo.disabled = false;
    }
    else
    {
      document.frmaction.cainfo.disabled = true;
    }
  }

  function switchtransport(is_json)
  {
    if (is_json == 0)
    {
      document.frmjsonrpc.yes.checked = false;
      document.frmxmlrpc.yes.checked = true;
    }
    else
    {
      document.getElementById("idcell").style.visibility = 'visible';
      document.frmjsonrpc.yes.checked = true;
      document.frmxmlrpc.yes.checked = false;
    }
  }

  function displaydialogeditorbtn(show)
  {
    if (show && ((typeof base64_decode) == 'function'))
    {
          //document.getElementById('methodpayloadbtn').innerHTML = '[<a href="#" onclick="activateeditor(); return false;">Edit</a>]';
        }
        else
        {
          //document.getElementById('methodpayloadbtn').innerHTML = '';
        }
  }

  function activateeditor()
  {
          var url = '<?php echo $editorpath; ?>visualeditor.php?params=<?php echo $alt_payload; ?>';
          var wnd = window.open(url, '_blank', 'width=750, height=400, location=0, resizable=1, menubar=0, scrollbars=1');
  }

  // if javascript version of the lib is found, allow it to send us params
  function buildparams(base64data)
  {
    if (typeof base64_decode == 'function')
    {
          if (base64data == '0') // workaround for bug in base64_encode...
            document.getElementById('methodpayload').value = '';
          else
        document.getElementById('methodpayload').value = base64_decode(base64data);
    }
  }

  // use GET for ease of refresh, switch to POST when payload is too big to fit in url (in IE: 2048 bytes! see http://support.microsoft.com/kb/q208427/)
  function switchFormMethod()
  {
      /// @todo use a more precise calculation, adding the rest of the fields to the actual generated url lenght
      if (document.frmaction.methodpayload.value.length > 1536 )
      {
          document.frmaction.action = 'action.php?usepost=true';
          document.frmaction.method = 'post';
      }
  }

    function update_playload(select_method){
        switch (select_method) {
            case 'login_forum':
            case 'report_post':
            case 'report_pm':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'login':
            case 'login_mod':
                document.getElementById('methodpayload').value = "<param><value><base64>admin</base64></value></param>\n<param><value><base64>123321</base64></value></param>";
                break;
			case 'forget_password':
			case 'register':
				document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>\n"+
		        "<param><value><base64></base64></value></param>\n<param><value><string></string></value></param>\n<param><value><string></string></value></param>";
				break;
			case 'update_email':
			case 'update_password':
				document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>\n"+
		        "<param><value><base64></base64></value></param>";
				break;			
            case 'get_user_info':
            case 'get_user_topic':
            case 'get_user_reply_post':
            case 'get_friend_list':
                document.getElementById('methodpayload').value = "<param><value><base64>admin</base64></value></param>\n<param><value><string>german</string></value></param>";
                break;
            case 'new_topic':
            case 'save_raw_post':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>\n<param><value><boolean>0</boolean></value></param>\n<param><value><array><data><value><string></string></value></data></array></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'get_topic':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'get_latest_topic':
            case 'get_unread_topic':
                document.getElementById('methodpayload').value = "<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>\n<param><value><string>search_id</string></value></param>"
+"\n<param><value><struct>"
+"\n <member><name>only_in</name><value><array><data>"
+"\n  <value><string></string></value><value><string></string></value>"
+"\n </data></array></value></member>"
+"\n <member><name>not_in</name><value><array><data>"
+"\n  <value><string></string></value><value><string></string></value>"
+"\n </data></array></value></member>"
+"\n</struct></value></param>";
break;
            case 'search': 
            document.getElementById('methodpayload').value = "<param><value><struct>"
+"\n <member><name>searchid</name><value><string></string></value></member>"
+"\n <member><name>keywords</name><value><base64>test</base64></value></member>"
+"\n <member><name>userid</name><value><string>1</string></value></member>"
+"\n <member><name>searchuser</name><value><base64>jay</base64></value></member>"
+"\n <member><name>forumid</name><value><string>2</string></value></member>"
+"\n <member><name>threadid</name><value><string>1</string></value></member>"
+"\n <member><name>titleonly</name><value><string>0</string></value></member>"
+"\n <member><name>showposts</name><value><string>0</string></value></member>"
+"\n <member><name>searchtime</name><value><int>86400</int></value></member>"
+"\n <member><name>only_in</name><value><array><data>"
+"\n  <value><string>1</string></value><value><string>2</string></value>"
+"\n </data></array></value></member>"
+"\n <member><name>not_in</name><value><array><data>"
+"\n  <value><string></string></value><value><string></string></value>"
+"\n </data></array></value></member>"
+"\n <member><name>page</name><value><int>1</int></value></member>"
+"\n <member><name>perpage</name><value><int>10</int></value></member>"
+"\n</struct></value></param>";
break;
            case 'get_new_topic':
            case 'get_subscribed_topic':
            case 'm_get_report_post':
            case 'm_get_report_pm':
            case 'get_conversations':
                document.getElementById('methodpayload').value = "<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>";
                break;
            case 'get_participated_topic':
                document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>\n<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'reply_post':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string></string></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>\n<param><value><array><data><value><string></string></value></data></array></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'get_quote_post':
            case 'get_raw_post':
            case 'subscribe_forum':
            case 'unsubscribe_forum':
            case 'subscribe_topic':
            case 'unsubscribe_topic':
            case 'mark_all_as_read':
            case 'get_quote_pm':
            case 'get_id_by_url':
            case 'get_new':
            case 'get_popular':
            case 'get_new_and_popular':
            case 'digg_topic':
            case 'thank_post':
            case 'remove_thank_post':
            case 'get_app_version':
            case 'like_post':
            case 'unlike_post':
            case 'm_mark_as_spam':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>";
                break;
            case 'get_announcement':
            case 'get_thread':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>\n<param><value><boolean>0</boolean></value></param>";
                break;
            case 'get_box':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>";
                break;
            case 'search_topic':
            case 'search_post':
                document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>\n<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>\n<param><value><string></string></value></param>\n";
                break;
            case 'create_message':
                document.getElementById('methodpayload').value = "<param><value><array><data><value><base64></base64></value></data></array></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'get_message':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string></string></value></param>\n<param><value><boolean>0</boolean></value></param>";
                break;
            case 'delete_message':
            case 'mark_pm_unread':
	    case 'mark_pm_read':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>";
                break;
            case 'authorize_user':
                document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'create_topic':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'reply_topic':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'attach_image':
                document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>\n<param><value><string>JPG</string></value></param>\n<param><value><string>1</string></value></param>";
                break;
            case 'get_directory':
                document.getElementById('methodpayload').value = "<param><value><int>1</int></value></param>\n<param><value><int>10</int></value></param>\n<param><value><string>Dog</string></value></param>\n<param><value><boolean>0</boolean></value></param>\n<param><value><string>DATE</string></value></param>\n<param><value><string>german</string></value></param>";
                break;
            case 'get_thread_by_post':
            case 'get_thread_by_unread':
                document.getElementById('methodpayload').value = "<param><value><string>21</string></value></param>\n<param><value><int>10</int></value></param>\n<param><value><boolean>0</boolean></value></param>";
                break;
            case 'remove_attachment':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string>2</string></value></param>\n<param><value><string></string></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'token_register':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string></string></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'log_usage':
                document.getElementById('methodpayload').value = "<param><value><string>29</string></value></param>\n<param><value><boolean>0</boolean></value></param>\n<param><value><string>iPhone</string></value></param>\n<param><value><string>a58f1122b314ffa34471060bb7a7fdb0c1a49fbd</string></value></param>";
                break;
            
            case 'get_forums_by_device_id':
                document.getElementById('methodpayload').value = "<param><value><string>a58f1122b314ffa34471060bb7a7fd</string></value></param>";
                break;
            case 'delete_cloud_account':
                document.getElementById('methodpayload').value = "<param><value><string>90</string></value></param>\n<param><value><string>22</string></value></param>";
                break;
                
            case 'update_push_status':
                document.getElementById('methodpayload').value = "<param><value><struct>\n <member><name>ann</name><value><string>0</string></value></member>\n <member><name>pm</name><value><string>1</string></value></member>\n <member><name>sub</name><value><string>0</string></value></member>\n</struct></value></param>\n<param><value><base64>admin</base64></value></param>\n<param><value><base64>123321</base64></value></param>\n";
                break;
            
            case 'log_common_usage':
                document.getElementById('methodpayload').value = "<param><value><struct>"
+"\n <member><name>action</name><value><string>test</string></value></member>"
+"\n <member><name>device_id</name><value><string>1234567890</string></value></member>"
+"\n <member><name>device_type</name><value><string>android</string></value></member>"
+"\n <member><name>forum_id</name><value><string>90</string></value></member>"
+"\n <member><name>username</name><value><base64>test</base64></value></member>"
+"\n <member><name>latitude</name><value><string>1111</string></value></member>"
+"\n <member><name>longitude</name><value><string>222</string></value></member>"
+"\n <member><name>comment1</name><value><base64></base64></value></member>"
+"\n <member><name>comment2</name><value><base64></base64></value></member>"
+"\n</struct></value></param>";
break;
            
            case 'log_user_subs':
                document.getElementById('methodpayload').value = "<param><value><string>29</string></value></param>\n<param><value><string>2</string></value></param>\n<param><value><array><data>\n<value><string>10</string></value>\n<value><string>11</string></value>\n</data></array></value></param>";
                break;
            case 'log_pm':
            case 'log_conversation':
                document.getElementById('methodpayload').value = "<param><value><struct>"
+"\n <member><name>forum_id</name><value><string>90</string></value></member>"
+"\n <member><name>device_id</name><value><string>1234567890</string></value></member>"
+"\n <member><name>author_name</name><value><base64>test</base64></value></member>"
+"\n <member><name>author_id</name><value><string>3</string></value></member>"
+"\n <member><name>data_id</name><value><string>123</string></value></member>"
+"\n <member><name>title</name><value><base64></base64></value></member>"
+"\n <member><name>position</name><value><string>10</string></value></member>"
+"\n <member><name>uids</name><value><array><data>"
+"\n  <value><string>1</string></value><value><string>2</string></value>"
+"\n </data></array></value></member>"
+"\n <member><name>unames</name><value><array><data>"
+"\n  <value><base64>test</base64></value><value><base64>admin</base64></value>"
+"\n </data></array></value></member>"
+"\n</struct></value></param>";
break;
            case 'log_reply_post':
                document.getElementById('methodpayload').value = "<param><value><string>29</string></value></param>\n<param><value><string>a58f1122b314ffa34471060bb7a7fdb0c1a49fbd</string></value></param>\n<param><value><base64>test</base64></value></param>\n<param><value><string>2</string></value></param>\n<param><value><string>10</string></value></param>\n<param><value><base64></base64></value></param>\n<param><value><string>100</string></value></param>";
                break;
            case 'log_new_topic':
            case 'log_file_upload':
                document.getElementById('methodpayload').value = "<param><value><string>29</string></value></param>\n<param><value><string>a58f1122b314ffa34471060bb7a7fdb0c1a49fbd</string></value></param>";
                break;
            case 'get_conversation':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>\n<param><value><boolean>0</boolean></value></param>";
                break;
            case 'invite_participant':
                document.getElementById('methodpayload').value = "<param><value><array><data><value><base64></base64></value></data></array></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'new_conversation':
                document.getElementById('methodpayload').value = "<param><value><array><data><value><base64></base64></value></data></array></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'reply_conversation':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'mark_conversation_unread':
                document.getElementById('methodpayload').value = "<param><value><string>1</string></value></param>";
                break;
            case 'get_quote_conversation':
            case 'add_hottopic':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'delete_conversation':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string></string></value></param>\n<param><value><int></int></value></param>";
                break;
            case 'get_dashboard':
            case 'get_category':
                document.getElementById('methodpayload').value = "<param><value><boolean></boolean></value></param>";
                break;
            case 'get_forum':
                document.getElementById('methodpayload').value = "<param><value><boolean></boolean></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'get_post':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><boolean>1</boolean></value></param>";
                break;
            case 'get_forum_status':
                document.getElementById('methodpayload').value = "<param><value><array><data>\n<value><string>1</string></value>\n<value><string>2</string></value>\n</data></array></value></param>";
                break;
            case 'get_nested_category':
                document.getElementById('methodpayload').value = "<param><value><boolean>0</boolean></value></param>\n<param><value><string>german</string></value></param>";
                break;
            case 'get_forum_d':
                document.getElementById('methodpayload').value = "<param><value><int>90</int></value></param>";
                break;
            case 'get_alert' :
            	document.getElementById('methodpayload').value = "<param><value><int>1</int></value></param>\n<param><value><int>10</int></value></param>";
                break;
            case 'get_forums_by_id':
                document.getElementById('methodpayload').value = "<param><value><array><data><value><string>90</string></value><value><string>91</string></value></data></array></value></param>";
                break;
            case 'get_ads':
                document.getElementById('methodpayload').value = "<param><value><string>90</string></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'get_checkin':
                document.getElementById('methodpayload').value = "<param><value><int>90</int></value></param>\n<param><value><int>90</int></value></param>";
                break;
            case 'get_feed_checkin':
                document.getElementById('methodpayload').value = "<param><value><int>90</int></value></param>\n<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>";
                break;
            case 'get_user_checkin':
                document.getElementById('methodpayload').value = "<param><value><int>90</int></value></param>\n<param><value><base64></base64></value></param>\n<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>";
                break;
            case 'get_topic_status':
            case 'mark_topic_read':
                document.getElementById('methodpayload').value = "<param><value><array><data>\n<value><string></string></value>\n<value><string></string></value>\n</data></array></value></param>";
                break;
            case 'register':
                document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'get_rebranding':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'get_recommended':
                document.getElementById('methodpayload').value = "<param><value><array><data>\n<value><string>90</string></value>\n<value><string>91</string></value>\n</data></array></value></param>\n<param><value><array><data>\n<value><string>1</string></value>\n<value><string>2</string></value>\n</data></array></value></param>";
                break;
            case 'check_in':
                document.getElementById('methodpayload').value = "<param><value><int>90</int></value></param>\n"+
"<param><value><string>E3BEA1A0-7A57-58AE-A381-7CE3AABD4A76</string></value></param>\n"+
"<param><value><base64></base64></value></param>\n"+
"<param><value><base64></base64></value></param>\n"+
"<param><value><string></string></value></param>\n"+
"<param><value><string>31.2724609375</string></value></param>\n"+
"<param><value><string>121.46720123291</string></value></param>\n"+
"<param><value><base64></base64></value></param>\n"+
"<param><value><string>http://www.tapatalk.com/forum/image.php?u=189&amp;amp;dateline=1251042011&amp;amp;type=thumb</string></value></param>\n"+
"<param><value><string>Post</string></value></param>\n"+
"<param><value><base64></base64></value></param>\n"+
"<param><value><int>547234</int></value></param>";
                break;
            case 'get_user_image_count':
                document.getElementById('methodpayload').value = "<param><value><string>28ceb883b3514ffa006f945b07d7cbf5</string></value></param>\n\n<param><value><string>90</string></value></param>\n<param><value><base64></base64></value></param>";
                break;
                
            case 'prefetch_account':
                document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>";
                break;
            case 'search_user':
                document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>\n<param><value><int>1</int></value></param>\n<param><value><int>10</int></value></param>";
                break;
            case 'get_recommended_user':
                document.getElementById('methodpayload').value = "<param><value><int>1</int></value></param>\n<param><value><int>20</int></value></param>\n<param><value><int>1</int></value></param>";
                break;
            case 'sign_in':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string></string></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>\n<param><value><base64></base64></value></param>";
                break;
            
            case 'ignore_user':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><int>1</int></value></param>";
                break;

            case 'm_stick_topic':
            case 'm_close_topic':
            case 'm_approve_topic':
            case 'm_approve_post':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><int></int></value></param>";
                break;
            case 'm_close_report':
		document.getElementById('methodpayload').value = "<param><value><string></string></value></param>";
                break;
            case 'm_delete_topic':
            case 'm_delete_post':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><int></int></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'm_undelete_topic':
            case 'm_undelete_post':
            case 'm_delete_post_by_user':
            case 'm_rename_topic':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'm_move_topic':
            case 'm_merge_post':
            case 'm_merge_topic':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string></string></value></param>";
                break;
            case 'm_move_post':
                document.getElementById('methodpayload').value = "<param><value><string></string></value></param>\n<param><value><string></string></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'm_get_moderate_topic':
            case 'm_get_moderate_post':
            case 'm_get_delete_topic':
            case 'm_get_delete_post':
                document.getElementById('methodpayload').value = "<param><value><int>0</int></value></param>\n<param><value><int>9</int></value></param>";
                break;
            case 'm_get_delete_topic':
            case 'm_get_delete_post':
                document.getElementById('methodpayload').value = "<param><value><int></int></value></param>\n<param><value><int></int></value></param>";
                break;
            case 'm_ban_user':
                document.getElementById('methodpayload').value = "<param><value><base64></base64></value></param>\n<param><value><int></int></value></param>\n<param><value><base64></base64></value></param>";
                break;
            case 'get_contact':
		document.getElementById('methodpayload').value = "<param><value><string></string></value></param>";
                break;
            default:
                document.getElementById('methodpayload').value = "";
        }
    }


-->
</script>

<script language="JavaScript" type="text/javascript">
    var Base64=function() {}
    Base64.prototype.keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    
    Base64.prototype.encode = function (input) {
           input = escape(input);
           var output = "";
           var chr1, chr2, chr3 = "";
           var enc1, enc2, enc3, enc4 = "";
           var i = 0;
    
           do {
              chr1 = input.charCodeAt(i++);
              chr2 = input.charCodeAt(i++);
              chr3 = input.charCodeAt(i++);
    
              enc1 = chr1 >> 2;
              enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
              enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
              enc4 = chr3 & 63;
    
              if (isNaN(chr2)) {
                 enc3 = enc4 = 64;
              } else if (isNaN(chr3)) {
                 enc4 = 64;
              }
    
              output = output +
                 this.keyStr.charAt(enc1) +
                 this.keyStr.charAt(enc2) +
                 this.keyStr.charAt(enc3) +
                 this.keyStr.charAt(enc4);
              chr1 = chr2 = chr3 = "";
              enc1 = enc2 = enc3 = enc4 = "";
           } while (i < input.length);
    
           return output;
        }
    Base64.prototype.decode=function (input) {
           var output = "";
           var chr1, chr2, chr3 = "";
           var enc1, enc2, enc3, enc4 = "";
           var i = 0;
    
           // remove all characters that are not A-Z, a-z, 0-9, +, /, or =
           var base64test = /[^A-Za-z0-9\+\/\=]/g;
           if (base64test.exec(input)) {
              alert("There were invalid base64 characters in the input text.\n" +
                    "Valid base64 characters are A-Z, a-z, 0-9, '+', '/', and '='\n" +
                    "Expect errors in decoding.");
           }
           input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");
    
           do {
              enc1 = this.keyStr.indexOf(input.charAt(i++));
              enc2 = this.keyStr.indexOf(input.charAt(i++));
              enc3 = this.keyStr.indexOf(input.charAt(i++));
              enc4 = this.keyStr.indexOf(input.charAt(i++));
    
              chr1 = (enc1 << 2) | (enc2 >> 4);
              chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
              chr3 = ((enc3 & 3) << 6) | enc4;
    
              output = output + String.fromCharCode(chr1);
    
              if (enc3 != 64) {
                 output = output + String.fromCharCode(chr2);
              }
              if (enc4 != 64) {
                 output = output + String.fromCharCode(chr3);
              }
    
              chr1 = chr2 = chr3 = "";
              enc1 = enc2 = enc3 = enc4 = "";
    
           } while (i < input.length);
    
           return unescape(output);
        }
        
    function string_base64()
    {
        var only_string = document.frmaction.only_string.value;
        $.get( "base64.php", { str: only_string, mode: 'encode' }, function(data){
          document.frmaction.base64.value = data;
        });
    }
    
    function base64_string()
    {
        var base64 = document.frmaction.base64.value;
        $.get( "base64.php", { str: base64, mode: 'decode' }, function(data){
          document.frmaction.only_string.value = data;
        });
    }
        
</script>

</head>

<body onload="switchtransport(0); switchaction(); switchssl(); switchauth(); swicthcainfo();<?php if ($run) echo ' document.forms[2].submit();'; ?>">
<h1 style="display:none;">XMLRPC <form name="frmxmlrpc" style="display: inline;" action="."><input name="yes" type="radio"/></form>
/<form name="frmjsonrpc" style="display: inline;" action="."><input name="yes" type="radio"/></form>JSONRPC Debugger (based on the <a href="http://phpxmlrpc.sourceforge.net">PHP-XMLRPC</a> library)</h1>

<form name="frmaction" id="forum_action" enctype="multipart/form-data" method="post" action="action.php" target="frmaction" onSubmit="switchFormMethod();">

<table id="serverblock">
<tr>
<td>Forum Root: <input type="text" id="host" name="host" size="60" value="<?php echo htmlspecialchars($host); ?>" />
    <!--
    <label><input type="radio" name="plugin_path" onclick="switchpath('dz');"> DZ</label>
    <label><input type="radio" name="plugin_path" onclick="switchpath('dzx');"> DZX</label>
    <label><input type="radio" name="plugin_path" onclick="switchpath('pw');"> PW</label>
    -->
    <label><input type="radio" name="plugin_path" onclick="switchpath('dir');"> DIR</label>
    <label><input type="radio" name="plugin_path" onclick="switchpath('mod');"> MOD</label>
    <label><input type="radio" name="plugin_path" onclick="switchpath('other');" checked="checked"> Other</label>   
</td>
</tr>
</table>

<table id="actionblock" style="display:none;">
<tr>
<td><h2>Action</h2></td>
<td>List available methods<input type="radio" name="action" value="list"<?php if ($action=='list') echo ' checked="checked"'; ?> onclick="switchaction();" /></td>
<td>Describe method<input type="radio" name="action" value="describe"<?php if ($action=='describe') echo ' checked="checked"'; ?> onclick="switchaction();" /></td>
<td>Execute method<input type="radio" name="action" value="execute"<?php if ($action=='execute') echo ' checked="checked"'; ?> onclick="switchaction();" /></td>
<td>Generate stub for method call<input type="radio" name="action" value="wrap"<?php if ($action=='wrap') echo ' checked="checked"'; ?> onclick="switchaction();" /></td>
</tr>
</table>

<table id="optionsblock">
<tr>
<td>
    COOKIES: <input type="text" id="clientcookies" name="clientcookies" size="65" value="<?php echo htmlspecialchars($clientcookies); ?>" />
    <label><input type="checkbox" id="more" name="more" onclick="more_checked()"> More</label>
    <label><input type="checkbox" value="1" name="ttdebug"> Debug</label>
</td>
</tr>

<tr id="hide_more1" style="display:none;">
<td>
    Path: <input size="25" id="path" type="text" name="path" value="<?php echo htmlspecialchars($path); ?>" />
    Timeout: <input type="text" name="timeout" size="3" value="<?php if ($timeout > 0) echo $timeout; ?>" />
    Protocol: <select name="protocol" onchange="switchssl(); switchauth(); swicthcainfo();">
                <option value="0"<?php if ($protocol == 0) echo ' selected="selected"'; ?>>HTTP 1.0</option>
                <option value="1"<?php if ($protocol == 1) echo ' selected="selected"'; ?>>HTTP 1.1</option>
                <option value="2"<?php if ($protocol == 2) echo ' selected="selected"'; ?>>HTTPS</option>
             </select>
    Port: <input type="text" id="port" name="port" value="<?php echo htmlspecialchars($port); ?>" size="5" maxlength="5" />
    <label><input type="checkbox" value="1" name="remove_ct">No Content-Type</label>
</td>
</tr>
<tr id="hide_more2" style="display:none;">
<td>
    COMPRESSION:
    Request: <select name="requestcompression">
                <option value="0"<?php if ($requestcompression == 0) echo ' selected="selected"'; ?>>None</option>
                <option value="1"<?php if ($requestcompression == 1) echo ' selected="selected"'; ?>>Gzip</option>
                <option value="2"<?php if ($requestcompression == 2) echo ' selected="selected"'; ?>>Deflate</option>
             </select>
    Response: <select name="responsecompression">
                <option value="0"<?php if ($responsecompression == 0) echo ' selected="selected"'; ?>>None</option>
                <option value="1"<?php if ($responsecompression == 1) echo ' selected="selected"'; ?>>Gzip</option>
                <option value="2"<?php if ($responsecompression == 2) echo ' selected="selected"'; ?>>Deflate</option>
                <option value="3"<?php if ($responsecompression == 3) echo ' selected="selected"'; ?>>Any</option>
              </select>
    User-agent: <select name="customizeUA">
        <option value="0"<?php if ($customizeUA == 0) echo ' selected="selected"'; ?>>None</option>
        <option value="1"<?php if ($customizeUA == 1) echo ' selected="selected"'; ?>>Tapatalk</option>
        <option value="2"<?php if ($customizeUA == 2) echo ' selected="selected"'; ?>>BYO</option>
        <option value="3"<?php if ($customizeUA == 3) echo ' selected="selected"'; ?>>BYO-2(iOS)</option>
        <option value="4"<?php if ($customizeUA == 4) echo ' selected="selected"'; ?>>BYO-4(Android)</option>
      </select>
</td>
</tr>
</table>
<table style="display:none;">
<tr>
<td><h2>Client options</h2></td>
<td class="labelcell">Show debug info:</td><td style="display:none;"><select name="debug">
<option value="0"<?php if ($debug == 0) echo ' selected="selected"'; ?>>No</option>
<option value="1"<?php if ($debug == 1) echo ' selected="selected"'; ?>>Yes</option>
<option value="2"<?php if ($debug == 2) echo ' selected="selected"'; ?>>More</option>
</select>
</td>
</tr>

<tr>
<td class="labelcell">AUTH:</td>
<td class="labelcell">Username:</td><td><input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" /></td>
<td class="labelcell">Pwd:</td><td><input type="password" name="password" value="<?php echo htmlspecialchars($password); ?>" /></td>
<td class="labelcell">Type</td><td><select name="authtype">
<option value="1"<?php if ($authtype == 1) echo ' selected="selected"'; ?>>Basic</option>
<option value="2"<?php if ($authtype == 2) echo ' selected="selected"'; ?>>Digest</option>
<option value="8"<?php if ($authtype == 8) echo ' selected="selected"'; ?>>NTLM</option>
</select></td>
<td></td>
</tr>
<tr>
<td class="labelcell">SSL:</td>
<td class="labelcell">Verify Host's CN:</td><td><select name="verifyhost">
<option value="0"<?php if ($verifyhost == 0) echo ' selected="selected"'; ?>>No</option>
<option value="1"<?php if ($verifyhost == 1) echo ' selected="selected"'; ?>>Check CN existance</option>
<option value="2"<?php if ($verifyhost == 2) echo ' selected="selected"'; ?>>Check CN match</option>
</select></td>
<td class="labelcell">Verify Cert:</td><td><input type="checkbox" value="1" name="verifypeer" onclick="swicthcainfo();"<?php if ($verifypeer) echo ' checked="checked"'; ?> /></td>
<td class="labelcell">CA Cert file:</td><td><input type="text" name="cainfo" value="<?php echo htmlspecialchars($cainfo); ?>" /></td>
</tr>
<tr>
<td class="labelcell">PROXY:</td>
<td class="labelcell">Server:</td><td><input type="text" name="proxy" value="<?php echo htmlspecialchars($proxy); ?>" /></td>
<td class="labelcell">Proxy user:</td><td><input type="text" name="proxyuser" value="<?php echo htmlspecialchars($proxyuser); ?>" /></td>
<td class="labelcell">Proxy pwd:</td><td><input type="password" name="proxypwd" value="<?php echo htmlspecialchars($proxypwd); ?>" /></td>
</tr>

</table>

<input type="hidden" name="methodsig" value="<?php echo htmlspecialchars($methodsig); ?>" />


<table id="methodblock">
<tr>
<td>
    <div id="regular_method">
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_config" id="get_config" name="method" checked="checked" onclick="update_playload('get_config');"> get_config</label>
        </div>

        <div style="width:25%; float:left">
            <label><input type="radio" value="login" name="method" onclick="update_playload('login');"> login</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_thread" name="method" onclick="update_playload('get_thread');"> get_thread</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_topic" name="method" onclick="update_playload('get_topic');"> get_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_forum" name="method" onclick="update_playload('get_forum');"> get_forum</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="sign_in" name="method" onclick="update_playload('sign_in');"> sign_in</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_latest_topic" name="method" onclick="update_playload('get_latest_topic');"> get_latest_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_participated_topic" name="method" onclick="update_playload('get_participated_topic');"> get_participated_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_unread_topic" name="method" onclick="update_playload('get_unread_topic');"> get_unread_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_subscribed_topic" name="method" onclick="update_playload('get_subscribed_topic');"> get_subscribed_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_thread_by_unread" name="method" onclick="update_playload('get_thread_by_unread');"> get_thread_by_unread</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_thread_by_post" name="method" onclick="update_playload('get_thread_by_post');"> get_thread_by_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="create_message" name="method" onclick="update_playload('create_message');"> create_message</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="delete_conversation" name="method" onclick="update_playload('delete_conversation');"> delete_conversation</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="delete_message" name="method" onclick="update_playload('delete_message');"> delete_message</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_announcement" name="method" onclick="update_playload('get_announcement');"> get_announcement</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_alert" id="get_alert" name="method" checked="checked" onclick="update_playload('get_alert');"> get_alert</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_box" name="method" onclick="update_playload('get_box');"> get_box</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_box_info" name="method" onclick="update_playload('get_box_info');"> get_box_info</label>
        </div>

        
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_conversation" id="get_conversation" name="method" onclick="update_playload('get_conversation');"> get_conversation</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_conversations" id="get_conversations" name="method" onclick="update_playload('get_conversations');"> get_conversations</label>
        </div>

        <div style="width:25%; float:left">
            <label><input type="radio" value="get_forum_status" name="method" onclick="update_playload('get_forum_status');"> get_forum_status</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_inbox_stat" name="method" onclick="update_playload('get_inbox_stat');"> get_inbox_stat</label>
        </div>

        <div style="width:25%; float:left">
            <label><input type="radio" value="get_message" name="method" onclick="update_playload('get_message');"> get_message</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_new_topic" name="method" onclick="update_playload('get_new_topic');"> get_new_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_online_users" name="method" onclick="update_playload('get_online_users');"> get_online_users</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_participated_forum" name="method" onclick="update_playload('get_participated_forum');"> get_participated_forum</label>
        </div>

        <div style="width:25%; float:left">
            <label><input type="radio" value="get_post" name="method" onclick="update_playload('get_post');"> get_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_quote_pm" name="method" onclick="update_playload('get_quote_pm');"> get_quote_pm</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_quote_post" name="method" onclick="update_playload('get_quote_post');"> get_quote_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_quote_conversation" name="method" onclick="update_playload('get_quote_conversation');"> get_quote_conversation</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_raw_post" name="method" onclick="update_playload('get_raw_post');"> get_raw_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_recommended_user" name="method" onclick="update_playload('get_recommended_user');"> get_recommended_user</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_smilies" name="method" onclick="update_playload('get_smilies');"> get_smilies</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_subscribed_forum" name="method" onclick="update_playload('get_subscribed_forum');"> get_subscribed_forum</label>
        </div>


        <div style="width:25%; float:left">
            <label><input type="radio" value="get_topic_status" name="method" onclick="update_playload('get_topic_status');"> get_topic_status</label>
        </div>

        <div style="width:25%; float:left">
            <label><input type="radio" value="get_id_by_url" name="method" onclick="update_playload('get_id_by_url');"> get_id_by_url</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_user_info" name="method" onclick="update_playload('get_user_info');"> get_user_info</label>
        </div>
	<div style="width:25%; float:left">
            <label><input type="radio" value="get_contact" name="method" onclick="update_playload('get_contact');"> get_contact</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_user_reply_post" name="method" onclick="update_playload('get_user_reply_post');"> get_user_reply_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_user_topic" name="method" onclick="update_playload('get_user_topic');"> get_user_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="ignore_user" name="method" onclick="update_playload('ignore_user');"> ignore_user</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="invite_participant" name="method" onclick="update_playload('invite_participant');"> invite_participant</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="like_post" name="method" onclick="update_playload('like_post');"> like_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="unlike_post" name="method" onclick="update_playload('unlike_post');"> unlike_post</label>
        </div>

        <div style="width:25%; float:left">
            <label><input type="radio" value="register" name="method" onclick="update_playload('register');"> register</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="update_password" name="method" onclick="update_playload('update_password');"> update_password</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="update_email" name="method" onclick="update_playload('update_email');"> update_email</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="forget_password" name="method" onclick="update_playload('forget_password');"> forget_password</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="login_forum" name="method" onclick="update_playload('login_forum');"> login_forum</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="logout_user" name="method" onclick="update_playload('logout_user');"> logout_user</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="mark_all_as_read" name="method" onclick="update_playload('mark_all_as_read');"> mark_all_as_read</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="mark_conversation_unread" name="method" onclick="update_playload('mark_conversation_unread');"> mark_conv...ion_unread</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="mark_pm_unread" name="method" onclick="update_playload('mark_pm_unread');"> mark_pm_unread</label>
        </div>
	<div style="width:25%; float:left">
            <label><input type="radio" value="mark_pm_read" name="method" onclick="update_playload('mark_pm_read');"> mark_pm_read</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="mark_topic_read" name="method" onclick="update_playload('mark_topic_read');"> mark_topic_read</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="new_conversation" name="method" onclick="update_playload('new_conversation');"> new_conversation</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="new_topic" name="method" onclick="update_playload('new_topic');"> new_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="prefetch_account" name="method" onclick="update_playload('prefetch_account');"> prefetch_account</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="reply_conversation" name="method" onclick="update_playload('reply_conversation');"> reply_conversation</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="reply_post" name="method" onclick="update_playload('reply_post');"> reply_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="save_raw_post" name="method" onclick="update_playload('save_raw_post');"> save_raw_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="search" name="method" onclick="update_playload('search');"> search</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="search_post" name="method" onclick="update_playload('search_post');"> search_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="search_topic" name="method" onclick="update_playload('search_topic');"> search_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="search_user" name="method" onclick="update_playload('search_user');"> search_user</label>
        </div>

        <div style="width:25%; float:left">
            <label><input type="radio" value="subscribe_forum" name="method" onclick="update_playload('subscribe_forum');"> subscribe_forum</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="subscribe_topic" name="method" onclick="update_playload('subscribe_topic');"> subscribe_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="thank_post" name="method" onclick="update_playload('thank_post');"> thank_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="remove_thank_post" name="method" onclick="update_playload('remove_thank_post');"> remove_thank_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="unsubscribe_forum" name="method" onclick="update_playload('unsubscribe_forum');"> unsubscribe_forum</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="unsubscribe_topic" name="method" onclick="update_playload('unsubscribe_topic');"> unsubscribe_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="report_post" name="method" onclick="update_playload('report_post');"> report_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="report_pm" name="method" onclick="update_playload('report_pm');"> report_pm</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_board_stat" name="method" onclick="update_playload('get_board_stat');"> get_board_stat</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_home_data" name="method" onclick="update_playload('get_home_data');"> get_home_data</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_home" name="method" onclick="update_playload('get_home');"> get_home</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="authorize_user" name="method" onclick="update_playload('authorize_user');"> authorize_user</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="create_topic" name="method" onclick="update_playload('create_topic');"> create_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="reply_topic" name="method" onclick="update_playload('reply_topic');"> reply_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="attach_image" name="method" onclick="update_playload('attach_image');"> attach_image</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="remove_attachment" name="method" onclick="update_playload('remove_attachment');"> remove_attachment</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_dashboard" name="method" onclick="update_playload('get_dashboard');"> get_dashboard</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="digg_topic" name="method" onclick="update_playload('digg_topic');"> digg_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_friend_list" name="method" onclick="update_playload('get_friend_list');"> get_friend_list</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="register" name="method" onclick="update_playload('register');"> register</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="update_push_status" name="method" onclick="update_playload('update_push_status');"> update_push_status</label>
        </div>
    </div>
    <div id="dir_method" style="display:none;">
        <div style="width:25%; float:left">
            <label><input type="radio" value="add_hottopic" id="add_hottopic" name="method" onclick="update_playload('add_hottopic');"> add_hottopic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_category" id="get_category" name="method" onclick="update_playload('get_category');"> get_category</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_nested_category" id="get_nested_category" name="method" onclick="update_playload('get_nested_category');"> get_nested_category</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_new" name="method" onclick="update_playload('get_new');"> get_new</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_directory" name="method" onclick="update_playload('get_directory');"> get_directory</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_popular" name="method" onclick="update_playload('get_popular');"> get_popular</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_new_and_popular" name="method" onclick="update_playload('get_new_and_popular');"> get_new_and_popular</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_rebranding" name="method" onclick="update_playload('get_rebranding');"> get_rebranding</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_recommended" name="method" onclick="update_playload('get_recommended');"> get_recommended</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="search" name="method" onclick="update_playload('search');"> search</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_app_version" name="method" onclick="update_playload('get_app_version');"> get_app_version</label>
        </div>
        
        
        <div style="width:25%; float:left">
            <label><input type="radio" value="check_in" name="method" onclick="update_playload('check_in');"> check_in</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="delete_cloud_account" name="method" onclick="update_playload('delete_cloud_account');"> delete_cloud_account</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="log_common_usage" name="method" onclick="update_playload('log_common_usage');"> log_common_usage</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="log_conversation" name="method" onclick="update_playload('log_conversation');"> log_conversation</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="log_file_upload" name="method" onclick="update_playload('log_file_upload');"> log_file_upload</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="log_new_topic" name="method" onclick="update_playload('log_new_topic');"> log_new_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="log_pm" name="method" onclick="update_playload('log_pm');"> log_pm</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="log_reply_post" name="method" onclick="update_playload('log_reply_post');"> log_reply_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="log_usage" name="method" onclick="update_playload('log_usage');"> log_usage</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="log_user_subs" name="method" onclick="update_playload('log_user_subs');"> log_user_subs</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_forum" name="method" onclick="update_playload('get_forum_d');"> get_forum</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_forums_by_id" name="method" onclick="update_playload('get_forums_by_id');"> get_forums_by_id</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_forums_by_device_id" name="method" onclick="update_playload('get_forums_by_device_id');"> get_forums_by_device_id</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_ads" name="method" onclick="update_playload('get_ads');"> get_ads</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_checkin" name="method" onclick="update_playload('get_checkin');"> get_checkin</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_feed_checkin" name="method" onclick="update_playload('get_feed_checkin');"> get_feed_checkin</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_user_checkin" name="method" onclick="update_playload('get_user_checkin');"> get_user_checkin</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="get_user_image_count" name="method" onclick="update_playload('get_user_image_count');"> get_user_image_count</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="check_status" name="method" onclick="update_playload('check_status');"> check_status</label>
        </div>
        
        <div style="width:25%; float:left">
            <label><input type="radio" value="token_register" name="method" onclick="update_playload('token_register');"> token_register</label>
        </div>
        
    </div>
    <div id="mod_method" style="display:none;">
        <div style="width:25%; float:left">
            <label><input type="radio" value="login_mod" name="method" onclick="update_playload('login_mod');"> login_mod</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_stick_topic" name="method" onclick="update_playload('m_stick_topic');"> m_stick_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_close_topic" name="method" onclick="update_playload('m_close_topic');"> m_close_topic</label>
        </div>
	<div style="width:25%; float:left">
            <label><input type="radio" value="m_close_report" name="method" onclick="update_playload('m_close_report');"> m_close_report</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_delete_topic" name="method" onclick="update_playload('m_delete_topic');"> m_delete_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_delete_post" name="method" onclick="update_playload('m_delete_post');"> m_delete_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_undelete_topic" name="method" onclick="update_playload('m_undelete_topic');"> m_undelete_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_undelete_post" name="method" onclick="update_playload('m_undelete_post');"> m_undelete_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_delete_post_by_user" name="method" onclick="update_playload('m_delete_post_by_user');"> m_delete_post_by_user</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_move_topic" name="method" onclick="update_playload('m_move_topic');"> m_move_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_move_post" name="method" onclick="update_playload('m_move_post');"> m_move_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_merge_post" name="method" onclick="update_playload('m_merge_post');"> m_merge_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_merge_topic" name="method" onclick="update_playload('m_merge_topic');"> m_merge_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_get_moderate_topic" name="method" onclick="update_playload('m_get_moderate_topic');"> m_get_moderate_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_get_moderate_post" name="method" onclick="update_playload('m_get_moderate_post');"> m_get_moderate_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_get_delete_topic" name="method" onclick="update_playload('m_get_delete_topic');"> m_get_delete_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_get_delete_post" name="method" onclick="update_playload('m_get_delete_post');"> m_get_delete_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_get_report_post" name="method" onclick="update_playload('m_get_report_post');"> m_get_report_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_get_report_pm" name="method" onclick="update_playload('m_get_report_pm');"> m_get_report_pm</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_approve_topic" name="method" onclick="update_playload('m_approve_topic');"> m_approve_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_approve_post" name="method" onclick="update_playload('m_approve_post');"> m_approve_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_get_delete_topic" name="method" onclick="update_playload('m_get_delete_topic');"> m_get_delete_topic</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_get_delete_post" name="method" onclick="update_playload('m_get_delete_post');"> m_get_delete_post</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_ban_user" name="method" onclick="update_playload('m_ban_user');"> m_ban_user</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_mark_as_spam" name="method" onclick="update_playload('m_mark_as_spam');"> m_mark_as_spam</label>
        </div>
        <div style="width:25%; float:left">
            <label><input type="radio" value="m_rename_topic" name="method" onclick="update_playload('m_rename_topic');"> m_rename_topic</label>
        </div>
    </div>
    <div><input type="button" id="execute" value="Execute"  style="margin-top:5px;margin-right:40px;float:right"/></div>
</td>
</tr>

<tr>
<td><textarea id="methodpayload" name="methodpayload" cols="80" rows="6"><?php echo htmlspecialchars($payload); ?></textarea><br/>
<input type="file" name="fileupload" /> forum_id : <input type="text" name="forum_id" value=""/> group_id : <input type="text" name="group_id" value=""/>
<input type="hidden" name="method_name" value="upload_attach"/>
</td>
</tr>
</table>
    
<table><tr><td>
<textarea name="only_string" cols="80" rows="2"></textarea><br /><br />
<input type="button" value="To Base64" onclick="string_base64()"> <input type="button" value="To String" onclick="base64_string()"><br /><br />
<textarea name="base64" cols="80" rows="2"></textarea>
</td></tr></table>
</form>
</body>