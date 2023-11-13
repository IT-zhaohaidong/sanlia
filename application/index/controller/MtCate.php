<?php

namespace app\index\controller;

use app\index\model\MtCateModel;
use app\index\model\MtDeviceGoodsModel;
use app\index\model\MtGoodsModel;
use app\index\model\MtShopModel;
use app\meituan\controller\MeiTuan;
use think\Db;

class MtCate extends BaseController
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

    //分类列表
    public function cateList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 10);
        $app_poi_code = request()->get('app_poi_code', '');
        $model = new MtCateModel();
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

    //同步分类
    public function syncCate()
    {
        $app_poi_code = request()->get('app_poi_code', '');
        if (empty($app_poi_code)) {
            return json(['code' => 100, 'msg' => '请选择店铺!']);
        }
        $model = new MtCateModel();
        $rows = $model->where('app_poi_code', $app_poi_code)->column('id,category_code', 'category_name');
        $data = (new MeiTuan())->getCatList($app_poi_code);
        $insert_data = [];
        foreach ($data['data'] as $k => $v) {
            if (!isset($rows[$v['category_name']])) {
                $insert_data[] = [
                    'app_poi_code' => $app_poi_code,
                    'category_code' => $v['category_code'],
                    'category_name' => $v['category_name'],
                    'sequence' => $v['sequence'],
                ];
            }
        }
        $model->saveAll($insert_data);
        return json(['code' => 200, 'msg' => '同步成功']);
    }

    //添加美团分类
    public function addCate()
    {
        $data = request()->post('data/a');
        if (empty($data['category_code']) || empty($data['app_poi_code'])) {
            return json(['code' => 100, 'msg' => '缺少主要参数']);
        }
        $meituan = new MeiTuan();
        $list = $meituan->getCatList($data['app_poi_code']);
        $bool = false;
        foreach ($list['data'] as $k => $v) {
            if ($v['category_code'] == $data['category_code']) {
                $bool = true;
            }
        }
        if ($bool) {
            return json(['code' => 100, 'msg' => '该分类在美团店铺已存在,请同步分类!']);
        }
        $insert_data = [
            'app_poi_code' => $data['app_poi_code'],
            'category_code' => $data['category_code'],
            'category_name' => self::$cate[$data['category_code']],
            'sequence' => $data['sequence'],
            'create_time' => time()
        ];
        $model = new MtCateModel();
        $id = $model->insertGetId($insert_data);
        $params = [
            'app_poi_code' => $data['app_poi_code'],
            'category_code' => $data['category_code'],
            'category_name' => self::$cate[$data['category_code']],
            'sequence' => $data['sequence'],
        ];
        $res = $meituan->createCat($params);
        if ($res['data'] == 'ng') {
            $model->where('id', $id)->delete();
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        return json(['code' => 200, 'msg' => '添加成功']);
    }

    public function updateCate()
    {
        $data = request()->post('data/a');
        if (empty($data['category_code']) || empty($data['id'])) {
            return json(['code' => 100, 'msg' => '缺少主要参数']);
        }
        $meituan = new MeiTuan();
        $model = new MtCateModel();
        $row = $model->where('id', $data['id'])->find();
        $list = $meituan->getCatList($data['app_poi_code']);
        $bool = false;
        foreach ($list['data'] as $k => $v) {
            if ($v['category_code'] == $data['category_code'] && $row['category_name'] != $v['category_name']) {
                $bool = true;
            }
        }
        if ($bool) {
            return json(['code' => 100, 'msg' => '该分类在美团店铺已存在,请同步分类!']);
        }
        $insert_data = [
            'app_poi_code' => $data['app_poi_code'],
            'category_code' => $data['category_code'],
            'category_name' => self::$cate[$data['category_code']],
            'sequence' => $data['sequence'],
            'update_time' => time()
        ];
        $model = new MtCateModel();
        $params = [
            'app_poi_code' => $data['app_poi_code'],
            'category_code' => $data['category_code'],
            'category_name' => self::$cate[$data['category_code']],
            'category_name_old' => $row['category_name'],
            'sequence' => $data['sequence'],
        ];
        $res = $meituan->updateCat($params);
        if ($res['data'] == 'ng') {
            return json(['code' => 100, 'msg' => $res['error']['msg']]);
        }
        $model->where('id', $data['id'])->update($insert_data);
        return json(['code' => 200, 'msg' => '添加成功']);
    }

    public function delCate()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MtCateModel();
        $row = $model->where('id', $id)->find();
        if ($row['type'] == 0) {
            $goods = (new MtGoodsModel())->where('app_poi_code', $row['app_poi_code'])->where('category_name', $row['category_name'])->find();
        } else {
            $goods = (new MtGoodsModel())->where('cate_id', $id)->find();
        }

        if ($goods) {
            return json(['code' => 100, 'msg' => '不可删除,该分类下存在商品']);
        }
        if ($row['type'] == 0) {
            $res = (new MeiTuan())->delCate($row['category_name'], $row['app_poi_code']);
            if ($res['data'] == 'ng') {
                return json(['code' => 100, 'msg' => $res['error']['msg']]);
            }
        }

        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    public function getCateList()
    {
        $app_poi_code = request()->get('app_poi_code', '');
        $id = request()->get('id', '');
        $cate_name = '';
        if ($id) {
            $row = (new MtCateModel())->where('id', $id)->find();
            $cate_name = $row['category_name'];
        }
        $list = $this->getChoseCate($app_poi_code, $cate_name);
        return json(['code' => 200, 'data' => $list]);
    }

    public function getChoseCate($app_poi_code, $cate_name = '')
    {
        $list = self::$cate;
        $data = [];
        $where = [];
        if ($app_poi_code) {
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
        $cate = (new MtCateModel())->where($where)->column('id,category_code', 'category_name');
        foreach ($list as $k => $v) {
            if (!isset($cate[$v]) || $cate_name == $v) {
                $data[] = [
                    'key' => $k,
                    'value' => $v
                ];
            }
        }
        return $data;
    }

    //获取普通售药机商品分类列表
    public function getMachineCate()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 10);
        $model = new MtCateModel();
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                return json(['code' => 100, 'msg' => '您没有权限!']);
            } else {
                $where['c.uid'] = ['=', $user['id']];
            }
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $user['username'] . '%'];
        }
        $count = $model->alias('c')
            ->join('system_admin a', 'a.id=c.uid', 'left')
            ->where($where)
            ->where('c.type', 1)->count();
        $list = $model->alias('c')
            ->join('system_admin a', 'a.id=c.uid', 'left')
            ->where($where)
            ->where('c.type', 1)
            ->page($page)
            ->limit($limit)
            ->field('c.*,a.username')
            ->order('c.sequence asc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //添加普通售药机商品分类
    public function addMachineCate()
    {
        $data = request()->post();
        if (empty($data['category_code'])) {
            return json(['code' => 100, 'msg' => '缺少主要参数']);
        }
        $user = $this->user;
        $model = new MtCateModel();
        $category_name = self::$cate[$data['category_code']];
        if ($user['role_id'] > 5) {
            $uid = $user['parent_id'];
        } else {
            $uid = $user['id'];
        }
        $bool = $model->where('uid', $uid)->where('category_name', $category_name)->find();
        if ($bool) {
            return json(['code' => 100, 'msg' => '该分类已存在']);
        }
        $insert_data = [
            'uid' => $uid,
            'category_code' => $data['category_code'],
            'category_name' => $category_name,
            'sequence' => $data['sequence'],
            'type' => 1,
            'create_time' => time()
        ];

        $id = $model->insertGetId($insert_data);

        return json(['code' => 200, 'msg' => '添加成功']);
    }

    public function editMachineCate()
    {
        $params = request()->post();
        if (empty($params['id']) || empty($params['sequence'])) {
            return json(['code' => 100, 'msg' => '缺少参数!']);
        }
        $model = new MtCateModel();
        $model->where('id', $params['id'])->update(['sequence' => $params['sequence']]);
        return json(['code'=>200,'msg'=>'修改成功']);
    }
}