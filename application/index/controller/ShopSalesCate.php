<?php

namespace app\index\controller;


use app\index\model\ShopGoodsModel;
use app\index\model\ShopSalesCateModel;

class ShopSalesCate extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 1 : $params['limit'];
        $where = [];
        if (!empty($params['title'])) {
            $where['title'] = ['like', '%' . $params['tile'] . '%'];
        }
        $model = new ShopSalesCateModel();
        $count = $model->where($where)->count();
        $list = $model
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('sort desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function saveCate()
    {
        $params = request()->post();
        if (!$params['title'] || trim($params['title']) == '') {
            return json(['code' => 100, 'msg' => '请填写商品标题']);
        }
        $data = [
            'title' => trim($params['title']),
            'sort' => $params['sort']
        ];
        $model = new ShopSalesCateModel();
        if (!empty($params['id'])) {
            $row = $model->where('id', '<>', $params['id'])->where('title', $data['title'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该分类已存在']);
            }
            $model->where('id', $params['id'])->update($data);
        } else {
            $row = $model->where('title', $data['title'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该分类已存在']);
            }
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
        $goods = (new ShopGoodsModel())->where('sales_id', $id)->find();
        if ($goods) {
            return json(['code' => 100, 'msg' => '该分类下存在商品']);
        }
        $model = new ShopSalesCateModel();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

}
