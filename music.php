<?php
/**
 * Created by PhpStorm.
 * User: vtnil<jydsliu@gmail.com>
 * Date: 15-7-31
 * Time: 下午2:57
 */
require(__DIR__ . '/lib/server.class.php');
require(__DIR__ . '/lib/player.class.php');
require(__DIR__ . '/lib/mp3file.class.php');

//$fp=fopen(__DIR__.'/data/2.mp3','rb');
//$str=fread($fp,8096);
//file_put_contents(__DIR__.'/data/1',$str);
//fclose($fp);





//$player = new player();
//$url = '';
//$xiamiUrl = 'http://www.xiami.com/song/playlist/id/1773571331';
//print_r($player->getStream('http://m5.file.xiami.com/949/90949/1311688232/1773571331_15971564_l.mp3?auth_key=7016aabf1550cb0eccfa7222c934183e-1438732800-0-null',1024));
//exit;
//
//$ret = $player->getCurl($xiamiUrl);
//$argv = simplexml_load_string($ret, 'SimpleXMLElement', LIBXML_NOCDATA);
//$mp3 = $player->de_Location($argv->trackList->track->location);
//$player->getCurl($xiamiUrl,true);
//
//echo $mp3;
//
//exit;
//$obj=new mp3file(__DIR__.'/data/1');
//$meta=$obj->get_metadata();
//print_r($obj->getframesize($meta));
//exit;







register_shutdown_function('handleFatal');
function handleFatal()
{
	$error = error_get_last();
	if (isset($error['type']))
	{
		switch ($error['type'])
		{
			case E_ERROR :
			case E_PARSE :
			case E_DEPRECATED:
			case E_CORE_ERROR :
			case E_COMPILE_ERROR :
				$message = $error['message'];
				$file = $error['file'];
				$line = $error['line'];
				$log = "$message ($file:$line)\nStack trace:\n";
				$trace = debug_backtrace();
				foreach ($trace as $i => $t)
				{
					if (!isset($t['file']))
					{
						$t['file'] = 'unknown';
					}
					if (!isset($t['line']))
					{
						$t['line'] = 0;
					}
					if (!isset($t['function']))
					{
						$t['function'] = 'unknown';
					}
					$log .= "#$i {$t['file']}({$t['line']}): ";
					if (isset($t['object']) && is_object($t['object']))
					{
						$log .= get_class($t['object']) . '->';
					}
					$log .= "{$t['function']}()\n";
				}
				if (isset($_SERVER['REQUEST_URI']))
				{
					$log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
				}
				error_log($log);
		}
	}
}

$radio = new Server();
$radio->start();
