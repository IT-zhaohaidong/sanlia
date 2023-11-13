<?php

namespace app\android\controller;


use app\applet\controller\Goods;
use app\index\model\FinanceOrder;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use think\Cache;
use think\Db;

class FacePay
{
    public function payOrder()
    {
        $ip = request()->ip();
        $order_id = request()->post('order_sn', '');
        $auth_code = request()->post('auth_code', '');
        if (empty($order_id) || empty($auth_code)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $order = (new FinanceOrder())->where('id', $order_id)->find();
        if (empty($order)) {
            return json(['code' => 100, 'msg' => '订单不存在']);
        }
        $order_sn = $order['order_sn'];
        $content = $this->pay($order['device_sn'], $order_sn, $order['price'], $ip, $auth_code);
        if ($content) {
            $data = [
                'transaction_id' => $content['transaction_id'],
                'status' => 1,
                'is_face' => 1,
                'pay_time' => time(),
                'openid' => $content['openid']
            ];
            (new FinanceOrder())->where('order_sn', $order_sn)->update($data);
        }
        $data = [
            'transaction_id' => $content['transaction_id'],
            'out_trade_no' => $order_sn
        ];
        (new Goods())->orderDeal($data, 1);
        return json(['code' => 200, 'data' => ['status' => 2, 'msg' => '支付成功']]);
    }

    //获取人脸支付凭证
    public function getCertificate()
    {
        $post = request()->post();
        trace($post, '获取支付凭证接收参数');
        $imei = request()->post('imei', '');//门店编号， 由商户定义， 各门店唯一。(安卓imei号)
        $row = (new MachineDevice())->where('imei', $imei)->find();
        if (!$row) {
            $device = Db::name('machine_android')
                ->alias('a')
                ->join('machine_device d', 'a.device_sn=d.device_sn', 'left')
                ->where('a.imei', $imei)->field('d.device_name,d.device_sn,a.face_sn')->find();
        } else {
            $device = $row;
        }

        $str = 'Certificate_' . $imei;
        $content = Cache::store('redis')->get($str);
        if ($content) {
            return json(['code' => 200, 'data' => $content]);
        }
        if (empty($device['face_sn'])) {
            return json(['code' => 100, 'msg' => '未配置刷脸设备sn']);
        }
        $store_id = $device['device_sn'];
        $store_name = $device['device_name'] ?? '匪石科技';//门店名称，由商户定义。（可用于展示）
//        $device_id = 'MTAP3FCA023AB94K2PFS4A1';//终端设备编号，由商户定义。
        $device_id = $device['face_sn'];//终端设备编号，由商户定义。
        $rawdata = request()->post('rawdata', '');//初始化数据。由微信人脸SDK的接口返回。
        if (empty($rawdata) || empty($imei)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $appid = '';//商户号绑定的公众号/小程序 appid
        $mch_id = '';//商户号
        $now = time();
        $version = 1;
        $sign_type = 'MD5';
        $nonce_str = getRand(32);
        $data = [
            'store_id' => $store_id,
            'store_name' => $store_name,
            'device_id' => $device_id,
            'rawdata' => $rawdata,
            'appid' => $appid,
            'mch_id' => $mch_id,
            'now' => $now,
            'version' => $version,
            'sign_type' => $sign_type,
            'nonce_str' => $nonce_str
        ];
        $key = '';
        $pay = new \app\applet\controller\Wxpay();
        $data['sign'] = $pay->makeSign($data, $key);
        $xmldata = self::array2xml($data);
        $url = 'https://payapp.weixin.qq.com/face/get_wxpayface_authinfo';
        $res = $pay->https_request($url, $xmldata);
        if (!$res) {
            exit(json_encode(array('status' => 100, 'result' => 'fail', 'errmsg' => "Can't connect the server")));
        }
        $content = $pay->xml2array($res);
        trace($content, '刷脸支付凭证');

        if (!empty($content['result_code'])) {
            if (strval($content['result_code']) == 'FAIL') {
                exit(json_encode(array('status' => 100, 'result' => 'fail', 'errmsg' => strval($content['err_code_des']))));
            }
        }
        $content['time'] = time();
        Cache::store('redis')->set($str, $content, 3600);
        return json(['code' => 200, 'data' => $content]);
    }


    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    protected function array2xml($arr, $level = 1)
    {
        $s = $level == 1 ? "<xml>" : '';
        foreach ($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s . "</xml>" : $s;
    }

    //人脸支付
    public function pay($device_sn, $order_sn, $money, $ip, $auth_code)
    {
        $appid = 'wx6fd3c40b45928f43';//商户号绑定的公众号/小程序 appid
        $mch_id = '1538520381';//商户号
        $data = [
            'appid' => $appid,
            'mch_id' => $mch_id,
            'device_info' => $device_sn,//终端设备号(商户自定义，如门店编号)
            'sign_type' => 'MD5',
            'nonce_str' => getRand(32),
            'body' => '匪石零售-售货机',
            'out_trade_no' => $order_sn,//订单号
            'spbill_create_ip' => $ip,//调用微信支付API的机器IP
            'total_fee' => $money * 100,//金额  分
            'auth_code' => $auth_code//设备读取用户微信中的条码或者二维码信息
        ];
        $key = 'wgduhzmxasi8ogjetftyio111imljs2j';
        $pay = new \app\applet\controller\Wxpay();
        $data['sign'] = $pay->makeSign($data, $key);
        $xmldata = self::array2xml($data);
        $url = 'https://api.mch.weixin.qq.com/pay/micropay';
        $res = $pay->https_request($url, $xmldata);
        if (!$res) {
            exit(json_encode(array('code' => 100, 'data' => ['status' => 4, 'msg' => "Can't connect the server"])));
        }
        $content = $pay->xml2array($res);
        trace($content, '刷脸支付进行发起订单支付');
        $arr = ['BANKERROR', 'SYSTEMERROR', 'USERPAYING'];
        if (!empty($content['result_code'])) {
            if (strval($content['result_code']) == 'FAIL') {
//                if (in_array($content['err_code'], $arr)) {
//                    $res = $this->orderQuery($order_sn, $appid, $mch_id, $key);
//                    if ($res['bool'] == 1) {
//                        return $res['content'];
//                    } elseif ($res['bool'] == 0) {
//                        $this->reverse($order_sn, $appid, $mch_id, $key);
//                        exit(json_encode(array('code' => 100, 'data' => ['status' => 4, 'msg' => '交易已关闭,请重新下单'])));
//                    } else {
//                        exit(json_encode(array('code' => 100, 'data' => ['status' => 4, 'msg' => strval($res['err_code_des'])])));
//                    }
//                } else {
                    exit(json_encode(array('code' => 100, 'data' => ['status' => 4, 'msg' => strval($content['err_code_des'])])));
//                }
            }
        }
        return $content;
    }


    //撤销交易
    public function reverse($order_sn, $appid, $mch_id, $key)
    {
        $data = [
            'appid' => $appid,
            'mch_id' => $mch_id,
            'out_trade_no' => $order_sn,
            'nonce_str' => getRand(32),
            'sign_type' => 'MD5'
        ];
        $pay = new \app\applet\controller\Wxpay();
        $data['sign'] = $pay->makeSign($data, $key);
        $xmldata = self::array2xml($data);
        $url = 'https://api.mch.weixin.qq.com/secapi/pay/reverse';
        $res = $pay->https_request($url, $xmldata);
        if (!$res) {
            exit(json_encode(array('status' => 100, 'msg' => "reverse Can't connect the server")));
        }
        $content = $pay->xml2array($res);
        trace($content, '撤销订单');
    }

    //订单支付结果未知,轮训订单状态
    public function orderQuery($order_sn, $appid, $mchid, $key)
    {
        $bool = 0;
        $msg = '';
        $content = [];
        $x = 1;
        do {
            $res = $this->query($order_sn, $appid, $mchid, $key);
            if ($res['result_code'] == 'SUCCESS' && $res['trade_state'] == 'SUCCESS') {
                $bool = 1;
                $msg = '支付成功';
                $content = $res;
                break;
            } elseif ($res['result_code'] == 'FAIL' && ($res['err_code'] == 'USERPAYING' || $res['err_code'] == 'BANKERROR' || $res['err_code'] == 'SYSTEMERROR')) {
                $bool = 0;
            } else {
                $msg = $res['err_code_des'];
                $bool = 2;
            }
            if ($x < 25) {
                sleep(1);
            }
            $x++;
        } while ($x <= 25);
        return ['bool' => $bool, 'msg' => $msg, 'content' => $content];
    }

    public function query($order_sn, $appid, $mchid, $key)
    {
        $data = [
            'appid' => $appid,
            'mch_id' => $mchid,
            'out_trade_no' => $order_sn,
            'nonce_str' => getRand(32),
            'sign_type' => 'MD5'
        ];
        $pay = new \app\applet\controller\Wxpay();
        $data['sign'] = $pay->makeSign($data, $key);
        $xmldata = self::array2xml($data);
        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $res = $pay->https_request($url, $xmldata);
        if (!$res) {
            exit(json_encode(array('status' => 100, 'msg' => "Can't connect the server")));
        }
        $content = $pay->xml2array($res);
        trace($content, '查询订单');
        return $content;
    }
}
