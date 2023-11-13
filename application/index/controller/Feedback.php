<?php

namespace app\index\controller;

use app\index\model\OperateFeedbackModel;

class Feedback extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $model = new OperateFeedbackModel();
        $count = $model->alias('a')
            ->join('operate_user u', 'u.openid=a.openid', 'left')
            ->field('a.*,u.nickname')->count();
        $list = $model->alias('a')
            ->join('operate_user u', 'u.openid=a.openid', 'left')
            ->field('a.*,u.nickname,u.type u_type')
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }
}