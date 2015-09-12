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
$server = $_SERVER['SERVER_ADDR'].':'. conf::$config['server']['port'];

if(isset($_GET['ajax'])){
	$web = new web();
	$ret = $web->run($_GET['ajax']);
	exit($ret);
}
include ROOT.'/tpl/index.phtml';