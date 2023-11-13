<?php

namespace app\applet\controller;

use app\index\common\Yuepai;
use app\index\model\FinanceOrder;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use app\index\model\MallGoodsModel;
use app\index\model\OrderGoods;
use think\Cache;
use think\Controller;
use think\Db;
use function AlibabaCloud\Client\value;

//雀客专用
class Queke extends Controller
{

    //验证是否有资格  openid device_sn
    public function checkAuth()
    {
        $post = $this->request->post();
        trace($post, '预支付参数');
        $post['status'] = 0;
        $order_sn = time() . mt_rand(1000, 9999);
        $post['order_sn'] = $order_sn;
        $device = (new MachineDevice())->where("device_sn", $post['device_sn'])->field("imei,supply_id")->find();
        if ($device['supply_id'] == 3) {
            $bool = (new Goods())->device_status($post['device_sn']);
            if (!$bool) {
                return json(["code" => 100, "msg" => "设备不在线"]);
            }
        }

        //合并商品
        if (!isset($post['goods_id']) || $post['goods_id'] < 1) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $amount = (new MachineGoods())
            ->where(['device_sn' => $post['device_sn'], 'goods_id' => $post['goods_id']])
            ->group('goods_id')
            ->field('sum(stock) total_stock')->find();
        if ($amount['total_stock'] < 1) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }
        $goods = (new MachineGoods())
            ->where(['device_sn' => $post['device_sn'], 'goods_id' => $post['goods_id']])
            ->where('stock', '>', 0)
            ->find();
        $post['num'] = $goods['num'];
        unset($post['goods_id']);
        $post['price'] = $goods['active_price'] > 0 ? $goods['active_price'] : $goods['price'];
        if ($post['price'] <= 0) {
            return json(['code' => 100, 'msg' => '订单金额不能小于0']);
        }
        $goods_detail = (new MallGoodsModel())->where('id', $goods['goods_id'])->find();
        if (!$goods_detail['goods_code']) {
            return json(['code' => 100, 'msg' => '该商品不可进行验证']);
        }

        $amount = (new MachineGoods())->where(['device_sn' => $post['device_sn'], 'num' => $post['num']])->value('stock');
        if ($amount < 1) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }
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
            $res = (new Yuepai())->check($order_sn, $post['openid'], $goods_detail['goods_code']);
            trace($res, '资格核验结果');
            if ($res['code'] == '00') {
                //系统支付宝支付
                $order_obj = new FinanceOrder();
                $num = $post['num'];
                unset($post['num']);
                $post['pay_type'] = 2;
                $post['order_type'] = 1;
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
                $res_data = json_decode($res['data'], true);
                $data = [
                    'skipUrl' => $res_data['skipUrl'],
                    'order_id' => $order_id
                ];
                return json(['code' => 200, 'data' => $data]);
            } else {
                return json(['code' => 100, 'msg' => $res['message']]);
            }
        }
    }

    //支付 order_id
    public function pay()
    {
        $post = request()->post();
        if (!$post['order_sn']) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $orderModel = new FinanceOrder();
        $order = $orderModel->where('order_sn', $post['order_sn'])->find();
        if (!$order) {
            return json(['code' => 100, 'msg' => '订单不存在']);
        }
        if ($order['status'] > 0) {
            return json(['code' => 100, 'msg' => '该订单已支付']);
        }
        $res = Cache::store('redis')->get($post['order_sn']);
        if ($res != 1) {
            return json(['code' => 100, 'msg' => '未成功入会,请稍后重试']);
        }
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/AopCertClient.php';
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/request/AlipayTradeCreateRequest.php';
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
        $object->out_trade_no = $order['order_sn'];
        $object->total_amount = $order['price'];
        $object->subject = '匪石零售订单';
        $object->buyer_id = $order['openid'];
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
                'order_sn' => $order['order_sn'],
                'orderno' => $result->$responseNode->trade_no,
                'total_amount' => $order['price']
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'msg' => $result->$responseNode->sub_msg]);
        }
    }

    public function getGoodsDetail()

    {
        $get = request()->get();
        if (!$get['order_sn']) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $orderModel = new FinanceOrder();
        $order = $orderModel->where('order_sn', $get['order_sn'])->find();
        if (!$order) {
            return json(['code' => 100, 'msg' => '订单不存在']);
        }
        $goods = (new OrderGoods())->alias('og')
            ->join('mall_goods g', 'og.goods_id=g.id', 'left')
            ->where('og.order_id', $order['id'])
            ->field('g.*')
            ->find();
        $goods['price'] = $order['price'];
        return json(['code' => 200, 'data' => $goods]);
    }

    //雀客资格结果回掉通知
    public function qkNotify()
    {
        $post = request()->post();
        trace($post, '雀客资格结果回掉通知');
        if ($post && $post['status'] == 'SUCCESS') {
            Cache::store('redis')->set($post['traceId'], 1);
        }
        return 'success';
    }

    public function test()
    {
        $res = (new Yuepai())->exposure();
        var_dump($res);
    }

}
