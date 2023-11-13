<?php

namespace app\applet\controller;

use think\Controller;
use think\Db;

class About extends Controller
{
    public function getAbout()
    {
        $device_sn = $this->request->get('device_sn');
        $uid = Db::name('machine_device')->where('device_sn', $device_sn)->value('uid');
        $uids = [1, $uid];
        $info = Db::name('operate_about')->whereIn('uid', $uids)->order('uid desc')->find();
        $data = [
            'code' => 200,
            'msg'  => '获取成功',
            'data' => $info
        ];
        return json($data);
    }
}