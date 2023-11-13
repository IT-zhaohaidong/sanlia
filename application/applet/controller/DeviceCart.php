<?php

namespace app\applet\controller;

use app\index\model\DeviceConfigModel;
use app\index\model\MachineCart;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use think\Controller;
use think\Db;

class DeviceCart extends Controller
{
    public function getCartList()
    {
        $params = request()->get();
        if (empty($params['openid']) || empty($params['device_sn']) || empty($params['port'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new \app\index\model\MachineDevice())
            ->where('device_sn', $params['device_sn'])
            ->field('id,device_sn,imei,num')
            ->find();
        $model = new MachineCart();
        $list = $model->alias('c')
            ->join('mall_goods g', 'g.id=c.goods_id')
            ->field('c.id,c.count,c.goods_id,g.title,g.image')
            ->where(['c.openid' => $params['openid'], 'device_sn' => $params['device_sn']])
            ->select();
        $where['g.port'] = ['in', [0, $params['port']]];
        $goods = (new MachineGoods())->alias("g")
            ->join("mall_goods s", "g.goods_id=s.id", "LEFT")
            ->where("g.device_sn", $params['device_sn'])
            ->where('g.num', '<=', $device['num'])
            ->where($where)
            ->where('g.goods_id', '>', 0)
            ->order('g.num asc')
            ->group('g.goods_id')
            ->column('g.price,g.active_price,sum(g.stock) total_stock', 'g.goods_id');
        $clearGoodsId = [];//已被替换的商品
        $data = [];
        foreach ($list as $k => $v) {
            if (!isset($goods[$v['goods_id']])) {
                $clearGoodsId[] = $v['goods_id'];
                unset($list[$k]);
                continue;
            }
            $item = $v;
            $item['price'] = $goods[$v['goods_id']]['price'];
            $item['active_price'] = $goods[$v['goods_id']]['active_price'];
            $item['total_stock'] = $goods[$v['goods_id']]['total_stock'];
            $data[] = $item;
        }
        //清除已被替换的商品
        $model->where(['device_sn' => $params['device_sn'], 'openid' => $params['openid']])->whereIn('goods_id', $clearGoodsId)->delete();
        return json(['code' => 200, 'data' => $data]);
    }

    //添加购物车/添加数量
    public function addCart()
    {
        $goods_id = request()->post('goods_id', '');
        $device_sn = request()->post('device_sn', '');
        $openid = request()->post('openid', '');
        if (!$goods_id || !$device_sn || !$openid) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineCart();
        $device_id = (new MachineDevice())->where('device_sn', $device_sn)->value('id');
        $config = (new DeviceConfigModel())->getDeviceConfig($device_id);
        //获取购物车已有数量
        $count = $model->where(['device_sn' => $device_sn, 'openid' => $openid])->sum('count');
        if ($count >= $config['cart_max']) {
            return json(['code' => 100, 'msg' => '已超出购物车最大数量']);
        }
        $amount = (new MachineGoods())
            ->where(['device_sn' => $device_sn, 'goods_id' => $goods_id])
            ->group('goods_id')
            ->field('sum(stock) total_stock')->find();

        $row = $model->where(['device_sn' => $device_sn, 'goods_id' => $goods_id, 'openid' => $openid])->find();
        if ($amount['total_stock'] - $row['count'] - 1 < 0) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }
        if ($row) {
            $model->where('id', $row['id'])->update(['count' => $row['count'] + 1]);
        } else {
            $model->save(['device_sn' => $device_sn, 'goods_id' => $goods_id, 'openid' => $openid, 'count' => 1]);
        }
        return json(['code' => 200, 'msg' => '添加成功']);
    }

    //删除商品
    public function del()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '请选择要删除的商品']);
        }
        $model = new MachineCart();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    //清空购物车
    public function clear()
    {
        $imei = request()->get('imei', '');
        if (empty($imei)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineCart();
        $model->where('imei', $imei)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    //减少数量
    public function reduceCount()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineCart();
        $row = $model->where(['id' => $id])->find();
        if ($row['count'] <= 1) {
            return json(['code' => 100, 'msg' => '购买数量不能小于1']);
        }
        $model->where(['id' => $id])->update(['count' => $row['count'] - 1]);
        return json(['code' => 200, 'msg' => '成功']);
    }
}
