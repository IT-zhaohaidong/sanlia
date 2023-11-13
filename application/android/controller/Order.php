<?php

namespace app\android\controller;

use app\index\model\FinanceOrder;
use app\index\model\MachineCart;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use app\index\model\MallGoodsModel;
use app\index\model\OrderGoods;

class Order
{
    public function createOrder()
    {
        $imei = request()->get('imei', '');
        if (!$imei) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = \think\Db::name('machine_device')->where(['imei' => $imei])->find();
        if (!$device) {
            return json(["code" => 100, "msg" => "设备不存在"]);
        }
        if ($device['is_lock'] == 0) {
            return json(["code" => 100, "msg" => "该设备已停用,请寻找其他设备"]);
        }
        if ($device['supply_id'] != 3) {
            if ($device['status'] != 1) {
                return json(['code' => 100, 'msg' => '设备不在线,请联系客服处理!']);
            }
        }
        if ($device['expire_time'] < time()) {
            return json(['code' => 100, 'msg' => '设备已过期,请联系客服处理!']);
        }
        $orderModel = new FinanceOrder();
        $cartModel = new MachineCart();
        $cart = $cartModel->alias('c')
            ->join('machine_goods g', 'c.num=g.num','left')
            ->field('c.num,c.count,g.goods_id,g.price')
            ->where('c.imei', $imei)
            ->where('g.imei', $imei)
            ->select();
        if (!$cart) {
            return json(['code' => 100, 'msg' => '请先选购商品']);
        }
        //判断库存
        $num = [];
        foreach ($cart as $k => $v) {
            $num[] = $v['num'];
        }
        $machineGoods = (new MachineGoods())->where('imei', $imei)->whereIn('num', $num)->column('stock', 'num');
        foreach ($cart as $k => $v) {
            if ($machineGoods[$v['num']] < $v['count']) {
                $goods_id = $v['goods_id'];
                $title = (new MallGoodsModel())->where('id', $goods_id)->value('title');
                if ($title) {
                    $msg = '商品:' . $title . ',库存不足';
                } else {
                    $msg = '商品不存在';
                }
                return json(['code' => 100, 'msg' => $msg]);
            }
        }
        $count = 0;
        $price = 0;
        foreach ($cart as $k => $v) {
            $count += $v['count'];
            $price += $v['count'] * $v['price'];
        }
        $order_sn = time() . mt_rand(1000, 9999);
        $orderData = [
            'order_sn' => $order_sn,
            'price' => $price,
            'count' => $count,
            'imei' => $imei,
            'uid' => $device['uid']
        ];
        $order_id = $orderModel->insertGetId($orderData);
        $order_goods = [];
        foreach ($cart as $k => $v) {
            $order_goods[] = [
                'order_id' => $order_id,
                'imei' => $imei,
                'num' => $v['num'],
                'goods_id' => $v['goods_id'],
                'price' => $v['price'],
                'count' => $v['count'],
                'total_price' => $v['price'] * $v['count']
            ];
        }
        (new OrderGoods())->saveAll($order_goods);
        $result = (new Wxpay())->prepay('', $order_sn, $price, 'NATIVE');
        if ($result['return_code'] == 'SUCCESS') {
            $cartModel->where('imei', $imei)->delete();
            $data = [
                'order_id' => $order_id,
                'url' => $result['code_url']
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'msg' => $result['return_msg']]);
        }
    }
}
