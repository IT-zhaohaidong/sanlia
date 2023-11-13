<?php

namespace app\index\controller;

use app\index\model\SystemAdmin;
use app\index\model\SystemNode;
use app\index\model\SystemRole;
use think\Cache;
use think\Db;

class Node extends BaseController
{
    public function nodeList()
    {
        $nodeModel = new SystemNode();
        $rows = $nodeModel->getList();
        foreach ($rows as $k => $v) {
            $device_type = $v['device_type'] ? explode(',', substr($v['device_type'], 1, -1)) : [];
            foreach ($device_type as $x => $y) {
                $device_type[$x] = (int)$y;
            }
            $rows[$k]['device_type'] = $device_type;
        }
        $list = $nodeModel->tree($rows);
        return json(['code' => 200, 'data' => $list]);
    }

    public function add()
    {
        Cache::store('redis')->rm('initMenus_admin');
        $post = request()->post();
        $post['device_type'] = $post['device_type'] ? ',' . implode(',', $post['device_type']) . ',' : '';
        Db::name('system_node')->insert($post);
        return json(['code' => 200, 'msg' => '添加成功']);
    }

    public function edit()
    {
        $data = request()->post('data/a');
        if (empty($data['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if (isset($data['children'])) {
            unset($data['children']);
        }
        $model = new SystemNode();
        $data['device_type'] = $data['device_type'] ? ',' . implode(',', $data['device_type']) . ',' : '';
        unset($data['create_time']);
        $data['update_time'] = time();
        Db::name('system_node')->where('id', $data['id'])->update($data);
        $list = $model->order('pid asc')->select();
        $ids = $model->getSonsId($list, $data['id']);

        Db::name('system_node')->whereIn('id', $ids)->update(['device_type' => $data['device_type']]);
        return json(['code' => 200, 'msg' => '编辑成功']);
    }

    public function changeStatus()
    {
        $id = request()->get('id', '');
        $is_use = request()->get('is_use', '');
        if (!$id || $is_use === '') {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        Db::name('system_node')->where('id', $id)->update(['is_use' => $is_use]);
        return json(['code' => 200, 'msg' => '修改成功']);
    }

    public function changeDeviceType()
    {
        $id = request()->post('id', '');
        $device_type = request()->post('device_type/a', []);
        $model = new SystemNode();
        $data['device_type'] = $device_type ? ',' . implode(',', $device_type) . ',' : '';
        Db::name('system_node')->where('id', $id)->update($data);
        $list = $model->order('pid asc')->select();
        $ids = $model->getSonsId($list, $id);
        Db::name('system_node')->whereIn('id', $ids)->update($data);
        return json(['code' => 200, 'msg' => '编辑成功']);
    }

    public function getinitMenus()
    {
        $params = request()->get();
        $time = time();
        $nodeModel = new SystemNode();
        $user = $this->user;

        $where['device_type'] = ['like', '%,' . $user['login_status'] . ',%'];
        if ($user['id'] == 1) {
            $rows = $nodeModel
                ->where('delete_time', null)
//                ->where($where)
                ->where('is_use', 1)
                ->order('pid asc')
                ->order('sort desc')->select();
//            if (isset($params['is_english']) && $params['is_english'] == 1) {
//                foreach ($rows as $k => $v) {
////                    $rows[$k]['title'] = $v['english_title'];
//                    $rows[$k]['meta'] = str_replace($v['title'], $v['english_title'], $v['meta']);
//                }
//            }
            $list = $nodeModel->tree($rows);
        } else {
            $roleModel = new SystemRole();
            $ids = $roleModel->where('id', $user['role_id'])->field('node_ids,medicine_node_ids')->select();
//            if ($user['login_status'] == 1) {
            $deal_ids = [];
            foreach ($ids as $k => $v) {
                $deal_ids = array_merge($deal_ids, explode(',', $v['node_ids']));
            }
            $deal_ids = array_unique($deal_ids);
//            } else {
//                $deal_ids = [];
//                foreach ($ids as $k => $v) {
//                    $deal_ids = array_merge($deal_ids, explode(',', $v['medicine_node_ids']));
//                }
//                $deal_ids = array_unique($deal_ids);
//            }
            $rows = $nodeModel->whereIn('id', $deal_ids)->where('is_use', 1)->order('pid asc')->order('sort desc')->select();
//            if (isset($params['is_english']) && $params['is_english'] == 1) {
//                foreach ($rows as $k => $v) {
////                    $rows[$k]['title'] = $v['english_title'];
//                    $rows[$k]['meta'] = str_replace($v['title'], $v['english_title'], $v['meta']);
//                }
//            }
            $list = $nodeModel->tree($rows);
        }
        $end_time = time();
        return json(['permissionList' => $list, 'message' => '成功', 'success' => true, 'time' => $time, 'end_time' => $end_time]);
    }

    public function saveMenus()
    {
        $post = input('post.', [], 'trim');
        $nodeModel = new SystemNode();
        $nodeModel->delMenus($post);
        $nodeModel->saveMenus($post);
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function delNode()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $nodeModel = new SystemNode();
        $row = $nodeModel->where('pid', $id)->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '不可删除']);
        }
        $nodeModel->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

}
