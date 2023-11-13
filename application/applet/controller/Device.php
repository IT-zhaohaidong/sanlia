<?php

namespace app\applet\controller;

use app\index\model\MachineDevice;
use app\index\model\MachineDeviceErrorModel;
use app\index\model\MachineGoods;
use app\index\model\MachineOutLogModel;
use app\index\model\MtDeviceGoodsModel;
use think\Cache;
use think\Controller;
use think\Db;

class Device extends Controller
{
    public function index()
    {
        $post = $this->request->post();
        trace($post, "什么玩意儿");
        return "ok";
    }

    /**
     *设备注册包信息
     */
    public function registration()
    {
        $post = $this->request->post();
        trace($post, "售卖机设备注册包信息");
        $str = $post['deviceNumber'] . '_heartBeat';
        Cache::store('redis')->set($str, 1, 60);
        $ip = $this->request->ip();
        $str = $post['deviceNumber'] . '_ip';
        Cache::store('redis')->set($str, $ip);
        if ($ip == '47.96.15.3') {
            $transfer = 1;
        } else {
            $transfer = 2;
        }
        // 开机记录入库 start
//        $datas = [
//            'device_sn' => $post['deviceNumber'],
//            'imei' => $post['imei'],
//            'work_time' => time()
//        ];*
//        $obj = 589
//        $obj->create($datas);
        // 开机记录入库 end
        //将设备信息录入库
        $row = Db::name('machine_device')->where('imei', $post['imei'])->find();
        if ($row && ($row['iccid'] != $post['iccId'] || $row['device_sn'] != $post['deviceNumber'] || $row['transfer'] != $transfer || $row['app_version'] != $post['versionInfo'])) {
            $data = [
                'iccid' => $post['iccId'],
                'card_type' => 1,
                'supplier_type' => 1,
                'device_sn' => $post['deviceNumber'],
                'transfer' => $transfer,
                'app_version' => $post['versionInfo'],
            ];
            if ($row['device_sn'] != $post['deviceNumber']) {
                $data['qr_code'] = qrcode($post['deviceNumber'], 1);
            }
            Db::name('machine_device')->where('id', $row['id'])->update($data);
        }
        if (!$row) {
            $params = [
                'device_sn' => $post['deviceNumber'],
                'device_name' => $post['deviceNumber'],
                'imei' => $post['imei'],
                'num' => 1,
                'qr_code' => qrcode($post['deviceNumber'], 1),
                'type_id' => 1,
                'transfer' => $transfer,
                'uid' => 1,
                'iccid' => $post['iccId'],
                'card_type' => 1,
                'supplier_type' => 1,
                'expire_time' => mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y") + 1),
                'create_time' => time()
            ];
            Db::name('machine_device')->insert($params);
        }
        return "ok";
    }

    /**
     * 心跳包信息
     */
    public function heartBeat()
    {
        $post = $this->request->post();
        $str = $post['deviceNumber'] . '_heartBeat';
        Cache::store('redis')->set($str, 1, 70);
        $ip = $this->request->ip();
        $str = $post['deviceNumber'] . '_ip';
        Cache::store('redis')->set($str, $ip);
        trace($post, "售卖机心跳包信息");
        return "ok";
    }

    /**
     * 货道状态
     */
    public function laneStatus()
    {
        $post = $this->request->post();
        trace($post, "售卖机货道状态");
        return "ok";
    }

    /**
     * 出货指令
     */
    public function goodsOut()
    {
        $num = $this->request->get('num', '');
        $url = 'http://feishi.feishi.vip:9100/api/vending/goodsOut';
        $data = [
            "Imei" => "866833058926772",
            "deviceNumber" => "6117784958",
            "laneNumber" => $num,
            "laneType" => 0,
            "paymentType" => 1,
            "orderNo" => "2021082231347000",
            "timestamp" => time(),
        ];

        $result = https_request($url, $data);
        var_dump($result);
        die();
    }

