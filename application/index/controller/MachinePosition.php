<?php

namespace app\index\controller;

use app\index\model\MachinePositionCateModel;
use app\index\model\MachinePositionModel;
use think\Env;

class MachinePosition extends BaseController
{
    public function getlist()
    {
        $user = $this->user;
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $where = [];
        if ($user['role_id'] != 1) {
            $where['p.uid'] = $user['id'];
        } else {
            if (!empty($prams['uid'])) {
                $where['p.uid'] = $params['uid'];
            }
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (!empty($params['name'])) {
            $where['p.name'] = ['like', '%' . $params['name'] . '%'];
        }
        if (!empty($params['cate_id'])) {
            $where['p.cate_id'] = $params['cate_id'];
        }
        $model = new MachinePositionModel();
        $count = $model->alias('p')
            ->join('system_admin a', 'p.uid=a.id', 'left')
            ->join('machine_position_cate c', 'p.cate_id=c.id', 'left')
            ->where($where)
            ->where('p.delete_time', null)->count();
        $list = $model->alias('p')
            ->join('system_admin a', 'p.uid=a.id', 'left')
            ->join('machine_position_cate c', 'p.cate_id=c.id', 'left')
            ->where('p.delete_time', null)
            ->where($where)
            ->field('p.*,a.username,c.title cate_name')
            ->page($page)
            ->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function save()
    {
        $data = request()->post();
        if (empty(trim($data['name']))) {
            return json(['code' => 100, 'msg' => '地点名称不能为空']);
        }
        $model = new MachinePositionModel();
        $user = $this->user;
        unset($data['cate_id']);
        if (empty($data['id'])) {
            $data['uid'] = $user['id'];
            $row = $model->where('uid', $user['id'])->where('name', $data['name'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该地点已存在']);
            }
            $model->save($data);
        } else {
            $row = $model->where('delete_time', null)->where('uid', $user['id'])->where('id', '<>', $data['id'])->where('name', $data['name'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该地点已存在']);
            }
            $model->where('id', $data['id'])->update($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function del()
    {
        $data = request()->get();
        $model = new MachinePositionModel();
        if (empty($data['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        } else {
            $model->where('id', $data['id'])->delete();
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function getPositionByUser()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $where['uid'] = ['=', $user['id']];
        }
        $model = new MachinePositionModel();
        $list = $model->where($where)->where('delete_time', null)->field('id,name')->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function getCateList()
    {
        $model = new MachinePositionCateModel();
        $list = $model->order('id desc')->field('id,title')->select();
        return json(['code' => 200, 'data' => $list]);
    }
}
