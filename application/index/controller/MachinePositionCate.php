<?php

namespace app\index\controller;

use app\index\model\MachinePositionCateModel;
use app\index\model\MachinePositionModel;

class MachinePositionCate extends BaseController
{
    public function getlist()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $where = [];
//        if ($user['role_id'] != 1) {
//            $where['p.uid'] = $user['id'];
//        } else {
//            if (!empty($prams['uid'])) {
//                $where['p.uid'] = $params['uid'];
//            }
//        }

        if (!empty($params['title'])) {
            $where['title'] = ['like', '%' . $params['title'] . '%'];
        }
        $model = new MachinePositionCateModel();
        $count = $model->where($where)->count();
        $list = $model
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function save()
    {
        $data = request()->post();
        if (empty(trim($data['title']))) {
            return json(['code' => 100, 'msg' => '分类不能为空']);
        }
        $model = new MachinePositionCateModel();
        if (empty($data['id'])) {
            $row = $model
                ->where('title', $data['title'])
                ->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该分类已存在']);
            }
            $model->save($data);
        } else {
            $row = $model
                ->where('id', '<>', $data['id'])
                ->where('title', $data['title'])
                ->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该分类已存在']);
            }
            $model->where('id', $data['id'])->update($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function del()
    {
        $data = request()->get();
        $model = new MachinePositionCateModel();
        if (empty($data['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $row = (new MachinePositionModel())
            ->where('cate_id', $data['id'])
            ->where('delete_time', null)
            ->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该分类下存在位置信息,不可删除']);
        }
        $model->where('id', $data['id'])->delete();
        return json(['code' => 200, 'msg' => '成功']);
    }
}
