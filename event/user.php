<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-5
 * Time: 下午2:13
 */
event::onAdd(event::EVENT_OPEN,   'user');
event::onAdd(event::EVENT_MESSAGE,'user');
event::onAdd(event::EVENT_CLOSE,  'user');


event::onAdd(event::EVENT_OPEN, 'playinfo');
event::onAdd(event::EVENT_MESSAGE, 'playinfo');

event::onAdd(event::EVENT_OPEN,   'chat');
event::onAdd(event::EVENT_MESSAGE,'chat');
event::onAdd(event::EVENT_CLOSE,  'chat');

event::onAdd(event::EVENT_MESSAGE,'op');

class user extends  baseEvent{


	function open(swoole_websocket_server $server,swoole_http_request $request){
		$user = shareAccess('user');
		$user[$request->fd] = $request->fd;
		shareAccess('user',$user);

		$online = shareAccess('online');
		$online+=1;
		shareAccess('online',$online);


		$this->broadcast(Server::badge('online',$online),$server); //广播在线用户数
		$this->eventLog(__CLASS__,'已记录当前用户连接 #'.$request->fd .'当前在线:'.$online);
	}

	function message(swoole_websocket_server $server,$frame){

		$badge = Server::badgeDecode($frame->data);
		if($badge->act == 'online'){
			$online = count($server->connections);
			$server->push($frame->fd,Server::badge('online',$online));
		}elseif($badge->act =='addsong'){
			$rate = conf::$config['song']['select_song_rate'];

			if(limit::verify('add_song_'.$frame->fd,$rate)){
				echo '点歌频次限制'.$rate.PHP_EOL;
				$player = new player();
				$data = player::getPlayUrl((int)$badge->data);
				if(!empty($data)){
					$player->pushSelectList((int)$badge->data);
					$response = Server::badge('ok','歌曲添加成功');
				}else{
					$response = Server::badge('error','歌曲不存在');
				}
				limit::keep('add_song_'.$frame->fd,$rate);
				$server->push($frame->fd,$response);
			}else{
				$server->push($frame->fd,Server::badge('error','操作太频繁啦'));
			}
		}
	}

	function close(swoole_websocket_server $server, $fd){

		$user = shareAccess('user');
		unset($user[$fd]);
		shareAccess('user',$user);

		$online = shareAccess('online');
		$online-=1;
		shareAccess('online',$online);

		if($online >0){
			$this->broadcast(Server::badge('online',$online),$server); //广播在线用户数
		}
		$this->eventLog(__CLASS__,'用户离线 #'.$fd.' 当前在线:'.$online);
	}

}

