## Secret Garden for sync Radio

一个基于websocket的同步电台，暂命名秘密花园


##指令约束

json数据格式 act 为指令名称，data 为指令参数。

------
## 指令列表


## 请求

* sync 无参数,请求服务器播放状态

##  响应

 * online 无参数，返回当前服务器在线数，返回类型为整型
 * sync 无参数,返回当前服务播放状态,返回类型为wait,或数组,如为wait需要等待1秒后继续发起sync请求

## Contributors

* anythink  (http://anythink.com.cn ) : project manager
* vtnil  (http://vtnil.com ) :project contributor

powered by swoole