<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-5
 * Time: 下午2:13
 */
class open_playinfo implements baseEvent{


	function request(swoole_websocket_server $server,swoole_http_request $request){
		$request->fd;
		//$server->push($request->fd,Server::badge('sync',['playId'=>$master->playId,'playTime'=>$master->playTime]));
		//$server->push($request->fd,Server::badge('online',$master->online));
	}

	function message(swoole_websocket_server $server,$frame){

		print_r($frame);
		if($frame->finish == 1){
			$controller = Server::badgeDecode($frame->data);
			print_r($controller);
		}

	}
}

event::onAdd('open','open_playinfo');
event::onAdd('message','open_playinfo');
