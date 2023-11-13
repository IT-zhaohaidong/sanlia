<?php

namespace app\applet\controller;

use app\index\model\FinanceOrder;
use think\Controller;

class Order extends Controller
{
    public function orderList()
    {
        $openid = $this->request->get('openid', '');
        if (empty($openid)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $orderModel = new FinanceOrder();
        $arr = $orderModel->alias("o")
            ->join("fs_order_goods og", "og.order_id=o.id")
            ->join("fs_mall_goods g", "og.goods_id=g.id")
            ->field("o.id,o.order_sn,o.count,og.goods_id,o.price,g.title,g.description,g.image,o.status,o.pay_type,o.pay_time")
            ->where('o.status', '>', 0)
            ->where("o.openid", $openid)
            ->order('o.id desc')
            ->select();
        $data = [
            "code" => 200,
            "msg" => "获取成功",
            "data" => $arr
        ];
        return json($data);
    }

    public function getOrderInfo()
    {
        $data = [
            'code' => 100,
            'msg' => "获取订单失败"
        ];
        $id = $this->request->get('id', '');
        $orderModel = new FinanceOrder();
        $row = $orderModel->alias("o")
            ->join("fs_order_goods og", "og.order_id=o.id")
            ->join("fs_mall_goods g", "og.goods_id=g.id")
            ->field("o.id,o.order_sn,o.count,og.goods_id,o.price,g.title,g.image,o.status,o.pay_type,o.pay_time")
            ->where('o.id', $id)
            ->find();

        if (empty($row)) {
            return json($data);
        }
        $row['pay_time'] = date('Y-m-d H:i:s', $row['pay_time']);
        $data = [
            "code" => 200,
            "msg" => "获取成功 ",
            'data' => $row
        ];
        return json($data);
    }



}
