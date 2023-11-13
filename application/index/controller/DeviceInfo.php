<?php

namespace app\index\controller;


class DeviceInfo
{
    /**
     * 出货指令
     */
    public function goodsOut($device_sn, $imei, $num,$order_sn)
    {
        $url = "http://47.96.15.3:8899/api/vending/goodsOut";
        $data = [
            "Imei" => $imei,
            "deviceNumber" => $device_sn,
            "laneNumber" => $num,
            "laneType" => 0,
            "paymentType" => 1,
            "orderNo" => $order_sn,
            "timestamp" => time(),
        ];

        $result = https_request('http://47.96.15.3:8899/api/vending/goodsOut', $data);
//        $result = https_request('http://test.feishikeji.cloud:9100/api/vending/goodsOut', $data);
        return $result;
    }
}