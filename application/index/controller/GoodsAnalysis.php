<?php

namespace app\index\controller;

use app\index\model\MallGoodsModel;
use app\index\model\SystemGoodsModel;
use think\Db;
use think\db\Expression;

class GoodsAnalysis extends BaseController
{
    public function goodsList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 6);
        $where = [];
        $user = $this->user;
        if ($user['role_id'] == 1) {
            if (!empty($params['title'])) {
                $where['system.title'] = ['like', '%' . $params['title'] . '%'];
            }
            if (!empty($params['cate_ids'])) {
                $str = ',' . $params['cate_ids'][count($params['cate_ids']) - 1] . ',';
                $where['system.cate_ids'] = ['like', '%' . $str . '%'];
            }
            $systemModel = new SystemGoodsModel();
            $system_ids = $systemModel->alias('system')
                ->join('mall_goods mall', 'system.id=mall.goods_id')
                ->join('order_goods order', 'mall.id=order.goods_id')
                ->where($where)
                ->where('mall.goods_id', '>', 0)
                ->group('mall.goods_id')
                ->field('system.id,system.image,system.title,sum(order.total_price) total_price,sum(order.count) total_count')
                ->order('total_count asc')
                ->select();
            $item = [];
            foreach ($system_ids as $k => $v) {
                $item[$v['id']] = $v;
            }
            $system_ids = $item;
            $system_id = implode(',', array_keys($system_ids));
            if ($system_ids) {
                $exp = new Expression("field(id,$system_id) desc");
            } else {
                $exp = '';
            }

            $count = $systemModel->alias('system')
                ->where($where)
                ->field('id,image,title')->count();
            $list = $systemModel->alias('system')
                ->where($where)
                ->field('id,image,title,create_time,cate_ids')
                ->order($exp)
                ->order('create_time desc')
                ->page($page)
                ->limit($limit)
                ->select();
            foreach ($list as $k => $v) {
                if (in_array($v['id'], array_keys($system_ids))) {
                    $list[$k]['total_price'] = $system_ids[$v['id']]['total_price'];
                    $list[$k]['total_count'] = $system_ids[$v['id']]['total_count'];
                } else {
                    $list[$k]['total_price'] = '0.00';
                    $list[$k]['total_count'] = 0;
                }
                $list[$k]['cate'] = $this->getCate($v['cate_ids']);
                $list[$k]['device_count'] = $this->getDeviceNum($v['id'], 1);
                $list[$k]['area'] = $this->getArea($v['id'], 1);
            }
        } else {
            if (!empty($params['title'])) {
                $where['mall.title'] = ['like', '%' . $params['title'] . '%'];
            }
            if (!empty($params['cate_ids'])) {
                $str = ',' . $params['cate_ids'][count($params['cate_ids']) - 1] . ',';
                $where['mall.cate_ids'] = ['like', '%' . $str . '%'];
            }
            $mallGoodsModel = new MallGoodsModel();
            $count = $mallGoodsModel->alias('mall')
                ->join('order_goods order', 'order.goods_id=mall.id', 'left')
                ->where($where)
                ->where('mall.uid', $user['id'])
                ->group('order.goods_id')
                ->field('mall.id,mall.title,mall.image,sum(order.total_price) total_price,sum(order.count) total_count')
                ->count();
            $list = $mallGoodsModel->alias('mall')
                ->join('order_goods order', 'order.goods_id=mall.id', 'left')
                ->where($where)
                ->where('mall.uid', $user['id'])
                ->group('order.goods_id')
                ->field('mall.id,mall.title,mall.cate_ids,mall.image,sum(order.total_price) total_price,sum(order.count) total_count')
                ->order('total_count desc')
                ->order('mall.create_time desc')
                ->page($page)
                ->limit($limit)
                ->select();
            foreach ($list as $k => $v) {
                $list[$k]['cate'] = $this->getCate($v['cate_ids']);
                $list[$k]['device_count'] = $this->getDeviceNum($v['id'], 2);
                $list[$k]['area'] = $this->getArea($v['id'], 2);
            }
        }
        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }

    public function getCate($cate_ids)
    {
        $cate_ids = substr($cate_ids, 1, -1);
        $mallCateModel = new \app\index\model\MallCate();
        $exp=new Expression("field(id,$cate_ids) asc");
        $name = $mallCateModel->whereIn('id', $cate_ids)->order($exp)->column('name');
        $name = implode('/', $name);
        return $name;
    }

    private function getDeviceNum($id, $type)
    {
        if ($type == 1) {
            $where['mall.goods_id'] = ['=', $id];
        } else {
            $where['mall.id'] = ['=', $id];
        }
        $mallGoodsModel = new MallGoodsModel();
        $count = $mallGoodsModel->alias('mall')
            ->join('machine_goods goods', 'mall.id=goods.goods_id')
            ->where($where)
            ->group('goods.device_sn')
            ->count();
        return $count;
    }

    private function getArea($id, $type)
    {
        if ($type == 1) {
            $where['mall.goods_id'] = ['=', $id];
        } else {
            $where['mall.id'] = ['=', $id];
        }
        $mallGoodsModel = new MallGoodsModel();
        $device_sn = $mallGoodsModel->alias('mall')
            ->join('machine_goods goods', 'mall.id=goods.goods_id')
            ->where($where)
            ->group('goods.device_sn')
            ->column('goods.device_sn');
        $area = Db::name('machine_device')->alias('d')
            ->join('machine_position p', 'p.id=d.position_id')
            ->whereIn('d.device_sn', $device_sn)
            ->column('p.name');
        $area = implode(',', $area);
        return $area;
    }
}