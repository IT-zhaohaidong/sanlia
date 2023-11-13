<?php

namespace app\index\controller;


use app\applet\controller\Wxpay;
use app\index\model\OrderAddressModel;
use app\index\model\ShopGoodsModel;
use app\index\model\ShopOrderGoodsModel;
use app\index\model\ShopOrderModel;
use think\Env;

class ShopOrder extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 15 : $params['limit'];
        $where = [];
        if (!empty($params['order_sn'])) {
            $where['o.order_sn'] = ['like', '%' . $params['order_sn'] . '%'];
        }
        if (!empty($params['phone'])) {
            $where['u.phone'] = ['like', '%' . $params['phone'] . '%'];
        }
        $model = new ShopOrderModel();
        $count = $model->alias('o')
            ->join('operate_user u', 'o.openid=u.openid', 'left')
            ->where($where)
            ->where('o.status', '>', 0)
            ->count();
        $list = $model->alias('o')
            ->join('operate_user u', 'o.openid=u.openid', 'left')
            ->where($where)
            ->where('o.status', '>', 0)
            ->page($page)
            ->limit($limit)
            ->field('o.*,u.phone')
            ->order('o.id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['pay_time'] = $v['pay_time'] ? date('Y-m-d H:i:s', $v['pay_time']) : '';
            $list[$k]['send_time'] = $v['send_time'] ? date('Y-m-d H:i:s', $v['send_time']) : '';
            $list[$k]['take_time'] = $v['take_time'] ? date('Y-m-d H:i:s', $v['take_time']) : '';
            $list[$k]['apply_time'] = $v['apply_time'] ? date('Y-m-d H:i:s', $v['apply_time']) : '';
            $list[$k]['check_time'] = $v['check_time'] ? date('Y-m-d H:i:s', $v['check_time']) : '';
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //查看订单商品
    public function getGoods()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new ShopOrderGoodsModel();
        $list = $model->alias('og')
            ->join('shop_goods g', 'g.id=og.goods_id', 'left')
            ->where('og.order_id', $id)
            ->field('og.*,g.title,g.image')
            ->select();
        $address = (new OrderAddressModel())->where('order_id', $id)->find();
        return json(['code' => 200, 'data' => $list, 'address' => $address]);
    }

    //发货
    public function sendGoods()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['waybill'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = [
            'status' => 3,
            'waybill' => $params['waybill'],
            'send_time' => time()
        ];
        (new ShopOrderModel())->where('id', $params['id'])->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //拒绝退款
    public function refuseRefund()
    {
        $id = request()->get('id');
        $reason = request()->get('reason');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if (empty($reason)) {
            return json(['code' => 100, 'msg' => '请输入拒绝原因']);
        }
        $model = new ShopOrderModel();
        $model->where('id', $id)->update(['status' => 7, 'check_time' => time(), 'refuse_reason' => $reason]);
        return json(['code' => 200, 'msg' => '拒绝成功']);
    }

    //同意退款
    public function refund()
    {
        $id = request()->get('id');
        $refund_price = request()->get('refund_price', 0);
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $order = (new ShopOrderModel())->where('id', $id)->find();

        if (in_array($order['status'], [0, 6])) {
            return json(['code' => 100, 'msg' => '该订单不可退款']);
        }
        if ($order['refund_type'] == 2) {
            (new ShopOrderModel())->where('id', $id)->update(['status' => 8]);
            return json(['code' => 200, 'msg' => '已同意退款']);
        } else {
            if ($refund_price <= 0) {
                return json(['code' => 100, 'msg' => '退款金额必须大于0']);
            }
            if ($refund_price > $order['pay_money']) {
                return json(['code' => 100, 'msg' => '退款金额不能大于支付金额']);
            }
        }

        if ($order['pay_type'] == 2) {
            $result = $this->aliRefund($order, $refund_price);
            if ($result['code'] == 200) {
                (new ShopOrderModel())->where('id', $id)->update(['status' => 6, 'refund_price' => $refund_price]);
                return json(['code' => 200, 'msg' => '退款成功']);
            } else {
                return json($result);
            }
        } elseif ($order['pay_type'] == 1) {
            $res = $this->systemWxRefund($id, $order, $refund_price);
            if ($res['code'] == 100) {
                return json(['code' => 100, 'msg' => $res['msg']]);
            }
            (new ShopOrderModel())->where('id', $id)->update(['refund_price' => $refund_price]);
            return json(['code' => 200, 'msg' => '退款成功']);
        } else {
            return json(['code' => 100, 'msg' => '只能对系统支付宝/系统微信订单进行退款,其他类型订单的退款功能暂未开放']);
        }
    }

    private function aliRefund($order, $refund_price)
    {
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/AopCertClient.php';
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/request/AlipayTradeRefundRequest.php';
        $app_id = '2021003143688161';
        //应用私钥
        $privateKeyPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/api.hnchaohai.com_私钥.txt';
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
        $appPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/appCertPublicKey_2021003143688161.crt";
        $aop->appCertSN = $aop->getCertSN($appPublicKey);
        //支付宝公钥证书地址
        $aliPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";;
        $aop->alipayCertSN = $aop->getCertSN($aliPublicKey);
        //调用getRootCertSN获取支付宝根证书序列号
        $rootCert = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";
        $aop->alipayRootCertSN = $aop->getRootCertSN($rootCert);

        $object = new \stdClass();
        $object->trade_no = $order['transaction_id'];
        $object->refund_amount = $refund_price;
        $object->refund_reason = $order['refund_reason'];

        $json = json_encode($object);
        $request = new \AlipayTradeRefundRequest();
        $request->setBizContent($json);

        $result = $aop->execute($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if (!empty($resultCode) && $resultCode == 10000) {
            return ['code' => 200, 'msg' => '退款成功'];
        } else {
            return ['code' => 100, 'msg' => $result->$responseNode->sub_msg];
        }
    }

    private function systemWxRefund($id, $order, $refund_price)
    {
        $orderModel = new ShopOrderModel();
        if ($order['refund_sn']) {
            $refund_sn = $order['refund_sn'];
        } else {
            $refund_sn = 'R' . time() . rand(1000, 9999);
            $orderModel->where('id', $id)->update(['refund_sn' => $refund_sn, 'check_time' => time()]);
        }

        $url = Env::get('server.server_name') . 'applet/goods/shopNotify';
        $data = [
            'appid' => 'wxfef945a30f78c17c',
            'mch_id' => '1642723027',
            'nonce_str' => getRand(32),
            'out_trade_no' => $order['order_sn'],
            'total_fee' => round($order['pay_money'] * 100),
            'refund_fee' => round($refund_price * 100),
            'out_refund_no' => $refund_sn,
            'notify_url' => $url,
        ];
        trace($data, '退款参数');
        $res = (new Wxpay())->refund($data, 'Yxc15943579579Yxc15943579579Yxc1');
        if ($res['return_code'] == 'SUCCESS') {
            return ['code' => 200, 'msg' => '退款提交成功,请稍后刷新查看'];
        } else {
            return ['code' => 100, 'msg' => '退款失败'];
        }
    }

    //退款退货 确认收货并退款
    public function confirmTake()
    {
        $id = request()->get('id', '');
        $refund_price = request()->get('refund_price', 0);
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $orderModel = new ShopOrderModel();
        $order = $orderModel->where('id', $id)->find();
        if ($order['status'] == 6) {
            return json(['code' => 200, 'msg' => '该订单已退款']);
        }
        if ($refund_price <= 0) {
            return json(['code' => 100, 'msg' => '退款金额必须大于0']);
        }
        if ($refund_price > $order['price']) {
            return json(['code' => 100, 'msg' => '退款金额不能大于支付金额']);
        }
        if ($order['pay_type'] == 2) {
            $result = $this->aliRefund($order, $refund_price);
            if ($result['code'] == 200) {
                (new ShopOrderModel())->where('id', $id)->update(['status' => 6, 'refund_price' => $refund_price]);
                return json(['code' => 200, 'msg' => '退款成功']);
            } else {
                return json($result);
            }
        } elseif ($order['pay_type'] == 1) {
            $res = $this->systemWxRefund($id, $order, $refund_price);
            if ($res['code'] == 100) {
                return json(['code' => 100, 'msg' => $res['msg']]);
            }
            (new ShopOrderModel())->where('id', $id)->update(['refund_price' => $refund_price]);
            return json(['code' => 200, 'msg' => '退款成功']);
        } else {
            return json(['code' => 100, 'msg' => '只能对系统支付宝/系统微信订单进行退款,其他类型订单的退款功能暂未开放']);
        }
    }
}
