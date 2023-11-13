<?php

namespace app\index\controller;

use app\index\model\AppletNodeModel;
use app\index\model\SystemNode;

class AppletNode extends BaseController
{
    public function getList()
    {
        $model = new AppletNodeModel();
        $list = $model
            ->order('weight desc')
            ->select();
        $list = (new SystemNode())->tree($list);
        return json(['code' => 200, 'data' => $list]);
    }

    public function getParentCate()
    {
        $model = new AppletNodeModel();
        $list = $model
            ->where('pid', 0)
            ->order('weight desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function save()
    {
        $data = request()->post();
        $model = new AppletNodeModel();
        if (empty($data['id'])) {
            $row = $model->where('name', $data['name'])->find();
        } else {
            $row = $model->where('id', '<>', $data['id'])
                ->where(function ($query) use ($data) {
                    $query->where('name', $data['name']);
                })
                ->find();
        }
        if ($row) {
            return json(['code' => 100, 'msg' => '该菜单已存在']);
        }
        $temp = [
            'name' => $data['name'],
            'pid' => $data['pid'],
            'path' => $data['path'],
            'image' => $data['image'],
            'weight' => $data['weight'],
            'type' => $data['type'],
        ];
        if (empty($data['id'])) {
            $model->save($temp);
        } else {
            $model->where('id', $data['id'])->update($temp);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

}