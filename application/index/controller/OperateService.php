<?php

namespace app\index\controller;

class OperateService extends BaseController
{
    public function getList()
    {
        $prams = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $model = new \app\index\model\OperateService();
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $where['s.uid'] = $user['id'];
        }
        $count = $model->alias('s')
            ->join('system_admin a', 's.uid=a.id', 'left')
            ->where($where)->count();
        $list = $model->alias('s')
            ->join('system_admin a', 's.uid=a.id', 'left')
            ->where($where)
            ->field('s.*,a.username')
            ->page($page)->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'prams' => $prams]);
    }

    public function save()
    {
        $post = request()->post();
        $user = $this->user;
        $model = new \app\index\model\OperateService();
        if (empty($post['id'])) {
            $post['uid'] = $user['id'];
            $model->save($post);
        } else {
            $model->where('id', $post['id'])->update($post);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function getServiceByUser()
    {
        $model = new \app\index\model\OperateService();
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $where['s.uid'] = $user['id'];
        }
        $data = $model->alias('s')
            ->join('system_admin a', 's.uid=a.id', 'left')
            ->where($where)
            ->field('s.id,s.title')
            ->select();
        return json(['code' => 200, 'data' => $data]);
    }

    public function del()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $row = (new \app\index\model\MachineDevice())->where('sid', $id)->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '不可删除,该客服绑定了设备']);
        }
        $model = new \app\index\model\OperateService();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功!']);
    }
}