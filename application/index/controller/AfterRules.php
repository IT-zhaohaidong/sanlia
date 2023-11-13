<?php

namespace app\index\controller;


use app\index\model\AppRulesModel;

class AfterRules extends BaseController
{
    public function getList()
    {
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $user = $this->user;
        $model = new AppRulesModel();
        $where = [];
        if ($user['role_id'] != 1) {
            $where['app.uid'] = ['=', $user['id']];
        }
        $count = $model->alias('app')
            ->join('system_admin a', 'app.uid=a.id', 'left')
            ->field('app.*,a.username')
            ->where($where)->count();
        $list = $model->alias('app')
            ->join('system_admin a', 'app.uid=a.id', 'left')
            ->field('app.*,a.username')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => request()->get()]);
    }

    public function save()
    {
        $user = $this->user;
        $post = request()->post();
        $model = new AppRulesModel();
        if (empty($post['id'])) {
            $row = $model->where('uid', $user['id'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '只限添加一条']);
            }
            $post['uid'] = $user['id'];
            $model->save($post);
        } else {
            $model->where('id', $post['id'])->update($post);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }
}