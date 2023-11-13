<?php

namespace app\index\controller;


use app\index\model\ShopVipOrderModel;

class ShopVipLog extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 10);
        $model = new ShopVipOrderModel();
        $count = $model->alias('o')
            ->join('operate_user u', 'o.openid=u.openid', 'left')
            ->where('o.status', 1)
            ->count();
        $list = $model->alias('o')
            ->join('operate_user u', 'o.openid=u.openid', 'left')
            ->where('o.status', 1)
            ->field('o.*,u.phone')
            ->order('id desc')
            ->page($page)
            ->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }
}
