<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-5
 * Time: 下午2:13
 */


class playinfo extends baseEvent{


	function open(swoole_websocket_server $server,swoole_http_request $request){
		//$server->push($request->fd,Server::badge('sync',['playId'=>$master->playId,'playTime'=>$master->playTime]));
		//$server->push($request->fd,Server::badge('online',$master->online));
		$this->eventLog(__CLASS__,' 用户进入 #'.$request->fd);
	}

	function message(swoole_websocket_server $server,$frame){
		$badge = Server::badgeDecode($frame->data);
		if($badge->act == 'sync'){
			if(shareAccess('play_id') != 0){
                $response = player::getPlayUrl(shareAccess('play_id'));
                $response['playId'] = shareAccess('play_id');
                $response['playTime'] = shareAccess('play_time');
				$server->push($frame->fd,Server::badge('sync',$response)); //返回歌曲信息
            }else{
				$server->push($frame->fd,Server::badge('sync','wait')); // 返回等待信息
			}
		}elseif($badge->act == 'playinfo'){
			$data = player::getPlayUrl(trim($badge->data));
			$server->push($frame->fd,Server::badge('playinfo',$data));
		}
	}

	function close(swoole_websocket_server $server, $frame){}
}


