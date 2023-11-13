<?php


namespace app\index\controller;

use app\index\model\OperateUserModel;

class OperateUser extends BaseController
{
    public function getList()
    {
        $user = $this->user;
        $params = request()->get();
        $limit = request()->get('limit', 15);
        $page = request()->get('page', 1);
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['uid'] = $user['parent_id'];
            } else {
                $where['uid'] = $user['id'];
            }
        }
        if (!empty($params['nickname'])) {
            $where['nickname'] = ['like', '%' . $params['nickname'] . '%'];
        }
        if (!empty($params['openid'])) {
            $where['openid'] = ['like', '%' . $params['openid'] . '%'];
        }
        if (!empty($params['phone'])) {
            $where['phone'] = ['like', '%' . $params['phone'] . '%'];
        }
        if (!empty($params['type'])) {
            $where['type'] = $params['type'];
        }
        $model = new OperateUserModel();
        $count = $model
            ->where($where)->count();
        $list = $model->alias('o')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }
}
