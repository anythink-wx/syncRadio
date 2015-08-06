<?php
/**
 * Created by PhpStorm.
 * User: vtnil<jydsliu@gmail.com>
 * Date: 15-7-31
 * Time: 下午2:57
 */
define('ROOT',__DIR__);
require(__DIR__ . '/lib/server.class.php');
require(__DIR__ . '/lib/player.class.php');
require(__DIR__ . '/lib/mp3file.class.php');
require(__DIR__ . '/lib/event.class.php');

$radio = new Server();
$radio->init();
$radio->start();
