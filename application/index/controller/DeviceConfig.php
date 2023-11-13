<?php

namespace app\index\controller;

use app\index\model\DeviceConfigModel;

class DeviceConfig extends BaseController
{
    //小程序配置
    public function getConfig()
    {
        $device_id = request()->get('device_id', '');
        if (!$device_id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new DeviceConfigModel();
        $data = $model->getDeviceConfig($device_id);
        return json(['code' => 200, 'data' => $data]);
    }

    //保存配置
    public function saveConfig()
    {
        $params = request()->post();
        $model = new DeviceConfigModel();
        $data = [
            'merge_goods' => $params['merge_goods'],
            'is_cart' => $params['is_cart'],
            'cart_max' => empty($params['cart_max']) ? 1 : $params['cart_max'],
            'device_id' => $params['device_id'],
            'zfb' => $params['zfb'],
            'wx' => $params['wx']
        ];
        if ($params['zfb'] == 0 && $params['wx'] == 0) {
            return json(['code' => 100, 'msg' => '微信/支付宝小程序,必须开启一个']);
        }
        $row = $model->where('device_id', $params['device_id'])->find();
        if ($row) {
            $model->where('device_id', $params['device_id'])->update($data);
        } else {
            $model->save($data);
        }
        return json(['code' => 200, 'msg' => '保存成功']);
    }
}
