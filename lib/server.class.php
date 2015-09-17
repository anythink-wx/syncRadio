<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-3
 * Time: 下午6:32
 */

class admin{

    function auth(&$request,&$response){

        print_r($request);

        if (!isset($request->header['authorization'])) {

            $response->header('WWW-Authenticate',' Basic realm="Dashboard"');
            $response->status(401);
            $response->end( 'Text to send if user hits Cancel button');
           return false;
        } else {
           // echo "<p>Hello {$request->header['authorization']}.</p>";
          //  echo "<p>You entered {$_SERVER['PHP_AUTH_PW']} as your password.</p>";
        }

        return false;
    }
}
class Server {

	protected $playerFlag = -1;             //播放服务状态

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
	private $server;

	function init(){
		if(!class_exists('swoole_websocket_server')){
			exit("This server need swoole php extension ".PHP_EOL."please run pecl install swoole installed".PHP_EOL);
		}
		@swoole_set_process_name('syncRadio');
        $this->clearRunTimeFile();
        new conf();
		$server = new swoole_websocket_server(conf::$config['server']['listen'], conf::$config['server']['port']);
        $server->addProcess($this->loadProcess());
        $server->on('open', [$this, 'onOpen']);
        $server->on('message', [$this, 'onMessage']);
        $server->on('close', [$this, 'onClose']);
        $server->on('request',[$this,'onRequest']);
        $server->on('start',[$this,'onStart']);
        $server->on('shutdown',[$this,'onShutdown']);
        $server->set([
			'reactor_num' => conf::$config['server']['reactor_num'],
			'worker_num' => conf::$config['server']['worker_num'],
			'backlog' => conf::$config['server']['backlog'],
			'max_request' => conf::$config['server']['max_request'],
            'daemonize' => conf::$config['server']['daemonize'],
            'log_file'  => conf::$config['server']['log_file'],
            'user' => conf::$config['server']['user'],
            'group' =>conf::$config['server']['group'],
		]);





        $this->server = $server;
        $this->player = new player();
        $this->event  = new event();
		shareAccess('online',0);
		shareAccess('play_id',0);
		shareAccess('play_time',0);
    }

    function onStart(swoole_server $server){
        @swoole_set_process_name('syncRadio:manager');
		file_put_contents(ROOT.'/music.pid',$server->manager_pid);
    }

	function onOpen(swoole_websocket_server $_server, swoole_http_request $request)	{
		$status = $_server->connection_info($request->fd);
		if($status['websocket_status'] != 0){
			//判断是否启动播放服务
			if (shareAccess('online') == 0 && $this->playerFlag == -1) {
				$this->playerFlag = swoole_timer_tick(1000,[$this,'onPlay']);
				$this->serverLog('播放模块已启动,模块ID：'.$this->playerFlag);
			}

			//加载open事件模块
			$this->event->eventOpen($_server,$request);
		}
	}

	function onMessage(swoole_websocket_server $_server, $frame){
		$this->event->eventMessage($_server,$frame);
	}

	function onClose(swoole_websocket_server $_server, $fd){
		$status = $_server->connection_info($fd);
		if($status['websocket_status'] != 0){
			$this->event->eventClose($_server,$fd);

			if (shareAccess('online') <= 0) {
				swoole_timer_clear($this->playerFlag);
				$this->serverLog("播放模块已停止工作");
				$this->playerFlag = -1;
			}
		}
	}

    function onShutdown(swoole_websocket_server $_server){
        $this->serverLog('服务器已退出');
    }

	function onPlay(){
		$play_id = shareAccess('play_id');
		$play_time = shareAccess('play_time');
        $speed=1;
		if($play_id !=0){
			if($play_time > 0){
				$play_time-=$speed;
				shareAccess('play_time',$play_time);
				if($play_time %10 ==0){
					$this->serverLog('正在播放:'.$play_id.' 剩余:'.$play_time.'s');
				}
			}else{
				shareAccess('play_time',0);
				shareAccess('play_id',0);
			}
		}else{
			shareAccess('play_time',0);
			$this->serverLog('当前曲目播放完毕 '.$play_id);
		}
	}

	function onRequest(swoole_http_request $request, swoole_http_response $response){
		$response->end();
	}

    private function clearRunTimeFile(){
        $dir = ROOT.'/data/';
        $list = scandir($dir);
        foreach($list as $d){
            if(substr($d,0,1) != '.'){
                unlink($dir.$d);
            }
        }
    }

    protected function loadProcess(){
        return new swoole_process(function(swoole_process $worker) {
            @swoole_set_process_name("syncRadio:loader");
            $player = $this->player;
			while(true){
				if(shareAccess('play_time') <= 0){
					$id = $player->shiftMusicList();
					if($id){
						$data = player::getPlayUrl($id);
						if(!empty($data)){
							shareAccess('play_id',$id);
							shareAccess('play_time',$data['length']);
						}

					}else{
						//$player->loadMusicList();
						$this->serverLog('没有成功拉取到下一首歌');
					}
				}
				sleep(1);
			}




		});
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

	function convert($size){
		$unit=array('B','KB','MB','GB','TB','PB','EB');
		return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
	}

	function start(){
		$this->server->start();
	}
}