$(document).ready(function () {
    // var wsServer = 'ws://127.0.0.1:8810';

    ws = new WebSocket(wsServer);
    ws.onopen = function (evt) {
        if (location.hash.substring(1) == '') {
            ws.send('{"act":"sync"}');
        } else {
            $('body').removeClass('adj');
            ws.send('{"act":"playinfo","data":' + location.hash.substring(1) + '}');
            console.log('获取到id,点播模式');
        }
    };
    ws.onclose = function (evt) {
        $('#status').html('已断开与服务器的链接');
    };
    ws.onmessage = function (evt) {
        console.log(evt.data);
        var res = eval('(' + evt.data + ')');
        if (res.act == 'sync') {
            if (res.data == 'wait') {
                //
                console.log('等2秒再发送同步信息');
                setTimeout(2000, function () {
                    ws.send('{"act":"sync"}');
                });
            } else {
                console.log('播放ID:' + res.data.playId);
                console.log('播放偏移:' + res.data.playTime);
                console.log('播放地址:' + res.data.url);
                console.log(res.data);
                music(res.data,1);
            }


        } else if (res.act == 'online') {
            console.log('在线人数:' + res.data);
            var num=res.data - 1;
            if(num){
                $('#status').html("有" + num  + "人与您共同聆听");
            }else{
                $('#status').html("还没有人陪您一起, 快拉朋友一起来听吧");
            }
        } else if (res.act == 'playinfo') {
            music(res.data, 0);
        } else if (res.act == 'ok' || res.act == 'error') {
            alert(res.data);
        } else {
            console.log(res.data);
        }
    };

    ws.onerror = function (e) {
        $('#status').html('已断开与服务器的链接');
        console.log("onerror");
    };
    //跨域（可跨所有域名）
    /*var _u = function (str) {
     for (var _a = parseInt(str),_n = str.substr(1),_c = Math.floor(_n.length / _a),_y = _n.length % _a,_s = new Array(),i = 0; i < _y; i++)_s[i] = _n.substr((_c + 1) * i, _c + 1);
     for (i = _y; i < _a; i++)_s[i] = _n.substr(_c * (i - _y) + (_c + 1) * _y, _c);
     for (i = 0, _c = _n = ''; i < _s[0].length; i++)for (j = 0; j < _s.length; j++)_c += _s[j].substr(i, 1);
     for (i = 0, _c = unescape(_c); i < _c.length; i++)_c.substr(i, 1) == '^' ? _n += '0' : _n += _c.substr(i, 1);
     return _n;
     };*/
    var xiami = document.getElementById("xiami");
    //播放结束后执行
    xiami.addEventListener('ended', function () {
        //获取下一首歌曲
        if ($('body').is('.adj')) {
            $('body').removeClass('first');
            console.log('电台播放,已经播放结束.发送同步');
            ws.send('{"act":"sync"}');
        } else {
            console.log('自主播放,已经播放结束.开始执行下一首歌曲');
            ws.send('{"act":"sync"}');
        }
    }, false);

//开始执行的内容


    xiami.addEventListener('canplay', bufferBar, false);
    function bufferBar() {
        //===============================================显示缓冲进度条
        bufferTimer = setInterval(function () {
            var bufferIndex = xiami.buffered.length;
            if (bufferIndex > 0 && xiami.buffered != undefined) {
                var bufferValue = xiami.buffered.end(bufferIndex - 1) / xiami.duration * 100;
                $('.buffer').css('width', bufferValue + '%');

                if (Math.abs(xiami.duration - xiami.buffered.end(bufferIndex - 1)) < 1) {
                    $('.buffer').css('width', 100 + '%');
                    clearInterval(bufferTimer);
                }
            }
        }, 1000);

    }

    function music(r, mod) {
        var can_play = 0;
        //var j = {"act":"sync","data":{"playId":"1769821950","playTime":"262","url":"http://127.0.0.1/xiami/1.mp3","title":"Eternal Light","length":"261","cover":"http:\/\/img.xiami.net\/images\/album\/img24\/46824\/3686691384929141.jpg","artist":"Libera","album":"Peace"}};
        //var A = new Audio();
        //A.src = _u(r[0].mp3);
        console.log('call function music');
        $('#xiami').attr({'src': r.url, 'title': r.title});
        $('.name').text(r.title);
        $('.album_name span').text(r.title);
        $('.artist span').text(r.artist);
        $('.Cover').css('background-image', 'url(' + r.cover + ')');
        $('.Cover-2').css('background-image', 'url(' + r.cover + ')');
        if (mod == 1) {
            if ($('body').is('.adj')) {
                xiami.addEventListener('canplay', function () {
                    if (can_play == 0) {
                        console.log('快进到与服务器同步位置:' + r.playTime);
                        xiami.currentTime = r.length - r.playTime;
                        can_play = 1;
                    }
                    return true;
                });
            }
        }
        xiami.play();
        console.log('当前正在播放');
    }


    //调整播放时间
    if (!$('body').is('.adj')) {
        $('.progress_bar').mousedown(function () {
            $('#progress').attr('class', 'on');
            $('.time').removeClass('on');
            $(window).mousemove(function (e) {
                $('#progress.on').css('width', e.pageX + 'px');
                var surplus = xiami.duration;
                var xx = e.pageX;
                var w = $(document).width();
                var progressValue = surplus * (xx / w);
                var surplusMin = parseInt(progressValue / 60);
                var surplusSecond = parseInt(progressValue % 60);
                if (surplusSecond < 10) {
                    surplusSecond = '0' + surplusSecond;
                }
                $('.time').text(surplusMin + ":" + surplusSecond);
            }).mouseup(function (ev) {
                $('#progress').attr('class', 'move');
                adjustPorgress(this, ev);
                $(window).unbind('mousemove').unbind('mouseup');
                $('.time').addClass('on');
                xiami.play();
            });
            document.body.onselectstart = document.body.ondrag = function () {
                return false;
            }
        });
    }

    //创建进度条和剩余时间
    xiami.addEventListener('timeupdate', function () {
        if (!isNaN(xiami.duration)) {
            //剩余时间
            var surplus = xiami.currentTime;
            var surplusMin = parseInt(surplus / 60);
            var surplusSecond = parseInt(surplus % 60);
            if (surplusSecond < 10) {
                surplusSecond = '0' + surplusSecond;
            }
            ;
            $('.time.on').text(surplusMin + ":" + surplusSecond);

            //播放进度条//currentTime当前播放//duration总秒数
            var progressValue = xiami.currentTime / xiami.duration * 100;
            $('#progress.move').css('width', progressValue + '%');
        }
        ;
    }, false);


    function adjustPorgress(dom, ev) {
        var event = window.event || ev;
        var progressX = event.clientX / $(dom).width() * 100;
        xiami.currentTime = parseInt(xiami.duration / 100 * progressX);
        xiami.removeEventListener('canplay', bufferBar, false);
    }


    (function () {
        if ('onhashchange' in window) {
            //if browser support onhaschange  如果浏览器支持onhaschange事件
            if (window.addEventListener) {
                window.addHashChange = function (func, before) {
                    window.addEventListener('hashchange', func, before);
                };
                window.removeHashChange = function (func) {
                    window.removeEventListener('hashchange', func);
                };
                return;
            } else if (window.attachEvent) {
                window.addHashChange = function (func) {
                    window.attachEvent('onhashchange', func);
                };
                window.removeHashChange = function (func) {
                    window.detachEvent('onhashchange', func);
                };
                return;
            }
        }
        //if the browser not support onhaschange 如果不支持的话
        var hashChangeFuncs = [];
        var oldHref = location.href;
        window.addHashChange = function (func, before) {
            if (typeof func === 'function')
                hashChangeFuncs[before ? 'unshift' : 'push'](func);
        };
        window.removeHashChange = function (func) {
            for (var i = hashChangeFuncs.length - 1; i >= 0; i--)
                if (hashChangeFuncs[i] === func)
                    hashChangeFuncs.splice(i, 1);
        };
        //!!inportant!! 用setInterval检测has的改变
        setInterval(function () {
            var newHref = location.href;
            if (oldHref !== newHref) {
                oldHref = newHref;
                for (var i = 0; i < hashChangeFuncs.length; i++) {
                    hashChangeFuncs[i].call(window, {
                        'type': 'hashchange',
                        'newURL': newHref,
                        'oldURL': oldHref
                    });
                }
            }
        }, 100);
    })();
    // Usage, infinitely many times: 使用方法
    addHashChange(function (e) {
        if (location.hash.substring(1) == '') {
            $('body').addClass('adj first');
            console.log('没有找到播放ID.开始同步播放');
            ws.send('{"act":"sync"}');
        } else {
            $('body').removeClass('adj first');
            ws.send('{"act":"playinfo","data":"' + location.hash.substring(1) + '"}');
            console.log('获取到id,点播模式');
        }
    });
    //播放按钮
    $('.play').click(function () {
        $(this).toggleClass('pause');
        if (!$(this).hasClass("pause")) {
            xiami.pause();
        } else {
            xiami.play();
        }
    });
    //音量调节
    $('#range').mousedown(function () {
        $(this).mousemove(function () {
            if (!$('.Volume').is('.off')) {
                var a = xiami.volume = $(this).val() / 100;
                console.log(xiami.volume = $(this).val() / 100);
                $.cookie('the_range', a);
            }
        });
    });
    $('.Volume').click(function () {
        if (!$('.Volume').is('.off')) {
            xiami.volume = 0;
            $(this).removeClass('on').addClass('off');
        } else {
            $(this).removeClass('off').addClass('on');
            xiami.volume = $('#range').val() / 100;
        }
    });
    $(".Volume.on").click(function () {
        console.log('321');
    });
    $(".Volume.off").click(function () {
        console.log('123');
    });
    if ($.cookie('the_range') != null) {
        var a = $.cookie('the_range');
        xiami.volume = a;
        $('#range').val(a * 100);
    } else {
        xiami.volume = 0.5;
    }
    //右侧列表
    $('.list-btn').click(function () {
        var t = $(".main");
        if (t.attr('style') == null) {
            t.css({'-webkit-transform': 'translateX(-300px)', 'transform': 'translateX(-300px)'});
        } else {
            t.removeAttr('style');
        }
    });


});
