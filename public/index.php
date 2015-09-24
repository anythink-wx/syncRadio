<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15/9/11
 * Time: 下午7:26
 */
define('ROOT',substr(__DIR__,0,-7));
require(__DIR__ . '/../lib/player.class.php');
require(__DIR__ . '/../lib/web.class.php');
require(__DIR__ . '/../lib/functions.php');
require(__DIR__ . '/../lib/sqlite.class.php');

$config = shareAccess('config');
$server = isset($config['web']['socket']) ? $config['web']['socket'] :  $_SERVER['REMOTE_ADDR'];
$server .= ':'. $config['server']['port'];
if($_SERVER['QUERY_STRING']){
	$ret = (new web)->run();
	exit($ret);
}

if(!$config){
	include ROOT.'/tpl/stop.phtml';
}else{
	include ROOT.'/tpl/online.phtml';
}
