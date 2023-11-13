<?php

namespace app\index\controller;

use app\index\model\GoodsTemplateGoodsModel;
use app\index\model\GoodsTemplateModel;
use app\index\model\MachineStockLogModel;
use app\index\model\MtGoodsModel;

class MachineGoods extends BaseController
{
    //设备商品列表
    public function getGoodsList()
    {
        $id = request()->get('id');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new \app\index\model\MachineDevice())->find($id);
        $model = new \app\index\model\MachineGoods();
        $list = $model->alias('d')
            ->join('mall_goods g', 'd.goods_id=g.id', 'left')
            ->where('d.device_sn', $device['device_sn'])
            ->field('d.*,g.image,g.title')
            ->order('d.num asc')
            ->limit($device['num'])
            ->select();

        $count = count($list);
        if ($count < $device['num']) {
            $nums = range(1, $device['num']);
            $device_nums = [];
            foreach ($list as $k => $v) {
                $device_nums[] = $v['num'];
            }
            $missing_num = array_values(array_diff($nums, $device_nums));
            for ($i = 1; $i <= count($missing_num); $i++) {
                $list[] = [
                    'num' => $missing_num[$i - 1],
                    'device_sn' => $device['device_sn'],
                    'goods_id' => '',
                    'image' => '',
                    'title' => '',
                    'volume' => '',
                    'stock' => 0.00,
                    'active_price' => 0.00,
                    'price' => 0.00,
                    'port' => 0,
                    'update_time' => '',
                    'lock' => 0,
                    'err_lock' => 0
                ];
            }
        }
        return json(['code' => 200, 'data' => $list]);
    }

    //异常货道取消锁定
    public function cancelErrLock()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new \app\index\model\MachineGoods();
        $model->where('id', $id)->update(['err_lock' => 0]);
        return json(['code' => 100, 'msg' => '解锁成功']);
    }

    //保存货道信息
    public function save()
    {
        $data = request()->post('data/a');
        $user = $this->user;
        $device_sn = request()->post('device_sn');
        if (!$device_sn) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new \app\index\model\MachineGoods();
        $ids = [];
        foreach ($data as $k => $v) {
            if (!empty($v['id'])) {
                $ids[] = $v['id'];
            }
            unset($data[$k]['image']);
            unset($data[$k]['title']);
        }
        if ($ids) {
            $model->whereNotIn('id', $ids)->where('device_sn', $device_sn)->delete();
        }
        $stock = $model->whereIn('id', $ids)->column('device_sn,num,goods_id,stock', 'id');
        $stockModel = new MachineStockLogModel();
        $stock_log = [];
        foreach ($data as $k => $v) {
            unset($v['create_time']);
            if (!empty($v['id'])) {
                if ($v['stock'] != $stock[$v['id']]['stock'] || $v['goods_id'] != $stock[$v['id']]['goods_id']) {
                    $v['update_time'] = time();
                } else {
                    unset($v['update_time']);
                }
                $model->where('id', $v['id'])->update($v);
                if ($v['goods_id'] != $stock[$v['id']]['goods_id']) {
                    $log = [];
                    if ($stock[$v['id']]['stock'] > 0) {
                        $log = [
                            [
                                'uid' => $user['id'],
                                'device_sn' => $device_sn,
                                'num' => $v['num'],
                                'old_stock' => $stock[$v['id']]['stock'],
                                'goods_id' => $stock[$v['id']]['goods_id'],
                                'type' => 2,
                                'new_stock' => 0,
                                'change_detail' => '更改货道商品,清空当前库存'
                            ], [
                                'uid' => $user['id'],
                                'device_sn' => $device_sn,
                                'num' => $v['num'],
                                'goods_id' => $v['goods_id'],
                                'type' => 1,
                                'old_stock' => 0,
                                'new_stock' => $v['stock'],
                                'change_detail' => '更改货道商品,库存增加' . $v['stock'] . '件'
                            ]
                        ];
                    }
                    $stock_log = array_merge($stock_log, $log);
                } else {
                    if ($stock[$v['id']]['stock'] == $v['stock']) {
                        continue;
                    }
                    if ($stock[$v['id']]['stock'] > $v['stock']) {
                        $change = $stock[$v['id']]['stock'] - $v['stock'];
                        $change_detail = '手动补货,库存减少' . $change . '件';
                        $type = 2;
                    } else {
                        $change = $v['stock'] - $stock[$v['id']]['stock'];
                        $change_detail = '手动补货,库存增加' . $change . '件';
                        $type = 1;
                    }
                    $stock_log[] = [
                        'uid' => $user['id'],
                        'device_sn' => $device_sn,
                        'num' => $v['num'],
                        'old_stock' => $stock[$v['id']]['stock'],
                        'goods_id' => $stock[$v['id']]['goods_id'],
                        'new_stock' => $v['stock'],
                        'type' => $type,
                        'change_detail' => $change_detail
                    ];
                }
            } else {
                $v['create_time'] = time();
                $v['update_time'] = time();
                $model->insert($v);
                $stock_log[] = [
                    'uid' => $user['id'],
                    'device_sn' => $device_sn,
                    'num' => $v['num'],
                    'old_stock' => 0,
                    'goods_id' => $v['goods_id'],
                    'new_stock' => $v['stock'],
                    'change_detail' => '货道添加商品,库存增加' . $v['stock'] . '件'
                ];
            }
        }
        $stockModel->saveAll($stock_log);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //清理商品
    public function clearGoods()
    {
        $id = request()->get('id');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new \app\index\model\MachineDevice())->find($id);
        $data = [
            'goods_id' => 0,
            'stock' => 0,
            'price' => 0,
            'active_price' => 0,
        ];
        (new \app\index\model\MachineGoods())->where('device_sn', $device['device_sn'])->update($data);
        return json(['code' => 200, 'msg' => '清除成功']);
    }

    //获取货道模板
    public function getTemplate()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['uid'] = $user['parent_id'];
            } else {
                $where['uid'] = ['=', $user['id']];
            }
        }
        $model = new GoodsTemplateModel();
        $list = $model->alias('c')
            ->where($where)
            ->field('id,name')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //应用模板
    public function useTemplate()
    {
        $get = request()->get();
        if (empty($get['id']) || empty($get['template_id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $deviceModel = new \app\index\model\MachineDevice();
        $templateModel = new GoodsTemplateModel();
        $device = $deviceModel->where('id', $get['id'])->find();
        $template = $templateModel->where('id', $get['template_id'])->find();
        //更新设备货道数量
        $deviceModel->where('id', $get['id'])->update(['num' => $template['num']]);
        $goodsModel = new \app\index\model\MachineGoods();
        $templateGoodsModel = new GoodsTemplateGoodsModel();
        //清除设备原货道信息
        $goodsModel->where('device_sn', $device['device_sn'])->delete();
        //更新货到信息
        $goodsTemplate = $templateGoodsModel
            ->where('template_id', $template['id'])
            ->select();
        $goodsData = [];
        foreach ($goodsTemplate as $k => $v) {
            $goodsData[] = [
                'device_sn' => $device['device_sn'],
                'num' => $v['num'],
                'goods_id' => $v['goods_id'],
                'volume' => $v['volume'],
                'stock' => 0,
                'price' => $v['price'],
                'active_price' => $v['active_price'],
                'port' => $v['port'],
                'lock' => $v['lock'],
            ];
        }
        $goodsModel->saveAll($goodsData);
        return json(['code' => 200, 'msg' => '应用成功']);
    }
}
