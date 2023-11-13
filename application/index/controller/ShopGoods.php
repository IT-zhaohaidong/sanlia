<?php

namespace app\index\controller;


use app\index\model\ShopGoodsCateModel;
use app\index\model\ShopGoodsModel;
use app\index\model\ShopSalesCateModel;

class ShopGoods extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 1 : $params['limit'];
        $where = [];
        if (!empty($params['title'])) {
            $where['g.title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['goods_cate'])) {
            $where['c.title'] = ['like', '%' . $params['goods_cate'] . '%'];
        }
        if (!empty($params['sales_cate'])) {
            $where['sc.title'] = ['like', '%' . $params['sales_cate'] . '%'];
        }
        $model = new ShopGoodsModel();
        $count = $model->alias('g')
            ->join('shop_goods_cate c', 'g.cate_id=c.id', 'left')
            ->join('shop_sales_cate sc', 'g.sales_id=sc.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('g')
            ->join('shop_goods_cate c', 'g.cate_id=c.id', 'left')
            ->join('shop_sales_cate sc', 'g.sales_id=sc.id', 'left')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('g.id desc')
            ->field('g.*,c.title goods_cate,sc.title sales_cate')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['detail_image'] = $v['detail_image'] ? explode(',', $v['detail_image']) : [];
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function saveGoods()
    {
        $params = request()->post();
        if (!$params['title'] || trim($params['title']) == '') {
            return json(['code' => 100, 'msg' => '请填写商品标题']);
        }
        $data = [
            'title' => trim($params['title']),
            'image' => $params['image'],
            'cate_id' => $params['cate_id'],
            'sales_id' => $params['sales_id'],
            'price' => $params['price'],
            'cost_price' => $params['cost_price'],
            'old_price' => $params['old_price'],
            'vip_price' => $params['vip_price'],
            'commission' => $params['commission'],
            'detail_image' => $params['detail_image'] ? implode(',', $params['detail_image']) : '',
            'description' => $params['description'],
            'stock' => $params['stock'],
            'sale_num' => $params['sale_num'],
            'putaway' => $params['putaway']
        ];
        $model = new ShopGoodsModel();
        if (!empty($params['id'])) {
            $row = $model->where('id', '<>', $params['id'])->where('title', $data['title'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该商品已存在']);
            }
            $model->where('id', $params['id'])->update($data);
        } else {
            $row = $model->where('title', $data['title'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该商品已存在']);
            }
            $model->save($data);
        }
        return json(['code' => 200, 'msg' => '保存成功']);
    }

    //添加/编辑  获取分类/销售分类 列表
    public function getCate()
    {
        $cateModel = new ShopGoodsCateModel;
        $salesModel = new ShopSalesCateModel();
        $cateList = $cateModel
            ->order('sort desc')
            ->field('id,title')
            ->select();
        $salesList = $salesModel
            ->order('sort desc')
            ->field('id,title')
            ->select();
        $data = compact('cateList', 'salesList');
        return json(['code' => 200, 'data' => $data]);
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
        $model = new ShopGoodsModel();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

}
