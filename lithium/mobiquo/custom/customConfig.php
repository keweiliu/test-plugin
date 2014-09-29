<?php

defined('MBQ_IN_IT') or exit;

/**
 * user custom config,to replace some config of MbqMain::$oMbqConfig->cfg.
 * you can change any config if you need,please refer to MbqConfig.php for more details.
 * 
 * @since  2012-7-19
 * @author Wu ZeTao <578014287@qq.com>
 */
MbqMain::$customConfig['base']['is_open'] = MbqBaseFdt::getFdt('MbqFdtConfig.base.is_open.range.yes');
MbqMain::$customConfig['base']['version'] = 'dev';
MbqMain::$customConfig['base']['api_level'] = 3;

MbqMain::$customConfig['forum']['report_post'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.report_post.range.support');
MbqMain::$customConfig['forum']['goto_post'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.report_post.range.support');
MbqMain::$customConfig['forum']['goto_unread'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.goto_unread.range.support');

?>