<?php

namespace app\index\controller;

use app\index\model\MachineCardLogModel;
use app\index\model\MachineCardModel;
use app\index\model\MallGoodsModel;
use think\Db;

class MachineCard extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 10);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['c.uid'] = $user['parent_id'];
            } else {
                $where['c.uid'] = ['=', $user['id']];
            }
        } else {
            if (!empty($params['uid'])) {
                $where['c.uid'] = $params['uid'];
            }
        }

        if (!empty($params['idcard'])) {
            $where['c.idcard'] = ['like', '%' . $params['idcard'] . '%'];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (!empty($params['name_card'])) {
            $where['c.username'] = ['like', '%' . $params['name_card'] . '%'];
        }

        if ($params['status'] !== '') {
            $where['c.status'] = ['=', $params['status']];
        }

        $model = new MachineCardModel();
        $count = $model
            ->alias('c')
            ->join('system_admin a', 'a.id=c.uid', 'left')
            ->join('mall_goods g', 'g.id=c.goods_id', 'left')
            ->where($where)->count();
        $list = $model
            ->alias('c')
            ->join('system_admin a', 'a.id=c.uid', 'left')
            ->join('mall_goods g', 'g.id=c.goods_id', 'left')
            ->where($where)
            ->field('c.*,a.username admin_name,g.title')
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //开卡
    public function addCard()
    {
        $data = request()->post();
        if (empty($data['id']) && $data['money'] <= 0) {
            return json(['code' => 100, 'msg' => '充值金额必须大于0']);
        }
        $user = $this->user;
        $uid = $user['role_id'] > 5 ? $user['parent_id'] : $user['id'];
        $model = new MachineCardModel();

        $insert_arr = [
            'username' => $data['username'],
            'uid' => $uid,
            'goods_id' => $data['goods_id'],
            'idcard' => $data['idcard'],
            'num' => $data['num'],
            'phone' => $data['phone'],
            'address' => $data['address'],
        ];
        if (empty($data['id'])) {
            $row = $model->where('idcard', $data['idcard'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该卡号已存在']);
            }
            $model->save($insert_arr);
            $log = [
                'money' => $data['money'],
                'idcard' => $data['idcard'],
                'uid' => $uid['uid'],
                'num' => $data['num'],
            ];
            (new MachineCardLogModel())->save($log);
        } else {
            unset($insert_arr['uid']);
            unset($insert_arr['num']);
            $row = $model->where('id', '<>', $data['id'])->where('idcard', $data['idcard'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该卡号已存在']);
            }
            $model->where('id', $data['id'])->update($insert_arr);
        }
        return json(['code' => 200, 'msg' => '保存成功']);
    }

    public function goodsList()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['uid'] = $user['parent_id'];
            } else {
                $where['uid'] = ['=', $user['id']];
            }
        }
        $goods = (new MallGoodsModel())
            ->where($where)
            ->field('id,title,image')
            ->select();
        return json(['code' => 200, 'data' => $goods]);
    }

    //挂失
    public function reportLoss()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineCardModel();
        $model->where('id', $id)->update(['status' => 1]);
        return json(['code' => 200, 'msg' => '挂失成功']);
    }

    //解挂
    public function unloss()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineCardModel();
        $model->where('id', $id)->update(['status' => 0]);
        return json(['code' => 200, 'msg' => '解除挂失成功']);
    }

    //补卡
    public function repairCard()
    {
        $params = request()->post();
        if (empty($params['id']) || empty($params['idcard'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineCardModel();
        $card = $model->where('id', $params['id'])->find();
        if (!$card) {
            return json(['code' => 100, 'msg' => '原卡不存在']);
        }
        $row = $model->where('idcard', $params['idcard'])->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该卡号已存在']);
        }
        $data = [
            'username' => $card['username'],
            'uid' => $card['uid'],
            'goods_id' => $card['goods_id'],
            'idcard' => $params['idcard'],
            'num' => $card['num'],
            'phone' => $card['phone'],
            'address' => $card['address'],
        ];
        $model->save($data);
        $model->where('id', $params['id'])->update(['status' => 2]);
        return json(['code' => 200, 'msg' => '补卡成功']);
    }

    //充值
    public function topUp()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['num'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if ($params['num'] < 1) {
            return json(['code' => 100, 'msg' => '充值次数必须大于1']);
        }
        if ($params['money'] <= 0) {
            return json(['code' => 100, 'msg' => '充值金额必须大于0']);
        }
        $model = new MachineCardModel();
        $card = $model->where('id', $params['id'])->find();
        $num = $card['num'] + $params['num'];
        $model->where('id', $params['id'])->update(['num' => $num]);
        $log = [
            'money' => $params['money'],
            'idcard' => $card['idcard'],
            'uid' => $card['uid'],
            'num' => $params['num'],
        ];
        (new MachineCardLogModel())->save($log);
        return json(['code' => 200, 'msg' => '充值成功']);
    }

    //删除
    public function del()
    {
        $id = request()->get('id', '');
        $model = new MachineCardModel();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    //充值记录
    public function cardLogList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $where = [];
        if (!empty($params['idcard'])) {
            $where['c.idcard'] = ['like', '%' . $params['idcard'] . '%'];
        }
        $user = $this->user;
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['c.uid'] = $user['parent_id'];
            } else {
                $where['c.uid'] = ['=', $user['id']];
            }
        }
        $model = new MachineCardLogModel();
        $count = $model
            ->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->field('c.*,a.username')
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }
}
