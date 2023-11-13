<?php

namespace app\medicine\controller;

use app\index\model\MachineDevice;
use app\index\model\OperateUserModel;
use app\index\model\SystemAdmin;
use think\Controller;
use think\Db;


class Login extends Controller
{
    protected $appid = "wx300f4ced661b5846";
    protected $appsecret = "4e2ddb6fb2b7ca99573ce39bbced1c14";

    /**
     *  获取用户openid
     */
    public function getOpenid()
    {
        $code = request()->get("code", "");
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$this->appid}&secret={$this->appsecret}&js_code={$code}&grant_type=authorization_code";
        $content = https_request($url);
        $content = json_decode($content, true);
        unset($content['session_key']);
        return json($content);
    }

    /**
     * 登录 保存用户信息
     */
    public function login()
    {
        $data = request()->post();
        trace($data, '用户信息');
        $user_obj = new OperateUserModel();
        if (strripos($data['openid'], 'SELECT')) {
            return json(['code' => 100, 'msg' => '非法攻击']);
        }
        $length = strlen($data['openid']);
        if ($length != 28 && $length != 16) {
            return json(['code' => 100, 'msg' => '非法攻击']);
        }
        $user_info = $user_obj->where("openid", $data['openid'])->find();
        $data['nickname'] = $this->emoji2str($data['nickname']);
        if ($user_info) {
            $user_obj->where("openid", $data['openid'])->update($data);
        } else {
            $data['type'] = 3;
            $user_obj->save($data);
        }
        return json(['code' => 200, 'msg' => '登录成功']);
    }

    function emoji2str($str)
    {
        $strEncode = '';
        $length = mb_strlen($str, 'utf-8');
        for ($i = 0; $i < $length; $i++) {
            $_tmpStr = mb_substr($str, $i, 1, 'utf-8');
            if (strlen($_tmpStr) >= 4) {
                $strEncode .= '??';
            } else {
                $strEncode .= $_tmpStr;
            }
        }
        return $strEncode;
    }


}