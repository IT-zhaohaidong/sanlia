<?php

namespace app\index\controller;

use app\index\model\MtCateModel;
use app\index\model\MtDeviceGoodsModel;
use app\index\model\MtGoodsModel;
use app\index\model\MtShopModel;
use app\meituan\controller\MeiTuan;
use think\Db;

class MtGoods extends BaseController
{
    static $cate = [
        '090100' => '感冒用药',
        '090200' => '清热解毒',
        '090300' => '呼吸系统',
        '090400' => '消化系统',
        '090500' => '妇科用药',
        '090600' => '儿童用药',
        '090700' => '滋养调补',
        '090800' => '男科用药',
        '090900' => '中药饮片',
        '091000' => '性福生活',
        '091100' => '皮肤用药',
        '091200' => '五官用药',
        '091300' => '营养保健',
        '091400' => '内分泌系统',
        '091500' => '医疗器械',
        '091600' => '养心安神',
        '091700' => '风湿骨伤',
        '091800' => '心脑血管用药',
        '092000' => '家庭常备',
        '092100' => '泌尿系统',
        '092200' => '神经用药',
        '092300' => '肿瘤用药',
        '092400' => '其他',
    ];

    //美团商品列表
    public function goodsList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 10);
        $app_poi_code = request()->get('app_poi_code', '');
        $model = new MtGoodsModel();
        $count = $model
            ->where('app_poi_code', $app_poi_code)
            ->count();
        $list = $model
            ->where('app_poi_code', $app_poi_code)
            ->page($page)->limit($limit)
            ->order('sequence asc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //店铺列表
    public function shopList()
    {
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
        $list = $model
            ->field('app_poi_code,name')
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //删除商品
    public function delGoods()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MtGoodsModel();
        $row = $model->where('id', $id)->find();
        if (empty($row)) {
            return json(['code' => 100, 'msg' => '该商品不存在']);
        }
        if ($row['type'] == 0) {
            $app_medicine_code = $row['app_medicine_code'] ?? $id;
            (new MeiTuan())->delMedicine($app_medicine_code, $row['app_poi_code']);
        }
        $model->where('id', $id)->delete();
        $goods = (new MtDeviceGoodsModel())->where('goods_id', $id)->select();
        if ($goods) {
            $data = [
                'goods_id' => '',
                'stock' => '',
                'price' => 0.00,
            ];
            $ids = [];
            foreach ($goods as $k => $v) {
                $ids[] = $v['id'];
            }
            (new MtDeviceGoodsModel())->whereIn('id', $ids)->update($data);
        }
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    //同步商品
    public function syncGoods()
    {
        $app_poi_code = request()->get('app_poi_code', '');
        if (empty($app_poi_code)) {
            return json(['code' => 100, 'msg' => '请选择店铺!']);
        }
        $model = new MtGoodsModel();
        $rows = $model->where('app_poi_code', $app_poi_code)->column('upc,name', 'upc');
        $data = (new MeiTuan())->medicineList($app_poi_code);
        $insert_data = [];
        foreach ($data['data'] as $k => $v) {
            if (!isset($rows[$v['upc']])) {
                $insert_data[] = [
                    'app_poi_code' => $app_poi_code,
                    'goods_id' => $v['id'],
                    'name' => $v['name'],
                    'upc' => $v['upc'],
                    'app_medicine_code' => $v['app_medicine_code'],
                    'medicine_no' => $v['medicine_no'],
                    'spec' => $v['spec'],
                    'price' => $v['price'],
                    'stock' => $v['stock'],
                    'category_code' => $v['category_code'],
                    'category_name' => $v['category_name'],
                    'is_sold_out' => $v['is_sold_out'],
                    'sequence' => $v['sequence'],
                    'medicine_type' => $v['medicine_type'],
                    'expiry_date' => $v['expiry_date']
                ];
            }
        }
        $model->saveAll($insert_data);
        return json(['code' => 200, 'msg' => '同步成功']);
    }

    public function addGoods()
    {
        $data = request()->post('data/a');
        if (empty($data['upc']) || empty($data['app_poi_code'])) {
            return json(['code' => 100, 'msg' => '缺少主要参数']);
        }
        $meituan = new MeiTuan();
        $list = $meituan->medicineList($data['app_poi_code']);
        $bool = false;
        foreach ($list['data'] as $k => $v) {
            if ($v['upc'] == $data['upc']) {
                $bool = true;
            }
        }
        if ($bool) {
            return json(['code' => 100, 'msg' => '该商品在美团店铺已存在,请同步商品!']);
        }
        $insert_data = [
            'app_poi_code' => $data['app_poi_code'],
            'category_code' => $data['category_code'],
            'image' => $data['image'],
            'detail_image' => $data['detail_image'],
            'detail' => $data['detail'],
            'category_name' => self::$cate[$data['category_code']],
            'is_sold_out' => 0,
            'upc' => $data['upc'],
            'medicine_no' => $data['medicine_no'],
            'spec' => $data['spec'],
            'price' => $data['price'],
            'stock' => $data['stock'],
            'create_time' => time()
        ];
        $model = new MtGoodsModel();
        $id = $model->insertGetId($insert_data);
        $params = [
            'app_poi_code' => $data['app_poi_code'],
            'app_medicine_code' => $id,
            'category_code' => $data['category_code'],
            'is_sold_out' => $data['is_sold_out'],
            'upc' => $data['upc'],
            'medicine_no' => $data['medicine_no'],
            'spec' => $data['spec'],
            'price' => $data['price'],
            'stock' => $data['stock'],
        ];
        $res = $meituan->createMedicine($params);
        if ($res['data'] == 'ng') {
            $model->where('id', $id)->delete();
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        $res = (new MeiTuan())->medicineList($data['app_poi_code']);
        $insert_data = [];
        foreach ($res['data'] as $k => $v) {
            if ($v['upc'] == $params['upc']) {
                $insert_data = [
                    'name' => $v['name'],
                    'upc' => $v['upc'],
                    'app_medicine_code' => $v['app_medicine_code'],
                    'medicine_no' => $v['medicine_no'],
                    'spec' => $v['spec'],
                    'price' => $v['price'],
                    'stock' => $v['stock'],
                    'category_code' => $v['category_code'],
                    'category_name' => $v['category_name'],
                    'is_sold_out' => $v['is_sold_out'],
                    'sequence' => $v['sequence'],
                    'medicine_type' => $v['medicine_type'],
                    'expiry_date' => $v['expiry_date']
                ];
            }
        }
        $model->where('id', $id)->update($insert_data);
        return json(['code' => 200, 'msg' => '添加成功']);
    }

    public function updateGoods()
    {
        $data = request()->post('data/a');
        if (empty($data['upc']) || empty($data['app_poi_code']) || empty($data['id'])) {
            return json(['code' => 100, 'msg' => '缺少主要参数']);
        }
        $model = new MtGoodsModel();

        $row = $model->where('id', $data['id'])->find();
        $meituan = new MeiTuan();
        $list = $meituan->medicineList($data['app_poi_code']);
        $bool = false;
        foreach ($list['data'] as $k => $v) {
            if ($v['upc'] == $data['upc'] && $row['upc'] != $v['upc']) {
                $bool = true;
            }
        }
        if ($bool) {
            return json(['code' => 100, 'msg' => '该商品在美团店铺已存在,请同步商品!']);
        }
        $insert_data = [
            'app_poi_code' => $data['app_poi_code'],
            'category_code' => $data['category_code'],
            'image' => $data['image'],
            'detail_image' => $data['detail_image'],
            'detail' => $data['detail'],
            'category_name' => self::$cate[$data['category_code']],
            'upc' => $data['upc'],
            'medicine_no' => $data['medicine_no'],
            'spec' => $data['spec'],
            'price' => $data['price'],
            'stock' => $data['stock'],
            'create_time' => time()
        ];
        $params = [
            'app_poi_code' => $data['app_poi_code'],
            'app_medicine_code' => $row['app_medicine_code'] ?? $data['id'],
            'category_code' => $data['category_code'],
            'is_sold_out' => $data['is_sold_out'],
            'upc' => $data['upc'],
            'medicine_no' => $data['medicine_no'],
            'spec' => $data['spec'],
            'price' => $data['price'],
            'stock' => $data['stock'],
        ];
        $res = $meituan->updateMedicine($params);
        if ($res['data'] == 'ng') {
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        $model->where('id', $data['id'])->update($insert_data);
        return json(['code' => 200, 'msg' => '修改成功']);
    }

    //上/下架   is_sold_out 0-上架 1:下架
    public function putaway()
    {
        $id = request()->get('id');
        $is_sold_out = request()->get('is_sold_out');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MtGoodsModel();
        $row = $model->where('id', $id)->find();
        $data = [[
            'app_medicine_code' => $row['app_medicine_code'] ?? $row['id'],
            'is_sold_out' => $is_sold_out
        ]];
        $meituan = new MeiTuan();
        $res = $meituan->soldOut($data, $row['app_poi_code']);
        if ($res['data'] == 'ng') {
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        $model->where('id', $id)->update(['is_sold_out' => $is_sold_out]);
        return json(['code' => 200, 'msg' => '修改成功']);

    }

    public function getCateList()
    {
        $app_poi_code = request()->get('app_poi_code', '');
        $where = [];
        if (empty($app_poi_code)) {
            $where['app_poi_code'] = $app_poi_code;
        } else {
            $user = $this->user;
            if ($user['role_id'] != 1) {
                if ($user['role_id'] > 5) {
                    $uid = $user['parent_id'];
                } else {
                    $uid = $user['id'];
                }
                $where['uid'] = $uid;
            }
        }
        $list = (new MtCateModel())->where($where)->order('sequence asc')->select();
        $data = [];
        foreach ($list as $k => $v) {
            if (!empty($app_poi_code)) {
                $data[] = [
                    'key' => $v['category_code'],
                    'value' => $v['category_name']
                ];
            } else {
                $data[] = [
                    'key' => $v['id'],
                    'value' => $v['category_name']
                ];
            }

        }
        return json(['code' => 200, 'data' => $data]);
    }

    //---------------------------普通售药机商品列表-------------------------
    public function machineGoodsList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 10);
//        $app_poi_code = request()->get('app_poi_code', '');
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['g.uid'] = ['=', $user['parent_id']];
            } else {
                $where['g.uid'] = ['=', $user['id']];
            }
        }
        if (!empty($params['name'])) {
            $where['g.name'] = ['like', "%" . $params['name'] . "%"];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', "%" . $params['username'] . "%"];
        }
        $where['g.type'] = 1;
        $model = new MtGoodsModel();
        $count = $model->alias('g')
            ->join('mt_cate c', 'g.cate_id=c.id', 'left')
            ->join('system_admin a', 'g.uid=a.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('g')
            ->join('mt_cate c', 'g.cate_id=c.id', 'left')
            ->join('system_admin a', 'g.uid=a.id', 'left')
            ->where($where)
            ->page($page)->limit($limit)
            ->order('sequence asc')
            ->field('g.*,c.category_name,a.username')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function saveMachineGoods()
    {
        $data = request()->post();
        if (empty($data['upc'])) {
            return json(['code' => 100, 'msg' => '缺少主要参数']);
        }
        $model = new MtGoodsModel();
        $user = $this->user;
        if ($user['role_id'] > 5) {
            $uid = $user['parent_id'];
        } else {
            $uid = $user['id'];
        }
        if (empty($data['id'])) {
            $bool = $model->where(['uid' => $uid, 'type' => 1])->where('upc', $data['upc'])->find();
        } else {
            $bool = $model->where(['uid' => $uid, 'type' => 1])->where('id', '<>', $data['id'])->where('upc', $data['upc'])->find();
        }
        if ($bool) {
            return json(['code' => 100, 'msg' => '该商品已存在']);
        }
        $category_code = (new MtCateModel())->where('id', $data['cate_id'])->value('category_code');
        $insert_data = [
//            'app_poi_code' => $data['app_poi_code'],
            'category_code' => $category_code,
            'image' => $data['image'],
            'name' => $data['name'],
            'type' => 1,
            'cate_id' => $data['cate_id'],
//            'category_name' => self::$cate[$data['category_code']],
//            'is_sold_out' => 0,
            'upc' => $data['upc'],
            'medicine_no' => $data['medicine_no'],
            'spec' => $data['spec'],
            'detail_image' => $data['detail_image'],
            'detail' => $data['detail'],
            'price' => $data['price'],
            'stock' => $data['stock'],
            'sequence' => $data['sequence'],
            'uid' => $uid,
            'medicine_type' => $data['medicine_type'],
            'expiry_date' => $data['expiry_date'],
            'create_time' => time()
        ];
        if (empty($data['id'])) {
            $model->insertGetId($insert_data);
        } else {
            $model->where('id', $data['id'])->update($insert_data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function getMachineCate()
    {
        $model = new MtCateModel();
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                return json(['code' => 100, 'msg' => '您没有权限!']);
            } else {
                $where['uid'] = ['=', $user['id']];
            }
        }

        $list = $model
            ->where($where)
            ->where('type', 1)
            ->order('sequence asc')
            ->field('id,category_name')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

}