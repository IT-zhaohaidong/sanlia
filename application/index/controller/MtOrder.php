<?php

namespace app\index\controller;

use app\index\model\MtGoodsModel;
use app\index\model\MtOrderModel;
use app\index\model\MtRefundLogModel;
use app\meituan\controller\MeiTuan;
use think\Db;

class MtOrder extends BaseController
{
    public function orderList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 10);
        $app_poi_code = request()->get('app_poi_code', '');
        $model = new MtOrderModel();
        $count = $model
            ->where('app_poi_code', $app_poi_code)
            ->count();
        $list = $model
            ->where('app_poi_code', $app_poi_code)
            ->page($page)->limit($limit)
            ->order('create_time desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['detail'] = json_decode($v['detail'], true);
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //确认订单
    public function confirmOrder()
    {
        $order_id = request()->get('order_id', '');
        if (empty($order_id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $order = (new MtOrderModel())->where('order_id', $order_id)->find();
        $res = (new MeiTuan())->confirmOrder($order_id,$order['app_poi_code']);
        if ($res['data'] == 'ng') {
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        (new MtOrderModel())->where('order_id', $order_id)->update(['status' => 4]);
        return json(['code' => 200, 'msg' => '接单成功']);
    }

    //商家取消订单
    public function cancelOrder()
    {
        $order_id = request()->get('order_id', '');
        if (empty($order_id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $order = (new MtOrderModel())->where('order_id', $order_id)->find();
        $res = (new MeiTuan())->cancelOrder($order_id,$order['app_poi_code']);
        if ($res['data'] == 'ng') {
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        (new MtOrderModel())->where('order_id', $order_id)->update(['status' => 9]);
        return json(['code' => 200, 'msg' => '取消成功']);
    }

    //商家同意退款
    public function agreeRefund()
    {
        $order_id = request()->get('order_id', '');
        $reason = request()->get('reason', '');
        if (empty($order_id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $order = (new MtOrderModel())->where('order_id', $order_id)->find();
        $res = (new MeiTuan())->agreeRefund($order_id,$order['app_poi_code'],$reason);
//        $res = (new MeiTuan())->reviewAfterSales($order['app_poi_code'],$order['wm_order_id_view']);
        if ($res['data'] == 'ng') {
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        (new MtOrderModel())->where('order_id', $order_id)->update(['status' => 11]);
        $log = [
            'order_id' => $order_id,
            'app_poi_code' => $order['app_poi_code'],
            'notify_type' => 'agree',
            'refund_id' => '',
            'reason' => $reason,
            'res_type' => 2,
            'is_appeal' => 0,
            'pictures' => '',
        ];
        (new MtRefundLogModel())->save($log);
        return json(['code' => 200, 'msg' => '已同意']);
    }

    //商家拒绝退款
    public function refuseRefund()
    {
        $order_id = request()->get('order_id', '');
        $reason = request()->get('reason', '');
        if (empty($order_id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $order = (new MtOrderModel())->where('order_id', $order_id)->find();
        $res = (new MeiTuan())->refuseRefund($order_id,$order['app_poi_code'],$reason);
        if ($res['data'] == 'ng') {
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        (new MtOrderModel())->where('order_id', $order_id)->update(['status' => 12]);
        $log = [
            'order_id' => $order_id,
            'app_poi_code' => $order['app_poi_code'],
            'notify_type' => 'reject',
            'refund_id' => '',
            'reason' => $reason,
            'res_type' => 1,
            'is_appeal' => 0,
            'pictures' => '',
        ];
        (new MtRefundLogModel())->save($log);
        return json(['code' => 200, 'msg' => '已拒绝']);
    }
}