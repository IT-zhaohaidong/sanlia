<?php


namespace app\index\controller;

use app\index\model\CheckStockLogModel;
use app\index\model\OperateGoodsModel;

class OperateGoods extends BaseController
{
    //运营库商品
    public function getList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 15 : $params['limit'];
        $where = [];
        $user = $this->user;
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['og.uid'] = $user['parent_id'];
            } else {
                $where['og.uid'] = $user['id'];
            }
        }
        if (!empty($params['title'])) {
            $where['g.title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['code'])) {
            $where['g.code'] = ['like', '%' . $params['code'] . '%'];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        $model = new OperateGoodsModel();
        $count = $model->alias('og')
            ->join('mall_goods g', 'g.id=og.goods_id', 'left')
            ->join('brand b', 'b.id=g.brand_id', 'left')
            ->join('system_admin a', 'a.id=og.uid', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('og')
            ->join('mall_goods g', 'g.id=og.goods_id', 'left')
            ->join('brand b', 'b.id=g.brand_id', 'left')
            ->join('system_admin a', 'a.id=og.uid', 'left')
            ->where($where)
            ->field('a.username,og.stock,og.id,og.goods_id,g.type,b.name,g.code,g.commission,g.show_port,g.title,g.image,g.description,g.active_price,g.price,g.remark,og.create_time,og.check,og.check_stock,og.end_time,og.last_check_time')
            ->page($page)
            ->limit($limit)
            ->select();
        foreach ($list as $k => $v) {
            if ($v['check'] == 1 || $v['check'] == 2) {
                $list[$k]['stock'] = '';
            }
            $list[$k]['end_time'] = $v['end_time'] ? date('Y-m-d H:i:s', $v['end_time']) : '';
            $list[$k]['last_check_time'] = $v['last_check_time'] ? date('Y-m-d H:i:s', $v['last_check_time']) : '';
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //盘库
    public function checkStock()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['end_time'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $end_time = strtotime($params['end_time']);
        $model = new OperateGoodsModel();
        $model->where('id', $params['id'])->update(['check' => 1, 'end_time' => $end_time, 'last_check_time' => time()]);
        return json(['code' => 200, 'msg' => '开始盘库']);
    }

    //盘库提交
    public function saveStock()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['check_stock'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OperateGoodsModel();
        $model->where('id', $params['id'])->update(['check' => 2, 'check_stock' => $params['check_stock']]);
        return json(['code' => 200, 'msg' => '盘库已提交']);
    }

    //盘库完成
    public function completeCheck()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OperateGoodsModel();
        $row = $model->where('id', $params['id'])->find();
        $model->where('id', $params['id'])->update(['check' => 0, 'check_stock' => 0, 'stock' => $row['check_stock']]);
        $log = [
            'uid' => $row['uid'],
            'goods_id' => $row['goods_id'],
            'stock' => $row['stock'],
            'check_stock' => $row['check_stock'],
            'check_time' => strtotime($row['last_check_time']),
            'complete_time' => time(),
        ];
        $logModel = new CheckStockLogModel();
        $logModel->save($log);
        return json(['code' => 200, 'msg' => '盘库完成']);
    }
}
