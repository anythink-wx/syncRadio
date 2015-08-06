<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-5
 * Time: 下午2:13
 */
event::onAdd(event::EVENT_OPEN, 'playinfo');
event::onAdd(event::EVENT_MESSAGE, 'playinfo');

class playinfo extends baseEvent{


	function open(swoole_websocket_server $server,swoole_http_request $request){
		//$server->push($request->fd,Server::badge('sync',['playId'=>$master->playId,'playTime'=>$master->playTime]));
		//$server->push($request->fd,Server::badge('online',$master->online));
		$this->eventLog(__CLASS__,' 用户进入 #'.$request->fd);
	}

	function message(swoole_websocket_server $server,$frame){
		print_r($frame);
		if($frame->finish == 1){
			$controller = Server::badgeDecode($frame->data);
			print_r($controller);
		}
	}

	function close(swoole_websocket_server $server, $frame){}
}


