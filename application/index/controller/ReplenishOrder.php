<?php


namespace app\index\controller;

use app\index\model\ProcurementOrderModel;
use app\index\model\SystemAdmin;
use think\Db;

class ReplenishOrder extends BaseController
{
    //补货单列表
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

            } else {
                $device_ids = (new \app\index\model\MachineDevice())->where('uid', $user['id'])->column('id');
            }
            $device_ids = $device_ids ? array_values($device_ids) : [];
            $where['o.device_id'] = ['in', $device_ids];
        }
        if (!empty($params['add_person'])) {
            $where['a.username'] = ['like', '%' . $params['add_person'] . '%'];
        }

        if (!empty($params['replenish_person'])) {
            $where['b.username'] = ['like', '%' . $params['replenish_person'] . '%'];
        }

        if ($params['status'] !== '') {
            $where['o.status'] = ['=', $params['status']];
        }

        if (!empty($params['device_sn'])) {
            $where['d.device_sn'] = ['=', $params['device_sn']];
        }

        $model = new ProcurementOrderModel();
        $count = $model->alias('o')
            ->join('system_admin a', 'o.admin_id=a.id', 'left')
            ->join('system_admin b', 'o.uid=b.id', 'left')
            ->join('machine_device d', 'o.device_id=d.id', 'left')
            ->field('o.*,a.username add_person,b.username replenish_person,d.device_name,d.device_sn')
            ->where($where)->count();
        $list = $model->alias('o')
            ->join('system_admin a', 'o.admin_id=a.id', 'left')
            ->join('system_admin b', 'o.uid=b.id', 'left')
            ->join('machine_device d', 'o.device_id=d.id', 'left')
            ->field('o.*,a.username add_person,b.username replenish_person,d.device_name,d.device_sn')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['complete_time'] = $v['complete_time'] ? date('Y-m-d H:i', $v['complete_time']) : '';
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //查看补货单
    public function detail()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = (new ProcurementOrderModel())->alias('p')
            ->join('machine_device d', 'p.device_id=d.id', 'left')
            ->where('p.id', $id)
            ->field('p.*,d.device_sn,d.device_name')
            ->find();
        $list = Db::name('procurement_goods')->alias('p')
            ->join('mall_goods g', 'p.goods_id=g.id', 'left')
            ->where('p.pro_id', $data['id'])
            ->field('p.*,g.title,g.image')
            ->select();
        $data['complete_time'] = $data['complete_time'] ? date('Y-m-d H:i', $data['complete_time']) : '';
        $data['goods_list'] = $list;
        return json(['code' => 200, 'data' => $data]);
    }

    //撤销补货单
    public function backout()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        (new ProcurementOrderModel())->where('id', $id)->update(['status' => 2]);
        $uid = (new ProcurementOrderModel())->where('id', $id)->value('uid');
        $openid = (new SystemAdmin())->where('id', $uid)->value('tencent_openid');
        (new ProcurementPush())->sendMsg($openid, '补货单已撤销');
        return json(['code' => 200, 'msg' => '撤销成功']);
    }
}