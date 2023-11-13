<?php

namespace app\applet\controller;

use app\index\model\ShopOrderGoodsModel;
use app\index\model\ShopOrderModel;
use think\Controller;

class ShopOrder extends Controller
{
    public function getList()
    {
        $params = request()->get();
        if (empty($params['openid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $where = [];
        if (!empty($params['status'])) {
            if ($params['status'] == 1) {
                $where['status'] = ['=', 0];
            }
            if ($params['status'] == 2) {
                $where['status'] = ['in', [5, 6, 7, 8, 9]];
            }
        }
        $model = new ShopOrderModel();
        $list = $model
            ->where('openid', $params['openid'])
            ->where($where)
            ->order('id desc')
            ->select();
        if ($list) {
            $order_ids = array_column($list, 'id');
            $goods = (new ShopOrderGoodsModel())->alias('og')
                ->join('shop_goods g', 'g.id=og.goods_id', 'left')
                ->field('og.*,g.title,g.image')
                ->whereIn('og.order_id', $order_ids)
                ->select();
            foreach ($list as $k => $v) {
                $item = [];
                foreach ($goods as $x => $y) {
                    if ($y['order_id'] == $v['id']) {
                        $item[] = $y;
                    }
                }
                $list[$k]['children'] = $item;
            }
        }
        return json(['code' => 200, 'data' => $list]);
    }

    //确认收货
    public function confirmTakeGoods()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = [
            'status' => 1, 'take_time' => time()
        ];
        $model = new ShopOrderModel();
        $model->where('id', $id)->update($data);
        return json(['code' => 200, 'msg' => '收货成功']);
    }

    //退款售后 提交退款申请
    public function applyRefund()
    {
        $id = request()->get('id', '');
        $refund_type = request()->get('refund_type', '');
        $refund_reason = request()->get('refund_reason', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if (!$refund_type) {
            return json(['code' => 100, 'msg' => '请选择退款方式']);
        }
        $data = [
            'status' => 5,
            'apply_time' => time(),
            'refund_type' => $refund_type,
            'refund_reason' => $refund_reason
        ];
        $model = new ShopOrderModel();
        $model->where('id', $id)->update($data);
        return json(['code' => 200, 'msg' => '退款申请已提交']);
    }

    //填写退货物流单号
    public function waybillSn()
    {
        $id = request()->get('id', '');
        $back_waybill = request()->get('back_waybill', '');
        if (strlen($back_waybill) < 12) {
            return json(['code' => 100, 'msg' => '请输入合法运单号']);
        }
        if (!$id || !$back_waybill) {
            return json(['code' => 100, 'msg' => '请填写运单号']);
        }
        $data = [
            'status' => 9,
            'back_waybill' => $back_waybill
        ];
        $model = new ShopOrderModel();
        $model->where('id', $id)->update($data);
        return json(['code' => 200, 'msg' => '已退回,请耐心等待']);
    }
}
