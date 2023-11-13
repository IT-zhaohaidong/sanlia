<?php

namespace app\index\controller;

use app\index\model\DevicePartnerModel;
use app\index\model\FinanceCash;
use app\index\model\OrderGoods;
use app\index\model\SystemAdmin;
use think\Db;
use think\db\Expression;

class IncomeCount extends BaseController
{
    public function index()
    {
        $orderModel = new \app\index\model\FinanceOrder();
        $deviceModel = new \app\index\model\MachineDevice();
        $user = $this->user;
        $time_count = [];
        $user_count = [];
        $today_time = strtotime(date('Y-m-d'));
        $yesterday_time = strtotime('-1day', $today_time);
        $month_time = strtotime(date('Y-m-01'));
        $last_month_time = strtotime(date('Y-m-01') . ' -1 month');//上一个月的时间
        if ($user['role_id'] == 1) {
            //------------------------------超级管理员-----------------------------

            //------------------------------日期收益统计-----------------------------
            //今日收益
            $today_get = $orderModel
                ->where('status', 1)
                ->where('create_time', '>=', time())
                ->sum('price');
            $yesterday_get = $orderModel
                ->where('status', 1)
                ->where('create_time', '>=', $yesterday_time)
                ->where('create_time', '<', $today_time)
                ->sum('price');
            $device_count = $deviceModel->where('uid', '<>', 1)->where('delete_time', null)->count();
            //月收益
            $month_get = $orderModel
                ->where('status', 1)
                ->where('create_time', '>=', $month_time)
                ->sum('price');
            $last_month_get = $orderModel
                ->where('status', 1)
                ->where('create_time', '<', $month_time)
                ->where('create_time', '>=', $last_month_time)
                ->sum('price');

            //总收益
            $total_get = $orderModel
                ->where('status', 1)
                ->sum('price');
            //-------------------------代理商收益统计----------------------
            $adminModel = new SystemAdmin();
            $admin_day = $adminModel
                ->where('role_id', 'between', '2,5')
                ->where('delete_time', null)
                ->field('id,username,role_id')
                ->limit(5)
                ->select();
            foreach ($admin_day as $k => $v) {
                $user_count['username'][] = $v['username'];
                $day_get = $orderModel
                    ->where('uid', $v['id'])
                    ->where('status', 1)
                    ->where('create_time', '>=', $today_time)
                    ->field("sum(price) day_price")
                    ->group('uid')
                    ->find();
                $total = $orderModel
                    ->where('uid', $v['id'])
                    ->where('status', 1)
                    ->field("sum(price) total_price")
                    ->group('uid')
                    ->find();
                $user_count['day_count'][] = $day_get['day_price'] ?? 0;
                $user_count['total_count'][] = $total['total_price'] ?? 0;
            }
        } else {
            if ($user['role_id'] <= 5) {
                $device_count = $deviceModel->where('uid', $user['id'])->where('delete_time', null)->count();
            } else {
                $partnerModel = new DevicePartnerModel();
                $device_count = $partnerModel->where('uid', $user['id'])->where('admin_id', $user['parent_id'])->count();
            }
            $cashModel = new FinanceCash();
            $today_get = $cashModel
                ->where('type', 1)
                ->where('uid', $user['id'])
                ->where('create_time', '>=', time())
                ->sum('price');
            $yesterday_get = $cashModel
                ->where('type', 1)
                ->where('create_time', '>=', $yesterday_time)
                ->where('create_time', '<', $today_time)
                ->sum('price');

            //月收益
            $month_get = $cashModel
                ->where('type', 1)
                ->where('uid', $user['id'])
                ->where('create_time', '>=', $month_time)
                ->sum('price');
            $last_month_get = $cashModel
                ->where('type', 1)
                ->where('uid', $user['id'])
                ->where('create_time', '<', $month_time)
                ->where('create_time', '>=', $last_month_time)
                ->sum('price');
            //总收益
            $total_get = $cashModel
                ->where('uid', $user['id'])
                ->where('type', 1)
                ->sum('price');

            //-------------------------运维人员收益统计----------------------
            if ($user['role_id'] <= 5) {
                $adminModel = new SystemAdmin();
                $admin_day = $adminModel
                    ->where('role_id', '>', '5')
                    ->where('parent_id', $user['id'])
                    ->where('delete_time', null)
                    ->field('id,username,role_id')
                    ->limit(5)
                    ->select();
                foreach ($admin_day as $k => $v) {
                    $user_count['username'][] = $v['username'];
                    $day_get = $cashModel
                        ->where('uid', $v['id'])
                        ->where('type', 1)
                        ->where('create_time', '>=', $today_time)
                        ->field("sum(price) day_price")
                        ->group('uid')
                        ->find();
                    $total = $cashModel
                        ->where('uid', $v['id'])
                        ->where('type', 1)
                        ->field("sum(price) total_price")
                        ->group('uid')
                        ->find();
                    $user_count['day_count'][] = $day_get['day_price'] ?? 0;
                    $user_count['total_count'][] = $total['total_price'] ?? 0;
                }
            }
        }
        $time_count['day']['today_get'] = $today_get;
        $time_count['day']['compare_yesterday'] = $yesterday_get ? sprintf("%.1f", ($today_get / $yesterday_get) * 100) . '%' : 0 . '%';
        $time_count['day']['daily'] = $today_get;
        $time_count['day']['device_daily'] = $device_count ? sprintf("%.2f", $today_get / $device_count) : 0;
        $time_count['month']['month_get'] = $month_get;
        $time_count['month']['compare_last_month'] = $last_month_get ? sprintf("%.1f", ($month_get / $last_month_get) * 100) . '%' : 0 . '%';
        $time_count['month']['daily'] = sprintf("%.2f", $month_get / date('d'));
        $time_count['month']['device_daily'] = $device_count ? sprintf("%.2f", ($month_get / date('d')) / $device_count) : 0;
        $time_count['total']['total_get'] = $total_get;
        $data = compact('time_count', 'user_count');
        return json(['code' => 200, 'data' => $data]);
    }

