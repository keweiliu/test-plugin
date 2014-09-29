<?php

defined('MBQ_IN_IT') or exit;

define('MBQ_DS', DIRECTORY_SEPARATOR);
define('MBQ_PATH', dirname($_SERVER['SCRIPT_FILENAME']).MBQ_DS);    /* mobiquo path */
define('MBQ_DIRNAME', basename(MBQ_PATH));    /* mobiquo dir name */
define('MBQ_PARENT_PATH', substr(MBQ_PATH, 0, strrpos(MBQ_PATH, MBQ_DIRNAME.MBQ_DS)));    /* mobiquo parent dir path */
define('MBQ_FRAME_PATH', MBQ_PATH.'mbqFrame'.MBQ_DS);    /* frame path */
require_once(MBQ_FRAME_PATH.'MbqBaseConfig.php');

//$_SERVER['SCRIPT_FILENAME'] = str_replace(MBQ_DIRNAME.'/', '', $_SERVER['SCRIPT_FILENAME']);  /* Important!!! */
//$_SERVER['PHP_SELF'] = str_replace(MBQ_DIRNAME.'/', '', $_SERVER['PHP_SELF']);  /* Important!!! */
//$_SERVER['SCRIPT_NAME'] = str_replace(MBQ_DIRNAME.'/', '', $_SERVER['SCRIPT_NAME']);    /* Important!!! */
//$_SERVER['REQUEST_URI'] = str_replace(MBQ_DIRNAME.'/', '', $_SERVER['REQUEST_URI']);    /* Important!!! */
$_SERVER['SCRIPT_FILENAME'] = str_replace('\\', '/', MBQ_PARENT_PATH.'mobiquo.php');
$_SERVER['PHP_SELF'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']);
$_SERVER['SCRIPT_NAME'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']);
$_SERVER['REQUEST_URI'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']);

/**
 * plugin config
 * 
 * @since  2012-7-2
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqConfig extends MbqBaseConfig {

    public function __construct() {
        parent::__construct();
        require_once(MBQ_CUSTOM_PATH.'customDetectJs.php');
        $this->initCfg();
    }
    
    /**
     * init cfg default value
     */
    protected function initCfg() {
        parent::initCfg();
    }
    
    /**
     * calculate the final config of $this->cfg through $this->cfg default value and MbqMain::$customConfig and MbqMain::$oMbqAppEnv and the plugin support degree
     */
    public function calCfg() {
        /* load custom config from sites custom folder */
        if (MbqMain::$oMbqAppEnv->config['tapatalk']) {
            foreach (MbqMain::$oMbqAppEnv->config['tapatalk'] as $k => $v) {
                $ks = explode('.', $k);
                $leftCode = 'MbqMain::$customConfig';
                foreach ($ks as $kPiece) {
                    $leftCode .= "['$kPiece']";
                }
                $vs = explode('.', $v);
                if (count($vs) > 1) {
                    $rightCode = "MbqBaseFdt::getFdt('$v')";
                } else {
                    if (is_string($v)) {
                        $rightCode = "'$v'";
                    } else {
                        $rightCode = $v;
                    }
                }
                $code = $leftCode . ' = ' . $rightCode . ';';
                if (strpos($k, '(') === false && strpos($k, ')') === false && strpos($k, '{') === false && strpos($k, '}') === false && strpos($k, ';') === false && strpos($v, '(') === false && strpos($v, ')') === false && strpos($v, '{') === false && strpos($v, '}') === false && strpos($v, ';') === false) {   //prevent invalid code or attack code
                    eval($code);
                } else {
                    MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . 'May find invalid code in:'.$code);
                }
            }
        }
        parent::calCfg();
      /* calculate the final config */
        $this->cfg['base']['sys_version']->setOriValue('Unknown');
        $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('settings', array('echoError' => false));
        if (MbqMain::$oMbqAppEnv->exttApiHasError($apiResult)) {
            $this->cfg['base']['is_open']->setOriValue(MbqBaseFdt::getFdt('MbqFdtConfig.base.is_open.range.no'));
        } else {
            $this->cfg['base']['is_open']->setOriValue(MbqBaseFdt::getFdt('MbqFdtConfig.base.is_open.range.yes'));
        }
    }
    
}

?>