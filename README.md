## Secret Garden for sync Radio

一个基于websocket的同步电台，暂命名秘密花园

## 系统要求

	确保运行Linux系列操作系统，程序不能运行在windows平台。
	必须安装php5.4及以上版本，php扩展要求安装curl，sqlite3，swoole 1.7.19及以上版本。

## 程序部署

	服务端
	cp conf/default.simple.ini conf/default.ini
	cp music.simple.db music.db
	./music.php

	web客户端及管理后台
	将public/目录绑定到一个域名，设置index.php为默认页面，否则不能正常访问web

	iOS客户端
	正在研发

	Android客户端
	准备中


##指令约束

json数据格式 act 为指令名称，data 为指令参数。传输时均位json字符串如：
 
     ws.send('{"act":"sync"}');
------
## 指令列表


## 客户端发起

* ```sync``` 无参数,请求服务器播放状态

*  ```online ``` 无参数,获取服务器在线人数

* ```playinfo ``` 参数为整形数字,获取某个虾米ID的播放地址等信息

-------------

* ```say ``` 参数为字符串,向所有在线用户发送一条消息

##  客户端接收

 * ```ok``` 返回类型[字符串].返回一个操作执行成功的提示信息,data为具体提示内容
 
 * ```error``` 返回类型[字符串].返回一个操作执行失败的提示信息,data为具体内容
 
-------------
 
 * ```online``` 返回类型[数字].返回当前服务器在线数
 
 * ```sync``` 返回类型[数组,字符串].返回当前服务播放状态,如果返回wait需要等待1秒后重新发起请sync请求
 
 * ```playinfo ``` 返回类型[数据],返回某个虾米ID的播放信息
 
-------------

* ```say ``` 参数为字符串,收到一条在线用户发的消息

* ```in_chat ``` 参数为字符串,收到一条用户上线的消息

* ```out_chat ``` 参数为字符串,收到一条用户离线的消息

## Contributors

* anythink  (http://anythink.com.cn ) : project manager
* vtnil  (http://vtnil.com ) :project contributor

powered by swoole