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

class user extends  baseEvent{


	function open(swoole_websocket_server $server,swoole_http_request $request){
		$user = kv::user();
		$user[$request->fd] = $request->fd;
		kv::user($user);
		kv::online(kv::online()+1);
		$this->eventLog(__CLASS__,'已记录当前用户连接 #'.$request->fd .'当前在线:'.kv::online());
	}

	function message(swoole_websocket_server $server,$frame){

		$badge = Server::badgeDecode($frame->data);
		if($badge->act == 'online'){
			$server->push($frame->fd,Server::badge('online',kv::online()));
		}
	}

	function close(swoole_websocket_server $server, $fd){
		$user = kv::user();
		unset($user[$fd]);
		kv::user($user);

		kv::online(kv::online()-1);

		$this->eventLog(__CLASS__,'用户离线 #'.$fd.' 当前在线:'.kv::online());
	}

}
