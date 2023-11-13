<?php

namespace app\applet\controller;

use app\index\model\FinanceOrder;
use app\index\model\OperateAddressModel;
use think\Controller;

class OperateAddress extends Controller
{
    //获取收货地址列表
    public function getList()
    {
        $params = request()->get();
        if (empty($params['openid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OperateAddressModel();
        $list = $model
            ->where('openid', $params['openid'])
            ->order('is_default desc')
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //添加/编辑地址
    public function saveAddress()
    {
        $params = request()->post();
        if (!preg_match('/^1[3-9]\d{9}$/', $params['phone'])) {
            return json(['code' => 100, 'msg' => '请输入合法手机号']);
        }
        $name = trim($params['name']);
        $detail = trim($params['detail']);
        if (!$name) {
            return json(['code' => 100, 'msg' => '收货人不能为空']);
        }
        if (!$detail) {
            return json(['code' => 100, 'msg' => '详细地址不能为空']);
        }
        $data = [
            'openid' => $params['openid'],
            'name' => $name,
            'phone' => $params['phone'],
            'province' => $params['province'],
            'city' => $params['city'],
            'area' => $params['area'],
            'detail' => $detail,
            'is_default' => $params['is_default']
        ];
        $model = new OperateAddressModel();
        if ($params['is_default'] == 1) {
            $model
                ->where('openid', $params['openid'])
                ->where('is_default', 1)
                ->update(['is_default' => 0]);
        }
        if (empty($params['id'])) {
            $model->save($data);
        } else {
            $model->where('id', $params['id'])->update($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    //设置默认
    public function setDefault()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['openid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OperateAddressModel();
        $model->where('openid', $params['openid'])
            ->where('is_default', 1)
            ->update(['is_default' => 0]);
        $model->where('id', $params['id'])->update(['is_default' => 1]);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //删除地址
    public function delAddress()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OperateAddressModel();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

}
