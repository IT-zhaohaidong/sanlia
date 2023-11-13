<?php

namespace app\applet\controller;

use app\box\controller\ApiV2;
use app\index\model\FinanceOrder;
use app\index\model\MachineCart;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use app\index\model\MallGoodsModel;
use app\index\model\MchidModel;
use app\index\model\OperateUserModel;
use app\index\model\OrderGoods;
use app\index\model\SystemAdmin;
use think\Cache;
use think\Controller;
use think\Db;
use think\Exception;

class AliPay extends Controller
{

    /**
     * 支付宝小程序支付接口
     */
    public function createOrder()
    {
        $post = $this->request->post();
        trace($post, '预支付参数');
//        $post['order_time'] = time();
        $post['status'] = 0;
        $order_sn = time() . mt_rand(1000, 9999);
        $post['order_sn'] = $order_sn;
        $device = (new MachineDevice())->where("device_sn", $post['device_sn'])->field("id,num,imei,uid,status,is_lock,supply_id")->find();
        if ($device['supply_id'] == 3) {
            $bool = (new Goods())->device_status($post['device_sn']);
            if (!$bool) {
                return json(["code" => 100, "msg" => "设备不在线"]);
            }
        } else {
            if ($device['status'] != 1) {
                return json(['code' => 100, 'msg' => '设备不在线,请联系客服处理!']);
            }
        }
        if ($device['is_lock'] < 1) {
            return json(['code' => 100, 'msg' => '设备已禁用']);
        }
        //合并商品
        if (!isset($post['goods_id']) || $post['goods_id'] < 1) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $goods = (new MallGoodsModel())->alias('g')
            ->join('brand b', 'b.id=g.brand_id', 'left')
            ->where('g.id', $post['goods_id'])
            ->field('g.*,b.status brand_status,b.num')
            ->find();
        $num = $goods['brand_status'] == 3 ? $goods['num'] : 5;
        //获取本周一时间戳
        $monday = strtotime("last Sunday+1days");
        $count = (new FinanceOrder())->alias('o')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->where('o.openid', $post['openid'])
            ->where('og.goods_id', $post['goods_id'])
            ->where('o.status', '=', 1)
            ->where('o.create_time', '>', $monday)
            ->count();
        if ($count >= $num) {
            return json(['code' => 100, 'msg' => '本周购买次数已用尽']);
        }
        $amount = (new MachineGoods())
            ->where(['device_sn' => $post['device_sn'], 'goods_id' => $post['goods_id']])
            ->where('err_lock', 0)
            ->group('goods_id')
            ->field('sum(stock) total_stock')->find();
        if ($amount['total_stock'] < 1) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }
        $goods = (new MachineGoods())
            ->where(['device_sn' => $post['device_sn'], 'goods_id' => $post['goods_id']])
            ->where('stock', '>', 0)
            ->find();
        $post['price'] = $goods['active_price'] > 0 ? $goods['active_price'] : $goods['price'];
        trace($post['price'], '支付宝价格');
        if ($post['price'] <= 0) {
            return json(['code' => 100, 'msg' => '订单金额不能小于0']);
        }
        $post['num'] = $goods['num'];
        unset($post['goods_id']);
//        }
        //判断是否有用户在购买
        $str = 'buying' . $post['device_sn'];
        $res = Cache::store('redis')->get($str);
        if ($res == 1) {
            return json(['code' => 100, 'msg' => '有其他用户正在购买,请稍后重试']);
        } else {
            Cache::store('redis')->set($str, 1, 120);
        }
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/AopCertClient.php';
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/request/AlipayTradeCreateRequest.php';
        $uid = (new MachineDevice())->where("device_sn", $post['device_sn'])->value("uid");
        $user = Db::name('system_admin')->where("id", $uid)->find();
        $post['uid'] = $uid;
        $post['create_time'] = time();
        $post['count'] = 1;
        if ($user['is_ali_mchid'] == 1 && $user['ali_mchid_id']) {
            //代理商支付宝支付
            //todo 代理商支付宝支付,待配置
            return false;
        } else {
            //系统支付宝支付
            $order_obj = new FinanceOrder();
            $num = $post['num'];
            unset($post['num']);
            $post['pay_type'] = 2;
            $mall_goods = (new MallGoodsModel())->where('id', $goods['goods_id'])->field('commission')->find();
            $post['commission'] = $mall_goods['commission'];
            $order_id = $order_obj->insertGetId($post);
            //添加订单商品
            $goods_data = [
                'order_id' => $order_id,
                'order_sn' => $order_sn . 'order1',
                'device_sn' => $goods['device_sn'],
                'num' => $num,
                'goods_id' => $goods['goods_id'],
                'price' => $post['price'],
                'count' => 1,
                'total_price' => $post['price'],
            ];
            (new OrderGoods())->save($goods_data);
//            $goods_detail = (new MallGoodsModel())->where('id', $goods['goods_id'])->find();
//            $goods_detail = [
//                'goods_id' => $goods['goods_id'],
//                'goods_name' => $goods_detail['title'],
//                'quantity' => 1,
//                'price' => $post['price']
//            ];

            $app_id = '2021004100643073';
            //应用私钥
            $privateKeyPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/应用私钥RSA2048.txt';
            $privateKey = file_get_contents($privateKeyPath);

            $aop = new \AopCertClient ();
            $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
            $aop->appId = $app_id;
            $aop->rsaPrivateKey = $privateKey;
            //支付宝公钥证书
            $aop->alipayPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/alipayCertPublicKey_RSA2.crt';

            $aop->apiVersion = '1.0';
            $aop->signType = 'RSA2';
            $aop->postCharset = 'UTF-8';
            $aop->format = 'json';
            //调用getCertSN获取证书序列号
            $appPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/appCertPublicKey_2021004100643073.crt";
            $aop->appCertSN = $aop->getCertSN($appPublicKey);
            //支付宝公钥证书地址
            $aliPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";;
            $aop->alipayCertSN = $aop->getCertSN($aliPublicKey);
            //调用getRootCertSN获取支付宝根证书序列号
            $rootCert = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";
            $aop->alipayRootCertSN = $aop->getRootCertSN($rootCert);

            $object = new \stdClass();
            $object->out_trade_no = $order_sn;
            $object->total_amount = $post['price'];
            $object->subject = '潮嗨试用中心订单';
            $object->buyer_id = $post['openid'];
            $object->timeout_express = '10m';

            $json = json_encode($object);
            $request = new \AlipayTradeCreateRequest();
            $request->setNotifyUrl('http://api.hnchaohai.com/applet/ali_pay/systemNotify');
            $request->setBizContent($json);

            $result = $aop->execute($request);

            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            trace($result, '支付宝预支付结果');
            if (!empty($resultCode) && $resultCode == 10000) {
                $data = [
                    'order_sn' => $order_sn,
                    'orderno' => $result->$responseNode->trade_no,
                    'total_amount' => $post['price']
                ];
                return json(['code' => 200, 'data' => $data]);
            } else {
                return json(['code' => 100, 'msg' => $result->$responseNode->sub_msg]);
            }
        }
    }

    public function systemNotify()
    {
        $params = request()->post();
        if (empty($params)) {
            echo 'error';
            exit;
        }
        trace($params, '支付宝支付回调');
        $data = [
            'out_trade_no' => $params['out_trade_no'],
            'total_fee' => $params['total_amount'],
            'transaction_id' => $params['trade_no'],
            'openid' => $params['buyer_id']
        ];
        $res = Cache::store('redis')->get('notify_' . $params['out_trade_no']);
        if (!$res) {
            Cache::store('redis')->set('notify_' . $params['out_trade_no'], 1, 300);
        } else {
            echo 'success';
            exit;
        }
        echo 'success';
        (new Goods())->orderDeal($data, 2);
        exit;
    }
}
