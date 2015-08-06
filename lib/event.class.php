<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-5
 * Time: 下午12:52
 */
abstract class baseEvent{
	protected $class;
	protected $method;

	abstract function open(swoole_websocket_server $server, swoole_http_request $request);
	abstract function message(swoole_websocket_server $server, $frame);
	abstract function close(swoole_websocket_server $server, $fd);


	/**
	 * 发送广播
	 * @param                         $badge 请求包
	 * @param swoole_websocket_server $server 服务worker
	 */
	function broadcast($badge,swoole_websocket_server $server){
	}

	/**
	 * 记录事件日志
	 * @param $className
	 * @param $msg
	 */
	function eventLog($className,$msg){
		$logPath = ROOT .'/data/event.log';
		$msgFormat = '['.date('Y-m-d H:i:s').'] '.$className .'->' .$msg. ' Mem'.$this->convert(memory_get_usage(true)) . PHP_EOL;
		file_put_contents($logPath,$msgFormat,FILE_APPEND);
		echo $msg.PHP_EOL;
	}
	private function convert($size){
		$unit=array('b','kb','mb','gb','tb','pb');
		return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
	}
}

class event extends Server {
	public static $on_list=[];
	private $includeList = [];
	private static $init = [];
	private $server;

	const EVENT_OPEN = 'open';
	const EVENT_MESSAGE = 'message';
	const EVENT_CLOSE = 'close';

	function __construct(){
		$dir = ROOT . '/event';
		$list = scandir($dir);
		foreach($list as $d){
			if(!isset($this->includeList[$d])){
				if(substr($d,0,1) != '.'){
					include($dir.'/'.$d);
					$this->serverLog('初始化事件模块:'.$dir.'/'.$d);
				}
			}
		}
	}



	/**
	 * 给事件注册调用
	 * @param $event
	 * @param $callName
	 */
	public static function onAdd($event,$callName){
		self::$on_list[$event][] = $callName;
	}



	function eventOpen($_server,$request){
		if(self::$on_list['open']){
			foreach(self::$on_list['open'] as  $class){
				echo 'call event open :'.$class.PHP_EOL;
				if(!isset(self::$init[$class])){
					self::$init[$class] = new $class();
				}
				call_user_func_array([self::$init[$class],'open'],[$_server,$request]);
			}
		}
	}

	function eventMessage(swoole_websocket_server $_server, $frame){
		if(self::$on_list['message']){
			foreach(self::$on_list['message'] as $class){
				echo 'call event message :'.$class.PHP_EOL;
				if(!isset(self::$init[$class])){
					self::$init[$class] = new $class();
				}
				call_user_func_array([self::$init[$class],'message'],[$_server,$frame]);
			}
		}
	}

	function eventClose(swoole_websocket_server $_server, $fd){
		if(self::$on_list['message']){
			foreach(self::$on_list['message'] as $class){
				echo 'call event message :'.$class.PHP_EOL;
				if(!isset(self::$init[$class])){
					self::$init[$class] = new $class();
				}
				call_user_func_array([self::$init[$class],'close'],[$_server,$fd]);
			}
		}
	}
}