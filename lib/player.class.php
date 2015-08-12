<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-3
 * Time: 下午6:32
 */

class player{

	/*
	 * 当前播放曲目
	 */
	public $playId = 0;

	/*
	 * 当前播放时长
	 */
	public $playTime = 0;
	public $timeList = [];


	public $playList = 'default.txt';
	private $dataPath;
	private $timerId=null;
	private $processList=[];
	public static $list=[];

	function __destruct(){
		foreach($this->processList as $pid){
			swoole_process::kill($pid, SIGKILL);
		}
	}

	public function __construct(){
		$this->dataPath = ROOT . '/data';
		$this->loadMusicList();
	}


	/**
	 * 获取播放列表
	 */
	public function loadMusicList($playList=''){
		$this->playList = $playList;
		if(!$playList){
			$playList = conf::$config['play']['default'];
			$this->playList = $playList;
		}
		echo '加载歌曲列表:'.$playList.PHP_EOL;

		$list = $contents = "";
		$file =  ROOT.'/list/'.$this->playList;
		echo $file.PHP_EOL;
		if(!file_exists($file)){
			file_put_contents($file,"");
		}else{
			$contents = file_get_contents($file);
		}
		$_list = explode("\r\n",$contents);
		echo '行数:'.count($_list).PHP_EOL;
		$list = [];
		foreach($_list as $d) $list[$d] = $d;
		player::$list = [];
		player::$list = $list;
		print_r(player::$list);
	}


	function pushMusicList($id){
		//如果没有list，则初始化music列表
		if(!player::$list) $this->loadMusicList();
		player::$list[$id] = $id;
		$string = implode("\r\n",player::$list);
		$file =  ROOT.'/list/'.$this->playList;
		file_put_contents($file,$string);
	}

	/**
	 * 获取某首歌的播放地址
	 * @param $id
	 */
	static function getPlayUrl($id){
		$data = [];
		$uri = 'http://www.xiami.com/song/playlist/id/' . $id;
		$xml_info = ROOT.'/data/'.$id.'.xml';

		if(file_exists($xml_info) && (filectime($xml_info) + 21600 > time())){
			$source = file_get_contents($xml_info);
		}else{
			$source = player::getCurl($uri);
			file_put_contents($xml_info,$source,LOCK_EX);
		}

		$argv = simplexml_load_string($source, 'SimpleXMLElement', LIBXML_NOCDATA|LIBXML_NOBLANKS);

		if(is_object($argv)){
			$data = [
				'url' => player::de_Location($argv->trackList->track->location),
				'title' => (string)  $argv->trackList->track->title,
				'length' =>(string)  $argv->trackList->track->length,
				'cover' => (string)  $argv->trackList->track->album_pic,
				'artist' => (string) $argv->trackList->track->artist,
				'album' => (string)  $argv->trackList->track->album_name,
			];
		}
		return $data;
	}

	/**
	 * 取出播放列表中的第一首歌
	 * @return string
	 */
	function shiftMusicList($preLoad=false){
		new conf();
		$random = conf::$config['play']['random'];
		if($random){
			$id = array_rand(player::$list,1);
			if($id){
				$playId = player::$list[$id];
				unset(player::$list[$id]); //从队列中移除
				echo '抽取随机歌曲'.$playId.PHP_EOL;
				echo '曲库剩余:'.count(player::$list).PHP_EOL;
			}
		}else{
			$playId = array_shift(player::$list);
		}

		if($preLoad){
			array_unshift(player::$list,$playId);

		}
		if(!$playId) return false;
		return $playId;
	}

	/**
	 * 获取一首歌
	 */
	function getMp3($id){
		$filePath = "{$this->dataPath}/{$id}";
		if(!file_exists($filePath)){
			$this->pmp3($id,$filePath);
		}else{
			$time = file_get_contents($filePath);
			$this->timeList[$id] = $time;
		}
	}

	static function getCurl($url,$headerOnly=false){
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array('Accept-Encoding: gzip, deflate'));
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate'); //允许执行gzip
		curl_setopt($ch,CURLOPT_TIMEOUT,30);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);//重定向次数
		curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:39.0) Gecko/20100101 Firefox/39.0'); //useragent
		$body = curl_exec($ch);
		curl_close($ch);
		return $body;
	}

	/**
	 * 解密虾米音乐
	 * @param $location
	 * @return mixed|string
	 */
	public static function de_Location($location){
		$loc_2 = (int)substr($location, 0, 1);
		$loc_3 = substr($location, 1);
		$loc_4 = floor(strlen($loc_3) / $loc_2);
		$loc_5 = strlen($loc_3) % $loc_2;
		$loc_6 = array();
		$loc_7 = 0;
		$loc_8 = '';
		$loc_9 = '';
		$loc_10 = '';
		while ($loc_7 < $loc_5){
			$loc_6[$loc_7] = substr($loc_3, ($loc_4+1)*$loc_7, $loc_4+1);
			$loc_7++;
		}
		$loc_7 = $loc_5;
		while($loc_7 < $loc_2){
			$loc_6[$loc_7] = substr($loc_3, $loc_4 * ($loc_7 - $loc_5) + ($loc_4 + 1) * $loc_5, $loc_4);
			$loc_7++;
		}
		$loc_7 = 0;
		while ($loc_7 < strlen($loc_6[0])){
			$loc_10 = 0;
			while ($loc_10 < count($loc_6)){
				$loc_8 .= isset($loc_6[$loc_10][$loc_7]) ? $loc_6[$loc_10][$loc_7] : null;
				$loc_10++;
			}
			$loc_7++;
		}
		$loc_9 = str_replace('^', 0, urldecode($loc_8));
		return $loc_9;
	}

}