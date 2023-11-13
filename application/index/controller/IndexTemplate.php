<?php

namespace app\index\controller;

use app\index\model\SystemAdmin;
use think\Env;

class IndexTemplate extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $where = [];
        if (!empty($params['title'])) {
            $where['i.title'] = ['like', '%' . $params['title'] . '%'];
        }
        $user = $this->user;
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $user['id'] = $user['parent_id'];
            }
            $where['i.uid'] = $user['id'];
        } else {
            if ($params['uid']) {
                $where['i.uid'] = $params['uid'];
            }
        }
        $model = new \app\index\model\IndexTemplate();
        $count = $model->alias('i')
            ->join('system_admin a', 'a.id=i.uid')
            ->where($where)
            ->where('i.delete_time', null)->count();
        $list = $model->alias('i')
            ->join('system_admin a', 'a.id=i.uid')
            ->where($where)
            ->where('i.delete_time', null)
            ->page($page)
            ->limit($limit)
            ->field('i.*,a.username')
            ->select();
        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }

    public function save()
    {
        $data = request()->post();
        $user = $this->user;
        $model = new \app\index\model\IndexTemplate();
        if (empty($data['id'])) {
            $row = $model->where('title', $data['title'])->where('uid', $user['id'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该模板已存在']);
            }
            $data['uid'] = $user['id'];
            $model->save($data);
        } else {
            $row = $model->where('delete_time', null)->where('uid', $user['id'])->where('id', '<>', $data['id'])->where('title', $data['title'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该模板已存在']);
            }
            $model->where('id', $data['id'])->update($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function del()
    {
        $data = request()->get();
        $model = new \app\index\model\IndexTemplate();
        if (empty($data['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        } else {
            $model->where('id', $data['id'])->delete();
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function bindDevice()
    {
        $id = request()->post('id', '');
        $check = request()->post('check/a');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new \app\index\model\MachineDevice();
        $model->where('index_id', $id)->whereNotIn('id', $check)->update(['index_id' => '']);
        $model->whereIn('id', $check)->update(['index_id' => $id]);
        return json(['code' => 200, 'msg' => '绑定成功']);
    }

}