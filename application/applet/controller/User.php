<?php

namespace app\applet\controller;

use app\index\model\MachineDevice;
use app\index\model\OperateUserModel;
use app\index\model\ShopVipOrderModel;
use app\index\model\SystemAdmin;
use think\Cache;
use think\Controller;
use think\Db;
use think\Env;

class User extends Controller
{
    public function getConfig()
    {
        $data = Db::name('system_config')->where('id', 1)->find();
        return json(['code' => 200, 'data' => $data]);
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo()
    {
        $openid = request()->get("openid", "");
        $device_sn = request()->get("device_sn", "");
        if (empty($openid)) {
            return json(['code' => 400, 'msg' => '缺少参数']);
        }
        $userObj = new OperateUserModel();
        $info = $userObj
            ->field("id,nickname,sex,photo,openid,uid,phone,is_vip,vip_expire_time")
            ->where("openid", $openid)->find();
        if ($info) {
            if (!empty($device_sn) && empty($info['uid'])) {
                $uid = (new MachineDevice())->where('device_sn', $device_sn)->value('uid');
                $userObj->where('id', $info['id'])->update(['uid' => $uid]);
            }
            if ($info['is_vip'] == 1) {
                if (time() > $info['vip_expire_time']) {
                    $info['is_vip'] = 0;
                    $userObj->where('id', $info['id'])->update(['is_vip' => 0]);
                }
            }
            $info['indate'] = $info['is_vip'] == 1?ceil(($info['vip_expire_time'] - time()) / (24 * 3600)) . '天':'未开通或已过期';
            $data = [
                "code" => 200,
                "msg" => "获取成功",
                "data" => $info
            ];
        } else {
            $data = [
                "code" => 400,
                "msg" => "用户不存在",
                "data" => $info
            ];
        }
        return json($data);
    }

    //代理商扫码绑定提现账户
    public function bindAdmin()
    {
        $post = request()->post();
        if (empty($post['openid']) || empty($post['uid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        (new SystemAdmin())->where('id', $post['uid'])->update(['openid' => $post['openid']]);
        return json(['code' => 200, 'msg' => '绑定成功,可以提现啦!']);
    }

    //购买会员,创建订单
    public function createOrder()
    {
        $post = request()->post();
        if (empty($post['openid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $system_config = Db::name('system_config')->where('id', 1)->find();
        if ($system_config['vip_money'] < 0.01) {
            return json(['code' => 100, 'msg' => '系统未配置价格,暂不可购买']);
        }
        $order_sn = time() . rand(1000, 9999);
        $order = [
            'openid' => $post['openid'],
            'status' => 0,
            'order_sn' => $order_sn,
            'money' => $system_config['vip_money']
        ];
        $model = new ShopVipOrderModel();
        $model->save($order);
        $user['is_wx_mchid'] = 0;
        $notify_url = Env::get('server.SERVER_NAME') . 'applet/user/notify';
        $pay = new Wxpay();
        $res = $pay->prepay($post['openid'], $order_sn, $system_config['vip_money'], $user, $notify_url);
        //小程序调用微信支付配置
        $data['appId'] = "wxfef945a30f78c17c";
        $data['timeStamp'] = strval(time());
        $data['nonceStr'] = $pay->getNonceStr();
        $data['signType'] = "MD5";
        $data['package'] = "prepay_id=" . $res['prepay_id'];
        $data['paySign'] = $pay->makeSign($data, 'Yxc15943579579Yxc15943579579Yxc1');
        $data['order_sn'] = $order_sn;
        return json(['code' => 200, 'data' => $data]);
    }

    public function notify()
    {

        $xml = request()->getContent();
        trace($xml, '微信支付毁掉');
        //将服务器返回的XML数据转化为数组
        $data = (new Wxpay())->xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $result = $data;
        //获取服务器返回的数据
        $out_trade_no = $data['out_trade_no'];        //订单单号
        $openid = $data['openid'];                    //付款人openID
        $total_fee = $data['total_fee'];            //付款金额
        $transaction_id = $data['transaction_id'];    //微信支付流水号

        // 返回状态给微信服务器
        if ($result) {
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        } else {
            $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        //TODO 此时可以根据自己的业务逻辑 进行数据库更新操作
        $order_str = 'wx_return' . $out_trade_no;
        $res = Cache::store('redis')->get($order_str);
        //判断是否接收到回调,避免重复执行
        if (!$res) {
            Cache::store('redis')->set($order_str, 1, 100);
            $data = [
                "status" => 1,
//                "transaction_id" => $transaction_id,
                "pay_time" => time()
            ];
            (new ShopVipOrderModel())->where('order_sn', $out_trade_no)->update($data);
            $user = (new OperateUserModel())->where('openid', $openid)->find();
            $now_expire = $user['vip_expire_time'];
            if ($now_expire > time()) {
                $expire_time = strtotime("+12 month", $now_expire);
            } else {
                $expire_time = strtotime("+12 month", time());
            }
            (new OperateUserModel())->where('openid', $openid)->update(['is_vip' => 1, 'vip_expire_time' => $expire_time]);
        }
        return $result;
    }
}
