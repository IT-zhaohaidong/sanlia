<?php

namespace app\applet\controller;

use app\index\model\FinanceOrder;
use app\index\model\MachineDevice;
use app\index\model\MachineDeviceErrorModel;
use app\index\model\MachineGoods;
use app\index\model\MachineStockLogModel;
use app\index\model\OrderGoods;
use think\Cache;
use think\Controller;
use think\Db;


class Korea extends Controller
{

    public function getList()
    {
        $device_sn = request()->get('device_sn', '');
        if (empty($device_sn)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new \app\index\model\MachineDevice())->where('device_sn', $device_sn)->find();
        $where = [];
//        if ($device['e_lock'] == 1) {
//            $where[] = ['g.num', '<>', $device['lock_num']];
//        }
        $data = (new \app\index\model\MachineGoods())->alias("g")
            ->join("mall_goods s", "g.goods_id=s.id", "LEFT")
            ->field("g.id,g.num,g.device_sn,g.goods_id,g.stock,g.price,s.image,s.title")
            ->where("g.device_sn", $device_sn)
            ->where($where)
            ->where('g.goods_id', '>', 0)
            ->order('g.num asc')
            ->select();
        $arr = [];
        foreach ($data as $k => $v) {
            if (!$v['goods_id']) {
                unset($data[$k]);
            }
            if ($v['num'] > $device['num']) {
                unset($data[$k]);
            }
            $arr[$v['num']] = $v;
        }
        return json(['code' => 200, 'data' => $arr]);
    }

    public function freeGet()
    {
        $post = request()->get();
        if (empty($post['device_sn']) || empty($post['num'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $order_sn = time() . mt_rand(1000, 9999);
        $goods = (new MachineGoods())
            ->where(['device_sn' => $post['device_sn'], 'num' => $post['num']])
            ->field('stock,goods_id')->find();
        if ($goods['stock'] < 1) {
            return json(['code' => 100, 'msg' => '재고 부족']);
        }
        $device = (new MachineDevice())
            ->where("device_sn", $post['device_sn'])
            ->field("uid,imei,supply_id,device_sn,status")
            ->find();
        if ($device['status'] != 1) {
            return json(['code' => 100, 'msg' => '장치가 켜져 있지 않음']);
        }
        $data = [
            'uid' => $device['uid'],
            'count' => 1,
            'device_sn' => $post['device_sn'],
            'order_sn' => $order_sn,
            'status' => 1,
            'pay_type' => 1,
            'price' => 0.00,
            'pay_time' => time(),
            'create_time' => time()
        ];
        $order_id = (new FinanceOrder())->insertGetId($data);
        $goods_data = [
            'order_id' => $order_id,
            'device_sn' => $post['device_sn'],
            'num' => $post['num'],
            'goods_id' => $goods['goods_id'],
            'price' => 0,
            'count' => 1,
            'total_price' => 0,
        ];
        (new OrderGoods())->save($goods_data);
        //出货
        $res = $this->out($device, $post['num'], $order_sn, $goods);
        if ($res['code'] == 1) {
            return json(['code' => 100, 'msg' => '출하 실패, 다시 시도하십시오']);
        }
        return json(['code' => 200, 'msg' => '수령 성공']);
    }

    public function out($device, $num, $order_sn, $goods)
    {
        $data['out_trade_no'] = $order_sn;
        $str = 'out_' . $order_sn;
        $order_no = $data['out_trade_no'] . 'order' . 0;
        $result = $this->goodsOut($device['device_sn'], $num, $order_no);
        if ($result == 1) {
            Cache::store('redis')->set($str, 2);
            (new FinanceOrder())->where('order_sn', $data['out_trade_no'])->update(['status' => 3]);
        } else {
//            Db::name('machine_goods')->where("num", $num)->where("device_sn", $device['device_sn'])->dec("stock")->update();
            $this->addStockLog($device['device_sn'], $num, $data['out_trade_no'], 1, $goods);
        }
    }

    public function addStockLog($device_sn, $num, $order_sn, $count, $goods)
    {
        $data = [
            'device_sn' => $device_sn,
            'num' => $num,
            'old_stock' => $goods['stock'],
            'new_stock' => $goods['stock'] - $count,
            'change_detail' => '用户下单,库存减少' . $count . '件;订单号:' . $order_sn,
        ];
        (new MachineStockLogModel())->save($data);
    }

    public function goodsOut($device_sn, $num, $order_sn)
    {
        $imei = (new MachineDevice())->where('device_sn', $device_sn)->value('imei');
        $data = [
            "imei" => $imei,
            "deviceNumber" => $device_sn,
            "laneNumber" => $num,
            "laneType" => 1,
            "paymentType" => 1,
            "orderNo" => $order_sn,
            "timestamp" => time()
        ];
        $str = $device_sn . '_ip';
        $ip = Cache::store('redis')->get($str);
        trace($ip, '板子ip');
//        if ($ip == '47.96.15.3') {
//            $url = 'http://47.96.15.3:8899/api/vending/goodsOut';
//        } else {
        $url = 'http://feishi.feishi.vip:9100/api/vending/goodsOut';
//        }
        $result = https_request($url, $data);
        $result = json_decode($result, true);
        $result['order_sn'] = $order_sn;
        trace($data, '出货参数');
        trace($result, '出货指令结果');
        if ($result['code'] == 200) {
            $res = $this->isBack($order_sn, 1);
            if (!$res) {
                //没有反馈业务处理
                $data = [
                    "imei" => $imei,
                    "device_sn" => $device_sn,
                    "num" => $num,
                    "order_sn" => explode('order', $order_sn)[0],
                    "status" => 5,
                ];
                (new MachineDeviceErrorModel())->save($data);
                return ['code' => 1, 'msg' => '失败'];
            }
            Db::name('machine_goods')->where("num", $num)->where("device_sn", $device_sn)->dec("stock")->update();

            return ['code' => 0, 'msg' => '成功'];
        } else {
            $save_data = [
                'device_sn' => $device_sn,
                'imei' => $imei,
                'num' => $num,
                'order_sn' => explode('order', $order_sn)[0],
                'status' => 3,
            ];
            (new MachineDeviceErrorModel())->save($save_data);
        }
        return ['code' => 1, 'msg' => '失败'];
    }

    public function isBack($order, $num)
    {
        if ($num <= 7) {
            $str = $order;
            $str = Cache::store('redis')->get($str);
            if ($str == 2) {
                return true;
            } else {
                if ($num > 1) {
                    sleep(1);
                }
                $res = $this->isBack($order, $num + 1);
                return $res;
            }
        } else {
            return false;
        }
    }
}