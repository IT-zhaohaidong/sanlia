<?php

namespace app\index\controller;

class Cash extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $user['id'] = $user['parent_id'];
            }
            $where['c.uid'] = $user['id'];
        } else {
            if (!empty($params['uid'])) {
                $where['c.uid'] = $params['uid'];
            }
        }
        if (!empty($params['order_sn'])) {
            $where['c.order_sn'] = ['like', '%' . $params['order_sn'] . '%'];
        }
        if (!empty($params['start_time'])) {
            $where['c.create_time'] = ['>=', strtotime($params['start_time'])];
        }
        if (!empty($params['end_time'])) {
            $where['c.create_time'] = ['<', strtotime($params['start_time']) + 3600 * 24];
        }
        $model = new \app\index\model\FinanceCash();
        $total_cash = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)->sum('c.price');
        $count = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->order('c.id desc')
            ->page($page)
            ->limit($limit)
            ->field('c.*,a.username')
            ->select();

        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count, 'total_cash' => $total_cash]);
    }
}
