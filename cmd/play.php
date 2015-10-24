<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15/10/24
 * Time: 下午8:09
 */



class play extends cmd{

	/**
	 * worker
	 */
	function sync_cmd(){
		$badge = server::badge('play|sync',['fd'=>$this->frame->fd]);
		$this->server->task($badge);
	}

	/**
	 * task
	 */
	function sync_task(){
		global $play_time;
		if(shareAccess('play_id') != 0){
			$response = player::getPlayUrl(shareAccess('play_id'));
			$response['playId'] = shareAccess('play_id');
			$response['playTime'] = $play_time->get();
			$this->push($this->args->fd,Server::badge('play|sync',$response));
			serverLog(Server::badge('play|sync',$response));
		}else{
			$this->server->push($this->args->fd,Server::badge('play|sync','wait')); // 返回等待信息
		}
	}
}

