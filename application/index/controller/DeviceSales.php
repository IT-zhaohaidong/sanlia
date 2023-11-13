<?php

namespace app\index\controller;

use think\Db;

class DeviceSales extends BaseController
{
    //设备收益统计
    public function getList()
    {
        $params = request()->get();
        $model = new \app\index\model\FinanceOrder();
        if (empty($params['device_ids'])) {
            return json(['code' => 100, 'msg' => '请选择设备']);
        }
        if (empty($params['start_time']) || empty($params['end_time'])) {
            return json(['code' => 100, 'msg' => '请选择时间']);
        }
        $start_time = strtotime($params['start_time']);
        $end_time = strtotime($params['end_time']) + 3600 * 24;
        $where['o.create_time'] = ['between', $start_time . ',' . $end_time];
        $device_sns = (new \app\index\model\MachineDevice())->whereIn('id', $params['device_ids'])->column('device_sn');
        //总交易
        $list = $model->alias('o')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods mg', 'og.goods_id=mg.id', 'left')
            ->whereIn('o.device_sn', $device_sns)
            ->where($where)
            ->where('o.status', '>', 0)
            ->group('og.goods_id')
            ->field('sum(og.count) total_count,sum(o.price) total_money,mg.title,mg.image,mg.id')
            ->order('total_money desc')
            ->select();
        //退款
        $refund = $model->alias('o')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods mg', 'og.goods_id=mg.id', 'left')
            ->whereIn('o.device_sn', $device_sns)
            ->where('o.create_time', '>=', $start_time)
            ->where('o.create_time', '<', $end_time)
            ->where('o.status', 2)
            ->group('og.goods_id')
            ->column('sum(og.count) refund_count,sum(o.price) refund_money,mg.title,mg.image', 'mg.id');
        //总交易
        $total_count = 0;
        $total_money = 0;
        //总退款
        $total_refund_count = 0;
        $total_refund_money = 0;

        foreach ($list as $k => $v) {
            $refund_count = empty($refund[$v['id']]) ? 0 : $refund[$v['id']]['refund_count'];
            $refund_money = empty($refund[$v['id']]) ? 0 : $refund[$v['id']]['refund_money'];
            $list[$k]['refund_count'] = $refund_count;
            $list[$k]['refund_money'] = $refund_money;
            $list[$k]['transaction_count'] = $v['total_count'] - $refund_count;
            $list[$k]['transaction_money'] = $v['total_money'] - $refund_money;
            $total_count += $v['total_count'];
            $total_money += $v['total_money'] * 10000;
            $total_refund_count += $refund_count;
            $total_refund_money += $refund_money;
        }
        $total_money = $total_money / 10000;
        //总成交
        $total_transaction_count = $total_count - $total_refund_count;
        $total_transaction_money = ($total_money * 100 - $total_refund_money * 100) / 100;
        $total = [
            'total_count' => $total_count,
            'total_money' => round($total_money,2),
            'total_refund_count' => $total_refund_count,
            'total_refund_money' =>  round($total_refund_money,2),
            'total_transaction_count' => $total_transaction_count,
            'total_transaction_money' => round($total_transaction_money,2),
        ];
        return json(['code' => 200, 'data' => $list, 'total' => $total]);
    }

