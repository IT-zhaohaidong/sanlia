<?php

namespace app\applet\controller;

use app\index\model\FinanceCash;
use app\index\model\FinanceOrder;
use app\index\model\MachineDevice;
use think\Controller;
use think\Db;

class Daping extends Controller
{
    public function index()
    {
        $orderModel = new FinanceOrder();
        $deviceModel = new MachineDevice();
        //设备销量前十
        $order = $orderModel
            ->where('status', '>', 0)
            ->group('device_sn')
            ->field('count(id) order_num,device_sn')
            ->order('order_num desc')
            ->limit(10)
            ->select();
        $device_sns = $order ? array_column($order, 'device_sn') : [];
        $device = $deviceModel->whereIn('device_sn', $device_sns)->column('device_name', 'device_sn');
        $top10_device = [];
        foreach ($order as $k => $v) {
            $device_name = !empty($device[$v['device_sn']]) ? $device[$v['device_sn']] : '';
            $top10_device[] = [$v['device_sn'], $device_name];
        }
        $data['top10_device'] = $top10_device;
        //商品销量前10
        $top10_goods = $orderModel->alias('o')
            ->join('order_goods g', 'o.id=g.order_id', 'left')
            ->join('mall_goods mg', 'mg.id=g.goods_id', 'left')
            ->where('o.status', '>', 0)
            ->group('g.goods_id')
            ->field('count(mg.id) value,mg.title name')
            ->order('value desc')
            ->limit(10)
            ->select();
        $data['top10_goods'] = $top10_goods;
        //收益信息
//        $cashModel = new FinanceOrder();
//        $total = $cashModel->where('status', 1)->sum('price');
//        $month_time = strtotime(date('Y-m-1'));
//        $month = $cashModel->where('create_time', '>', $month_time)->sum('price');
//        $day_time = strtotime(date('Y-m-d'));
//        $day = $cashModel->where('create_time', '>', $day_time)->sum('price');
        $day = strtotime(date('Y-m-d'));
        $month = strtotime(date('Y-m-01'));
        $day = $this->getOrderNum($day);
        $month = $this->getOrderNum($month);
        $total = $this->getOrderNum();
        $cash = [
            ['name' => '总交易', 'value' => $total['money']],
            ['name' => '月交易', 'value' => $month['money']],
            ['name' => '日交易', 'value' => $day['money']]
        ];
        $data['cash'] = $cash;
        //设备在线情况
        $online = $deviceModel->where('status', 1)->count();
        $total = $deviceModel->count();
        $data['device_online'] = [$total, $online, $total - $online];
        //销售占比图
        $data['goods_sales'] = $top10_goods;
        //销售额趋势
        $week_time = mktime(0, 0, 0, date('m'), date('d') - 7, date('Y')) - 1;
        $sql = 'select count(id) as day_count,sum(price) as day_money, FROM_UNIXTIME(create_time, "%Y-%m-%d") as datetime from fs_finance_order where create_time>= ' . $week_time . ' and status = 1' . ' group by datetime order by datetime;';
        $week = Db::query($sql);
        $datetime = [];
        $dateMoney = [];
        foreach ($week as $k => $v) {
            $datetime[] = date('m-d', strtotime($v['datetime']));
            $dateMoney[] = $v['day_money'];
        }
        $data['week_get'] = [
            'datetime' => $datetime,
            'dateMoney' => $dateMoney,
        ];
        $data = [
            'code' => 200,
            'msg' => '获取成功',
            'data' => $data
        ];
        return json($data);
    }

    public function getOrderNum($time = '')
    {
//        $user = $this->user;
//        $where = [];
//        if ($user['role_id'] != 1) {
//            if (!in_array('2', explode(',', $user['roleIds']))) {
//                $where['device_sn'] = $this->getBuHuoWhere();
//            } else {
//                $where['uid'] = $user['id'];
//            }
//        }
        $time_where = [];
        if ($time) {
            $time_where['pay_time'] = ['>', $time];
        }
        $model = new \app\index\model\FinanceOrder();
        $num = $model->where($time_where)->where('status', 1)->count() ?? 0;
        $money = $model
            ->where($time_where)
            ->where('status', 1)->field('sum(price) total_price')
            ->group('status')
            ->find();
        return ['num' => $num, 'money' => $money['total_price'] ?? '0.00'];
    }
}
