<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-5
 * Time: 下午2:13
 */


class op extends baseEvent{


	function open(swoole_websocket_server $server,swoole_http_request $request){}

	function message(swoole_websocket_server $server,$frame){

		$badge = Server::badgeDecode($frame->data);
		if($badge->act == 'online'){
			$online = count($server->connections);
			$server->push($frame->fd,Server::badge('online',$online));
			//查看歌曲列表
		}elseif($badge->act =='admin-list'){
			 $string = implode("\r\n",player::$list);
			$server->push($frame->fd,Server::badge('admin-list',$string));
			//切歌
		}elseif($badge->act == 'admin-cut'){
			kv::play_time(0); //剩余时间
			if(kv::play_id()){
				$data = player::getPlayUrl(kv::play_id());
				$this->broadcast(Server::badge('sync',$data),$server);
			}
			$server->push($frame->fd,Server::badge('ok','已经设定到服务器'));
		}
	}

	function close(swoole_websocket_server $server, $fd){}
}