    //商品收益统计
    public function goodsSales()
    {
        $params = request()->get();
        $model = new \app\index\model\FinanceOrder();
        if (empty($params['start_time']) || empty($params['end_time'])) {
            return json(['code' => 100, 'msg' => '请选择时间']);
        }
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $start_time = strtotime($params['start_time']);
        $end_time = strtotime($params['end_time']) + 3600 * 24;
        //总交易
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $where['o.device_sn'] = $this->getBuHuoWhere();
            } else {
                $where['o.uid'] = $user['id'];
            }
        }
        if (!empty($params['title'])) {
            $where['mg.title'] = ['like', '%' . $params['title'] . '%'];
        }
        $pageList = $model->alias('o')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods mg', 'og.goods_id=mg.id', 'left')
            ->where($where)
            ->where('o.create_time', '>=', $start_time)
            ->where('o.create_time', '<', $end_time)
            ->where('o.status', '>', 0)
            ->group('og.goods_id')
            ->field('sum(og.count) total_count,sum(o.price) total_money,sum(o.commission) total_commission,mg.title,mg.image,mg.id')
            ->order('total_money desc')
            ->page($page)
            ->limit($limit)
            ->select();
        //退款
        $pageRefund = $model->alias('o')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods mg', 'og.goods_id=mg.id', 'left')
            ->where($where)
            ->where('o.create_time', '>=', $start_time)
            ->where('o.create_time', '<', $end_time)
            ->where('o.status', 2)
            ->group('og.goods_id')
            ->page($page)
            ->limit($limit)
            ->column('sum(og.count) refund_count,sum(o.price) refund_money,mg.title,mg.image', 'mg.id');
        foreach ($pageList as $k => $v) {
            $refund_count = empty($pageRefund[$v['id']]) ? 0 : $pageRefund[$v['id']]['refund_count'];
            $refund_money = empty($pageRefund[$v['id']]) ? 0 : $pageRefund[$v['id']]['refund_money'];
            $pageList[$k]['refund_count'] = $refund_count;
            $pageList[$k]['refund_money'] = $refund_money;
            $pageList[$k]['transaction_count'] = $v['total_count'] - $refund_count;
            $pageList[$k]['transaction_money'] = $v['total_money'] - $refund_money;
        }
        $list = $model->alias('o')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods mg', 'og.goods_id=mg.id', 'left')
            ->where($where)
            ->where('o.create_time', '>=', $start_time)
            ->where('o.create_time', '<', $end_time)
            ->where('o.status', '>', 0)
            ->group('og.goods_id')
            ->field('sum(og.count) total_count,sum(o.price) total_money,sum(o.commission) total_commission,mg.title,mg.image,mg.id')
            ->order('total_money desc')
            ->select();
        //退款
        $refund = $model->alias('o')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods mg', 'og.goods_id=mg.id', 'left')
            ->where($where)
            ->where('o.create_time', '>=', $start_time)
            ->where('o.create_time', '<', $end_time)
            ->where('o.status', 2)
            ->group('og.goods_id')
            ->column('sum(og.count) refund_count,sum(o.price) refund_money,sum(o.commission) total_commission,mg.title,mg.image', 'mg.id');
        //总交易
        $total_count = 0;
        $total_money = 0;
        //总退款
        $total_refund_count = 0;
        $total_refund_money = 0;

        foreach ($list as $k => $v) {
            $refund_count = empty($refund[$v['id']]) ? 0 : $refund[$v['id']]['refund_count'];
            $refund_money = empty($refund[$v['id']]) ? 0 : $refund[$v['id']]['refund_money'];
//            $list[$k]['refund_count'] = $refund_count;
//            $list[$k]['refund_money'] = $refund_money;
//            $list[$k]['transaction_count'] = $v['total_count'] - $refund_count;
//            $list[$k]['transaction_money'] = $v['total_money'] - $refund_money;
            $total_count += $v['total_count'];
            $total_money += $v['total_money'] * 100;
            $total_refund_count += $refund_count;
            $total_refund_money += $refund_money;
        }
        $total_money = $total_money / 100;
        //总成交
        $total_transaction_count = $total_count - $total_refund_count;
        $total_transaction_money = ($total_money * 100 - $total_refund_money * 100) / 100;
        $total = [
            'total_count' => $total_count,
            'total_money' => $total_money,
            'total_refund_count' => $total_refund_count,
            'total_refund_money' => $total_refund_money,
            'total_transaction_count' => $total_transaction_count,
            'total_transaction_money' => $total_transaction_money,
        ];
        $count = count($list);
        return json(['code' => 200, 'data' => $list, 'total' => $total, 'params' => $params, 'count' => $count]);
    }
}
