<?php

namespace app\index\controller;

use app\index\model\MtDeviceGoodsModel;
use think\Db;

class MedicineDevice extends BaseController
{
    public function deviceList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $device_type = empty($params['device_type']) ? 1 : $params['device_type'];
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
        } else {
            if (!empty($prams['uid'])) {
                $where['d.uid'] = $prams['uid'];
            }
        }
        $model = new \app\index\model\MachineDevice();
        $count = $model->alias('d')
            ->join('system_admin a', 'a.id=d.uid', 'left')
            ->where('d.device_type', $device_type)
            ->where('d.delete_time', null)
            ->where($where)
            ->count();
        $list = $model->alias('d')
            ->join('system_admin a', 'a.id=d.uid', 'left')
            ->where('d.device_type', $device_type)
            ->where('d.delete_time', null)
            ->where($where)
            ->field('d.*,a.username')
            ->page($page)
            ->limit($limit)
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['remain_time'] = ($v['expire_time'] > time()) ? ceil(($v['expire_time'] - time()) / (3600 * 24)) : '已过期';
            $list[$k]['expire_time'] = $v['expire_time'] ? date('Y-m-d H:i:s', $v['expire_time']) : '';
            if ($v['supply_id'] == 2) {
                $list[$k]['face_sn'] = $model->where('id', $v['id'])->value('face_sn');
            } elseif ($v['supply_id'] == 1) {
//                $list[$k]['face_sn'] = Db::name('machine_android')->where('device_sn', $v['device_sn'])->value('face_sn');
            }
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //将普通设备添加到售药机设备,获取设备列表
    public function getDevice()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                return json(['code' => 100, 'msg' => '您没有权限!']);
            } else {
                $where['uid'] = ['=', $user['id']];
            }
        } else {
            if (!empty($prams['uid'])) {
                $where['uid'] = $prams['uid'];
            }
        }
        $model = new \app\index\model\MachineDevice();
        $list = $model
            ->where('delete_time', null)
            ->where('device_type', 0)
            ->where($where)
            ->where('device_type', 0)
            ->field('id,device_name,device_sn')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //将普通设备添加到售药机设备   不能添加美团设备
    public function addDevice()
    {
        $ids = request()->post('ids/a', []);
        if (empty($ids)) {
            return json(['code' => 100, 'msg' => '请选择设备!']);
        }
        $model = new \app\index\model\MachineDevice();
        $device = $model->whereIn('id', $ids)->field('id,device_sn')->select();
        foreach ($device as $k => $v) {
            $medicineQrCode = medicineQrCode($v['device_sn']);
            $model->where('id', $v['id'])->update(['medicine_qr_code' => $medicineQrCode, 'device_type' => 1]);
        }
        return json(['code' => 200, 'msg' => '添加成功!']);
    }

    //货道列表
    public function getGoodsList()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new \app\index\model\MachineDevice())->where('id', $id)->find();
        $model = new MtDeviceGoodsModel();
        $list = $model->alias('d')
            ->join('mt_goods g', 'd.goods_id=g.id', 'left')
            ->where('d.device_id', $id)
            ->field('d.*,g.name')
            ->order('d.num asc')
            ->limit($device['num'])
            ->select();
        $count = count($list);
        if ($count < $device['num']) {
            for ($i = 1; $i <= $device['num'] - $count; $i++) {
                $list[] = [
                    'num' => $count + $i,
                    'device_id' => $id,
                    'goods_id' => '',
                    'name' => '',
                    'volume' => '',
                    'stock' => '',
                    'price' => ''
                ];
            }
        }
        return json(['code' => 200, 'data' => $list, 'device_id' => $id]);
    }


    //移除
    public function outDevice()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new \app\index\model\MachineDevice();
        $row = $model->where('id', $id)->find();
        if ($row['device_type'] == 2) {
            return json(['code' => 100, 'msg' => '只可移出普通售药机设备']);
        }
        $model->where('id', $id)->update(['device_type' => 0]);
        return json(['code' => 200, 'msg' => '移出成功']);
    }
}