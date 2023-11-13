<?php

namespace app\index\controller;


use app\index\model\NoticeReadModel;
use app\index\model\SystemAdmin;
use app\index\model\SystemNoticeModel;
use app\index\model\SystemRole;

class SystemNotice extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $limit = request()->get('limit', 15);
        $page = request()->get('page', 1);
        $where = [];
        if (!empty($params['title'])) {
            $where['n.title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        $model = new SystemNoticeModel();
        $count = $model->alias('n')
            ->join('system_admin a', 'n.uid=a.id', 'left')
            ->where('n.delete_time', null)
            ->where($where)->count();
        $list = $model->alias('n')
            ->join('system_admin a', 'n.uid=a.id', 'left')
            ->field('n.*,a.username')
            ->where('n.delete_time', null)
            ->where($where)
            ->order('n.id desc')
            ->page($page)
            ->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function addNotice()
    {
        $post = request()->post();
        $user = $this->user;
        $post['uid'] = $user['id'];
        if ($post['to_type'] == 0) {
            $post['to_user'] = '';
        } else {
//            $post['to_user'] = ',' . implode(',', array_unique($post['to_user'])) . ',';
            if (!empty($post['to_user'])) {
                $item = [];
                foreach ($post['to_user'] as $k => $v) {
                    $item[] = $v[count($v) - 1];
                }
                $item_unique = array_unique($item);
                $post['to_user'] = ',' . implode(',', $item_unique) . ',';
            }
        }
        $model = new SystemNoticeModel();
        $model->save($post);
        return json(['code' => 200, 'msg' => '添加成功']);
    }

    public function checkAll()
    {
        $adminModel = new SystemAdmin();
        $list = $adminModel->where('delete_time', null)
            ->order('parent_id asc')
            ->select();
        $list = (new SystemAdmin())->tree($list, 0, 0);
        $data = $this->test($list);
        return json(['code' => 200, 'data' => $data]);
    }


    private function test($list, $p = [])
    {
        $item = [];
        foreach ($list as $k => $v) {
            $p1 = $p;
            $p[] = $v['id'];
            $item[] = $p;
            if (!empty($v['children'])) {
                $item = array_merge($item, $this->test($v['children'], $p));
            }
            $p = $p1;
        }
        return $item;
    }

    public function getCountByUser()
    {
        $user = $this->user;
        $readModel = new NoticeReadModel();
        $read = $readModel->where('uid', $user['id'])->column('notice_id');
        $noticeModel = new SystemNoticeModel();
        $count = $noticeModel
            ->where(function ($query) use ($user) {
                $query->where(['to_user' => ['like', '%,' . $user['id'] . ',%'], 'to_type' => 1])
                    ->whereOr('to_type', 0);
            })
            ->whereNotIn('id', $read)
            ->count();
        return json(['code' => 200, 'data' => ['count' => $count]]);
    }

    public function getListByUser()
    {
        $params = request()->get();
        $limit = request()->get('limit', 10);
        $page = request()->get('page', 1);
        $user = $this->user;
        $readModel = new NoticeReadModel();
        $read = $readModel->where('uid', $user['id'])->column('notice_id');
        $read_id = $read ? array_values($read) : [];
        $del = $readModel->where(['uid' => $user['id'], 'del' => 1])->column('notice_id');
        $noticeModel = new SystemNoticeModel();
        $count = $noticeModel
            ->where(function ($query) use ($user) {
                $query->where(['to_user' => ['like', '%,' . $user['id'] . ',%'], 'to_type' => 1])
                    ->whereOr('to_type', 0);
            })
            ->whereNotIn('id', $del)
            ->count();
        $list = $noticeModel
            ->where(function ($query) use ($user) {
                $query->where(['to_user' => ['like', '%,' . $user['id'] . ',%'], 'to_type' => 1])
                    ->whereOr('to_type', 0);
            })
            ->whereNotIn('id', $del)
            ->limit($limit)
            ->page($page)
            ->order('id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['status'] = in_array($v['id'], $read_id) ? 1 : 0;
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function readOne()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = $this->user;
        $model = new NoticeReadModel();
        $data = ['uid' => $user['id'], 'notice_id' => $id];
        $row = $model->where($data)->find();
        if (!$row) {
            $model->save($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function delOne()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = $this->user;
        $model = new NoticeReadModel();
        $data = ['uid' => $user['id'], 'notice_id' => $id];
        $row = $model->where($data)->find();
        if (!$row) {
            $data['del'] = 1;
            $model->save($data);
        } else {
            $model->where($data)->update(['del' => 1]);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function readAll()
    {
        $user = $this->user;
        $readModel = new NoticeReadModel();
        $read = $readModel->where('uid', $user['id'])->column('notice_id');
        $noticeModel = new SystemNoticeModel();
        $notice = $noticeModel
            ->where(function ($query) use ($user) {
                $query->where(['to_user' => ['like', '%,' . $user['id'] . ',%'], 'to_type' => 1])
                    ->whereOr('to_type', 0);
            })
            ->whereNotIn('id', $read)
            ->column('id');
        $arr = [];
        foreach ($notice as $k => $v) {
            $arr[] = ['uid' => $user['id'], 'notice_id' => $v];
        }
        $readModel->saveAll($arr);
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function delAll()
    {
        $user = $this->user;
        $readModel = new NoticeReadModel();
        $readModel
            ->where('uid', $user['id'])
            ->where('del', 0)
            ->update(['del' => 1]);
        return json(['code' => 200, 'msg' => '成功']);
    }
}