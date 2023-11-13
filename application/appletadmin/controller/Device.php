<?php

namespace app\appletadmin\controller;

use app\index\controller\BaseController;
use app\index\model\MachineDevice;
use app\index\model\MachinePositionModel;

class Device extends BaseController
{
    //获取设备信息
    public function getDeviceInfo()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new MachineDevice())->where('id', $id)->field('device_name,phone,position_id')->find();
        $position = (new MachinePositionModel())->where('id', $device['position_id'])->field('name,lng,lat,description')->find();
        $device['name'] = $position ? $position['name'] : '';
        $device['lng'] = $position ? $position['lng'] : '';
        $device['lat'] = $position ? $position['lat'] : '';
        $device['description'] = $position ? $position['description'] : '';
        return json(['code' => 200, 'data' => $device]);
    }

    //保存设备信息
    public function saveDeviceInfo()
    {
        $params = request()->post();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $positionModel = new MachinePositionModel();
        if (!empty($params['name'])) {
            $position = $positionModel->where('name', $params['name'])->find();
            if ($position) {
                $positionModel->where('id', $position['id'])->update(['lng' => $params['lng'], 'lat' => $params['lat'], 'description' => $params['description']]);
                $position_id = $position['id'];
            } else {
                $position_data = [
                    'uid' => $this->user['id'],
                    'name' => $params['name'],
                    'lng' => $params['lng'],
                    'lat' => $params['lat'],
                    'description' => $params['description'],
                    'create_time' => time(),
                ];
                $position_id = $positionModel->insertGetId($position_data);
            }
            $device_data = [
                'position_id' => $position_id,
                'phone' => $params['phone'],
                'device_name' => $params['device_name'],
            ];
        } else {
            $device_data = [
                'position_id' => '',
                'phone' => $params['phone'],
                'device_name' => $params['device_name'],
            ];
        }
        (new MachineDevice())->where('id',$params['id'])->update($device_data);
        return json(['code' => 200, 'msg' => '成功']);
    }

}
