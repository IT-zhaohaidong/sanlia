<?php

namespace app\index\controller;

use app\index\model\MachineGroupModel;
use app\index\model\MachinePositionCateModel;
use app\index\model\MachinePositionModel;

class MachineGroup extends BaseController
{
    public function getlist()
    {
        $user = $this->user;
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $where = [];
        if ($user['role_id'] != 1) {
            $where['g.uid'] = $user['id'];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (!empty($params['group_name'])) {
            $where['g.group_name'] = ['like', '%' . $params['group_name'] . '%'];
        }
        $model = new MachineGroupModel();
        $count = $model->alias('g')
            ->join('system_admin a', 'g.uid=a.id', 'left')
            ->where('g.delete_time', null)
            ->where($where)
            ->count();
        $list = $model->alias('g')
            ->join('system_admin a', 'g.uid=a.id', 'left')
            ->where('g.delete_time', null)
            ->where($where)
            ->field('g.*,a.username')
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function save()
    {
        $data = request()->post();
        if (empty(trim($data['group_name']))) {
            return json(['code' => 100, 'msg' => '分组名不能为空']);
        }
        $model = new MachineGroupModel();
        $user = $this->user;
        if (empty($data['id'])) {
            $data['uid'] = $user['id'];
            $row = $model->where('uid', $user['id'])->where('group_name', $data['group_name'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该分组已存在']);
            }
            $model->save($data);
        } else {
            $row = $model->where('delete_time', null)->where('uid', $user['id'])->where('id', '<>', $data['id'])->where('group_name', $data['group_name'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该分组已存在']);
            }
            $model->where('id', $data['id'])->update($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function addDevice()
    {
        $id = request()->post('id', '');
        $device_ids = request()->post('device_ids/a');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new \app\index\model\MachineDevice();
        $model->where('group_id', $id)->whereNotIn('id', $device_ids)->update(['group_id' => '']);
        $model->whereIn('id', $device_ids)->update(['group_id' => $id]);
        return json(['code' => 200, 'msg' => '操作成功']);
    }

    public function getDeviceList()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $where['uid'] = $user['id'];
        } else {
            if (!empty($prams['uid'])) {
                $where['uid'] = $prams['uid'];
            }
        }
        $model = new \app\index\model\MachineDevice();
        $list = $model
            ->where('delete_time', null)
            ->where($where)
            ->field('id,device_sn,device_name')
            ->order('id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['device_name'] = $v['device_name'] ?? $v['device_sn'];
        }
        $check = $model
            ->where('delete_time', null)
            ->where($where)
            ->where('group_id', $id)
            ->column('id');
        $check = $check ? array_values($check) : [];
        return json(['code' => 200, 'data' => $list, 'check' => $check]);

    }

    public function del()
    {
        $data = request()->get();
        $model = new MachineGroupModel();
        if (empty($data['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model->where('id', $data['id'])->update(['delete_time' => time()]);
        (new \app\index\model\MachineDevice())->where('group_id', $data['id'])->update(['group_id' => '']);
        return json(['code' => 200, 'msg' => '成功']);
    }
}