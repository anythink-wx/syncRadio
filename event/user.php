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

class user extends  baseEvent{


	function open(swoole_websocket_server $server,swoole_http_request $request){
		$user = kv::user();
		$user[$request->fd] = $request->fd;
		kv::user($user);


		$online = kv::online();
		$online+=1;
		kv::online($online);


		$this->broadcast(Server::badge('online',$online),$server); //广播在线用户数
		$this->eventLog(__CLASS__,'已记录当前用户连接 #'.$request->fd .'当前在线:'.$online);
	}

	function message(swoole_websocket_server $server,$frame){

		$badge = Server::badgeDecode($frame->data);
		if($badge->act == 'online'){
			$online = count($server->connections);
			$server->push($frame->fd,Server::badge('online',$online));
		}elseif($badge->act =='addsong'){
			$player = new player();
			$data = player::getPlayUrl((int)$badge->data);
			if(!empty($data)){
				$player->pushMusicList((int)$badge->data);
				$response = Server::badge('ok','歌曲添加成功');
			}else{
				$response = Server::badge('error','歌曲不存在');
			}
			$server->push($frame->fd,$response);
		}
	}

	function close(swoole_websocket_server $server, $fd){

		$user = kv::user();
		unset($user[$fd]);
		kv::user($user);

		$online = kv::online();
		$online-=1;
		kv::online($online);

		if($online >0){
			$this->broadcast(Server::badge('online',$online),$server); //广播在线用户数
		}
		$this->eventLog(__CLASS__,'用户离线 #'.$fd.' 当前在线:'.$online);
	}

}

