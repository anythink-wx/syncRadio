<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-3
 * Time: 下午6:32
 */


class kv {
	public static function __callStatic($name, $arguments)	{
		if(empty($arguments)){
			return self::shareGet($name);
		}else{
			return self::sharePut($name,$arguments[0]);
		}
	}

	static function sharePut($k,$v){
		$file = ROOT.'/data/'.$k;
		file_put_contents($file,serialize($v),LOCK_EX);
	}

	static function shareGet($k){
		$file = ROOT.'/data/'.$k;
		return unserialize(file_get_contents($file));
	}
}

class Server {

	public $online = 0; // 在线用户数
	protected $playerFlag = -1;             //播放服务状态
	protected $processFlag = -1;

	/**
	 * player 实例
	 * @var player
	 */
	private $player;
	/**
	 * 事件实例
	 * @var event
	 */
	private $event;


	/**
	 * 当前播放曲目
	 * @var int
	 */
	public $playId = 0;

	/**
	 * 当前剩余时间
	 * @var int
	 */
	public $playTime = 0;


	private $server;
	private $process;


	function init(){
		swoole_set_process_name('syncRadio');
		$server = new swoole_websocket_server("0.0.0.0", 8810);
		$server->on('open', [$this, 'onOpen']);
		$server->on('message', [$this, 'onMessage']);
		$server->on('close', [$this, 'onClose']);
		$server->set([
			'reactor_num' => 2, //核心数
			'worker_num' => 20,    //进程数量
			'backlog' => 128,   //参数将决定最多同时有多少个待accept的连接
			'max_request' => 2000, //worker进程在处理完n次请求后结束运行。
		]);


		$this->server = $server;
		$this->player = new player();
		$this->event  = new event();

		kv::online(0); //初始化在线统计
		kv::user([]);  //初始化在线列表
		kv::play_id(0); //当前播放数

		$this->process = new swoole_process(function(swoole_process $worker){
			$play_id = 0;
			$playTime = 0;
			swoole_set_process_name("syncRadio : player");
			$player = new player();
			$id = $player->list[0];
			$data = player::getPlayUrl($id);
			while(true){
				if(kv::play_id() == 0 ){
					$data['play_id'] = $id;
					$data['playTime'] = $data['length'];
					$worker->write(json_encode($data));
					kv::play_id($data['play_id']);
				}
				sleep(1);
			}
		});
		$server->addProcess($this->process);
	}

	function onProcess(){
		if($data = $this->process->read()){
			$data = json_decode($data,true);
			kv::play_id($data['play_id']);

			$this->playId = $data['play_id'];
			$this->playTime = $data['length'];

			print_r($data);
			//$data =  json_decode($data);
			//kv::play_id($data['play_id']);
		}else{
			echo 'proess is no message send'.PHP_EOL;
		}

	}



	function onOpen(swoole_websocket_server $_server, swoole_http_request $request)	{
		if($this->processFlag == -1){
		//	$this->processFlag = swoole_timer_tick(1000,[$this,'onProcess']);
		}

		//判断是否启动播放服务
		if (kv::online() == 0 && $this->playerFlag == -1) {
			$this->playerFlag = swoole_timer_tick(1000,[$this,'onPlay']);
			$this->serverLog('播放模块已启动,模块ID：'.$this->playerFlag);
		}

		//加载open事件模块
		$this->event->eventOpen($_server,$request);
	}




	function onMessage(swoole_websocket_server $_server, $frame){
		$frame->playId = $this->playId;
		$frame->playTime = $this->playTime;

		$this->event->eventMessage($_server,$frame);
	}



	function onClose(swoole_websocket_server $_server, $fd){

		if (kv::online() <= 0 && $this->playerFlag != -1) {
			swoole_timer_clear($this->playerFlag);
			$this->serverLog("播放模块已停止工作");
			$this->playerFlag = -1;
			$this->playId=0;
		}

		$this->event->eventClose($_server,$fd);
	}

	function onPlay(){

		echo kv::play_id();
		echo 'play id ';
		print_r($this->playId);
		echo 'play time ';
		print_r($this->playTime);

		if($this->playId !=0){
			$this->playTime--;
			$this->serverLog('正在播放 剩余 '.$this->playTime);
			if($this->playTime %10 ==0){
				$this->serverLog('正在播放 剩余 '.$this->playTime);
			}

			if($this->playTime <0){
				$this->playId = 0;
				$this->playTime = 0;
				kv::play_id(0);
			}

		}else{
			$this->playId = 0;
			$this->serverLog('当前曲目播放完毕 '.$this->playId);
		}
	}


	static function badge($action,$message){
		return json_encode(['act'=>$action,'data'=>$message]);
	}
	static function badgeDecode($str){
		$str = json_decode($str);
		return $str;
	}

	function serverLog($msg){
		$logPath = ROOT .'/data/server.log';
		if(method_exists($this,'stats')){
			$stats = $this->server->stats();
			$msgFormat = '['.date('Y-m-d H:i:s').'] '.$msg.
				' Status: connect '.$stats['connection_num'].', accept '.$stats['accept_count'].
				', close '.$stats['close_count'].', task '.$stats['tasking_num'] .
				' Memusage'.$this->convert(memory_get_usage(true)) . PHP_EOL;
		}else{
			$msgFormat = '['.date('Y-m-d H:i:s').'] '.$msg.' Memusage'.$this->convert(memory_get_usage(true)) . PHP_EOL;
		}

		file_put_contents($logPath,$msgFormat,FILE_APPEND);
		echo $msg.PHP_EOL;
	}

	private function convert($size){
		$unit=array('b','kb','mb','gb','tb','pb');
		return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
	}

	function start(){
		$this->server->start();
	}
}