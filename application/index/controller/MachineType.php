<?php

namespace app\index\controller;

use think\Env;

class MachineType extends BaseController
{
    public function getlist()
    {
        $model = new \app\index\model\MachineType();
        $list = $model
            ->where('delete_time', null)
            ->field('id,title,image,create_time,update_time')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function save()
    {
        $data = request()->post();
        $model = new \app\index\model\MachineType();
        if (empty($data['id'])) {
            $row = $model->where('title', $data['title'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该分类已存在']);
            }
            $model->save($data);
        } else {
            $row = $model->where('delete_time', null)->where('id', '<>', $data['id'])->where('title', $data['title'])->find();
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
        $model = new \app\index\model\MachineType();
        if (empty($data['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        } else {
            $model->where('id', $data['id'])->delete();
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function getTypeByUser()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $arr = explode(',', $user['device_type']);
            $where['id'] = ['in', $arr];
        }
        $model = new \app\index\model\MachineType();
        $list = $model->where($where)->where('delete_time', null)->select();
        return json(['code' => 200, 'data' => $list]);
    }
}