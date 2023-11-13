<?php

namespace app\android\controller;

use app\index\model\MachineCart;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;

class Cart
{
    public function getCartList()
    {
        $imei = request()->post('imei', '');
        if (!$imei) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineCart();
        $list = $model->alias('c')
            ->join('machine_goods mg', 'c.num=mg.num')
            ->join('mall_goods g', 'g.id=mg.goods_id')
            ->field('c.id,c.num,c.count,g.title,g.image,mg.price,mg.stock')
            ->where('c.imei', $imei)
            ->select();
        $total_price = 0;
        $goods_count = 0;
        foreach ($list as $k => $v) {
            $total_price += $v['count'] * $v['price'];
            $goods_count += $v['count'];
        }
        $data = compact('list', 'total_price', 'goods_count');
        return json(['code' => 200, 'data' => $data]);
    }

    //添加购物车
    public function addCart()
    {
        $imei = request()->post('imei', '');
        $num = request()->post('num', '');
        if (!$imei || !$num) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $stock = (new MachineGoods())->where(['imei' => $imei, 'num' => $num])->value('stock');
        if ($stock < 1) {
            return json(['code' => 100, 'msg' => '暂无库存']);
        }
        $model = new MachineCart();
        $row = $model->where(['imei' => $imei, 'num' => $num])->find();
        if ($stock - $row['count'] < 1) {
            return json(['code' => 100, 'msg' => '暂无库存']);
        }
        if ($row) {
            $model->where('id', $row['id'])->update(['count' => $row['count'] + 1]);
        } else {
            $model->save(['imei' => $imei, 'num' => $num, 'count' => 1]);
        }
        return json(['code' => 200, 'msg' => '添加成功']);
    }

    //删除商品
    public function del()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
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

    public function addCount()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineCart();
        $row = $model->where(['id' => $id])->find();
        $stock = (new MachineGoods())->where(['imei' => $row['imei'], 'num' => $row['num']])->value('stock');
        if ($row['count'] >= $stock) {
            return json(['code' => 100, 'msg' => '购买数量不得超过库存']);
        }
        $model->where(['id' => $id])->update(['count' => $row['count'] + 1]);
        return json(['code' => 200, 'msg' => '添加成功']);
    }

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