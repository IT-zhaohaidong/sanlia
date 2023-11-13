<?php

namespace app\android\controller;


use app\index\model\DevicePartnerModel;
use app\index\model\MachineDevice;
use app\index\model\SystemAdmin;
use think\Db;

class Login
{
    public function login()
    {
        $post = input('post.', [], 'trim');
        $username = $post['username'];
        $imei = $post['imei'];
        $password = password($post['password']);
        if (empty($username) || empty($imei) || empty($password)) {
            return json(['code' => 100, 'msg' => '缺少参数!']);
        }
        $adminModel = new SystemAdmin();
        $user = $adminModel->getOne($username);//role_id==1  //设备所属代理商  //该设备的补货员
        $device = (new MachineDevice())->where('imei', $imei)->field('uid,device_sn,id')->find();
        $partner = (new DevicePartnerModel())->where(['device_id' => $device['id'], 'uid' => $user['id']])->find();
        if (!$user) {
            return json(['code' => 100, 'msg' => '用户不存在']);
        }
        if ($user['password'] != $password) {
            return json(['code' => 100, 'msg' => '密码错误']);
        }
        if ($user['role_id'] == 1 || $device['uid'] == $user['id'] || ($user['role_id'] == 9 && $partner)) {
//            $token = getRand(32);
//            Db::name('system_login')->insert(['uid' => $user['id'], 'token' => $token, 'expire_time' => time() + 1800]);
            return json(['code' => 200, 'msg' => '登陆成功', 'data' => $user]);
        } else {
            return json(['code' => 100, 'msg' => '您没有权限!']);
        }
    }


}