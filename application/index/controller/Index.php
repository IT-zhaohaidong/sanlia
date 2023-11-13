<?php

namespace app\index\controller;

use app\index\model\DevicePartnerModel;
use app\index\model\SystemAdmin;
use think\Db;

class Index extends BaseController
{
    public function index()
    {
        $params = request()->get();
        $user = $this->user;
        if (isset($params['start_time']) && $params['start_time']) {
            $start_time = strtotime($params['start_time']);
        } else {
            $start_time = strtotime(date('Y-m-d', strtotime('-7 days')));
        }
        if (isset($params['end_time']) && $params['end_time']) {
            $end_time = strtotime($params['end_time']) + 24 * 3600;
        } else {
            $end_time = time();
        }
        $where = '';
        $jiaoyi_where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] <= 5) {
                $where = ' and uid = ' . $user['id'];
                $jiaoyi_where['uid'] = ['=', $user['id']];
            } else {
                $partnerModel = new DevicePartnerModel();
                $device_sn = $partnerModel->alias('p')
                    ->join('machine_device d', 'p.device_id=d.id')
                    ->where('p.uid', $user['id'])->where('admin_id', $user['parent_id'])
                    ->column('d.device_sn');
                $where = $device_sn ? ' and device_sn in ' . implode(',', $device_sn) : '';
                $jiaoyi_where['device_sn'] = ['in', $device_sn];
            }
        }
        $jiaoyi = Db::name('finance_order')
            ->where($jiaoyi_where)
            ->where('pay_time', 'between', [$start_time, $end_time])
            ->whereIn('status', [1, 2])
            ->field('sum(price) total_price,count(id) order_count')
            ->find();
        $refund = Db::name('finance_order')
            ->where($jiaoyi_where)
            ->where('pay_time', 'between', [$start_time, $end_time])
            ->where('status', 2)
            ->field('sum(price) total_price,count(id) order_count')
            ->find();
        $chengjiao = Db::name('finance_order')
            ->where($jiaoyi_where)
            ->where('pay_time', 'between', [$start_time, $end_time])
            ->where('status', 1)
            ->field('sum(price) total_price,count(id) order_count')
            ->find();
        $sql = 'select count(id) as day_count,sum(price) as day_money, FROM_UNIXTIME(pay_time, "%Y-%m-%d") as datetime from fs_finance_order where pay_time>= ' . $start_time . ' and pay_time < ' . $end_time . ' and status > 0' . $where . ' group by datetime order by datetime;';
        $data = Db::query($sql);
        $date = [];
        $day_count = [];
        $day_money = [];
        foreach ($data as $k => $v) {
            $date[] = date('Y-m-d', strtotime($v['datetime']));
            $day_count[] = $v['day_count'];
            $day_money[] = $v['day_money'];
        }
        $all_data = compact('date', 'day_count', 'day_money', 'jiaoyi', 'refund', 'chengjiao');
        $data = [
            'code' => 200,
            'data' => $all_data,
        ];
        return json($data);
    }

    //经营情况
    public function todayCount()
    {
        $user = $this->user;
        $today_time = strtotime(date('Y-m-d'));
        $yesterday_time = strtotime('-1day', $today_time);
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] <= 5) {
                $where['uid'] = ['=', $user['id']];
            } else {
                $partnerModel = new DevicePartnerModel();
                $device_sn = $partnerModel->alias('p')
                    ->join('machine_device d', 'p.device_id=d.id')
                    ->where('p.uid', $user['id'])->where('admin_id', $user['parent_id'])
                    ->column('d.device_sn');
                $where['device_sn'] = ['in', $device_sn];
            }
        }
        $jiaoyi = Db::name('finance_order')
            ->where($where)
            ->where('pay_time', '>=', $today_time)
            ->whereIn('status', [1, 2])
            ->field('sum(price) total_price,count(id) order_count')
            ->find();
        $z_jiaoyi = Db::name('finance_order')
            ->where($where)
            ->where('pay_time', '<', $today_time)
            ->where('pay_time', '>=', $yesterday_time)
            ->whereIn('status', [1, 2])
            ->sum('price');
        $jiaoyi['is_up'] = $jiaoyi['total_price'] >= $z_jiaoyi ? 1 : 0;
        $difference = $jiaoyi['total_price'] - $z_jiaoyi;
        $difference_value = $difference >= 0 ? $difference : 0 - $difference;
        $jiaoyi['up_num'] = $z_jiaoyi ? round(($difference_value / $z_jiaoyi) * 100, 2) . '%' : '0%';

        $refund = Db::name('finance_order')
            ->where($where)
            ->where('pay_time', '>=', $today_time)
            ->where('status', 2)
            ->field('sum(price) total_price,count(id) order_count')
            ->find();
        $z_refund = Db::name('finance_order')
            ->where($where)
            ->where('pay_time', '<', $today_time)
            ->where('pay_time', '>=', $yesterday_time)
            ->where('status', 2)
            ->sum('price');
        $refund['is_up'] = $refund['total_price'] >= $z_refund ? 1 : 0;
        $difference = $refund['total_price'] - $z_refund;
        $difference_value = $difference >= 0 ? $difference : 0 - $difference;
        $refund['up_num'] = $z_refund ? round(($difference_value / $z_refund) * 100, 2) . '%' : '0%';

        $chengjiao = Db::name('finance_order')
            ->where($where)
            ->where('pay_time', '>=', $today_time)
            ->where('status', 1)
            ->sum('price');
        $data = compact('jiaoyi', 'refund', 'chengjiao');
        return json(['code' => 200, 'data' => $data]);
    }

}
