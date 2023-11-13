<?php

namespace app\index\controller;

class Applet extends BaseController
{
    //小程序管理后台
    public function getUserInfo()
    {
        $data = $this->user;
        return json(['code' => 200, 'data' => $data]);
    }
}