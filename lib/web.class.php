<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15/9/12
 * Time: 下午6:02
 */
class web{

	function run(){
		if(isset($_SERVER['QUERY_STRING'])){
			$query_array = explode('/',$_SERVER['QUERY_STRING']);
			$controller = isset($query_array[0]) ? $query_array[0] : "";
			$method     = isset($query_array[1]) ? $query_array[1] : "index";
			$call_function = $controller.'_'.$method;
			if(method_exists($this,$call_function)){
				return call_user_func([$this,$call_function]);
			}
		}
		return $this->responseError('undefined router');
	}

	function args($key=''){
		$arg = [];
		$args = explode('?',$_SERVER['REQUEST_URI']);
		if(isset($args[1])){
			$argv = explode('&',$args[1]);
			foreach($argv as $d){
				$_key = explode('=',$d);
				if(isset($_key[1]))$arg[$_key[0]] = htmlspecialchars($_key[1]);
			}
		}
		$_split = explode('/',$_SERVER['REQUEST_URI']);
		$count = count($_split);
		for($i=3;$i<=$count;$i++){
			$next = $i+1;
			if(isset($_split[$i]) && isset($_split[$next])){
				$_v = explode('?',$_split[$next]);
				$arg[$_split[$i]] = htmlspecialchars($_v[0]);
				$i+=1;
			}
		}
		if($key){
			if(isset($arg[$key])){
				return $arg[$key];
			}else{
				return  "";
			}
		}else{
			return $arg;
		}
	}

	function admin_index(){
		$config = shareAccess('config');
		if(!isset($config['web']['auth'])) die('config missing,please run service once');
		$user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : "";
		$pwd = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : "";
		if(($user != $config['web']['auth']) && ($pwd != $config['web']['password'])){
			header('WWW-Authenticate: Basic realm="SyncRadio"');
			header('HTTP/1.0 401 Unauthorized');
			die('<a href="/admin">press username and password </a>');
		}
		$ajax = $this->args('ajax');
		if($ajax){
			return call_user_func([$this,'ajax_'.$ajax]);
		}
		return include ROOT.'/tpl/dashboard.phtml';
	}

	function ajax_config(){
		if($this->args('controller') == 'cut'){
			if(shareAccess('play_id')){
				shareAccess('play_time',3);
			}
			return $this->responseSuccess('cut success');
		}
		$config = shareAccess('config');
		if($config){
			//设置随机播放
			if($this->args('controller') == 'play_random'){
				$config['play']['random'] = ($this->args('switch') == 'true') ? 1: 0;
				shareAccess('config',$config);
				return $this->responseSuccess('play random set success');
			}

			//设置列表循环
			if($this->args('controller') == 'play_loop'){
				$config['play']['loop'] = ($this->args('switch') == 'true') ? 1: 0;
				shareAccess('config',$config);
				return $this->responseSuccess('play loop set success');
			}

			//是否开启点歌功能
			if($this->args('controller') == 'select_song'){
				$config['song']['select_song'] = ($this->args('switch') == 'true') ? 1: 0;
				shareAccess('config',$config);
				return $this->responseSuccess('select_song set success');
			}

			if($this->args('controller') == 'select_song_random'){
				$config['song']['random'] = ($this->args('switch') == 'true') ? 1: 0;
				shareAccess('config',$config);
				return $this->responseSuccess('select_song set success');
			}

			if($this->args('controller') == 'select_song_rate'){
				$config['song']['select_song_rate'] = (int) $this->args('switch');
				shareAccess('config',$config);
				return $this->responseSuccess('select_song set success');
			}



			unset($config['web']['auth'],$config['web']['password']);
			return $this->responseSuccess($config);
		}else{
			return $this->responseError('config missing,please run service once');
		}
	}

	/**
	 * 搜搜歌曲使用虾米主搜索接口
	 * http://www.xiami.com/ajax/search-index?key=keyword&_=time;
	 * @return string
	 */
	function ajax_search(){
		$key = htmlspecialchars($_GET['key']);
		$url = 'http://www.xiami.com/ajax/search-index?key='.$key.'&_='.time();
		$html = player::getCurl($url);
		preg_match_all('/<a\shref=\"(.*?)\"\stitle=\"(.*?)\"\sclass=\"song_result\">(.*?)<\/a>/i',$html,$match);
		$data = [];
		if($match[3]){
			foreach($match[3] as $index => $d){
				$data[] = [
					'title' => strip_tags($d),
					'id'   => $this->strip_xiami_id($match[1][$index]),
				];
			}
			return $this->responseSuccess($data);
		}
		return $this->responseError('获取数据错误,请稍后再试');
	}

	/**
	 * 使用虾米单曲播放页面
	 * http://www.xiami.com/song/2074127?spm=a1z1s.6659513.0.0.xHTiDi
	 */
	function ajax_xiami_url(){
		$key = htmlspecialchars(urldecode($_GET['key']));
		$parser = parse_url($key);
		preg_match('/http:\/\/www.xiami.com\/song\/(.*)\?/i',$key,$match);
		if(isset($match[1])){
			return $this->responseSuccess($match[1]);
		}
		return $this->responseError("解析地址失败");
	}



	private function responseSuccess($data){
		return json_encode(['code'=>0,'body'=>$data]);
	}

	private function responseError($msg,$code=1){
		return json_encode(['code'=>$code,'message'=>$msg]);
	}

	/**
	 * 过滤虾米id
	 * http://www.xiami.com/song/1772130329?from=search_popup_song
	 * @param $url
	 */
	private function strip_xiami_id($url){
		preg_match('/http:\/\/www.xiami.com\/song\/(.*?)\?from=search_popup_song/i',$url,$ma);
		if($ma[1]){
			return $ma[1];
		}
		return false;
	}
}