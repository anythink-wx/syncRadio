<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15-8-3
 * Time: 下午6:32
 */


class kv {
	public static function __callStatic($name, $arguments)	{
		if(empty($arguments)){
			return self::shareGet($name);
		}else{
			return self::sharePut($name,$arguments[0]);
		}
	}

	static function sharePut($k,$v){
		$file = ROOT.'/data/'.$k;
		file_put_contents($file,serialize($v),LOCK_EX);
	}

	static function shareGet($k){
		$file = ROOT.'/data/'.$k;
		return unserialize(file_get_contents($file));
	}
}

class conf{

    public static $config;
    function __construct(){
        if(isset($_SERVER['argv'][1])){
            if(file_exists(ROOT.'/conf/'. $_SERVER['argv'][1])){
                self::$config = parse_ini_file(ROOT.'/conf/'. $_SERVER['argv'][1],true);
            }else{
                throw new Exception('configure '.$_SERVER['argv'][1].' is not exists');
            }
        }else{
            self::$config = parse_ini_file( ROOT.'/conf/default.ini',true);
        }

    }
}



function mime_content($filename) {

	$mime_types = array(
		'txt' => 'text/plain',
		'htm' => 'text/html',
		'html' => 'text/html',
		'php' => 'text/html',
		'css' => 'text/css',
		'js' => 'application/javascript',
		'json' => 'application/json',
		'xml' => 'application/xml',
		'swf' => 'application/x-shockwave-flash',
		'flv' => 'video/x-flv',

		// images
		'png' => 'image/png',
		'jpe' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'ico' => 'image/vnd.microsoft.icon',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',
		'svg' => 'image/svg+xml',
		'svgz' => 'image/svg+xml',

		// archives
		'zip' => 'application/zip',
		'rar' => 'application/x-rar-compressed',
		'exe' => 'application/x-msdownload',
		'msi' => 'application/x-msdownload',
		'cab' => 'application/vnd.ms-cab-compressed',

		// audio/video
		'mp3' => 'audio/mpeg',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',

		// adobe
		'pdf' => 'application/pdf',
		'psd' => 'image/vnd.adobe.photoshop',
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'ps' => 'application/postscript',

		// ms office
		'doc' => 'application/msword',
		'rtf' => 'application/rtf',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',

		// open office
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	);

	$_file = explode('.',$filename);
	$ext = strtolower(array_pop($_file));
	if (array_key_exists($ext, $mime_types)) {
		return $mime_types[$ext];
	}
	elseif (function_exists('finfo_open')) {
		$finfo = finfo_open(FILEINFO_MIME);
		$mimetype = finfo_file($finfo, $filename);
		finfo_close($finfo);
		return $mimetype;
	}
	else {
		return 'application/octet-stream';
	}
}
