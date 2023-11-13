<?php

namespace app\index\controller;


use app\index\model\ShopBannerModel;

class ShopBanner extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 1 : $params['limit'];
        $where = [];
//        if (!empty($params['title'])) {
//            $where['title'] = ['like', '%' . $params['tile'] . '%'];
//        }
        $model = new ShopBannerModel();
        $count = $model->where($where)->count();
        $list = $model
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('sort desc')
            ->order('status desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function saveCate()
    {
        $params = request()->post();
        $data = [
            'status' => $params['status'],
            'image' => $params['image'],
            'sort' => $params['sort']
        ];
        $model = new ShopBannerModel();
        if (!empty($params['id'])) {
            $model->where('id', $params['id'])->update($data);
        } else {
            $model->save($data);
        }
        return json(['code' => 200, 'msg' => '保存成功']);
    }

    public function del()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
//        $goods = (new ShopGoodsModel())->where('id', $id)->find();
//        if ($goods) {
//            return json(['code' => 100, 'msg' => '该分类下存在商品']);
//        }
        $model = new ShopBannerModel();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

}
