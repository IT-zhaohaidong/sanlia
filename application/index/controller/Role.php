<?php

namespace app\index\controller;

use app\index\model\AppletNodeModel;
use app\index\model\SystemNode;
use app\index\model\SystemRole;
use think\Cache;

class Role extends BaseController
{
    public function roleList()
    {
        $nodeModel = new SystemRole();
        $rows = $nodeModel->getList();
        foreach ($rows as $k => $v) {
            $rows[$k]['applet_check'] = $v['applet_check'] ? json_decode($v['applet_check'], true) : [];
        }
        $rows = (new SystemNode())->tree($rows, 0);
        return json(['code' => 200, 'data' => $rows]);
    }

    public function getOne()
    {
        $id = input('get.id', '');
        $rows = [];
        $nodeModel = new SystemRole();
        if ($id) {
            $rows = $nodeModel->getOne($id)->toArray();
            $res = $nodeModel->where('pid', $rows['id'])->find();
            $rows['is_check'] = $res ? 0 : 1;
            $rows['node_ids'] = explode(',', $rows['node_ids']);
            $rows['check'] = explode(',', $rows['check']);
            $rows['applet_check'] = $rows['applet_check'] ? json_decode($rows['applet_check'], true) : [];
            $rows['medicine_check'] = $rows['medicine_check'] ? explode(',', $rows['medicine_check']) : [];
            $rows['pid'] = explode(',', $rows['pids']);
        }

        $list = $nodeModel->where('delete_time', null)->field('id value,name label,pid')->select();
        $list = $nodeModel->tree($list, 0);
        return json(['code' => 200, 'data' => $rows, 'list' => $list]);
    }

    public function add()
    {
        $post = input('post.', [], 'trim');
        $post['check'] = implode(',', $post['check']);
        $post['pids'] = implode(',', $post['pid']);
        $post['pid'] = $post['pid'][count($post['pid']) - 1];
        if (isset($post['is_check'])) {
            unset($post['is_check']);
            unset($post['applet_check']);
            unset($post['medicine_check']);
            unset($post['medicine_node_ids']);
        }
        $model = new SystemRole();
        if (empty($post['id'])) {
            $model->save($post);
        } else {
            $model->where('id', $post['id'])->update($post);
        }
        $list = Cache::store('redis')->get('agentList');
        if ($list) {
            Cache::store('redis')->rm('agentList');
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function del()
    {
        $id = input('get.id', '');
        $nodeModel = new SystemRole();
        $rows = $nodeModel->where('id', $id)->update(['delete_time' => time()]);
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    public function allRoles()
    {
        $user = $this->user;
        $nodeModel = new SystemRole();
        $where = [];
        if ($user['id'] != 1) {
            $condition = '>=';
            if ($user['role_id'] == 5) {
                $condition = '>';
            }
            $where['id'] = [$condition, $user['role_id']];
        }
        $rows = $nodeModel
            ->where('delete_time', null)
//            ->where(function ($query) use ($where) {
//                if ($where) {
//                    $query->whereOr($where)
//                        ->whereOr('id', 2);
//                }
//            })
            ->field('id value,name label,type disabled,pid')
            ->select();
        $role_id = 0;
        foreach ($rows as $k => $v) {
            if ($v['disabled'] == 0) {
                $rows[$k]['disabled'] = true;
            } else {
                if ($user['id'] != 1 && $user['role_id'] >= $v['value']) {
                    $rows[$k]['disabled'] = true;
                } else {
                    $rows[$k]['disabled'] = false;
                }
            }
            if (!$role_id) {
                $role_id = $v['value'];
            }
        }
        $rows = (new SystemRole())->tree($rows, 0, $role_id);
        return json(['code' => 200, 'data' => $rows]);
    }

    //小程序权限保存
    public function saveApplet()
    {
        $data = request()->post();
        $nodeModel = new SystemRole();
        $node_ids = [];
        foreach ($data['applet_node_ids'] as $k => $v) {
            $node_ids = array_merge($node_ids, $v);
        }
        $applet_node_ids = implode(',', $node_ids);
        $applet_check = $data['applet_node_ids'] ? json_encode($data['applet_node_ids']) : '';
        $nodeModel->where('id', $data['id'])->update(['applet_node_ids' => $applet_node_ids, 'applet_check' => $applet_check]);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //售药机权限保存
    public function saveMedicine()
    {
        $data = request()->post();
        $nodeModel = new SystemRole();
        $applet_node_ids = implode(',', $data['medicine_node_ids']);
        $applet_check = implode(',', $data['medicine_check']);
        $nodeModel->where('id', $data['id'])->update(['medicine_node_ids' => $applet_node_ids, 'medicine_check' => $applet_check]);
        return json(['code' => 200, 'msg' => '成功']);
    }


    public function nodeList()
    {
        //type 1:售卖机 2:售药机
//        $type = request()->get('type', 0);
        $nodeModel = new SystemNode();
        $rows = $nodeModel->getList(0);
        $list = $nodeModel->tree($rows);
        return json(['code' => 200, 'data' => $list]);
    }
}
