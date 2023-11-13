<?php

namespace app\index\controller;

use app\index\model\GoodsTemplateGoodsModel;
use app\index\model\GoodsTemplateModel;
use think\Db;

class GoodsTemplate extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['c.uid'] = $user['parent_id'];
            } else {
                $where['c.uid'] = ['=', $user['id']];
            }
        } else {
            if (!empty($params['uid'])) {
                $where['c.uid'] = $params['uid'];
            }
        }
        if (!empty($params['name'])) {
            $where['c.name'] = ['like', '%' . $params['name'] . '%'];
        }
        $model = new GoodsTemplateModel();
        $count = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->field('c.*,a.username')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //保存模板
    public function saveTemplate()
    {
        $data = request()->post();
        if (empty($data['name'])) {
            return json(['code' => 100, 'msg' => '模板名称不能为空']);
        }
        $user = $this->user;
        $uid = $user['id'];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $uid = $user['parent_id'];
            }
        }
        $model = new GoodsTemplateModel();
        if (!empty($data['id'])) {
            //编辑
            $row = $model
                ->where('uid', $uid)
                ->where('name', $data['name'])
                ->where('id', '<>', $data['id'])
                ->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '模板名称已存在']);
            }
            $model->where('id', $data['id'])->update(['name' => $data['name'], 'num' => $data['num'], 'remark' => $data['remark']]);
            $goodsModel = new GoodsTemplateGoodsModel();
            $goodsModel->where('template_id', $data['id'])->where('num', '>', $data['num'])->delete();
        } else {
            //添加
            $row = $model
                ->where('uid', $uid)
                ->where('name', $data['name'])
                ->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '模板名称已存在']);
            }
            $save = [
                'uid' => $uid,
                'name' => $data['name'],
                'num' => $data['num'],
                'remark' => $data['remark'],
            ];
            $model->save($save);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    //模板商品列表
    public function getGoodsList()
    {
        $id = request()->get('id');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $template = (new GoodsTemplateModel())->where('id', $id)->find();
        $model = new GoodsTemplateGoodsModel();
        $list = $model->alias('d')
            ->join('mall_goods g', 'd.goods_id=g.id', 'left')
            ->where('d.template_id', $id)
            ->field('d.*,g.image')
            ->order('d.num asc')
            ->limit($template['num'])
            ->select();
        $count = count($list);
        if ($count < $template['num']) {
            for ($i = 1; $i <= $template['num'] - $count; $i++) {
                $list[] = [
                    'num' => $count + $i,
                    'template_id' => $id,
                    'goods_id' => '',
                    'image' => '',
                    'volume' => '',
                    'stock' => '',
                    'active_price' => 0.00,
                    'price' => 0.00,
                    'port' => 0,
                    'lock' => 0
                ];
            }
        }
        return json(['code' => 200, 'data' => $list]);
    }

    //保存货道信息
    public function save()
    {
        $data = request()->post('data/a');
        $template_id = request()->post('template_id');
        if (!$template_id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new GoodsTemplateGoodsModel();
        $ids = [];
        foreach ($data as $k => $v) {
            if (!empty($v['id'])) {
                $ids[] = $v['id'];
            }
            unset($data[$k]['image']);
        }
        if ($ids) {
            $model->whereNotIn('id', $ids)->where('template_id', $template_id)->delete();
        }
        foreach ($data as $k => $v) {
            unset($v['create_time']);
            $v['update_time'] = time();
            if (!empty($v['id'])) {
                $model->where('id', $v['id'])->update($v);
            } else {
                $v['create_time'] = time();
                $model->insert($v);
            }
        }
        return json(['code' => 200, 'msg' => '成功']);
    }
}
