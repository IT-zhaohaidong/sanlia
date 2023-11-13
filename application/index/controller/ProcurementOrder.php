<?php

namespace app\index\controller;

use app\index\model\MachineStockLogModel;
use app\index\model\ProcurementOrderModel;
use think\Db;

class ProcurementOrder
{
    //获取补货单
    public function getOrder()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = (new ProcurementOrderModel())->alias('p')
            ->join('machine_device d', 'p.device_id=d.id', 'left')
            ->where('p.id', $id)
            ->field('p.*,d.device_sn,d.device_name')
            ->find();
        $list = Db::name('procurement_goods')->alias('p')
            ->join('mall_goods g', 'p.goods_id=g.id', 'left')
            ->where('p.pro_id', $data['id'])
            ->field('p.*,g.title,g.image')
            ->select();
        $data['complete_time']=date('Y-m-d H:i',$data['complete_time']);
        $data['goods_list'] = $list;
        return json(['code' => 200, 'data' => $data]);
    }

    //补货完成
    public function complete()
    {
        $id = request()->get('id', '');
        $orderModel = new ProcurementOrderModel();
        $order = $orderModel->where('id', $id)->find();
        if ($order['status'] != 0) {
            $msg = $order['status'] == 1 ? '该补货单已完成' : '该补货单已撤销';
            return json(['code' => 100, 'msg' => $msg]);
        }
        //更改补货单状态
        $orderModel->where('id', $id)->update(['status' => 1,'complete_time'=>time()]);
        //更改货道库存
        $pro_goods = Db::name('procurement_goods')
            ->where('pro_id', $id)
            ->select();
        $device_sn = (new \app\index\model\MachineDevice())
            ->where('id', $order['device_id'])
            ->value('device_sn');
        $goodsModel = new \app\index\model\MachineGoods();
        $machine_goods = $goodsModel
            ->where('device_sn', $device_sn)
            ->column('id,goods_id,stock', 'num');
        $stockLog = [];
        foreach ($pro_goods as $k => $v) {
            $total_stock = $v['count'] + $machine_goods[$v['num']]['stock'];
            $goodsModel->where('id', $machine_goods[$v['num']]['id'])->update(['stock' => $total_stock]);
            $stockLog[] = [
                'uid' => $order['uid'],
                'device_sn' => $device_sn,
                'num' => $v['num'],
                'goods_id' => $v['goods_id'],
                'old_stock' => $machine_goods[$v['num']]['stock'],
                'new_stock' => $total_stock,
                'change_detail' => '补货单补货,库存增加' . $v['count'] . '件;补货单号:'.$order['order_sn'],
            ];
        }

        //添加库存日志
        (new MachineStockLogModel())->saveAll($stockLog);
        return json(['code' => 200, 'msg' => '补货成功']);
    }
}