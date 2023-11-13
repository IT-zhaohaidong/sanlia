<?php

namespace app\appletadmin\controller;

use app\index\controller\BaseController;
use app\index\model\AppletNodeModel;
use app\index\model\DevicePartnerModel;
use app\index\model\FinanceCash;
use app\index\model\MachineDevice;
use app\index\model\SystemNode;
use app\index\model\SystemRole;
use think\Db;

class UserInfo extends BaseController
{
    public function getUserInfo()
    {
        $user = $this->user;
        $role = (new SystemRole())->where('id', $user['role_id'])->find();
        $where = [];
        if ($user['role_id'] != 1) {
            $node_ids = explode(',', $role['applet_node_ids']);
            $where['id'] = ['in', $node_ids];
        }
        $user['role_name'] = $role['name'];
        $data = $this->total($user['id']);
        $user['total_get'] = $data['totalMoney'][3] ?? 0.00;
        $user['today_get'] = $data['totalMoney'][0];
        $user['month_get'] = $data['totalMoney'][2];
        $user['week_get'] = $data['totalMoney'][1];
        $user['balance'] = ($user['agent_ali_balance'] * 100 + $user['agent_wx_balance'] * 100 + $user['system_balance'] * 100) / 100;
        $device_where = [];
        if ($user['role_id'] > 1) {
            if ($user['role_id'] > 5) {
                $device_ids = (new DevicePartnerModel())->where('uid', $user['id'])->column('device_id');
                $device_where['d.id'] = ['in', $device_ids];
            } else {
                $device_where['d.uid'] = ['=', $user['id']];
            }
        }
        $is_lock = (new MachineDevice())->alias('d')
            ->join('machine_goods g', 'g.device_sn=d.device_sn', 'left')
            ->where($device_where)
            ->where('g.err_lock', 1)
            ->find();
        $user['num_lock'] = $is_lock ? 1 : 0;
        $model = new AppletNodeModel();
        $list = $model
            ->where($where)
            ->order('weight desc')
            ->select();
        $auth = (new SystemNode())->tree($list);
        $data = compact('user', 'auth');
        return json(['code' => 200, 'data' => $data,]);
    }

    /**
     * 查询销售数据统计汇总
     */
    public function total($uid)
    {
        // 获取当天时间范围
        $day_start = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $day_end = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;

        // 获取本周时间范围
        $week_start = mktime(0, 0, 0, date('m'), date('d') - date('w') + 1, date('Y'));
        $week_end = mktime(23, 59, 59, date('m'), date('d') - date('w') + 7, date('Y'));

        // 获取本月时间范围
        $month_start = mktime(0, 0, 0, date('m'), 1, date('Y'));
        $month_end = mktime(23, 59, 59, date('m'), date('t'), date('Y'));

        $where['create_time'] = ['between', $month_start . ',' . $month_end];
        $where['uid'] = $uid;
        $model = new FinanceCash();
        $data = $model->field("price,create_time")->where("type", 1)->where($where)->select();
        $number = [0, 0, 0];
        $totalMoney = [0, 0, 0];
        foreach ($data as $key => $value) {
            if (strtotime($value['create_time']) >= $day_start && strtotime($value['create_time']) <= $day_end) {
                $number[0] += 1;
                $totalMoney[0] += $value['price'];
            }
            if (strtotime($value['create_time']) >= $week_start && strtotime($value['create_time']) <= $week_end) {
                $number[1] += 1;
                $totalMoney[1] += $value['price'];
            }
            $number[2] += 1;
            $totalMoney[2] += $value['price'];
        }
        $totalMoney[0] = round($totalMoney[0], 2);
        $totalMoney[1] = round($totalMoney[1], 2);
        $totalMoney[2] = round($totalMoney[2], 2);
        $totals = $model->field("sum(price) AS moneys,COUNT(*) AS num")
            ->where("type", 1)
            ->where("uid", $uid)
            ->select();
        $totalMoney[3] = $totals[0]['moneys'];
        $number[3] = $totals[0]['num'];
        $arr = [
            "number" => $number,
            "totalMoney" => $totalMoney
        ];
        return $arr;
    }

    /**
     * 绑定设备 [post]
     * device_sn 设备号 required
     */
    public function bindDevice()
    {
        $user = $this->user;
        $data = request()->post();
        if (!in_array('2', explode(',', $user['roleIds']))) {
            $data = [
                'code' => 100,
                'msg' => '没有权限'
            ];
            return json($data);
        }
        if (empty($data['device_sn'])) {
            $data = [
                'code' => 100,
                'msg' => '设备号无效'
            ];
            return json($data);
        }
        $row = Db::name('machine_device')
            ->where('device_sn', $data['device_sn'])
            ->find();
        if (!$row) {
            $data = [
                'code' => 100,
                'msg' => '设备号无效'
            ];
            return json($data);
        }
        if ($row['uid'] && $row['uid'] != 1) {
            $data = [
                'code' => 100,
                'msg' => '设备已被绑定'
            ];
            return json($data);
        }
        if ($row['is_bind'] == 0) {
            $data = [
                'code' => 100,
                'msg' => '暂时不能绑定'
            ];
            return json($data);
        }
        Db::name('machine_device')->where('id', $row['id'])->update(['uid' => $user['id']]);
        $data = [
            'code' => 200,
            'msg' => '绑定成功'
        ];
        return json($data);
    }
}
