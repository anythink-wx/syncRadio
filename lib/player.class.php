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
	 * 获取某首歌的播放地址
	 * @param $id
	 */
	static function getPlayUrl($id){
		$data = [];
		$uri = 'http://www.xiami.com/song/playlist/id/' . $id;
		echo 'loadurl . ' . $uri .PHP_EOL;
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
            $db = new db();
            $play_list = $db->findAll("playNow",'isPlay = 0');
            if(is_array($play_list)){
                $index = array_rand($play_list,1);

                $music = $play_list[$index];
                $db->update('playNow',['id' => $music['id']],['isPlay' => 1]);
                echo '抽取随机歌曲'.$music['xiami_id'].PHP_EOL;
                echo '曲库剩余:'.count($play_list).PHP_EOL;

            }else{
                echo '曲库已经没有可播放列表'.PHP_EOL;
            }

		}else{
			$db = new db();
            $music = $db->first("playNow",'isPlay = 0  limit 1');
            $db->update('playNow',['id'=>$music['id']],['isPlay' => 1]);
			echo '正常抽取歌曲'.$music['xiami_id'].PHP_EOL;
		}

		if(!$music) return false;
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