    /**
     * 设备回复出货结果
     */
    public function response()
    {
        $post = $this->request->post();
        trace($post, "售卖机设备回复出货结果");
        $post['laneNumber'] = hexdec((string)$post['laneNumber']);
        $str = $post['deviceNumber'] . '_heartBeat';
        Cache::store('redis')->set($str, 1, 70);
        $arr = explode('test', $post['orderNo']);
        if ($arr[0] == '100') {
            $arr = [0 => '正常出货', 1 => '电机正常,未检测到出货', 2 => '无效货道', 4 => '货道超时'];
            $str = $post['deviceNumber'] . '_testResult';
            $res = Cache::store('redis')->get($str) ?? [];
            $data = [
                'num' => $post['laneNumber'],
                'status' => $arr[$post['status']],
                'orderNo' => $post['orderNo'],
                'time' => date('H:i:s')
            ];
            $res[] = $data;
            Cache::store('redis')->set($str, $res, 300);

            $outStr = 'out_' . $post['orderNo'];
            if ($post['status'] > 0) {
                Cache::store('redis')->set($outStr, 2);
                $log = ["device_sn" => $post['deviceNumber'], "num" => $post['laneNumber'], "order_sn" => $post['orderNo'], 'status' => 3];
                (new MachineOutLogModel())->save($log);
            } else {
                Cache::store('redis')->set($outStr, 1);
                $log = ["device_sn" => $post['deviceNumber'], "num" => $post['laneNumber'], "order_sn" => $post['orderNo'], 'status' => 0];
                (new MachineOutLogModel())->save($log);
            }
            return "ok";
        }
        Cache::store('redis')->set($post['orderNo'], 2, 30);
        $post['orderNo'] = explode('test', $post['orderNo'])[0];
        //美团订单号
        $order_sn = strstr($post['orderNo'], "mt_");
//        $old_order_sn = explode('order', $post['orderNo'])[0];
        $outStr = 'out_' . $post['orderNo'];
        if ($post['status'] > 0) {
//            if ($order_sn) {
//                $device_id = (new MachineDevice())->where('device_sn', $post['deviceNumber'])->value('id');
//                $post['orderNo'] = substr($order_sn, 3);
//                $stock = (new MtDeviceGoodsModel())->where([
//                    'device_id' => $device_id,
//                    'num' => $post['laneNumber'],
//                ])->value('stock');
//                (new MtDeviceGoodsModel())->where([
//                    'device_id' => $device_id,
//                    'num' => $post['laneNumber'],
//                ])->update(['stock' => $stock + 1]);
//            }
//            else {
//                $stock = (new MachineGoods())->where([
//                    'device_sn' => $post['deviceNumber'],
//                    'num' => $post['laneNumber'],
//                ])->value('stock');
//                (new MachineGoods())->where([
//                    'device_sn' => $post['deviceNumber'],
//                    'num' => $post['laneNumber'],
//                ])->update(['stock' => $stock + 1]);
//            }
            $data = [
                'order_sn' => $post['orderNo'],
                'device_sn' => $post['deviceNumber'],
                'num' => $post['laneNumber'],
                'status' => 1,
            ];
            (new MachineDeviceErrorModel())->save($data);
            Cache::store('redis')->set($outStr, 2);
            $log = ["device_sn" => $post['deviceNumber'], "num" => $post['laneNumber'], "order_sn" => $post['orderNo'], 'status' => 3];
            (new MachineOutLogModel())->save($log);
        } else {
            //获取成功反馈
            if (!$order_sn) {
                $order_sn = explode('order', $post['orderNo'])[0];
            }
            $str = 'chuohuoresult_' . $order_sn;
            Cache::store('redis')->set($str, 1, 3000);
            Cache::store('redis')->set($outStr, 1);
            $log = ["device_sn" => $post['deviceNumber'], "num" => $post['laneNumber'], "order_sn" => $post['orderNo'], 'status' => 0];
            (new MachineOutLogModel())->save($log);
        }
//        if ($post['status'] != 0) {
////            $url = "https://tanhuang.feishikeji.cloud/api/Withdrawal/tuikuan";
//            $data = ['order_sn' => $post['orderNo']];
////            https_request($url, $data);
//        }
        return "ok";
    }

    //管理后台测试获取出货结果
    public function getDeviceResponse()
    {
        $device_sn = request()->post('device_sn', '');
        $str = $device_sn . '_testResult';
        $res = Cache::store('redis')->get($str) ?? [];
        $res = array_reverse($res);
        return $res;
    }

}
