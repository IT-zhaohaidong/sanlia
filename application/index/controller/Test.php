<?php

namespace app\index\controller;

class Test
{
    public function connect()
    {
        error_reporting(E_ALL ^ E_NOTICE);
        ob_implicit_flush();

        //地址与接口，即创建socket时需要服务器的IP和端口
        $sk = new WebSocket('127.0.0.1', 8000);
        //对创建的socket循环进行监听，处理数据
        $sk->run();
        return json(['code'=>100,'msg'=>'成功']);
    }
}