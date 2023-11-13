<?php

namespace app\appletadmin\controller;

use app\index\controller\BaseController;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use app\index\model\MachineStockLogModel;
use app\index\model\MallGoodsModel;
use app\index\model\OperateGoodsModel;
use app\index\model\OperateStockLogModel;

class DeviceStock extends BaseController
{
    //入库
    public function putStock()
    {
        $params = request()->post();
        if (empty($params['num']) || empty($params['device_sn'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = $this->user;
        $machineGoodsModel = new MachineGoods();
        $num = $machineGoodsModel
            ->where('device_sn', $params['device_sn'])
            ->where('num', $params['num'])
            ->find();
        $goodsModel = new MallGoodsModel();
        if (empty($params['goods_id'])) {
            $where = [];
            if ($user['role_id'] != 1) {
                if ($user['role_id'] > 5) {
                    $where['og.uid'] = $user['parent_id'];
                } else {
                    $where['og.uid'] = $user['id'];
                }
            }
            $where['g.code'] = ['=', $params['code']];
            $model = new OperateGoodsModel();
            $goodsList = $model->alias('og')
                ->join('mall_goods g', 'g.id=og.goods_id', 'left')
                ->where($where)
                ->field('g.id,g.image,g.title,g.price,g.active_price')
                ->select();
            if (count($goodsList) > 1) {
                return json(['code' => 201, 'msg' => '请选择商品', 'data' => $goodsList]);
            }
        }

        if (!$num || !$num['goods_id'] || ($num['stock'] == 0 && $num['lock'] == 0)) {
            if (isset($params['goods_id']) && $params['goods_id'] > 0) {
                $goods = $goodsModel->where('id', $params['goods_id'])->find();
            } else {
                $goods = $goodsModel->where('code', $params['code'])->find();
            }
            if (!$goods) {
                return json(['code' => 100, 'msg' => '商品库不存在该商品']);
            }
            if ($num) {
                $data = [
                    'goods_id' => $goods['id'],
                    'stock' => 0,
                    'price' => $goods['price'],
                    'active_price' => $goods['active_price'],
                    'port' => $goods['show_port'],
                ];
                $machineGoodsModel->where('id', $num['id'])->update($data);
            } else {
                $data = [
                    'device_sn' => $params['device_sn'],
                    'num' => $params['num'],
                    'goods_id' => $goods['id'],
                    'stock' => 0,
                    'volume' => 1,
                    'price' => $goods['price'],
                    'active_price' => $goods['active_price'],
                    'port' => $goods['show_port'],
                    'lock' => 0
                ];
                $machineGoodsModel->save($data);
            }
            $num = $machineGoodsModel
                ->where('device_sn', $params['device_sn'])
                ->where('num', $params['num'])
                ->find();
        }
        if ($num['stock'] >= $num['volume']) {
            return json(['code' => 100, 'msg' => '失败;已超出当前货道容量']);
        }
        $goods = $goodsModel->where('id', $num['goods_id'])->find();
        if (empty($params['goods_id']) && $goods['code'] != $params['code']) {
            return json(['code' => 100, 'msg' => '与货道商品不匹配']);
        }
        if (isset($params['goods_id']) && $params['goods_id'] > 0 && $goods['id'] != $params['goods_id']) {
            return json(['code' => 100, 'msg' => '与货道商品不匹配1']);
        }
        $operateGoodsModel = new OperateGoodsModel();
        //判断运营库库存

        $uid = $user['role_id'] < 5 ? $user['id'] : $user['parent_id'];
        $operateGoods = $operateGoodsModel->where('uid', $uid)->where('goods_id', $goods['id'])->find();
        if (!$operateGoods) {
            return json(['code' => 100, 'msg' => '您的库存没有该商品']);
        }
        if ($operateGoods['stock'] < 1) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }
        if ($operateGoods['check'] > 0) {
            return json(['code' => 100, 'msg' => '盘库中,不可补货']);
        }

        //加货道库存
        $machineGoodsModel->where('id', $num['id'])->update(['stock' => $num['stock'] + 1]);
        //加入库记录
        $data = [
            'uid' => $user['id'],
            'device_sn' => $params['device_sn'],
            'num' => $params['num'],
            'type' => 1,
            'goods_id' => $num['goods_id'],
            'old_stock' => $num['stock'],
            'new_stock' => $num['stock'] + 1,
            'change_detail' => '扫码补货,库存加1',
        ];
        (new MachineStockLogModel())->save($data);
        //扣除运营库库存  todo 上线时,去掉false
        if (false) {
            $operateGoodsModel->where('id', $operateGoods['id'])->update(['stock' => $operateGoods['stock'] - 1]);
            //添加出库记录
            $log = [
                'uid' => $user['id'],
                'device_sn' => $params['device_sn'],
                'num' => $params['num'],
                'type' => 2,
                'goods_id' => $num['goods_id'],
                'count' => -1,
            ];
            (new OperateStockLogModel())->save($log);
        }

        $data = $machineGoodsModel->alias('mg')
            ->join('mall_goods g', 'g.id=mg.goods_id', 'left')
            ->where('mg.device_sn', $params['device_sn'])
            ->where('mg.num', $params['num'])
            ->field('mg.*,g.image,g.title')
            ->find();
        return json(['code' => 200, 'msg' => '入库成功', 'data' => $data]);
    }

    //减库
    public function outStock()
    {
        $params = request()->post();
        if (empty($params['code']) || empty($params['num']) || empty($params['device_sn'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = $this->user;
        $machineGoodsModel = new MachineGoods();
        $goodsModel = new MallGoodsModel();
        $num = $machineGoodsModel
            ->where('device_sn', $params['device_sn'])
            ->where('num', $params['num'])
            ->find();
        if (!$num || !$num['goods_id']) {
            return json(['code' => 100, 'msg' => '该货道未配置商品']);
        }

        $goods = $goodsModel->where('id', $num['goods_id'])->find();
        if ($goods['code'] != $params['code']) {
            return json(['code' => 100, 'msg' => '与货道商品不匹配']);
        }
        //判断货道库存
        if ($num['stock'] < 1) {
            return json(['code' => 100, 'msg' => '货道已无库存']);
        }
        $operateGoodsModel = new OperateGoodsModel();
        $uid = (new MachineDevice())->where('device_sn', $params['device_sn'])->value('uid');
        $operateGoods = $operateGoodsModel->where('uid', $uid)->where('goods_id', $num['goods_id'])->find();
        if ($operateGoods['check'] > 0) {
            return json(['code' => 100, 'msg' => '盘库中,不可操作']);
        }
        //减货道库存
        $machineGoodsModel->where('id', $num['id'])->update(['stock' => $num['stock'] - 1]);
        //加减库记录
        $data = [
            'uid' => $user['id'],
            'device_sn' => $params['device_sn'],
            'num' => $params['num'],
            'type' => 2,
            'goods_id' => $num['goods_id'],
            'old_stock' => $num['stock'],
            'new_stock' => $num['stock'] - 1,
            'change_detail' => '扫码减库,库存减1',
        ];
        (new MachineStockLogModel())->save($data);
        //增加运营库库存  todo 上线时,去掉false
//        $operateGoodsModel = new OperateGoodsModel();
        if (false) {
//            $uid = (new MachineDevice())->where('device_sn', $params['device_sn'])->value('uid');
//            $operateGoods = $operateGoodsModel->where('uid', $uid)->where('goods_id', $num['goods_id'])->find();
            $operateGoodsModel->where('id', $operateGoods['id'])->update(['stock' => $operateGoods['stock'] + 1]);
            //添加出库记录
            $log = [
                'uid' => $user['id'],
                'device_sn' => $params['device_sn'],
                'num' => $params['num'],
                'type' => 1,
                'goods_id' => $num['goods_id'],
                'count' => 1,
            ];
            (new OperateStockLogModel())->save($log);
        }

        return json(['code' => 200, 'msg' => '出库成功']);
    }

    //修改货道容量
    public function changeVolume()
    {
        $volume = request()->get('volume', 0);
        $num = request()->get('num', '');
        $device_sn = request()->get('device_sn', '');
        if (!$num || !$device_sn) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if ($volume < 0) {
            return json(['code' => 100, 'msg' => '容量不能小于0']);
        }
        $machineGoodsModel = new MachineGoods();
        $goods = $machineGoodsModel->where(['device_sn' => $device_sn, 'num' => $num])->find();
        if (!$goods) {
            $data = [
                'device_sn' => $device_sn,
                'num' => $num,
                'stock' => 0,
                'volume' => $volume,
                'port' => 0,
                'lock' => 0
            ];
            $machineGoodsModel->save($data);
        } else {
            $machineGoodsModel->where('id', $goods['id'])->update(['volume' => $volume]);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }
}
