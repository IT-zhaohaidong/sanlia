<?php

namespace app\index\controller;

use app\index\common\Tencent;
use app\index\model\SystemAdmin;

//微信公众号,推送补货单
class ProcurementPush
{
    private $appId = "wxcd9d1b873948d10a";
    private $appSecret = "53f811e733ae09382ce7c57e5eb1d4f2";

    public function send($id,$name,$openid)
    {
        $token = (new Tencent())->getToken($this->appId, $this->appSecret);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $token;
        $params = [
            'touser' => $openid,//接收消息的openid
            'template_id' => 'w3R14sBdfE0E2wM4sb40UADhUrFVjIxpcrGZka1OyB0',//模板ID
            'url' => 'http://manghe.feishi.vip/#/replenishmentOrder?id='.$id, //点击详情后的URL可以动态定义
            'data' => [
                'first' => [
                    'value' => '设备补货通知',
                    'color' => '#173177'
                ],
                'keyword2' => [
                    'value' => $name,
                    'color' => '#173177'
                ],
                'keyword4' => [
                    'value' => date('Y-m-d H:i'),
                    'color' => '#173177'
                ]
            ]
        ];
        $data = json_encode($params, JSON_UNESCAPED_UNICODE);
        $res = https_request($url, $data);
    }

    public function getTemp()
    {
        $token = (new Tencent())->getToken($this->appId, $this->appSecret);
        $url = 'https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token=' . $token;
//        $data = json_encode($params, JSON_UNESCAPED_UNICODE);
        $res = https_request($url);
        var_dump($res);
    }

    public function createMenu()
    {
        $data = '{
  "button":[
  {
        "name": "扫码", 
        "sub_button": [
            {
                "type": "scancode_waitmsg", 
                "name": "扫码绑定", 
                "key": "rselfmenu_0_0", 
                "sub_button": [ ]
            }
         ]
      }
   ]
}';
        $token = (new Tencent())->getToken($this->appId, $this->appSecret);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=" . $token);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return $tmpInfo;
    }

    public function delMenu()
    {

        $token = (new Tencent())->getToken($this->appId, $this->appSecret);
        $url = "https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=" . $token;
        $res = https_request($url);
        var_dump($res);
        die();
    }

    public function bind()
    {
        $post = request()->post();
        trace($post,'绑定公众号参数');
        $openid = $post['openid'];
        $uid = $post['uid'];
        (new SystemAdmin())->where('id', $uid)->update(['tencent_openid' => $openid]);
        return ['code' => 200, 'msg' => '绑定成功'];
    }

    //给公众号发送消息
    public function sendMsg($openid, $msg)
    {
        $token = (new Tencent())->getToken($this->appId, $this->appSecret);
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}";
        //        $html = "<a href=\"http://www.qq.com\" data-miniprogram-appid=\"wx8f027456c1e7e60f\" data-miniprogram-path=\"pages/index/index?q=https://tanhuang.feishikeji.cloud/public?device={$msg}\">点击跳转小程序</a>";

        $data = [
            "touser" => "{$openid}",
            "msgtype" => "text",
            "text" =>
                [
                    "content" => $msg,
                ]
        ];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $obj = https_request($url, $json);
        trace($obj, '绑定推送结果');
    }
}