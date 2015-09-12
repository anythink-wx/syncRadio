<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15/9/12
 * Time: 下午6:02
 */
class web{

	function run($controller){
		if(method_exists($this,'ajax_'.$controller)){
			return call_user_func([$this,'ajax_'.$controller]);
		}else{
			return $this->responseError('未定义的路由');
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