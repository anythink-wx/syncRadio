<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15/9/11
 * Time: 下午7:26
 */
define('ROOT',substr(__DIR__,0,-7));
$config = 'server.ini';
require(__DIR__ . '/../lib/player.class.php');
require(__DIR__ . '/../lib/web.class.php');
require(__DIR__ . '/../lib/functions.php');
new conf();
if(isset(conf::$config['web']['socket'])){
	$server = conf::$config['web']['socket'].':'. conf::$config['server']['port'];
}else{
	$server = $_SERVER['REMOTE_ADDR'].':'. conf::$config['server']['port'];
}

$server = !empty(conf::$config['web']['socket']) ? conf::$config['web']['socket'] : $_SERVER['REMOTE_ADDR'];
$server = $server .':'. conf::$config['server']['port'];
if(isset($_GET['ajax']) && $_GET['ajax'] != '' ){
	$web = new web();
	$ret = $web->run($_GET['ajax']);
	exit($ret);
}
include ROOT.'/tpl/online.phtml';