    //设备收益统计
    public function deviceIncome()
    {
        $limit = request()->get('limit', 5);
        $page = request()->get('page', 1);
        $params = request()->get();
        $user = $this->user;
        $today_time = strtotime(date('Y-m-d'));
        $yesterday_time = strtotime('-1day', $today_time);
        $month_time = strtotime(date('Y-m-01'));
        $last_month_time = strtotime(date('Y-m-01') . ' -1 month');//上一个月的时间
        $orderModel = new \app\index\model\FinanceOrder();
        $deviceModel = new \app\index\model\MachineDevice();
        if ($user['role_id'] == 1) {
            $device_count = $deviceModel->where('uid', '<>', 1)->where('delete_time', null)->count();
            $today_get = $orderModel
                ->where('status', 1)
                ->where('create_time', '>=', $today_time)
                ->sum('price');
            $yesterday_get = $orderModel
                ->where('status', 1)
                ->where('create_time', '<', $today_time)
                ->where('create_time', '>=', $yesterday_time)
                ->sum('price');
            $today_daily = $device_count ? sprintf("%.2f", $today_get / $device_count) : 0;
            $yesterday_daily = $device_count ? sprintf("%.2f", $yesterday_get / $device_count) : 0;
            //列表
//            $device = $orderModel
//                ->where('status', 1)
//                ->group('device_sn')
//                ->field('device_sn,count(id) order_count,sum(price) device_price')
//                ->order('order_count asc')
//                ->select();
//            $device_sns = [];
//            foreach ($device as $k => $v) {
//                $device_sns[] = $v['device_sn'];
//            }
//            if ($device_sns) {
//                $device_sns = implode(',', $device_sns);
//                $exp = new Expression("field(device_sn,$device_sns) desc");
//            } else {
//                $exp = '';
//            }

            $count = Db::name('machine_device')->alias('d')
                ->join('finance_order o','d.device_sn=o.device_sn','left')
                ->group('d.device_sn')
//                ->where($where)
                ->where('d.delete_time', null)->count();
            $list = Db::name('machine_device')->alias('d')
                ->join('finance_order o','d.device_sn=o.device_sn','left')
                ->group('d.device_sn')
//                ->where($where)
                ->where('d.delete_time', null)
                ->limit($limit)->page($page)
                ->field('d.id,d.device_name,d.device_sn,count(o.id) order_count,sum(o.price) device_price')
                ->orderRaw('order_count desc')
                ->select();
            foreach ($list as $k => $v) {
                $today = $orderModel
                    ->where('device_sn', $v['device_sn'])
                    ->where('status', 1)
                    ->where('create_time', '>=', $today_time)
                    ->group('device_sn')
                    ->field('count(id) order_count,sum(price) day_get')
                    ->find();
                $list[$k]['today_count'] = $today['order_count'];
                $list[$k]['today_get'] = $today['day_get'];
                $yesterday = $orderModel
                    ->where('device_sn', $v['device_sn'])
                    ->where('status', 1)
                    ->where('create_time', '<', $today_time)
                    ->where('create_time', '>=', $yesterday_time)
                    ->group('device_sn')
                    ->field('count(id) order_count,sum(price) day_get')
                    ->find();
                $list[$k]['yesterday_count'] = $yesterday['order_count'];
                $list[$k]['yesterday_get'] = $yesterday['day_get'];
                $month = $orderModel
                    ->where('device_sn', $v['device_sn'])
                    ->where('status', 1)
                    ->where('create_time', '>=', $month_time)
                    ->group('device_sn')
                    ->field('count(id) order_count,sum(price) day_get')
                    ->find();
                $list[$k]['month_count'] = $month['order_count'];
                $list[$k]['month_get'] = $month['day_get'];
            }
        } else {
            $where = [];
            if ($user['role_id'] <= 5) {
                $where['d.uid'] = ['=', $user['id']];
                $device_count = $deviceModel->where('uid', '<>', 1)->where('delete_time', null)->count();
            } else {
                $partnerModel = new DevicePartnerModel();
                $device_sn = $partnerModel->alias('p')
                    ->join('machine_device d', 'p.device_id=d.id')
                    ->where('uid', $user['id'])->where('admin_id', $user['parent_id'])
                    ->column('p.device_sn');
                $device_count = count($device_sn);
                $where['d.device_sn'] = ['in', $device_sn];
            }
            $cashModel = new FinanceCash();
            $today_get = $cashModel
                ->where('uid', $user['id'])
                ->where('create_time', '>=', $today_time)
                ->sum('price');
            $yesterday_get = $cashModel
                ->where('uid', $user['id'])
                ->where('create_time', '<', $today_time)
                ->where('create_time', '>=', $yesterday_time)
                ->sum('price');
            $today_daily = $device_count ? sprintf("%.2f", $today_get / $device_count) : 0;
            $yesterday_daily = $device_count ? sprintf("%.2f", $yesterday_get / $device_count) : 0;
            //列表
//            $device = $orderModel
//                ->where('status', 1)
//                ->where($where)
//                ->group('device_sn')
//                ->field('device_sn,count(id) order_count,sum(price) device_price')
//                ->order('order_count asc')
//                ->select();
//            $device_sns = [];
//            foreach ($device as $k => $v) {
//                $device_sns[] = $v['device_sn'];
//            }
//            $device_sns = implode(',', $device_sns);
//            $exp = $device_sns ? new Expression("field(device_sn,$device_sns) desc") : '';
            $count = Db::name('machine_device')->alias('d')
                ->join('finance_order o','d.device_sn=o.device_sn','left')
                ->group('d.device_sn')
                ->where($where)
                ->where('d.delete_time', null)->count();
            $list = Db::name('machine_device')->alias('d')
                ->join('finance_order o','d.device_sn=o.device_sn','left')
                ->group('d.device_sn')
                ->where($where)
                ->where('d.delete_time', null)
                ->limit($limit)->page($page)
                ->field('d.id,d.device_name,d.device_sn,count(o.id) order_count,sum(o.price) device_price')
                ->orderRaw('order_count desc')
                ->select();
            foreach ($list as $k => $v) {
                $today = $orderModel->alias('o')
                    ->join('finance_cash c', 'o.order_sn=c.order_sn', 'left')
                    ->where('o.device_sn', $v['device_sn'])
                    ->where('o.status', 1)
                    ->where('c.uid', $user['id'])
                    ->where('o.create_time', '>=', $today_time)
                    ->group('o.device_sn')
                    ->field('count(o.id) order_count,sum(c.price) day_get')
                    ->find();
                $list[$k]['today_count'] = $today['order_count'];
                $list[$k]['today_get'] = $today['day_get'];
                $yesterday = $orderModel->alias('o')
                    ->join('finance_cash c', 'o.order_sn=c.order_sn', 'left')
                    ->where('o.device_sn', $v['device_sn'])
                    ->where('o.status', 1)
                    ->where('c.uid', $user['id'])
                    ->where('o.create_time', '<', $today_time)
                    ->where('o.create_time', '>=', $yesterday_time)
                    ->group('o.device_sn')
                    ->field('count(o.id) order_count,sum(c.price) day_get')
                    ->find();
                $list[$k]['yesterday_count'] = $yesterday['order_count'];
                $list[$k]['yesterday_get'] = $yesterday['day_get'];
                $month = $orderModel->alias('o')
                    ->join('finance_cash c', 'o.order_sn=c.order_sn', 'left')
                    ->where('o.device_sn', $v['device_sn'])
                    ->where('o.status', 1)
                    ->where('c.uid', $user['id'])
                    ->where('o.create_time', '>=', $month_time)
                    ->group('o.device_sn')
                    ->field('count(o.id) order_count,sum(c.price) day_get')
                    ->find();
                $list[$k]['month_count'] = $month['order_count'];
                $list[$k]['month_get'] = $month['day_get'];
            }
        }
        $daily = compact('today_daily', 'yesterday_daily');
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params, 'daily' => $daily]);
    }

    //查看全部代理商/运维人员
    public function getUserList()
    {
        $user = $this->user;
        $today_time = strtotime(date('Y-m-d'));
        $adminModel = new SystemAdmin();
        $orderModel = new \app\index\model\FinanceOrder();
        if ($user['id'] == 1) {
            $admin_day = $adminModel->alias('a')
                ->where('role_id', 'between', '2,5')
                ->where('delete_time', null)
                ->field('id,username')
                ->select();
            foreach ($admin_day as $k => $v) {
                $day_get = $orderModel
                    ->where('uid', $v['id'])
                    ->where('status', 1)
                    ->where('create_time', '>=', $today_time)
                    ->field("sum(price) day_price")
                    ->group('uid')
                    ->find();
                $total = $orderModel
                    ->where('uid', $v['id'])
                    ->where('status', 1)
                    ->field("sum(price) total_price")
                    ->group('uid')
                    ->find();
                $admin_day[$k]['day_price'] = $day_get['day_price'] ?? 0;
                $admin_day[$k]['total_count'] = $total['total_price'] ?? 0;
            }
        } else {
            $admin_day = $adminModel
                ->where('role_id', '>', 5)
                ->where('parent_id', $user['id'])
                ->where('delete_time', null)
                ->field('id,username')
                ->select();
            $cashModel = new FinanceCash();
            foreach ($admin_day as $k => $v) {
                $day_get = $cashModel
                    ->where('uid', $v['id'])
                    ->where('type', 1)
                    ->where('create_time', '>=', $today_time)
                    ->field("sum(price) day_price")
                    ->group('uid')
                    ->find();
                $total = $cashModel
                    ->where('uid', $v['id'])
                    ->where('type', 1)
                    ->field("sum(price) total_price")
                    ->group('uid')
                    ->find();
                $admin_day[$k]['day_price'] = $day_get['day_price'] ?? 0;
                $admin_day[$k]['total_count'] = $total['total_price'] ?? 0;
            }
        }
        return json(['code' => 200, 'data' => $admin_day]);
    }

    public function searchIncome()
    {
        $start_time = request()->get('start_time', '');
        $end_time = request()->get('end_time', '');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if (($end_time - $start_time) / (3600 * 24) > 30) {
            return json(['code' => 100, 'msg' => '查询时间跨度不得超过30天']);
        }
        $end_time = $end_time + 3600 * 24;
        $day = ($end_time - $start_time) / (3600 * 24);
        $orderModel = new \app\index\model\FinanceOrder();
        $deviceModel = new \app\index\model\MachineDevice();
        $orderGoodsModel = new OrderGoods();
        $user = $this->user;
        $data = [];
        if ($user['role_id'] == 1) {
            $total_get = $orderModel
                ->where('status', 1)
                ->where('create_time', '>=', $start_time)
                ->where('create_time', '<', $end_time)
                ->sum('price');
            $device_count = $deviceModel->where('uid', '<>', 1)->where('delete_time', null)->count();

            $device_get = $orderModel
                ->where('status', 1)
                ->where('create_time', '>=', $start_time)
                ->where('create_time', '<', $end_time)
                ->field('device_sn,sum(price) total_price')
                ->group('device_sn')
                ->order('total_price desc')
                ->find();

            $order_ids = $orderModel
                ->where('status', 1)
                ->where('create_time', '>=', $start_time)
                ->where('create_time', '<', $end_time)
                ->column('id');
            $goods = $orderGoodsModel->alias('og')
                ->join('mall_goods g', 'og.goods_id=g.id', 'left')
                ->whereIn('og.order_id', $order_ids)
                ->group('og.goods_id')
                ->field('g.title,sum(og.total_price) total_price')
                ->order('total_price desc')
                ->find();

//            $data['goods']['device_daily'] = sprintf("%.2f", $device_get['total_price'] / $day);
        } else {
            $where = [];
            if ($user['role_id'] <= 5) {
                $where['o.uid'] = ['=', $user['id']];
                $device_count = $deviceModel->where('uid', $user['id'])->where('delete_time', null)->count();
            } else {
                $partnerModel = new DevicePartnerModel();
                $device_sn = $partnerModel->alias('p')
                    ->join('machine_device d', 'p.device_id=d.id')
                    ->where('uid', $user['id'])->where('admin_id', $user['parent_id'])
                    ->column('p.device_sn');
                $device_count = count($device_sn);
                $where['o.device_sn'] = ['in', $device_sn];
            }
            //总收益
            $cashModel = new FinanceCash();
            $total_get = $cashModel->where('uid', $user['uid'])->where('type', 1)->sum('price');
            $device_get = $orderModel->alias('o')
                ->join('finance_cash c', 'o.order_sn=c.order_sn')
                ->where('o.status', 1)
                ->where('c.uid', $user['id'])
                ->where($where)
                ->where('o.create_time', '>=', $start_time)
                ->where('o.create_time', '<', $end_time)
                ->field('o.device_sn,sum(c.price) total_price')
                ->group('o.device_sn')
                ->order('total_price desc')
                ->find();
            $order_ids = $orderModel
                ->where('status', 1)
                ->where($where)
                ->where('create_time', '>=', $start_time)
                ->where('create_time', '<', $end_time)
                ->column('id');
            $goods = $orderGoodsModel->alias('og')
                ->join('mall_goods g', 'og.goods_id=g.id', 'left')
                ->whereIn('og.order_id', $order_ids)
                ->group('og.goods_id')
                ->field('g.title,sum(og.total_price) total_price')
                ->order('total_price desc')
                ->find();
        }
        $data['total']['total_get'] = $total_get;
        $data['total']['daily'] = sprintf("%.2f", $total_get / $day);
        $data['total']['device_daily'] = sprintf("%.2f", $total_get / $day / $device_count);
        $data['device']['total_get'] = $device_get['total_price'] ?? 0.00;
        $data['device']['device_sn'] = $device_get['device_sn'] ?? '';
        $data['device']['daily'] = sprintf("%.2f", $device_get['total_price'] / $day);
        $data['device']['device_daily'] = sprintf("%.2f", $device_get['total_price'] / $day);
        $data['goods']['total_get'] = $goods['total_price'] ?? 0.00;
        $data['goods']['title'] = $goods['title'] ?? '';
        $data['goods']['daily'] = sprintf("%.2f", $goods['total_price'] / $day);
        return json(['code' => 200, 'data' => $data]);
    }

    public function goodsIncome()
    {
        $params = request()->get();
        $start_time = request()->get('start_time', '');
        $end_time = request()->get('end_time', '');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if (($end_time - $start_time) / (3600 * 24) > 30) {
            return json(['code' => 100, 'msg' => '查询时间跨度不得超过30天']);
        }
        $end_time = $end_time + 3600 * 24;
        $limit = request()->get('limit', 5);
        $page = request()->get('page', 1);
        $user = $this->user;
        $orderModel = new \app\index\model\FinanceOrder();
        $orderGoodsModel = new OrderGoods();
        if ($user['role_id'] == 1) {
            $order_ids = $orderModel
                ->where('status', 1)
                ->where('create_time', '>=', $start_time)
                ->where('create_time', '<', $end_time)
                ->column('id');
        } else {
            $order_where = [];
            if ($user['role_id'] <= 5) {
                $order_where['uid'] = ['=', $user['id']];
            } else {
                $partnerModel = new DevicePartnerModel();
                $device_sn = $partnerModel->alias('p')
                    ->join('machine_device d', 'p.device_id=d.id')
                    ->where('uid', $user['id'])->where('admin_id', $user['parent_id'])
                    ->column('p.device_sn');
                $order_where['device_sn'] = ['in', $device_sn];
            }
            $order_ids = $orderModel
                ->where('status', 1)
                ->where('create_time', '>=', $start_time)
                ->where('create_time', '<', $end_time)
                ->where($order_where)
                ->column('id');
        }
        $count = $orderGoodsModel->alias('og')
            ->join('mall_goods g', 'og.goods_id=g.id', 'left')
            ->whereIn('og.order_id', $order_ids)
            ->group('og.goods_id')
            ->field('g.title,sum(og.total_price) total_price,sum(count) total_count')
            ->order('total_count desc')->count();
        $goods = $orderGoodsModel->alias('og')
            ->join('mall_goods g', 'og.goods_id=g.id', 'left')
            ->whereIn('og.order_id', $order_ids)
            ->group('og.goods_id')
            ->field('g.title,sum(og.total_price) total_price,sum(count) total_count')
            ->order('total_count desc')
            ->page($page)
            ->limit($limit)
            ->select();
        $start = ($page - 1) * $limit;
        foreach ($goods as $k => $v) {
            $start += 1;
            $goods[$k]['rank'] = $start;
        }
        return json(['code' => 200, 'data' => $goods, 'count' => $count, 'params' => $params]);
    }
}