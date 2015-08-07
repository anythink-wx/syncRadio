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


	private $playList='play.log';
	private $dataPath;
	private $timerId=null;
	private $processList=[];
	public $list=[];

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
	private function loadMusicList(){
		$list = $contents = "";
		$file =  ROOT.'/lib/'.$this->playList;
		if(!file_exists($file)){
			file_put_contents($file,"");
		}else{
			$contents = file_get_contents($file);
		}
		$list = explode("\r\n",$contents);
		$this->list = $list;
	}


	function pushMusicList($id){
		//如果没有list，则初始化music列表
		if(!$this->list) $this->loadMusicList();

		if(is_array($this->list)){
			array_push($this->list,$id);
		}else{
			$this->list[] = $id;
		}

		$string = implode("\n",$this->list);
		$file =  ROOT.'/lib/'.$this->playList;
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
		if(file_exists($xml_info)){
			$source = file_get_contents($xml_info);
		}else{
			$source = self::getCurl($uri);
			file_put_contents($xml_info,$source,LOCK_EX);
		}

		$argv = simplexml_load_string($source, 'SimpleXMLElement', LIBXML_NOCDATA|LIBXML_NOBLANKS);

		if(is_object($argv)){
			$data = [
				'url' => self::de_Location($argv->trackList->track->location),
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
	function shiftMusicList(){
		$music = array_shift($this->list);
		if(!$music) return false;
		return $music;
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


	function pmp3($id,$filePath){
		echo 'pmp3',PHP_EOL;
		$self = $this;
		$process = new swoole_process(function (swoole_process $worker) use ($id) {
			$data = self::getPlayUrl($id);
			$this->timeList[$id] = $data['length'];
		});
		if($pid = $process->start()){
			$this->processList[$pid]=$pid;
			if(null===$this->timerId){
				$id=swoole_timer_tick(1000,function ($id){
					$res=swoole_process::wait(false);
					unset($this->processList[$res['pid']]);
					if(empty($this->processList)){
						swoole_timer_clear($id);
						$this->timerId=null;
					}
				});
				$this->timerId=$id;
			}
		}
	}

	function processLoadMp3($id){
		echo 'create_process'.PHP_EOL;
		$that = $this;
		$process = new swoole_process(function (swoole_process $worker) use ($id) {
			$data = $that::getPlayUrl($id);
			if(!empty($data)){
				$this->timeList[$id] = $data['length'];
			}
			var_dump($data);
		}, true);
		if($pid = $process->start()){
			$this->processList[$pid]=$pid;
			if(null===$this->timerId){
				$id=swoole_timer_tick(1000,function ($id){
					$res=swoole_process::wait(false);
					unset($this->processList[$res['pid']]);
					if(empty($this->processList)){
						swoole_timer_clear($id);
						$this->timerId=null;
					}
				});
				$this->timerId=$id;
			}
		}
	}

	function downloadMp3($id,$filePath){
		$process = new swoole_process(function (swoole_process $worker) use ($id,$filePath) {
			$xiamiUrl = 'http://www.xiami.com/song/playlist/id/' . $id;
			echo '开始获取播放信息：' . $xiamiUrl . PHP_EOL;
			$source = self::getCurl($xiamiUrl);
			if(!$source){
				return false;
			}
			$argv = simplexml_load_string($source, 'SimpleXMLElement', LIBXML_NOCDATA);
			$mp3 = self::de_Location($argv->trackList->track->location);
			echo '开始缓冲',PHP_EOL;
			list($length,$bin) = $this->getStream($mp3);

			$tmp=sys_get_temp_dir().'/'.uniqid();
			file_put_contents($tmp, $bin);
			$mp3obj = new mp3file($tmp,$length);

			$time=$mp3obj->get_time();
			file_put_contents($filePath,$time);
			$this->timeList[$id]=$time;
		}, true);
		if($pid = $process->start()){
			$this->processList[$pid]=$pid;
			if(null===$this->timerId){
				$id=swoole_timer_tick(1000,function ($id){
					$res=swoole_process::wait(false);
					unset($this->processList[$res['pid']]);
					if(empty($this->processList)){
						swoole_timer_clear($id);
						$this->timerId=null;
					}
				});
				$this->timerId=$id;
			}
		}
	}

	function getStream($url,$length=4096){
		$fp=fopen($url,'rb');
		$header=stream_get_meta_data($fp);
		$content=stream_get_contents($fp,$length);
		fclose($fp);
		$length = $this->my_headers($header['wrapper_data']);
		return [$length,$content];
	}

	function my_headers($headers){
		$_header = [];
		if(!empty($headers)){
			unset($headers[0]);
			foreach ($headers as $d){
				list($k,$v) = explode(":",$d);
				$_header[$k] = trim($v);
			}
		}
		if($_header['Content-Length']) return $_header['Content-Length'];
		return 0;
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