<?php

namespace app\index\controller;


use app\index\model\ShipGoodsModel;
use app\index\model\ShipOrderModel;

class ShipOrder extends BaseController
{
    //发货单列表
    public function getList()
    {
        $params = request()->post();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 15 : $params['limit'];
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['o.uid'] = $user['parent_id'];
            } else {
                $where['o.uid'] = $user['id'];
            }
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (!empty($params['order_sn'])) {
            $where['o.order_sn'] = ['like', '%' . $params['order_sn'] . '%'];
        }
        if (isset($params['status']) && $params['status'] != '') {
            $where['o.status'] = ['=', $params['status']];
        }
        $model = new ShipOrderModel();
        $count = $model->alias('o')
            ->join('system_admin a', 'a.id=o.uid', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('o')
            ->join('system_admin a', 'a.id=o.uid', 'left')
            ->field('o.*,a.username')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('o.id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['send_time'] = $v['send_time'] ? date('Y-m-d H:i:s', $v['send_time']) : '';
            $list[$k]['check_time'] = $v['check_time'] ? date('Y-m-d H:i:s', $v['check_time']) : '';
            $list[$k]['receive_time'] = $v['send_time'] ? date('Y-m-d H:i:s', $v['receive_time']) : '';
            $list[$k]['back_time'] = $v['send_time'] ? date('Y-m-d H:i:s', $v['back_time']) : '';
            $list[$k]['complete_time'] = $v['send_time'] ? date('Y-m-d H:i:s', $v['complete_time']) : '';
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //获取发货单详情
    public function getDetail()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new ShipOrderModel();
        $order = $model->alias('o')
            ->join('system_admin a', 'a.id=o.uid', 'left')
            ->where('o.id', $params['id'])
            ->field('o.*,a.username,a.phone user_phone')
            ->find();
        $order['send_time'] = $order['send_time'] ? date('Y-m-d', $order['send_time']) : '';
        $goodsModel = new ShipGoodsModel();
        $goodsList = $goodsModel->alias('og')
            ->join('mall_goods g', 'g.id=og.goods_id', 'left')
            ->where('og.order_id', $order['id'])
            ->field('og.*,g.image,g.title,g.code')
            ->select();
        $data = compact('order', 'goodsList');
        return json(['code' => 200, 'data' => $data]);
    }

    //驳回
    public function refuse()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if (!$params['refuse_reason']) {
            return json(['code' => 100, 'msg' => '请输入驳回原因']);
        }
        $data = [
            'check_time' => time(),
            'refuse_reason' => $params['refuse_reason'],
            'status' => 2
        ];
        (new ShipOrderModel())
            ->where('id', $params['id'])
            ->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //通过
    public function pass()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = [
            'check_time' => time(),
            'status' => 1
        ];
        (new ShipOrderModel())
            ->where('id', $params['id'])
            ->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //发货
    public function sendGoods()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['waybill_no'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = [
            'back_time' => time(),
            'waybill_no' => $params['waybill_no'],
            'status' => 4
        ];
        (new ShipOrderModel())
            ->where('id', $params['id'])
            ->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //运损单寄回,确认收货
    public function confirmTake()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = [
            'complete_time' => time(),
            'status' => 5
        ];
        (new ShipOrderModel())
            ->where('id', $params['id'])
            ->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

}
