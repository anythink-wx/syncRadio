<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-3
 * Time: 下午6:32
 */

class player{



	public $playList = 'default.txt';
	private $dataPath;

	public function __construct(){
		$this->dataPath = ROOT . '/data';
		serverLog('初始化播放列表');
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
		$list = $contents = "";
		$file =  ROOT.'/list/'.$this->playList;
		serverLog('加载歌曲列表'.$file.PHP_EOL);

		if(!file_exists($file)){
			file_put_contents($file,"");
		}else{
			$contents = file_get_contents($file);
		}
		$_list = explode("\r\n",$contents);
		$db = new db();
        $db->truncate('playNow');
        foreach($_list as $id){
            $id = trim($id);
            if($id != ''){
                $db->create('playNow',['xiami_id' => $id]);
            }
        }
	}


	function pushMusicList($id){
        $db = new db();
        $list = $db->findAll('playNow',"isPlay = 0");
        $db->create('playNow',['xiami_id'=>$id]);
		$string = implode("\r\n",$list);
		$file =  ROOT.'/lib/'.$this->playList;
		//file_put_contents($file,$string);
	}

	/**
	 * 点播歌单
	 */
	function pushSelectList($id){
		$db = new db();
		$res = $db->first('playSelect',"xiami_id = $id");
		if(!$res){
			return $db->create('playSelect',['xiami_id'=>$id]);
		}
		return false;
	}

	/**
	 * 获取某首歌的播放地址
	 * @param $id
	 */
	static function getPlayUrl($id){
		$data = [];
		$uri = 'http://www.xiami.com/song/playlist/id/' . $id;
		serverLog('加载资源：'.$id.' ,loadUrl . ' . $uri);
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

		$select_song = conf::$config['song']['select_song'];
		$db = new db();

		if($select_song){
			$random = conf::$config['song']['random'];
			$play_list = $db->findAll("playSelect",'isPlay = 0');
			if($play_list){
				if($random){
					$index = array_rand($play_list,1);
					$music = $play_list[$index];
					$db->update('playSelect',['id' => $music['id']],['isPlay' => 1]);
					serverLog('抽取点播随机歌曲'.$music['xiami_id'].', 曲库剩余'.count($play_list));
					return $music['xiami_id'];
				}else{
					$music = $play_list[0];
					$db->update('playSelect',['id'=>$music['id']],['isPlay' => 1]);
					serverLog('正常抽取点播歌曲'.$music['xiami_id']);
					return $music['xiami_id'];
				}
			}
		}


		serverLog('无点播歌曲，走系统播放歌单');
		$random = conf::$config['play']['random'];
		if($random){
            $play_list = $db->findAll("playNow",'isPlay = 0');
			serverLog('取出尚未播放列表 player.class #141');
			serverLog(json_encode($play_list));
            if(is_array($play_list)){
                $index = array_rand($play_list,1);
                $music = $play_list[$index];
                $db->update('playNow',['id' => $music['id']],['isPlay' => 1]);
				serverLog('抽取随机歌曲'.$music['xiami_id'].',曲库剩余:'.count($play_list));

            }else{
				serverLog('$play_list 为空 曲库已经没有可播放列表 player.classs #153');
            }

		}else{
			$db = new db();
            $music = $db->first("playNow",'isPlay = 0  limit 1');
            $db->update('playNow',['id'=>$music['id']],['isPlay' => 1]);
			serverLog('正常抽取歌曲 '.$music['xiami_id'].'player.classs #158');
		}

		if(!$music){
			serverLog('return $music false player.class #160');
			return false;
		}
		return $music['xiami_id'];
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