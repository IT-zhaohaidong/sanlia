<?php

namespace app\applet\controller;

use app\index\model\MachineDevice;
use app\index\model\OperateUserModel;
use app\index\model\SystemAdmin;
use think\Cache;
use think\Controller;
use think\Db;
use function AlibabaCloud\Client\value;


class Login extends Controller
{
    protected $appid = "wxfef945a30f78c17c";
    protected $appsecret = "f958096de1a2e1d7431a45453431f054";

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
     * 支付宝登录 保存用户信息
     */
    public function login()
    {
        $data = request()->post();
        trace($data, '用户信息');
        $user_obj = new OperateUserModel();
        if (strripos($data['openid'], 'SELECT')) {
            return json(['code' => 100, 'msg' => '非法攻击']);
        }

        $user_info = $user_obj->where("openid", $data['openid'])->find();
        $data['nickname'] = !empty($data['nickname']) ? $this->emoji2str($data['nickname']) : '支付宝用户';
        if ($user_info) {
            unset($data['device_sn']);
            $user_obj->where("openid", $data['openid'])->update($data);
        } else {
            if (!empty($data['device_sn'])) {
                $uid = (new MachineDevice())->where('device_sn', $data['device_sn'])->value('uid');
                $arr['uid'] = $uid;
            }
            unset($data['device_sn']);
            $user_obj->save($data);
        }
        return json(['code' => 200, 'msg' => '登录成功']);
    }

    /**
     * 获取用户手机号 todo 废弃
     */
    public function getPhone()
    {
        $post = $this->request->post();
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$this->appid}&secret={$this->appsecret}&js_code={$post['code']}&grant_type=authorization_code";
        $content = https_request($url);
        $content = json_decode($content, true);
        if (!isset($content['session_key'])) {
            return json($content);
        }
        $sessionKey = $content['session_key'];
        trace($content, '获取手机号的sessionKey');
        $encryptedData = $post['encryptedData'];
        $iv = $post['iv'];
        $data = "";
        $pc = new \app\applet\controller\WXBizDataCrypt($this->appid, $sessionKey);
        $errCode = $pc->decryptData($encryptedData, $iv, $data);
        $data = json_decode($data, true);
        $phone = $data['purePhoneNumber'];
        trace($errCode, '获取手机号状态码');
        trace($data, '获取手机号结果');
        if ($errCode == 0) {
            $arr = ["openid" => $content['openid'], 'phone' => $phone];
            if (isset($post['third_id']) && $post['third_id']) {
                $arr['third_id'] = $post['third_id'];
            }
            $obj = new OperateUserModel();
            $info = $obj->where('openid', $arr['openid'])->find();
            if ($info) {
                $obj->where('id', $info['id'])->update($arr);
            } else {
                $obj->save($arr);
            }
            $data = [
                "code" => 200,
                "msg" => "获取成功",
                'phone' => $phone,
                'openid' => $content['openid']
            ];
            return json($data);
        } else {
            return json(['code' => 100, 'msg' => '获取失败,请重新获取']);
        }
    }

    public function getPhon()
    {
        $code = request()->post('code', '');
        $openid = request()->post('openid', '');
        $device_sn = request()->post('device_sn', '');
        $third_id = request()->post('third_id', '');
        if (!$code || !$openid) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $token = $this->getToken();
        trace('token', $token);
        $url = "https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token={$token}";
        $data = json_encode(['code' => $code]);
        $res = https_request($url, $data);
        $result = json_decode($res, true);
        if ($result['errcode'] == 0) {
            $arr = ["openid" => $openid, 'phone' => $result['phone_info']['purePhoneNumber']];
            $obj = new OperateUserModel();
            $info = $obj->where('openid', $arr['openid'])->find();
            if ($info) {
                $obj->where('id', $info['id'])->update($arr);
            } else {
                if ($device_sn) {
                    $uid = (new MachineDevice())->where('device_sn', $device_sn)->value('uid');
                    $arr['uid'] = $uid;
                }
                if ($third_id){
                    $arr['third_id'] = $third_id;
                }
                $obj->save($arr);
            }
            $data = [
                "code" => 200,
                "msg" => "获取成功",
                'phone' => $arr['phone'],
                'openid' => $openid
            ];
            return json($data);
        } else {
            return json(['code' => 100, 'msg' => '获取失败,请重新获取']);
        }
    }

    public function getToken()
    {
        $str = 'applet_token';
        $token = Cache::store('redis')->get($str);
        if ($token) {
            return $token;
        } else {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appid}&secret={$this->appsecret}";
            $res = https_request($url);
            $res = json_decode($res, true);
            trace($res, '获取token');
            $token = $res['access_token'];
            Cache::store('redis')->set($str, $token, 7000);
            return $token;
        }
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
