<?php

namespace app\index\controller;

use app\index\model\OperateCompanyModel;
use app\index\model\OperateQuestionnaireModel;
use think\Db;

class CompanyInfo extends BaseController
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
        }
        if (!empty($params['username'])) {
            $where['a.username'] = $params['username'];
        }
        if (!empty($params['company'])) {
            $where['c.company'] = $params['company'];
        }
//        if (!empty($params['operName'])) {
//            $where['c.operName'] = $params['operName'];
//        }
//        if (!empty($params['credit_code'])) {
//            $where['c.credit_code'] = $params['credit_code'];
//        }
        $model = new OperateQuestionnaireModel();
        $count = $model->alias('c')
            ->join('system_admin a', 'a.id=c.uid', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('c')
            ->join('system_admin a', 'a.id=c.uid', 'left')
            ->where($where)
            ->field('c.*,a.username')
            ->page($page)
            ->limit($limit)
            ->order('c.id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }


    //获取全部数据 导出
    public function getAll()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['c.uid'] = $user['parent_id'];
            } else {
                $where['c.uid'] = $user['id'];
            }
        }
        $model = new OperateQuestionnaireModel();
        $list = $model->alias('c')
            ->join('system_admin a', 'a.id=c.uid', 'left')
            ->where($where)
            ->field('c.*,a.username')
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }
}