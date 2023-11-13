<?php

namespace app\index\controller;


use app\index\model\DevicePartnerModel;
use app\index\model\ProcurementOrderModel;
use app\index\model\SystemAdmin;
use think\Db;

class MachineStockout extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $user = $this->user;
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $device_ids = Db::name('machine_device_partner')
                    ->where(['admin_id' => $user['parent_id'], 'uid' => $user['id']])
                    ->column('device_id');
                $device_ids = $device_ids ? array_values($device_ids) : [];
                $where['device.id'] = ['in', $device_ids];
            } else {
                $where['device.uid'] = ['=', $user['id']];
            }
        }
        if (!empty($params['imei'])) {
            $where['device.imei'] = ['like', '%' . $params['imei'] . '%'];
        }

        if (!empty($params['keyword'])) {
            $where['device.device_sn|device.device_name'] = ['like', '%' . $params['keyword'] . '%'];
        }
        $model = new \app\index\model\MachineDevice();
        $count = $model->alias('device')
            ->join('machine_goods goods', 'device.device_sn=goods.device_sn', 'left')
            ->where($where)
            ->where('goods.stock', 0)
            ->where('goods.num', '<>', 'device.lock_num')
            ->field('device.lock_num,goods.id,device.device_sn,device.device_name,goods.num,goods.update_time,goods.create_time,goods.volume,goods.stock')
            ->order('goods.update_time desc')
            ->order('goods.create_time desc')
            ->group('device.device_sn')
            ->count();


        $device = $model->alias('device')
            ->join('machine_goods goods', 'device.device_sn=goods.device_sn', 'left')
            ->where($where)
            ->where('goods.stock', 0)
            ->where('goods.num', '<>', 'device.lock_num')
            ->field('device.lock_num,device.id,device.device_sn,device.imei,device.device_name,device.num,goods.update_time,goods.create_time,goods.volume,goods.stock')
            ->order('goods.update_time desc')
            ->order('goods.create_time desc')
            ->group('device.device_sn')
            ->page($page)
            ->limit($limit)
            ->select();
        foreach ($device as $k => $v) {
            if ($v['lock_num'] == $v['num']) {
                continue;
            }
            if (!$v['update_time']) {
                $device[$k]['update_time'] = $v['create_time'];
            }
            $device[$k]['username'] = $user['username'];
            unset($device[$k]['create_time']);
        }
        $data = [
            'code' => 200,
            'msg' => '',
            'data' => $device,
            'count' => $count,
            'params' => $params,

        ];
        return json($data);
    }

    //缺货详情
    public function getDetail()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数!']);
        }
        $device = Db::name('machine_device')->find($id);
        $model = new \app\index\model\MachineGoods();
        $list = $model->alias('n')
            ->join('mall_goods g', 'n.goods_id=g.id', 'left')
            ->where('n.device_sn', $device['device_sn'])
            ->where('n.num', '<>', $device['lock_num'])
            ->where('n.stock', 0)
            ->field('n.*,g.title,g.image')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function getProcurementUser()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = (new DevicePartnerModel())->alias('p')
            ->join('system_admin a', 'p.uid=a.id', 'left')
            ->where('p.device_id', $id)
            ->where('a.role_id', 9)
            ->field('a.username,a.id')
            ->select();
        return json(['code' => 200, 'data' => $data]);
    }

    //创建补货单
    public function createOrder()
    {
        $id = request()->post('id', '');//设备ID
        $uid = request()->post('uid', '');//补货员ID
        $remark = request()->post('remark', '');//补货单备注
        $data = request()->post('data/a', []);//货道数据
        if (empty($data) || empty($uid) || empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $tencent = (new SystemAdmin())->where('id', $uid)->value('tencent_openid');
        if (empty($tencent)) {
            return json(['code' => 100, 'msg' => '该补货员未绑定公众号,请先绑定']);
        }
        $row = (new ProcurementOrderModel())->where('device_id', $id)->where('status', 0)->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该设备尚有补货单未未完成']);
        }
        $admin_id = $this->user['id'];//添加人id
        $order_data = [
            'order_sn' => time() . rand(1000, 9000),
            'device_id' => $id,
            'uid' => $uid,
            'admin_id' => $admin_id,
            'remark' => $remark,
            'create_time' => time(),
        ];
        $order_id = (new ProcurementOrderModel())->insertGetId($order_data);
        foreach ($data as $k => $v) {
            $data[$k]['pro_id'] = $order_id;
        }
        Db::name('procurement_goods')->insertAll($data);
        $device = (new \app\index\model\MachineDevice())->where('id', $id)->field('device_sn,device_name')->find();
        $device_name = !$device['device_name'] || $device['device_sn'] == $device['device_name'] ? '' : '(' . $device['device_name'] . ')';
        $name = $device['device_sn'] . $device_name;
        (new ProcurementPush())->send($order_id, $name, $tencent);
        return json(['code' => 200, 'msg' => '创建成功']);
    }
}