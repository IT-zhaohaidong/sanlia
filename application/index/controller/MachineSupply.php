<?php

namespace app\index\controller;

use app\index\model\CardSupplyModel;
use app\index\model\MachineSupplyModel;
use think\Env;

class MachineSupply extends BaseController
{
    //------------------------------设备供应商----------------------------------
    public function getlist()
    {
        $model = new MachineSupplyModel();
        $list = $model
            ->field('id,number,name,remark,create_time,update_time')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function save()
    {
        $data = request()->post();
        $model = new MachineSupplyModel();
        $row = $model->where('name', $data['name'])->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该供应商已存在']);
        }
        $row = $model->where('number', $data['number'])->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该编号已存在']);
        }
        $model->save($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //------------------------------------物联卡供应商------------------------------
    public function getCardSupplyList()
    {
        $model = new CardSupplyModel();
        $list = $model
            ->field('id,name,create_time,update_time')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function addCardSupply()
    {
        $data = request()->post();
        $model = new CardSupplyModel();
        $row = $model->where('name', $data['name'])->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该供应商已存在']);
        }
        $model->save($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

}