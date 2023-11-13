<?php

namespace app\medicine\controller;

use app\index\model\MedicineOrderGoodsModel;
use app\index\model\MedicineOrderModel;
use app\index\model\MedicineOrderRefundModel;
use think\Controller;

//绑定设备版本
class Order extends Controller
{
    /**
     * 订单列表
     * type 1:已完成  2:异常  3:售后
     * @return \think\response\Json
     */
    public function getList()
    {
        $params = request()->get();
        if (empty($params['openid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 10 : $params['limit'];
        $where = [];
        if ($params['type'] == 1) {
            $where['status'] = ['=', 1];
        } elseif ($params['type'] == 2) {
            $where['status'] = ['in', [3, 4]];
        } elseif ($params['type'] == 3) {
            $where['status'] = ['in', [2, 5]];
        }
        $model = new MedicineOrderModel();
        $orderGoodsModel = new MedicineOrderGoodsModel();
        $count = $model
            ->where('openid', $params['openid'])
            ->where($where)
            ->count();
        $list = $model
            ->where('openid', $params['openid'])
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        $order_ids = [];
        foreach ($list as $k => $v) {
            $order_ids[] = $v['id'];
        }
        $order_goods = $orderGoodsModel->alias('og')
            ->join('mt_goods g', 'og.goods_id=g.id', 'left')
            ->whereIn('order_id', $order_ids)
            ->group('og.order_id,g.goods_id')
            ->field('g.name,g.image,sum(og.count) total_count,sum(total_price) total_price,og.goods_id,og.order_id,og.price single_price,g.detail')
            ->select();
        foreach ($list as $k => $v) {
            foreach ($order_goods as $x => $y) {
                if ($y['order_id'] == $v['id']) {
                    $list[$k]['goods'][] = $y;
                }
            }
        }
        return json(['code' => 200, 'count' => $count, 'data' => $list, 'params' => $params]);
    }

    //提交退款申请
    public function submit_refund()
    {
        $params = request()->get();
        if (empty($params('order_id')) || empty($params['num']) || empty($params['goods_id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $orderGoodsModel = new MedicineOrderGoodsModel();
        $refundModel = new MedicineOrderRefundModel();
        $total_count = $orderGoodsModel
            ->where('order_id', $params['order_id'])
            ->where('goods_id', $params['id'])
            ->sum('count');
        $refund_count = $refundModel
            ->where('order_id', $params['order_id'])
            ->where('goods_id', $params['id'])
            ->where('status', '<>', 1)
            ->sum('num');
        if ($params['num'] > $total_count - $refund_count) {
            return json(['code' => 100, 'msg' => '超出可退款数量']);
        }
        $price = (new MedicineOrderGoodsModel())->where('order_id', $params['order_id'])->where('goods_id', $params['goods_id'])->value('price');
        $data = [
            'order_id' => $params['order_id'],
            'goods_id' => $params['goods_id'],
            'price' => $price,
            'num' => $params['num'],
            'total_price' => ($price * 100 * $params['num']) / 100,
            'status' => 0,
            'refund_sn' => time() . rand(1000, 9999)
        ];
        if ($data['total_price'] <= 0) {
            return json(['code' => 100, 'msg' => '退款金额必须大于0']);
        }
        $refundModel->save($data);
        return json(['code' => 200, 'msg' => '提交成功']);
    }
}