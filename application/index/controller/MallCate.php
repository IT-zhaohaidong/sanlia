<?php

namespace app\index\controller;

use app\index\model\MallGoodsModel;
use app\index\model\SystemNode;
use app\index\model\SystemRole;
use think\Cache;
use think\Db;

class MallCate extends BaseController
{
    public function getList()
    {
//        $list = Cache::store('redis')->get('cateList');
//        if ($list) {
//            return json(['code' => 200, 'data' => $list]);
//        }
        $user = $this->user;
        $cateModel = new \app\index\model\MallCate();
        $rows = $cateModel->alias('c')
            ->join('system_admin a', 'a.id=c.uid', 'left')
            ->where('c.delete_time', null)
            ->where('c.status', '<', 3)
            ->field('c.*,a.username')
            ->select();
        $rows = (new SystemNode())->tree($rows, 0);
        if ($user['role_id'] != 1) {
            $cate_ids = explode(',', $user['cate_ids']);
            $list = [];
            foreach ($rows as $k => $v) {
                if (in_array($v['id'], $cate_ids)) {
                    $list[] = $v;
                }
            }
        } else {
            $list = $rows;
        }
//        Cache::store('redis')->set('cateList', $list);
        return json(['code' => 200, 'data' => $list]);
    }


    public function getCate()
    {
        $id = request()->get('id', '');
        $user = $this->user;
        $cateModel = new \app\index\model\MallCate();
        $rows = [];
        if ($id) {
            $rows = $cateModel->where('id', $id)->find();
            $rows['pids'] = explode(',', $rows['pids']);
        }
        $lists = $cateModel->where('delete_time', null)
            ->where('status', 1)
            ->order('pid asc')
            ->field('id value,name label,pid')
            ->select();
        $lists = $cateModel->tree($lists, 0, $id);
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $p_user = Db::name('system_admin')->where('id', $user['pid'])->find();
                $user['cate_ids'] = $p_user['cate_ids'];
            }
            $cate_ids = explode(',', $user['cate_ids']);
            $list = [];
            foreach ($lists as $k => $v) {
                if (in_array($v['value'], $cate_ids)) {
                    $list[] = $v;
                }
            }
        } else {
            $list = $lists;
        }
        return json(['code' => 200, 'data' => $rows, 'list' => $list]);
    }

    public function getOne()
    {
        $id = request()->get('id', '');
        $user = $this->user;
        $cateModel = new \app\index\model\MallCate();
        $rows = [];
        if ($id) {
            $rows = $cateModel->where('id', $id)->find();
            $rows['pids'] = explode(',', $rows['pids']);
        }
        $lists = $cateModel->where('delete_time', null)
            ->where('status', 1)
            ->where('pid', 0)
            ->order('pid asc')
            ->field('id value,name label,pid')
            ->select();
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $p_user = Db::name('system_admin')->where('id', $user['pid'])->find();
                $user['cate_ids'] = $p_user['cate_ids'];
            }
            $cate_ids = explode(',', $user['cate_ids']);
            $list = [];
            foreach ($lists as $k => $v) {
                if (in_array($v['value'], $cate_ids)) {
                    $list[] = $v;
                }
            }
        } else {
            $data[] = ['value' => 0, 'label' => '顶级分类'];
            $list = array_merge($data, $lists);
        }
        return json(['code' => 200, 'data' => $rows, 'list' => $list]);
    }

    public function add()
    {
        $post = input('post.', [], 'trim');
        $user = $this->user;
        $post['pid'] = $post['pids'][count($post['pids']) - 1];
        $post['pids'] = implode(',', $post['pids']);
        $model = new \app\index\model\MallCate();
        if (empty($post['id'])) {
            $post['uid'] = $user['id'];
            $res = $model->where(['pid' => $post['pid'], 'name' => $post['name']])->find();
            if ($res) {
                return json(['code' => 100, 'msg' => '该分类已存在']);
            }
            $status = in_array($user['role_id'],[1,3]) ? 1 : 2;
            $post['status'] = $status;
            $model->save($post);
        } else {
            $res = $model->where(['pid' => $post['pid'], 'name' => $post['name']])->where('id', '<>', $post['id'])->find();
            if ($res) {
                return json(['code' => 100, 'msg' => '该分类已存在']);
            }
            $model->where('id', $post['id'])->update($post);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function del()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $res = (new MallGoodsModel())->where('delete_time', null)->where('cate_ids', 'like', '%,' . $id . ',%')->find();
        if ($res) {
            return json(['code' => 100, 'msg' => '该分类下存在商品']);
        }
        $model = new \app\index\model\MallCate();
        $res = $model->where('delete_time', null)->where('pid', $id)->find();
        if ($res) {
            return json(['code' => 100, 'msg' => '请先删除子分类']);
        }
        $model->where('id', $id)->update(['delete_time' => time()]);
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    public function check()
    {
        $post = request()->post();
        $data = [
            'id' => $post['id'],
            'status' => $post['status']
        ];
        $cateModel = new \app\index\model\MallCate();
        $cateModel->where('id', $data['id'])->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

}
