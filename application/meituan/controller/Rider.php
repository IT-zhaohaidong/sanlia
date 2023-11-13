<?php

namespace app\meituan\controller;

use app\applet\controller\Goods;
use app\index\model\MachineDevice;
use app\index\model\MtDeviceGoodsModel;
use app\index\model\MtGoodsModel;
use app\index\model\MtOrderModel;
use app\index\model\MtRefundLogModel;
use app\index\model\MtShopModel;

class Rider
{
    public function goodsOut()
    {
        $order_str = request()->get('order_str', '');
        $device_sn = request()->get('device_sn', '');
        $len = strlen($order_str);
        if ($len !== 4) {
            return json(['code' => 100, 'msg' => '非法参数']);
        }
        if (!$device_sn) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $time = time() - 3600 * 72;
        $model = new MtOrderModel();
        $order = $model
            ->where('order_id', 'like', '%' . $order_str)
            ->where('create_time', '>', $time)->find();
        if (!$order) {
            return json(['code' => 100, 'msg' => '找不到订单']);
        }
        $shop = (new MtShopModel())->where('app_poi_code', $order['app_poi_code'])->find();
        if (!$shop || !$shop['device_id']) {
            return json(['code' => 100, 'msg' => '请前往柜台取药']);
        }
        $device = (new MachineDevice())->where('id', $shop['device_id'])->field('device_sn,imei')->find();
        if ($device['device_sn'] != $device_sn) {
            return json(['code' => 100, 'msg' => '该设备未找到此订单']);
        }
        $medicine = json_decode($order['detail'], true);
        $is_use = true;
        $deviceGoodsModel = new MtDeviceGoodsModel();
        foreach ($medicine as $k => $v) {
            $goods_id[] = $v['app_medicine_code'];
            $stock = $deviceGoodsModel
                ->where(['device_id' => $shop['device_id'], 'goods_id' => $v['app_medicine_code']])
                ->group('device_id')
                ->field('sum(stock) total')
                ->find();
            if ($stock['total'] < $v['quantity']) {
                $is_use = false;
                break;
            }
        }
        if (!$is_use) {
            return json(['code' => 100, 'msg' => '设备库存不足,请前往柜台取药']);
        }
        $order_sn = 'mt_' . $order['order_id'];
        foreach ($medicine as $k => $v) {
            for ($i = 1; $i <= $v['quantity']; $i++) {
                $goods = $deviceGoodsModel
                    ->where(['device_id' => $shop['device_id'], 'goods_id' => $v['app_medicine_code']])
                    ->where('stock', '>', 0)
                    ->find();

                (new Goods())->goodsOut($device['device_sn'], $goods['num'], $order_sn);
                sleep(5);
            }
        }

        return json(['code' => 200, 'msg' => '出货成功']);
    }

}