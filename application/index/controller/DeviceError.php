<?php

namespace app\index\controller;

use app\index\model\DevicePartnerModel;
use app\index\model\MachineDeviceErrorModel;
use app\index\model\SystemAdmin;
use think\Cache;
use think\Db;
use think\Env;

class DeviceError extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $device_ids = Db::name('machine_device_partner')
                    ->where(['admin_id' => $user['parent_id'], 'uid' => $user['id']])
                    ->column('device_id');
                $device_ids = $device_ids ? array_values($device_ids) : [];
                $where['d.id'] = ['in', $device_ids];
            } else {
                $where['d.uid'] = ['=', $user['id']];
            }
        }
        if (!empty($params['device_sn'])) {
            $where['d.device_sn'] = ['like', '%' . $params['device_sn'] . '%'];
        }
        $model = new MachineDeviceErrorModel();
        $count = $model->alias('e')
            ->join('machine_device d', 'e.device_sn=d.device_sn', 'left')
            ->where($where)->count();
        $list = $model->alias('e')
            ->join('machine_device d', 'e.device_sn=d.device_sn', 'left')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('e.id desc')
            ->field('e.*,d.imei')
            ->select();
        $arr = [
            1 => '电机正常,未检测到出货',
            2 => '无效货道',
            3 => '设备未开机',
            4 => '货道超时',
            5 => '没有反馈',
        ];
        foreach ($list as $k => $v) {
            $list[$k]['detail'] = isset($arr[$v['status']]) ? $arr[$v['status']] : '未知错误';
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }
}