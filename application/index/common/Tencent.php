<?php


namespace app\index\common;


use think\Cache;
use think\Db;

class Tencent
{
    public function getToken($appid, $secret)
    {
        //两小时有效
//        $appid = 'wxcd9d1b873948d10a';
//        $secret = '53f811e733ae09382ce7c57e5eb1d4f2';
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
        $content = https_request($url);
        $content = json_decode($content, true);
        if (!empty($content['errmsg'])){
            echo json_encode(['code'=>100,'msg'=>$content['errmsg']]);
            die();
        }
        $name = 'token' . $appid;
        $res = Cache::store('redis')->get($name);
        if (!$res) {
            Cache::store('redis')->set($name, $content['access_token'], $content['expires_in']);
        }

        return $content['access_token'];
    }

    public function getTicket($appid, $secret, $device)
    {
        $name = 'token' . $appid;
        $token = Cache::store('redis')->get($name);
        $token = $token ? $token : $this->getToken($appid, $secret);
//        $token = '57_55Uj3CIaQ2sIVb3PvWrn2K6T3Ne9Tg192KEblgk0Sh7tzZ_FvKPjcqVuhudEHhYDTDwtLzXU7bKckiAtC7Gymp7MmBg2X0a5dVZdIzyU3BT1dpwhcL8chbClNxqNj0KQuYTBMH8rIbR6o8uHYOBaAAAJZM';
        $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={$token}";
        $data = [
            'action_name' => 'QR_LIMIT_STR_SCENE',
            'action_info' => [
                'scene' => [
                    'scene_str' => $device
                ]
            ],
        ];
        $data = json_encode($data);
        $content = https_request($url, $data);
        $content = json_decode($content, true);
        return $content['ticket'];
    }

    public function getImage($appid, $secret, $device)
    {
        $ticket = $this->getTicket($appid, $secret, $device);
//        $ticket = 'gQHo8TwAAAAAAAAAAS5odHRwOi8vd2VpeGluLnFxLmNvbS9xLzAyUkZ4TjFHSjRlSmwxMDAwME0wN2gAAgSeJp9iAwQAAAAA';
        $url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket={$ticket}";
        return $url;
    }
}