<?php

namespace app\index\controller;

use app\index\model\BrandModel;
use app\index\model\MallGoodsModel;

class Brand extends BaseController
{
    //获取品牌列表
    public function getList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 15 : $params['limit'];
        $where = [];
        if (!empty($params['name'])) {
            $where['name'] = ['like', '%' . $params['name'] . '%'];
        }
        if (!empty($params['phone'])) {
            $where['phone'] = ['like', '%' . $params['phone'] . '%'];
        }
        if (!empty($params['brand_contacts'])) {
            $where['brand_contacts'] = ['like', '%' . $params['brand_contacts'] . '%'];
        }
        if (!empty($params['ch_contacts'])) {
            $where['ch_contacts'] = ['like', '%' . $params['ch_contacts'] . '%'];
        }
        if (!empty($params['ch_phone'])) {
            $where['ch_phone'] = ['like', '%' . $params['ch_phone'] . '%'];
        }
        $model = new BrandModel();
        $count = $model
            ->where($where)
            ->count();
        $list = $model
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //添加/编辑品牌
    public function saveBrand()
    {
        $data = request()->post();
        if (empty($data['name']) || empty($data['phone'])) {
            return json(['code' => 100, 'msg' => '品牌名称和品牌手机号不能为空']);
        }
        $brand = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'brand_contacts' => $data['brand_contacts'],
            'ch_contacts' => $data['ch_contacts'],
            'ch_phone' => $data['ch_phone'],
            'logo' => $data['logo'],
            'status' => $data['status'],
            'num' => $data['num'],
        ];
        $model = new BrandModel();
        if (isset($data['id']) && $data['id'] > 0) {
            //编辑
            $model->where('id', $data['id'])->update($brand);
        } else {
            //添加
            $model->save($brand);
        }
        return json(['code' => 200, 'msg' => '保存成功']);
    }

    //删除品牌
    public function delBrand()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $goods = (new MallGoodsModel())->where('brand_id', $id)->find();
        if ($goods) {
            return json(['code' => 100, 'msg' => '请先清除该品牌下的商品']);
        }
        $model = new BrandModel();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }
}
