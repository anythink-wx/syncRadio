<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-3
 * Time: 下午6:32
 */
class Server {

	protected $online  = 0;                // 在线用户数
	protected $playerFlag = -1;             //播放服务状态

	/**
	 * @var swoole_websocket_server
	 */
	private $server;
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

	function __construct(){
		$server = new swoole_websocket_server("0.0.0.0", 8810);
		$server->on('open', [$this, 'onOpen']);
		$server->on('message', [$this, 'onMessage']);
		$server->on('close', [$this, 'onClose']);

		$this->server = $server;
		$this->player = new player();
		$this->event  = new event();
	}



	function onOpen(swoole_websocket_server $_server, swoole_http_request $request)	{
		if ($this->online == 0 && $this->playerFlag == -1) {

			$this->playerFlag = swoole_timer_tick(1000,[$this,'onPlay']);
			echo '播放模块已启动,模块ID：'.$this->playerFlag.PHP_EOL;
		}
		$this->online++;
		$this->event->onOpen();
		echo "新用户连线:{$_server->worker_pid}: 标示 fd#{$request->fd} 在线:{$this->online}\n";
	}

	function onMessage(swoole_websocket_server $_server, $frame){

		//$url = "/{$this->play}/time/{$this->muiscNow}";

		echo "received " . strlen($frame->data) . " bytes\n";
		//echo "receive from {$fd}:{$data},opcode:{$opcode},fin:{$fin}";
		//$_server->push($frame->fd, "this is server");
		//$_server->push($frame->fd, $url);
		//$_server->close($frame->fd);
	}

	function onClose(swoole_websocket_server $_server, $fd){
		$this->online--;

		if ($this->online <= 0 && $this->playerFlag != -1) {
			swoole_timer_clear($this->playerFlag);
			echo '播放模块已停止工作'.PHP_EOL;
			$this->playerFlag = -1;
			$this->playId=0;
		}

		echo "用户离线:{$_server->worker_pid}: 在线:{$this->online}\n";
	}

	function onPlay(){

		if($this->playId == 0) {
			echo '正在获取播放列表信息'.PHP_EOL;
			if($play_id = $this->player->shiftMusicList()){
				$this->playId = $play_id;
			}else{
				echo '播放列表暂无歌曲'.PHP_EOL;
			}
		}elseif($this->playTime < 1){
			$this->player->getMp3($this->playId);
			if(isset($this->player->timeList[$this->playId])){
				$this->playTime = $this->player->timeList[$this->playId];
				echo '已初始化播放曲目 '.$this->playId .' - '.$this->playTime.PHP_EOL;
				$this->playBuffer = 0;
			}else{
				echo '正在初始化播放曲目 '.$this->playId .PHP_EOL;
				$this->playTime = 0;
			}
		}else{
			$this->playTime--;
			echo '正在播放 剩余:'.$this->playTime.PHP_EOL;
			if($this->playTime < 1){
				$this->playId = 0;
				echo '当前曲目播放完毕'.PHP_EOL;
			}
		}



	}

	function start(){
		$this->server->start();
	}
}