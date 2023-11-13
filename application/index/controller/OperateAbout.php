<?php


namespace app\index\controller;

use app\index\model\OperateAboutModel;

class OperateAbout extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $where['about.uid'] = $user['id'];
        }
        $model = new OperateAboutModel();
        $count = $model->alias('about')
            ->join('system_admin a', 'about.uid=a.id', 'left')
            ->field('about.*,a.username')
            ->where($where)->count();
        $list = $model->alias('about')
            ->join('system_admin a', 'about.uid=a.id', 'left')
            ->field('about.*,a.username')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }

    public function save()
    {
        $user = $this->user;
        $data = request()->post();
        $model = new OperateAboutModel();
        if (empty($data['id'])) {
            $res = $model->where('uid', $user['id'])->find();
            if ($res) {
                return json(['code' => 100, 'msg' => '每人只能添加一条']);
            }
            $insert_data = [
                'uid' => $user['id'],
                'logo' => $data['logo'],
                'content' => $data['content'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'business' => $data['business'],
            ];
            $model->save($insert_data);
        } else {
            $insert_data = [
                'logo' => $data['logo'],
                'content' => $data['content'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'business' => $data['business'],
            ];
            $model->where('id', $data['id'])->update($insert_data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }
}