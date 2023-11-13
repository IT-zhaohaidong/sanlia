<?php

namespace app\index\controller;

use app\index\model\MachineStockLogModel;
use app\index\model\MtDeviceGoodsModel;
use app\index\model\MtShopModel;
use app\meituan\controller\MeiTuan;
use think\Db;

class MtShop extends BaseController
{
    //店铺列表
    public function shopList()
    {
        $params = input('post.', '');
        $limit = input('post.limit', 10);
        $page = input('post.page', 1);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['s.uid'] = ['=', $user['parent_id']];
            } else {
                $where['s.uid'] = ['=', $user['id']];
            }
        } else {
            if (!empty($params['uid'])) {
                $where['s.uid'] = $params['uid'];
            }
        }

        $model = new MtShopModel();
        $count = $model->alias('s')
            ->join('system_admin a', 's.uid=a.id', 'left')
            ->field('s.*,a.username')
            ->count();
        $list = $model->alias('s')
            ->join('system_admin a', 's.uid=a.id', 'left')
            ->join('machine_device d', 's.device_id=d.id', 'left')
            ->field('s.*,a.username,d.device_sn,d.device_name')
            ->page($page)
            ->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }

    //添加店铺
    public function addShop()
    {
        $app_poi_code = request()->get('app_poi_code', '');
        if (empty($app_poi_code)) {
            return json(['code' => 100, 'msg' => '门店id不能为空!']);
        }
        $user = $this->user;
        $model = new MtShopModel();
        $row = $model->where('app_poi_code', $app_poi_code)->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该门店已添加']);
        }
        $res = (new MeiTuan())->getShopDetail($app_poi_code);
        if (empty($res['data'])) {
            return json(['code' => 100, 'msg' => '该门店不存在']);
        }
        $shop = $res['data'][0];
        $data = [
            'uid' => $user['id'],
            'app_poi_code' => $shop['app_poi_code'],
            'name' => $shop['name'],
            'address' => $shop['address'],
            'pic_url' => $shop['pic_url'],
            'open_level' => $shop['open_level'],
            'is_online' => $shop['is_online'],
        ];
        $model->save($data);
        return json(['code' => 200, 'msg' => '添加成功']);
    }

    //绑定设备
    public function bindDevice()
    {
        $id = request()->get('id', '');
        $device_id = request()->get('device_id', '');
        if (empty($id) || empty($device_id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MtShopModel();
        $is_bind = $model->where('id', '<>', $id)->where('device_id', $device_id)->find();
        if ($is_bind) {
            return json(['code' => 100, 'msg' => '该设备已被其他门店绑定']);
        }
        $deviceModel = new \app\index\model\MachineDevice();
        $device = $deviceModel->where('id', $device_id)->find();
        $goods = (new \app\index\model\MachineGoods())
            ->where('device_sn', $device['device_sn'])
            ->where('goods_id', '>', 0)
            ->find();
        if ($goods) {
            return json(['code' => 100, 'msg' => '请先清空该设备下商品']);
        }
        $shop = $model->where('id', $id)->find();
        if ($shop['device_id']) {
            $deviceModel->where('id', $shop['device_id'])->update(['device_type' => 1]);
        }

        $model->where('id', $id)->update(['device_id' => $device_id]);

        $data = [
            'device_type' => 2,
        ];
        if (empty($device['medicine_qr_code'])) {
            $medicineQrCode = medicineQrCode($device['device_sn']);
            $data['medicine_qr_code'] = $medicineQrCode;
        }
        if (empty($device['rider_qr_code'])) {
            $riderQrCode = medicineQrCode($device['device_sn'], 1);
            $data['rider_qr_code'] = $riderQrCode;
        }

        $deviceModel->where('id', $device_id)->update($data);
        return json(['code' => 200, 'msg' => '绑定成功']);
    }

    //设备列表
    public function deviceList()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $device_ids = Db::name('machine_device_partner')
                    ->where(['admin_id' => $user['parent_id'], 'uid' => $user['id']])
                    ->column('device_id');
                $device_ids = $device_ids ? array_values($device_ids) : [];
                $where['id'] = ['in', $device_ids];
            } else {
                $where['uid'] = ['=', $user['id']];
            }
        }
        $model = new \app\index\model\MachineDevice();
        $list = $model
            ->where($where)
            ->field('id,device_sn,device_name')
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //设备货道列表
    public function deviceGoods()
    {
        $id = request()->get('id');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MtDeviceGoodsModel();
        $shop = (new MtShopModel())->find($id);
        if (!$shop['device_id']) {
            return json(['code' => 100, 'msg' => '请先绑定设备']);
        }
        $device = (new \app\index\model\MachineDevice())->where('id', $shop['device_id'])->find();
        $list = $model->alias('d')
            ->join('mt_goods g', 'd.goods_id=g.id', 'left')
            ->where('d.device_id', $shop['device_id'])
            ->field('d.*,g.name')
            ->order('d.num asc')
            ->limit($device['num'])
            ->select();
        $count = count($list);
        if ($count < $device['num']) {
            for ($i = 1; $i <= $device['num'] - $count; $i++) {
                $list[] = [
                    'num' => $count + $i,
                    'device_id' => $shop['device_id'],
                    'goods_id' => '',
                    'name' => '',
                    'volume' => '',
                    'stock' => '',
                    'price' => ''
                ];
            }
        }
        return json(['code' => 200, 'data' => $list, 'device_id' => $shop['device_id']]);
    }

    //保存货道信息
    public function save()
    {
        $data = request()->post('data/a');
        $user = $this->user;
        $device_id = request()->post('device_id');
        if (!$device_id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MtDeviceGoodsModel();
        $ids = [];
        foreach ($data as $k => $v) {
            if (!empty($v['id'])) {
                $ids[] = $v['id'];
            }
            unset($data[$k]['name']);
        }
        if ($ids) {
            $model->whereNotIn('id', $ids)->where('device_id', $device_id)->delete();
        }
        $stock = $model->whereIn('id', $ids)->column('device_id,num,goods_id,stock', 'id');
        $stockModel = new MachineStockLogModel();
        $stock_log = [];
        $device_sn = (new \app\index\model\MachineDevice())->where('id', $device_id)->value('device_sn');
        foreach ($data as $k => $v) {
            unset($v['create_time']);
            $v['update_time'] = time();
            if (!empty($v['id'])) {
                $model->where('id', $v['id'])->update($v);
                if ($v['goods_id'] != $stock[$v['id']]['goods_id']) {
                    $log = [
                        [
                            'uid' => $user['id'],
                            'device_sn' => $device_sn,
                            'num' => $v['num'],
                            'old_stock' => $stock[$v['id']]['stock'],
                            'goods_id' => $stock[$v['id']]['goods_id'],
                            'new_stock' => 0,
                            'change_detail' => '更改货道商品,清空当前库存'
                        ], [
                            'uid' => $user['id'],
                            'device_sn' => $device_sn,
                            'num' => $v['num'],
                            'goods_id' => $v['goods_id'],
                            'old_stock' => 0,
                            'new_stock' => $v['stock'],
                            'change_detail' => '更改货道商品,库存增加' . $v['stock'] . '件'
                        ]
                    ];
                    $stock_log = array_merge($stock_log, $log);
                } else {
                    if ($stock[$v['id']]['stock'] == $v['stock']) {
                        continue;
                    }
                    if ($stock[$v['id']]['stock'] > $v['stock']) {
                        $change = $stock[$v['id']]['stock'] - $v['stock'];
                        $change_detail = '补货,库存减少' . $change . '件';
                    } else {
                        $change = $v['stock'] - $stock[$v['id']]['stock'];
                        $change_detail = '补货,库存增加' . $change . '件';
                    }
                    $stock_log[] = [
                        'uid' => $user['id'],
                        'device_sn' => $device_sn,
                        'num' => $v['num'],
                        'old_stock' => $stock[$v['id']]['stock'],
                        'goods_id' => $stock[$v['id']]['goods_id'],
                        'new_stock' => $v['stock'],
                        'change_detail' => $change_detail
                    ];
                }
            } else {
                $v['create_time'] = time();
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

    //设置店铺为营业中
    public function openShop()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $shop = (new MtShopModel())->find($id);
        $res = (new MeiTuan())->openShop($shop['app_poi_code']);
        if ($res['data'] == 'ng') {
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        (new MtShopModel())->where('id', $id)->update(['open_level' => 1]);
        return json(['code' => 200, 'msg' => '设置成功']);
    }

    //设置店铺为休息中
    public function closeShop()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $shop = (new MtShopModel())->find($id);
        $res = (new MeiTuan())->closeShop($shop['app_poi_code']);
        if ($res['data'] == 'ng') {
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        (new MtShopModel())->where('id', $id)->update(['open_level' => 3]);
        return json(['code' => 200, 'msg' => '设置成功']);
    }
}