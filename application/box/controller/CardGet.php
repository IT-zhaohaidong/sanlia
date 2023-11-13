<?php

namespace app\box\controller;

use app\applet\controller\Goods;
use app\index\model\AppVersionModel;
use app\index\model\FinanceOrder;
use app\index\model\MachineCardModel;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use app\index\model\OrderGoods;
use think\Cache;
use think\Controller;

//刷卡领取
class CardGet extends Controller
{
    public function cardGet()
    {
        $params = request()->get();
        if (empty($params['idcard']) || empty($params['imei'])) {
            return json(['code' => 100, 'msg' => '缺少参数!']);
        }
        $str = 'cartGet' . $params['idcard'];
        $res = Cache::store('redis')->get($str);
        if ($res) {
            return json(['code' => 100, 'msg' => '请勿重复刷卡']);
        } else {
            Cache::store('redis')->set($str, 1, 30);
        }
        $device = (new MachineDevice())->where('imei', $params['imei'])->find();
        if (empty($device)) {
            return json(['code' => 100, 'msg' => '设备不存在']);
        }
        if ($device['is_lock'] == 0) {
            return json(['code' => 100, 'msg' => '该设备已被禁用']);
        }
        if ($device['expire_time'] < time()) {
            return json(['code' => 100, 'msg' => '设备已过期,请联系客服处理!']);
        }
        if ($device['supply_id'] != 3) {
            if ($device['status'] != 1) {
                return json(['code' => 100, 'msg' => '设备不在线,请联系客服处理!']);
            }
        }
        $device_sn = $device['device_sn'];
        $card = (new MachineCardModel())
            ->where('idcard', $params['idcard'])
            ->where('status', 0)
            ->where('num', '>', 0)
            ->find();
        if (!$card) {
            return json(['code' => 100, 'msg' => '无效卡,请联系管理员处理']);
        }
        if (empty($card['goods_id'])) {
            return json(['code' => 100, 'msg' => '卡未绑定商品']);
        }
        $amount = (new MachineGoods())
            ->where(['device_sn' => $device_sn, 'goods_id' => $card['goods_id']])
            ->group('goods_id')
            ->field('sum(stock) stock')->find();
        if ($amount['stock'] < 1) {
            return json(['code' => 100, 'msg' => '该设备暂无可领取商品']);
        }
        $str = 'buying' . $device['device_sn'];
        $res = Cache::store('redis')->get($str);
        if ($res == 1) {
            return json(['code' => 100, 'msg' => '有其他用户正在购买,请稍后重试']);
        } else {
            Cache::store('redis')->set($str, 1, 120);
        }
        $goods = (new MachineGoods())
            ->where(['device_sn' => $device_sn, 'goods_id' => $card['goods_id']])
            ->where('stock', '>', 0)
            ->find();
        $post['num'] = $goods['num'];
        $order_sn = time() . mt_rand(1000, 9999);
        $data = [
            'uid' => $card['uid'],
            'count' => 1,
            'device_sn' => $device_sn,
            'idcard' => $params['idcard'],
            'openid' => '刷卡领取',
            'order_sn' => $order_sn,
            'status' => 1,
            'pay_type' => 5,
//            'is_free' => 2,
            'price' => 0.00,
            'pay_time' => time(),
            'create_time' => time()
        ];
        $order_id = (new FinanceOrder())->insertGetId($data);
        $goods_data = [
            'order_id' => $order_id,
            'device_sn' => $device_sn,
            'order_sn' => $order_sn . 'order1',
            'num' => $post['num'],
            'goods_id' => $card['goods_id'],
            'price' => 0,
            'count' => 1,
            'total_price' => $goods['price'],
        ];
        (new OrderGoods())->save($goods_data);
        //出货
//        $machine_goods = (new MachineGoods())
//            ->where(['device_sn' => $device_sn, 'num' => $post['num']])
//            ->field('stock,goods_id')->find();
        $res = (new Goods())->out($device, $order_sn);
        Cache::store('redis')->rm($str);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //牛奶机获取最新安装包
    public function getApk()
    {
        $app_key = request()->get('app_key', '');
        if (empty($app_key)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new AppVersionModel();
        $row = $model->where('app_key', $app_key)->where('delete_time', null)->order('version_code desc')->find();
        return json(['code' => 200, 'data' => $row ?? []]);
    }

    //获取会员卡剩余次数
    public function getCard()
    {
        $params = request()->get();
        if (empty($params['idcard'])) {
            return json(['code' => 100, 'msg' => '缺少参数!']);
        }
        usleep(500000);
        $data = (new MachineCardModel())->where('idcard', $params['idcard'])->field('num,idcard')->find();
        return json(['code' => 200, 'data' => $data]);
    }
}
