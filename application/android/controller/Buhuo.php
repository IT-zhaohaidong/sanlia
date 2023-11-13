<?php

namespace app\android\controller;


use app\index\controller\MachineDevice;
use app\index\model\MachineStockLogModel;
use app\index\model\SystemAdmin;
use think\Db;

class Buhuo
{
//    public $token;
//    public $user;
//
//    public function __construct()
//    {
//        if (empty($_SERVER['HTTP_TOKEN'])) {
//            echo json_encode(["code" => 400, "msg" => "token不存在"]);
//            exit();
//        }
//        $token = $_SERVER['HTTP_TOKEN'];
//        $token_info = Db::name("system_login")->where("token", $token)->where("expire_time", '>', time())->find();
//        if (empty($token_info)) {
//            echo json_encode(["code" => 400, "msg" => "登录状态已失效"]);
//            exit();
//        }
//        $this->user = Db::name('system_admin')->where('id', $token_info['uid'])->find();
//        $this->token = $token;
//    }

    public function getList()
    {
        //设备商品列表
        $imei = request()->get('imei');
        if (!$imei) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new \app\index\model\MachineDevice())->where('imei', $imei)->find();
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
            for ($i = 1; $i <= $device['num'] - $count; $i++) {
                $list[] = [
                    'num' => $count + $i,
                    'device_sn' => $device['device_sn'],
                    'goods_id' => '',
                    'title' => '',
                    'image' => '',
                    'volume' => '',
                    'stock' => '',
                    'price' => ''
                ];
            }
        }
        return json(['code' => 200, 'data' => $list]);
    }

    //保存货道信息
    public function save()
    {
        $data = request()->post('data/a');
        $user['id'] = request()->post('id', '');
        if (empty($user['id'])) {
            return json(['code' => 100, 'msg' => '登录失效']);
        }
        $imei = request()->post('imei');
        if (!$imei) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device_sn = (new \app\index\model\MachineDevice())->where('imei', $imei)->value('device_sn');
        $model = new \app\index\model\MachineGoods();
        $ids = [];
        foreach ($data as $k => $v) {
            if (!empty($v['id'])) {
                $ids[] = $v['id'];
            }
            unset($data[$k]['image']);
        }
        if ($ids) {
            $model->whereNotIn('id', $ids)->where('device_sn', $device_sn)->delete();
        }
        $stock = $model->whereIn('id', $ids)->column('device_sn,num,goods_id,stock', 'id');
        $stockModel = new MachineStockLogModel();
        $stock_log = [];
        foreach ($data as $k => $v) {
            unset($v['title']);
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


}