<?php

namespace app\android\controller;

use app\index\model\FinanceOrder;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use app\index\model\OrderGoods;

class Index
{
    //首页
    public function getIndex()
    {
        $imei = request()->get('imei', '');
        if (!$imei) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineDevice();
        $data = $model->alias('device')
            ->join('index_template index', 'device.index_id=index.id', 'left')
            ->where('device.imei', $imei)
            ->field('index.*')
            ->find();
        return json(['code' => 200, 'data' => $data]);
    }

    //商品列表
    public function goodsList()
    {
        $imei = request()->get('imei', '');
        if (!$imei) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device_sn = (new MachineDevice())->where('imei', $imei)->value('device_sn');
        $model = new MachineGoods();
        $activeList = $model->alias('mg')
            ->join('mall_goods g', 'mg.goods_id=g.id')
            ->where('mg.device_sn', $device_sn)
            ->where('g.mark', '>', 0)
            ->field('mg.id,mg.num,mg.stock,mg.price,g.title,g.image,g.mark')
            ->select();
        $list = $model->alias('mg')
            ->join('mall_goods g', 'mg.goods_id=g.id')
            ->where('mg.device_sn', $device_sn)
            ->field('mg.id,mg.num,mg.stock,mg.price,g.title,g.image,g.mark')
            ->order('mg.num asc')
            ->select();
        $data = compact('list', 'activeList');
        return json(['code' => 200, 'data' => $data]);
    }

    //商品详情
    public function goodsDetail()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineGoods();
        $data = $model->alias('mg')
            ->join('mall_goods g', 'mg.goods_id=g.id')
            ->where('mg.id', $id)
            ->field('mg.id,mg.num,mg.stock,mg.price,g.title,g.image,g.mark,g.detail')
            ->find();
        return json(['code' => 200, 'data' => $data]);
    }

    //创建订单,获取跳转小程序码
    public function getPayCode()
    {
        $data = request()->post('data/a', []);
        $imei = request()->post('imei', '');
//        $data = [
//            ['num' => 1, 'count' => 1],
//            ['num' => 2, 'count' => 1],
//        ];
        if (empty($imei)) {
            return json(['code' => 100, 'msg' => '缺少设备参数']);
        }
        if (empty($data)) {
            return json(['code' => 100, 'msg' => '请选购商品']);
        }
        $device = (new MachineDevice())->where('imei', $imei)->field('device_sn,expire_time,is_lock')->find();
        $device_sn = $device['device_sn'];
        if ($device['expire_time'] < time()) {
            return json(['code' => 100, 'msg' => '设备已过期,请联系客服处理!']);
        }
        if ($device['is_lock'] < 0) {
            return json(['code' => 100, 'msg' => '设备已禁用']);
        }
        $goodsModel = new MachineGoods();
        $uid = (new MachineDevice())->where('device_sn', $device_sn)->value('uid');
        $goods = $goodsModel->where('device_sn', $device_sn)->column('goods_id,price', 'num');
        $total_price = 0;
        $goods_count = 0;
        foreach ($data as $k => $v) {
            $total_price += $goods[$v['num']]['price'] * $v['count'];
            $goods_count += $v['count'];
        }
        $order_sn = time() . rand(1000, 9999);
        $order_data = [
            'order_sn' => $order_sn,
            'uid' => $uid,
            'device_sn' => $device_sn,
            'count' => $goods_count,
            'price' => $total_price,
            'status' => 0,
            'create_time' => time(),
        ];
        $order_id = (new FinanceOrder())->insertGetId($order_data);
        $order_goods = [];
        foreach ($data as $k => $v) {
            $order_goods[] = [
                'order_id' => $order_id,
                'device_sn' => $device_sn,
                'num' => $v['num'],
                'goods_id' => $goods[$v['num']]['goods_id'],
                'price' => $goods[$v['num']]['price'],
                'count' => $v['count'],
                'total_price' => $goods[$v['num']]['price'] * $v['count'],
                'create_time' => time()
            ];
        }
        (new OrderGoods())->saveAll($order_goods);
        $qrcode = qrcode($device_sn, 0, $order_sn);
        return json(['code' => 200, 'data' => ['qrcode' => $qrcode, 'order_sn' => $order_sn]]);
    }

    //获取客服二维码
    public function getService()
    {
        $imei = request()->get('imei', '');
        $model = new MachineDevice();
        $qrcode = $model->alias('d')
            ->join('operate_service s', 'd.sid=s.id', 'left')
            ->where('d.imei', $imei)
            ->value('s.qr_code');
        return json(['code' => 200, 'data' => ['qrcode' => $qrcode]]);
    }
}
