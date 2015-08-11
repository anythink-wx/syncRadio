<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-5
 * Time: 下午2:13
 */


class chat extends  baseEvent{


	function open(swoole_websocket_server $server,swoole_http_request $request){

        $ip = $request->server['remote_addr'];
        $msg = "用户 ".$ip." 进入了";
		$this->broadcast(Server::badge('in_chat',$msg),$server);
		$this->eventLog(__CLASS__,$msg);
	}

	function message(swoole_websocket_server $server,$frame){
        $badge = Server::badgeDecode($frame->data);
        if($badge->act == 'say'){
            $ip = $server->connection_info($frame->fd)['remote_ip'];
            $msg = $ip ." 说:" . $badge->msg;
            $this->broadcast(Server::badge('say',$msg),$server);

        }

	}

	function close(swoole_websocket_server $server, $fd){


        $ip = $server->connection_info($fd)['remote_ip'];
        $msg = "用户 ".$ip." 离开了";
        $this->broadcast(Server::badge('out_chat',$msg),$server);
        $this->eventLog(__CLASS__,$msg);

		$this->eventLog(__CLASS__,'用户离线 #'.$fd);
	}

}

