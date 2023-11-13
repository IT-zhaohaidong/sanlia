<?php

namespace app\index\controller;


use app\index\model\SystemAdmin;
use think\Db;
use think\Env;

class Login
{
    public function login()
    {
        $post = input('post.', [], 'trim');
        $login_status = request()->post('login_status', 1);
        $username = $post['username'];
        $password = password($post['password']);
        $adminModel = new SystemAdmin();
        $user = $adminModel->getOne($username);
        if (!$user) {
            return json(['code' => 100, 'msg' => '用户不存在']);
        }
        if ($user['password'] != $password) {
            return json(['code' => 100, 'msg' => '密码错误']);
        }
        if ($user['account_type'] == 1) {
            if (!$user['expire_time']) {
                $adminModel->where('id', $user['id'])->update(['expire_time' => time() + 3600]);
            } else {
                if (time() > $user['expire_time']) {
                    return json(['code' => 100, 'msg' => '该测试账号已过期']);
                }
            }
        }
        //topBar权限判定
        $user['menu'] = explode(',', $user['device_type']);
        if ($user['id'] > 1 && !in_array($post['login_status'], $user['menu'])) {
            return json(['code' => 100, 'msg' => '您没有该系统的权限']);
        }
        if ($user['login_status'] != $login_status) {
            $adminModel->where('id', $user['id'])->update(['login_status' => $login_status]);
        }
        $user['login_status'] = $login_status;

        $token = getRand(32);
        Db::name('system_login')->insert(['uid' => $user['id'], 'token' => $token, 'expire_time' => time() + 8 * 60 * 60]);
        if (!$user['photo']) {
            $user['photo'] = 'https://api.feishi.vip/upload/photo.png';
        } else {
            $dirpath = $_SERVER['DOCUMENT_ROOT'] . '/';
            $photo = str_replace(Env::get('server.server_name', ''), $dirpath, $user['photo']);
            if (!file_exists($photo)){
                $user['photo'] = 'https://api.feishi.vip/upload/photo.png';
            }
        }
        return json(['code' => 200, 'info' => $user, 'token' => $token]);
    }

    public function applet_login()
    {
        $post = request()->post();
        $username = $post['username'];
        $password = password($post['password']);
        $adminModel = new SystemAdmin();
        $user = $adminModel->getOne($username);
        if (!$user) {
            return json(['code' => 100, 'msg' => '用户不存在']);
        }
        if ($user['password'] != $password) {
            return json(['code' => 100, 'msg' => '密码错误']);
        }
        if ($user['account_type'] == 1) {
            if (!$user['expire_time']) {
                $adminModel->where('id', $user['id'])->update(['expire_time' => time() + 3600]);
            } else {
                if (time() > $user['expire_time']) {
                    return json(['code' => 100, 'msg' => '该测试账号已过期']);
                }
            }
        }

        $token = getRand(32);
        Db::name('system_login')->insert(['uid' => $user['id'], 'token' => $token, 'expire_time' => time() + 24 * 60 * 60]);
        return json(['code' => 200, 'info' => $user, 'token' => $token]);
    }

    public function getlist()
    {
        $model = new \app\index\model\MachineType();
        $list = $model
            ->where('delete_time', null)
            ->field('id,title,image,create_time,update_time')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

}