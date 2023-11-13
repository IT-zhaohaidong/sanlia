<?php

namespace app\index\controller;

use app\index\common\Wxpay;
use app\index\model\MachineRenewModel;
use think\Db;

class MachineRenew extends BaseController
{
    protected $appId = '';
    protected $appSecret = '';

    //续费
    public function reNew()
    {
        $data = request()->post();
        $user = $this->user;
        if (empty($data['device_sn'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $timing = $data['timing'];
        if ($timing < 1) {
            return json(['code' => 100, 'msg' => '续费时长不得低于一年']);
        }
        $price = 150;
        $total_fee = $price * $timing;
        $pay = new Wxpay();
        $order_sn = 'X' . time() . mt_rand(1000, 9999);
        $model = new MachineRenewModel();
        $count = $model->where(['uid' => $user['id'], 'status' => 1, 'device_sn' => $data['device_sn']])->count();
        $insert = [
            'device_sn' => $data['device_sn'],
            'uid' => $user['id'],
            'order_sn' => $order_sn,
            'price' => $total_fee,
            'timing' => $timing,
            'count' => $count + 1,//
            'status' => 0,//
            'create_time' => time(),//
        ];
        $order_id = $model->insertGetId($insert);
        $notify_url = 'https://api.hnchaohai.com/index/machine_renew/notify';
        if (empty($data['type'])) {
            $type = 'NATIVE';
            $openid = '';
            $result = $pay->prepay($openid, $order_sn, $total_fee, $notify_url, $type);
        } else {
            $type = 'JSAPI';
            $url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $this->appId . "&secret=" . $this->appSecret . "&js_code=" . $data['code'] . "&grant_type=authorization_code";
//        getUrl是在common中封装的，封装样式如下
            function getUrl($url)
            {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $output = curl_exec($ch);
                curl_close($ch);
                $output = json_decode($output, true);
                return $output;
            }

            $res = getUrl($url);
            if (isset($res['errcode']) && $res['errcode'] == 40163) {
                $data = [
                    'code' => 100,
                    'msg' => '请绑定微信'
                ];
                return json($data);
            }
//        调用成功后定义一个新的数组，最主要的session_key和openid两个值
            $openid = $res['openid'];
            $result = $pay->renewPrepay($openid, $order_sn, $total_fee, $notify_url, $type);
            var_dump($result);
            die();
        }

        if (!empty($data['type'])) {
            $data['appId'] = '';
            $data['timeStamp'] = strval(time());
            $data['nonceStr'] = $pay->getNonceStr();
            $data['signType'] = "MD5";
            $data['package'] = "prepay_id=" . $result['prepay_id'];
            $data['paySign'] = $pay->makeSign($data, '');
            $data['order_sn'] = $order_sn;
            echo json_encode($data, 256);
            return true;
        }
        if ($result['return_code'] == 'SUCCESS') {
            $data = [
                'order_id' => $order_id,
                'order_sn' => $order_sn,
                'money' => $total_fee,
                'url' => $result['code_url']
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'data' => '失败']);
        }
    }


    //支付回调
    public function notify()
    {
        $xml = file_get_contents("php://input");
        //将服务器返回的XML数据转化为数组
        $data = (new OperateOffice)->xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $pay = new Wxpay();
        $sign = $pay->makeSign($data, 'wgduhzmxasi8ogjetftyio111imljs2j');

        // 判断签名是否正确  判断支付状态
        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS')) {
            $result = $data;
            //获取服务器返回的数据
            $out_trade_no = $data['out_trade_no'];        //订单单号
            $openid = $data['openid'];                    //付款人openID
            $total_fee = $data['total_fee'];            //付款金额
            $transaction_id = $data['transaction_id'];    //微信支付流水号
            //支付成功的业务逻辑
            self::dealOrder($out_trade_no, $transaction_id);
        } else {
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result) {
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        } else {
            $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        return $result;
    }

    private function dealOrder($out_trade_no, $transaction_id)
    {
        (new MachineRenewModel())
            ->where('order_sn', $out_trade_no)
            ->update(['status' => 1, 'transaction_id' => $transaction_id]);
        $row = (new MachineRenewModel())->where('order_sn', $out_trade_no)->find();
        $deviceModel = new \app\index\model\MachineDevice();
        $device = $deviceModel->where('device_sn', $row['device_sn'])->find();
        $time = time();
        $str = '+' . $row['timing'] . 'years';
        if ($device['expire_time'] < $time) {
            $expire_time = strtotime($str, $time);
        } else {
            $expire_time = strtotime($str, $device['expire_time']);
        }
        $deviceModel->where('device_sn', $row['device_sn'])->update(['expire_time' => $expire_time]);
    }


    //获取付款进度
    public function getOrderStatus()
    {
        $id = request()->post('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineRenewModel();
        $order = $model->where('id', $id)->find();
        if ($order['status'] > 0) {
            return json(['code' => 200, 'msg' => '付款成功']);
        } else {
            $result = (new Wxpay())->orderInfo($order['order_sn']);
            trace($result, '设备续费支付结果');
            if (!empty($result['trade_state']) && $result['trade_state'] == 'SUCCESS') {
                self::dealOrder($order['order_sn'], $result['transaction_id']);
                return json(['code' => 200, 'msg' => '付款成功']);
            }
            return json(['code' => 100, 'msg' => '暂未付款']);
        }
    }

    //重新获取二维码
    public function getUrl()
    {
        $id = request()->post('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineRenewModel();
        $order = $model->where('id', $id)->find();
        $notify_url = 'https://api.hnchaohai.com/index/machine_renew/notify';
        $pay = new Wxpay();
        $result = $pay->prepay('', $order['order_sn'], $order['price'], $notify_url, 'NATIVE');
        if ($result['return_code'] == 'SUCCESS') {
            $data = [
                'order_id' => $id,
                'url' => $result['code_url']
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'msg' => $result['return_msg']]);
        }
    }

    //续费记录
    public function getList()
    {
        $params = request()->get();
        $limit = request()->get('limit', 15);
        $page = request()->get('page', 1);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $device_ids = Db::name('machine_device_partner')
                    ->where(['admin_id' => $user['parent_id'], 'uid' => $user['id']])
                    ->column('device_id');
                $device_ids = $device_ids ? array_values($device_ids) : [];
                $where['d.id'] = ['in', $device_ids];
            } else {
                $where['r.uid'] = ['=', $user['id']];
            }
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (!empty($params['device_sn'])) {
            $where['r.device_sn'] = ['like', '%' . $params['device_sn'] . '%'];
        }
        $model = new MachineRenewModel();
        $count = $model->alias('r')
            ->join('system_admin a', 'a.id=r.uid', 'left')
            ->join('machine_device d', 'd.device_sn=r.device_sn', 'left')
            ->where($where)->count();
        $list = $model->alias('r')
            ->join('system_admin a', 'a.id=r.uid', 'left')
            ->join('machine_device d', 'd.device_sn=r.device_sn', 'left')
            ->where($where)
            ->field('r.*,a.username')
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }
}
