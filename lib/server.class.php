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
		$server->on('request',[$this,'onRequest']);
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
		kv::play_time(0); //剩余时间
        kv::play_end(0); //播放停止

		$this->process = new swoole_process(function(swoole_process $worker){
			$play_id = 0;
			$playTime = 0;
			swoole_set_process_name("syncRadio : player");
			$player = new player();
			$id = $player->shiftMusicList();
			$data = player::getPlayUrl($id);
			while(true){
				if(kv::play_id() == 0 ){
					kv::play_id($id);
					kv::play_time($data['length']);


				}elseif(kv::play_time() <= 0){
					$id = $player->shiftMusicList();
					if($id){
						$data = player::getPlayUrl($id);
						kv::play_id($id);
						kv::play_time($data['length']);
					}else{
                        kv::play_end(1);
						echo '没歌啦'.PHP_EOL;
					}
				}elseif(kv::play_time() >=5 and kv::play_time() <=10){
                    //
                    $id = $player->list[0];
                    $data = player::getPlayUrl($id);
                    echo '预下载'.$id.PHP_EOL;
                    sleep(6);
                }
				sleep(1);
			}
		});
		$server->addProcess($this->process);
	}

	



	function onOpen(swoole_websocket_server $_server, swoole_http_request $request)	{

		//判断是否启动播放服务
		if (kv::online() == 0 && $this->playerFlag == -1) {
			$this->playerFlag = swoole_timer_tick(1000,[$this,'onPlay']);
			$this->serverLog('播放模块已启动,模块ID：'.$this->playerFlag);
		}

		//加载open事件模块
		$this->event->eventOpen($_server,$request);
	}


	function onRequest(swoole_http_request $request, swoole_http_response $response){
		$path_info =  $request->server['path_info'];
		if($path_info == '/'){
			ob_start();
			include ROOT.'/public/index.html';
			$content = ob_get_clean();
			$response->end($content);
		}else{
			$static = ROOT .'/public'. $path_info;
			if(is_file($static)){
				$response->end(file_get_contents($static));
			}else{
				$response->end();
			}
		}
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
		$play_id = kv::play_id();
		$play_time = kv::play_time();


		if($play_id !=0){
			if($play_time > 0){
				$play_time-=1;
				kv::play_time($play_time);
				$this->serverLog('正在播放 '.$play_id.'剩余 '.$play_time);
				if($play_time %10 ==0){
					//$this->serverLog('正在播放 剩余 '.$play_time);
				}

			}else{
				kv::play_time(0);
			}

		}else{
			kv::play_time(0);
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