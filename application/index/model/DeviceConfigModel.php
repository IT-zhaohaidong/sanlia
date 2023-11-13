<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class DeviceConfigModel extends TimeModel
{
    protected $name = 'device_config';
    protected $deleteTime = false;

    public function getDeviceConfig($device_id)
    {
        $data = self::where('device_id', $device_id)->find();
        if (!$data) {
            $data = [
                'device_id' => $device_id,
                'merge_goods' => 0,
                'is_cart' => 0,
                'cart_max' => 1,
                'zfb' => 1,
                'wx' => 1,
            ];
        }
        return $data;
    }
}
