<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-3
 * Time: 下午6:32
 */


$play_time = new swoole_atomic(0);
$online_count = new swoole_atomic(0);


$memTable = new swoole_table(1024);
$memTable->column('v',swoole_table::TYPE_STRING,65535);
$memTable->create();

/**
 * 数据共享存取
 * @param        $key
 * @param string $v
 */
function shareAccess($key,$v=false){
	global $memTable;
	$access = '';

	if($res = $memTable->get($key)){
		$access = unserialize($res['v']);
	}
	if($v !== false){
		serverLog('put key:'.$key.' <-> '. json_encode($v));
		return $memTable->set($key,['v'=>serialize($v)]);
	}
	if($access){
		serverLog('getKey:'.$key.' <-> '. json_encode($access));
		return $access;
	}
	return false;
}


function serverLog($msg){
	$logPath = ROOT .'/data/server.log';
	$msgFormat = '['.date('Y-m-d H:i:s').'] '.$msg.' Memusage'.convert(memory_get_usage(true)) . PHP_EOL;
	file_put_contents($logPath,$msgFormat,FILE_APPEND);
	//echo $msg.PHP_EOL;
}


function convert($size){
	$unit=array('B','KB','MB','GB','TB','PB','EB');
	return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}

class conf{

    public static $config;
    function __construct(){
        if(isset($_SERVER['argv'][1])){
            if(file_exists(ROOT.'/conf/'. $_SERVER['argv'][1])){
                self::$config = parse_ini_file(ROOT.'/conf/'. $_SERVER['argv'][1],true);
            }else{
                throw new Exception('configure '.$_SERVER['argv'][1].' is not exists');
            }
        }else{
            self::$config = parse_ini_file( ROOT.'/conf/default.ini',true);
        }

    }
}





class limit{
	static function verify($key,$time){
		$res = shareAccess($key);
		if($res){
			if($res + $time > time()){
				return false;
			}else{
				return true;
			}
		}
		return true;
	}

	static function keep($key){
		shareAccess($key,time());
	}
}



class event  {
	public static $on_list=[];
	private static $init = [];

	const EVENT_OPEN = 'open';
	const EVENT_MESSAGE = 'message';
	const EVENT_CLOSE = 'close';
	/**
	 * 给事件注册调用
	 * @param $event
	 * @param $callName
	 */
	public static function add($event,$callName){
		if(!isset(self::$on_list[$event])){
			self::$on_list[$event] = [];
		}
		array_push(self::$on_list[$event],$callName);
	}

	function eventOpen(){

	}

	function eventMessage(swoole_websocket_server $_server, $frame){
		if(self::$on_list['message']){
			foreach(self::$on_list['message'] as $class){
				//echo 'call event message :'.$class.PHP_EOL;
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



class cmd{

	public $method;
	public $args;
	protected $server;
	protected $frame;
	function __construct(swoole_websocket_server $_server, $frame){
		$badge = isset($frame->data) ? Server::badgeDecode($frame->data):"";
		if($badge){
			list($controller,$method) = explode('|',$badge->act);
			$this->controller = $controller;
			$this->method = $method;
			$this->args = isset($badge->data) ? $badge->data : "";
		}
		$this->server = $_server;
		$this->frame = $frame;
	}

	/**
	 * Message流程
	 */
	function message(){
		if(method_exists($this,$this->method.'_cmd')){
			call_user_func([$this,$this->method.'_cmd']);
		}else{
			serverLog('action :'.$this->controller .'/'. $this->method . '_cmd not found');
		}
	}

	/**
	 * Task流程初始化
	 */
	function task(){
		if(method_exists($this,$this->method.'_task')){
			call_user_func([$this,$this->method.'_task']);
		}else{
			serverLog('action :'.$this->controller .'/'. $this->method . '_task not found');
		}
	}

	function broadcast($badge){
		$this->server->task($badge);
	}

	function push($fd,$badge){
		$this->server->push($fd ,$badge);
	}

}

//event::add(event::EVENT_OPEN,['user','open']);


function mime_content($filename) {

	$mime_types = array(
		'txt' => 'text/plain',
		'htm' => 'text/html',
		'html' => 'text/html',
		'php' => 'text/html',
		'css' => 'text/css',
		'js' => 'application/javascript',
		'json' => 'application/json',
		'xml' => 'application/xml',
		'swf' => 'application/x-shockwave-flash',
		'flv' => 'video/x-flv',

		// images
		'png' => 'image/png',
		'jpe' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'ico' => 'image/vnd.microsoft.icon',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',
		'svg' => 'image/svg+xml',
		'svgz' => 'image/svg+xml',

		// archives
		'zip' => 'application/zip',
		'rar' => 'application/x-rar-compressed',
		'exe' => 'application/x-msdownload',
		'msi' => 'application/x-msdownload',
		'cab' => 'application/vnd.ms-cab-compressed',

		// audio/video
		'mp3' => 'audio/mpeg',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',

		// adobe
		'pdf' => 'application/pdf',
		'psd' => 'image/vnd.adobe.photoshop',
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'ps' => 'application/postscript',

		// ms office
		'doc' => 'application/msword',
		'rtf' => 'application/rtf',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',

		// open office
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	);

	$_file = explode('.',$filename);
	$ext = strtolower(array_pop($_file));
	if (array_key_exists($ext, $mime_types)) {
		return $mime_types[$ext];
	}
	elseif (function_exists('finfo_open')) {
		$finfo = finfo_open(FILEINFO_MIME);
		$mimetype = finfo_file($finfo, $filename);
		finfo_close($finfo);
		return $mimetype;
	}
	else {
		return 'application/octet-stream';
	}
}
