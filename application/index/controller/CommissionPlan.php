<?php

namespace app\index\controller;


use app\index\model\CommissionPlanModel;
use app\index\model\CommissionPlanUserModel;
use app\index\model\MachineCommissionModel;
use app\index\model\SystemAdmin;
use think\Db;
use think\Env;

class CommissionPlan extends BaseController
{
    //获取计划列表
    public function getPlanList()
    {
        $params = request()->get();
        $page = !empty($params['page']) ? $params['page'] : 1;
        $limit = !empty($params['limit']) ? $params['limit'] : 15;
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $user['id'] = $user['parent_id'];
            }
            $where['c.uid'] = $user['id'];
        }
        if (!empty($params['title'])) {
            $where['title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['name'])) {
            $where['name'] = ['like', '%' . $params['name'] . '%'];
        }
        $model = new CommissionPlanModel();
        $count = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
//            ->join('commission_plan_user u', 'c.id=u.plan_id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
//            ->join('commission_plan_user u', 'c.id=u.plan_id', 'left')
            ->where($where)
            ->field('c.*,a.username')
            ->page($page)
            ->limit($limit)
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['user_count'] = (new CommissionPlanUserModel())->where('plan_id', $v['id'])->count();
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

//    //第一步 设置基本信息
//    public function createPlan()
//    {
//        $post = request()->post();
//        $user = $this->user;
//        if (empty($post['title']) || empty($post['name'])) {
//            return json(['code' => 100, 'msg' => '缺少参数']);
//        }
//        $where = [];
//        if (!empty($post['id']) && !$post['id']) {
//            $where['id'] = ['<>', $post['id']];
//        }
//        $model = new CommissionPlanModel();
//        $row = $model
//            ->where('uid', '<>', $user['id'])
//            ->where($where)
//            ->where('title', $post['title'])
//            ->find();
//        if ($row) {
//            return json(['code' => 100, 'msg' => '该计划名称已存在']);
//        }
//        $data = [
//            'title' => $post['title'],
//            'name' => $post['name'],
//            'area' => $post['area'],
//            'phone' => $post['phone'],
//        ];
//        if (!empty($post['id']) && $post['id']) {
//            $model->where('id', $post['id'])->update($data);
//        } else {
//            $data['uid'] = $user['id'];
//            $data['create_time'] = time();
//            $post['id'] = $model->insertGetId($data);
//        }
//        return json(['code' => 200, 'msg' => '成功', 'id' => $post['id']]);
//    }

    //第二步 获取已分佣用户列表
    public function getCommissionUser()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new CommissionPlanUserModel();
        $list = $model->alias('c')
            ->join('system_admin a', 'a.id=c.uid', 'left')
            ->join('system_role r', 'a.role_id=r.id', 'left')
            ->where('plan_id', $id)
            ->field('a.id,a.username,a.photo,a.remark,r.name,c.ratio')
            ->select();
        foreach ($list as $k => $v) {
            if (!$v['photo'] || $v['photo'] == '') {
                $list[$k]['photo'] = 'https://api.feishi.vip/upload/photo.png';
            } else {
                $dirpath = $_SERVER['DOCUMENT_ROOT'] . '/';
                $photo = str_replace(Env::get('server.server_name', ''), $dirpath, $v['photo']);
                if (!file_exists($photo)) {
                    $list[$k]['photo'] = 'https://api.feishi.vip/upload/photo.png';
                }
            }
        }
        return json(['code' => 200, 'data' => $list]);
    }

    //第二步 获取可分佣用户列表
    public function getUserList()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] == 1) {
            $where = [];
        } else {
            $where['a.parent_id'] = ['=', $user['id']];
        }
        $list = (new SystemAdmin())->alias('a')
            ->join('system_role r', 'a.role_id=r.id', 'left')
            ->where($where)
            ->whereOr('a.id', $user['id'])
            ->field('a.id,a.username,a.photo,a.remark,r.name')
            ->select();
        foreach ($list as $k => $v) {
            if (!$v['photo'] || $v['photo'] == '') {
                $list[$k]['photo'] = 'https://api.feishi.vip/upload/photo.png';
            } else {
                $dirpath = $_SERVER['DOCUMENT_ROOT'] . '/';
                $photo = str_replace(Env::get('server.server_name', ''), $dirpath, $v['photo']);
                if (!file_exists($photo)) {
                    $list[$k]['photo'] = 'https://api.feishi.vip/upload/photo.png';
                }
            }
            $list[$k]['ratio'] = 0;
        }
        return json(['code' => 200, 'data' => $list]);
    }

    //保存分佣用户列表
//    public function saveCommissionUser()
//    {
//        $post = request()->post();
////        [
////            'id' => 1,
////            'data' => [
////                ['uid' => 2, 'ratio' => 30],
////                ['uid' => 3, 'ratio' => 30],
////                ['uid' => 4, 'ratio' => 40],
////            ]
////        ];
//        if (empty($post['id'])) {
//            return json(['code' => 100, 'msg' => '缺少参数']);
//        }
//        $total_ratio = 0;
//        $uids = [];
//        $data = $post['data'];
//        foreach ($data as $k => $v) {
//            $uids[] = $v['uid'];
//            $total_ratio += $v['ratio'];
//            $data[$k]['plan_id'] = $post['id'];
//        }
//        if ($total_ratio != 100 || $total_ratio != 0) {
//            return json(['code' => 100, 'msg' => '缺少参数']);
//        }
//        $uid = (new CommissionPlanModel())->where('id', $post['id'])->value('uid');
//        $count = (new SystemAdmin())
//            ->whereIn('id', $uids)
//            ->where('role_id', '<', '7')
//            ->where('id', '<>', $uid)
//            ->count();
//        if ($count > 1) {
//            return json(['code' => 100, 'msg' => '只能添加一位下级代理商']);
//        }
//        $model = new CommissionPlanUserModel();
//        Db::name('commission_plan_user')->where('plan_id', $post['id'])->delete();
//        $model->save($data);
//        return json(['code' => 200, 'msg' => '成功']);
//
//    }

    //第三步 获取可分佣设备列表
    public function getDeviceList()
    {
        $params = request()->post();
//        $data=[
//            ['uid'=>1,'ratio'=>20],
//            ['uid'=>2,'ratio'=>20],
//            ['uid'=>3,'ratio'=>60]
//        ];
        $id = request()->post('id', '');
        $page = request()->post('page', 1);
        $limit = request()->post('limit', 10);
        $deviceModel = new \app\index\model\MachineDevice();
        $deviceCommission = new MachineCommissionModel();
        $planModel = new CommissionPlanModel();
        $machineCommission = new MachineCommissionModel();
        $user = $this->user;

        $total_ratio = 0;
        $uids = [];
        $data = $params['data'];
        foreach ($data as $k => $v) {
            $uids[] = $v['uid'];
            $total_ratio += $v['ratio'];
        }
        if ($total_ratio != 100) {
            return json(['code' => 100, 'msg' => '分佣比例之和必须等于100']);
        }


        $where = [];
        if (!empty($params['device_name'])) {
            $where['d.device_name'] = ['like', '%' . $params['device_name'] . '%'];
        }
        if (!empty($params['device_sn'])) {
            $where['d.device_sn'] = ['like', '%' . $params['device_sn'] . '%'];
        }
        $commissionDeviceIds = [];
        if ($id) {
            //已分佣设备
            $deviceIds = $deviceCommission->where('plan_id', $id)->column('device_id');
            $commissionDeviceIds = array_unique($deviceIds);
            $uid = $planModel->where('id', $id)->value('uid');
            $commission = $machineCommission->where('plan_id', $id)->select();
        } else {
            $commission = [];
            $uid = $user['id'];
        }
        $plan_where = [];
        if ($id) {
            $plan_where['id'] = ['<>', $id];

        }
        $plan_ids = $planModel->where('uid', $uid)->where($plan_where)->column('id');
        $other_commission_device = $deviceCommission->whereIn('plan_id', $plan_ids)->column('device_id');
        $count = (new SystemAdmin())
            ->where('role_id', '<', '7')
            ->whereIn('id', $uids)
            ->where('id', '<>', $uid)
            ->count();
        if ($count > 1) {
            return json(['code' => 100, 'msg' => '只能添加一位下级代理商']);
        }
        $count = Db::name('machine_device')->alias('d')
            ->join('system_admin a', 'd.uid=a.id', 'left')
            ->where($where)
            ->where('d.delete_time', null)
//            ->where(function ($query) use ($uid, $other_commission_device) {
            ->where('d.uid', $uid)
            ->whereNotIn('d.id', $other_commission_device)
//            })
            ->count();
        $list = Db::name('machine_device')->alias('d')
            ->join('system_admin a', 'd.uid=a.id', 'left')
            ->where($where)
            ->where('d.delete_time', null)
//            ->where(function ($query) use ($uid, $other_commission_device) {
            ->where('d.uid', $uid)
            ->whereNotIn('d.id', $other_commission_device)
//            })
            ->page($page)
            ->limit($limit)
            ->field('d.id,d.uid,d.device_sn,d.imei,d.device_name,a.username')
            ->select();
        $data = array_column($params['data'], 'ratio', 'uid');
        foreach ($list as $k => $v) {
            //获取可分佣金额
            $money_data = $planModel->getMoney(100, $v['id']);

            $res = $this->getMoneyByUser($user['id'], $money_data);
            $money = $res['code'] == 1 ? $res['money'] : 0;
            $ratio = $deviceCommission
                ->where(['device_id' => $v['id'], 'add_user' => $user['id'], 'uid' => $user['id']])
                ->value('ratio');
            if ($ratio) {
                $money = round($money / ($ratio / 100), 2);
            }
            $list[$k]['money'] = $money;
            $is_alone_set = 0;
            foreach ($data as $x => $y) {
                //获取单独分佣设备的分佣比例
                $commissionRatio = $this->getCommissionByUser($id, $v['id'], $x, $commission);
                if ($commissionRatio['code'] == 1) {
                    $is_alone_set = 1;
                    $list[$k][$x] = ['ratio' => $commissionRatio['ratio'], 'money' => round($money * 100 * $commissionRatio['ratio'] / 100) / 100];
                } else {
                    $list[$k][$x] = ['ratio' => $y, 'money' => round($money * 100 * $y / 100) / 100];
                }
            }
            $list[$k]['is_alone_set'] = $is_alone_set;
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //第二步 下一步 不保存单独设置的分佣,其他覆盖 (编辑)
    public function nextStep()
    {
        $params = request()->post();
//        $data=[
//            ['uid'=>1,'ratio'=>20],
//            ['uid'=>2,'ratio'=>20],
//            ['uid'=>3,'ratio'=>60]
//        ];
        $id = request()->post('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $login_user = $this->user;
        $deviceModel = new \app\index\model\MachineDevice();
        $machineCommission = new MachineCommissionModel();
        $planModel = new CommissionPlanModel();
        //已分佣的设备
        $deviceIds = $machineCommission->where('plan_id', $id)->column('device_id');
        $uid = $planModel->where('id', $id)->value('uid');
        $data = array_column($params['data'], 'ratio', 'uid');
        $list = Db::name('machine_device')->alias('d')
            ->join('system_admin a', 'a.id=d.uid', 'left')
            ->where('d.id', 'in', $deviceIds)
            ->where('d.delete_time', null)
            ->field('d.id,d.uid,d.device_sn,d.imei,d.device_name,a.username')
            ->select();
        $commission = $machineCommission->where('plan_id', $id)->select();
        $uids = array_keys($data);
        $users = (new SystemAdmin())->whereIn('id', $uids)->column('username', 'id');
        $commissionUser = $params['data'];
        foreach ($commissionUser as $k => $v) {
            $commissionUser[$k]['username'] = isset($users[$v['uid']]) ? $users[$v['uid']] : '';
        }
        foreach ($list as $k => $v) {
            //获取可分佣金额
            $money_data = $planModel->getMoney(100, $v['id']);
            $res = $this->getMoneyByUser($login_user['id'], $money_data);
            $money = $res['code'] == 1 ? $res['money'] : 0;
            $ratio = $machineCommission
                ->where(['device_id' => $v['id'], 'add_user' => $login_user['id'], 'uid' => $login_user['id']])
                ->value('ratio');
            if ($ratio) {
                $money = round($money / ($ratio / 100), 2);
            }
            $list[$k]['money'] = $money;
            $is_alone_set = 0;
            foreach ($data as $x => $y) {
                //获取单独分佣设备的分佣比例
                $commissionRatio = $this->getCommissionByUser($id, $v['id'], $x, $commission);
                if ($commissionRatio['code'] == 1) {
                    $is_alone_set = 1;
                    $list[$k][$x] = ['ratio' => $commissionRatio['ratio'], 'money' => round($money * 100 * $commissionRatio['ratio'] / 100) / 100];
                } else {
                    $list[$k][$x] = ['ratio' => $y, 'money' => round($money * 100 * $y / 100) / 100];
                }
            }
            $list[$k]['is_alone_set'] = $is_alone_set;
        }
        return json(['code' => 200, 'data' => $list, 'commission' => $commission, 'commissionUser' => $commissionUser]);
    }

    public function getMoneyByUser($uid, $data)
    {
        $money = 0;
        $bool = false;
        foreach ($data as $k => $v) {
            if ($v['uid'] == $uid) {
                $money = $v['money'];
                $bool = true;
                break;
            }
        }
        if ($bool) {
            return ['code' => 1, 'money' => $money];
        } else {
            return ['code' => 0, 'money' => 0];
        }
    }

    public function getCommissionByUser($plan_id, $device_id, $uid, $data)
    {
        $bool = false;
        $ratio = 0;
        foreach ($data as $k => $v) {
            if ($plan_id == $v['plan_id'] && $device_id == $v['device_id'] && $uid == $v['uid'] && $v['is_alone_set'] == 1) {
                $bool = true;
                $ratio = $v['ratio'];
                break;
            }
        }
        if ($bool) {
            return ['code' => 1, 'ratio' => $ratio];
        } else {
            return ['code' => 0, 'ratio' => $ratio];
        }
    }

    //第二步 全部覆盖 不保存当前页设置
    public function allCover()
    {
        $params = request()->post();
//        $data=[
//            ['uid'=>1,'ratio'=>20],
//            ['uid'=>2,'ratio'=>20],
//            ['uid'=>3,'ratio'=>60]
//        ];
        $id = request()->post('id', '');
        $deviceModel = new \app\index\model\MachineDevice();
        $machineCommission = new MachineCommissionModel();
        $planModel = new CommissionPlanModel();
        //已分佣的设备
        if (!empty($id)) {
            $deviceIds = $machineCommission->where('plan_id', $id)->column('device_id');
        } else {
            $deviceIds = [];
        }
        $login_user = $this->user;
        $uid = $planModel->where('id', $id)->value('uid');
        $data = array_column($params['data'], 'ratio', 'uid');
        $uids = array_keys($data);
        $users = (new SystemAdmin())->whereIn('id', $uids)->column('username', 'id');
        $commissionUser = $params['data'];
        foreach ($commissionUser as $k => $v) {
            $commissionUser[$k]['username'] = isset($users[$v['uid']]) ? $users[$v['uid']] : '';
        }
        $list = Db::name('machine_device')->alias('d')
            ->join('system_admin a', 'a.id=d.uid', 'left')
            ->whereIn('d.id', $deviceIds)
            ->where('d.delete_time', null)
            ->field('d.id,d.uid,d.device_sn,d.imei,d.device_name,a.username')
            ->select();
        foreach ($list as $k => $v) {
            //获取可分佣金额
            $money_data = $planModel->getMoney(100, $v['id']);
            $res = $this->getMoneyByUser($login_user['id'], $money_data);
            $money = $res['code'] == 1 ? $res['money'] : 0;
            $ratio = $machineCommission
                ->where(['device_id' => $v['id'], 'add_user' => $login_user['id'], 'uid' => $login_user['id']])
                ->value('ratio');
            if ($ratio) {
                $money = round($money / ($ratio / 100), 2);
            }
            $list[$k]['money'] = $money;
            foreach ($data as $x => $y) {
                //获取单独分佣设备的分佣比例
                $list[$k][$x] = ['ratio' => $y, 'money' => round($money * 100 * $y / 100) / 100];
            }
            $list[$k]['is_alone_set'] = 0;
        }
        return json(['code' => 200, 'data' => $list, 'commission' => $params['data'], 'commissionUser' => $commissionUser]);
    }

    public function savePlan()
    {
        $data = request()->post();
//        [
//            'id' => '',//计划id
//            'plan' => [
//                'title' => '任务名称',
//                'name' => '代理商名称',
//                'phone' => '联系方式',
//                'area' => '区域代码',
//                'area_text' => '区域',
//            ],
//            'user' => [
//                ['uid' => 2, 'ratio' => '分佣比例'],
//                ['uid' => 3, 'ratio' => '分佣比例'],
//                ['uid' => 4, 'ratio' => '分佣比例'],
//            ],
//            'device' => [
//                'device_id' => '设备id',
//                'is_alone_set' => '是否单独设置 0:否 1是',
//                2 => ['ratio' => '分佣比例'],
//                3 => ['ratio' => '分佣比例'],
//                4 => ['ratio' => '分佣比例']
//            ],
//        ];
        $planModel = new CommissionPlanModel();
        $userModel = new CommissionPlanUserModel();
        $deviceModel = new MachineCommissionModel();
        $bool = true;
        $device = '';
        foreach ($data['device'] as $k => $v) {
            $total_ratio = 0;
            foreach ($data['user'] as $x => $y) {
                $ratio = isset($v[$y['uid']]['ratio']) ? $v[$y['uid']]['ratio'] : 0;
                $total_ratio += $ratio;
            }
            if ($total_ratio != 100) {
                $bool = false;
                $device = $v['device_id'];
                break;
            }
        }
        if (!$bool) {
            return json(['code' => 100, 'msg' => '设备id:' . $device, ',分佣比例不等于100,请检查!']);
        }
        if (isset($data['id']) && $data['id']) {
            //============编辑=================
            //清除用户
            Db::name('commission_plan_user')->where('plan_id', $data['id'])->delete();
            //清除设备
            $deviceModel->where('plan_id', $data['id'])->delete();
            //更新计划信息
            $planModel->where('id', $data['id'])->update($data['plan']);
            $uid = $planModel->where('id', $data['id'])->value('uid');
        } else {
            //=============添加================
            $data['plan']['create_time'] = time();
            $uid = $this->user['id'];
            $data['plan']['uid'] = $uid;
            $data['id'] = $planModel->insertGetId($data['plan']);
        }
        //添加分佣用户
        $user = $data['user'];
        foreach ($user as $k => $v) {
            $user[$k]['plan_id'] = $data['id'];
        }
        $userModel->saveAll($user);
        //添加设备分佣
        $device_data = [];
        foreach ($data['device'] as $k => $v) {
            foreach ($data['user'] as $x => $y) {
                $device_data[] = [
                    'device_id' => $v['device_id'],
                    'add_user' => $uid,
                    'is_alone_set' => $v['is_alone_set'],
                    'plan_id' => $data['id'],
                    'uid' => $y['uid'],
                    'ratio' => isset($v[$y['uid']]['ratio']) ? $v[$y['uid']]['ratio'] : ''
                ];
            }
        }
        $deviceModel->saveAll($device_data);
        //若分佣计划存在下级代理商,将设备归属下级代理商
        $userIds = array_values(array_column($data['user'], 'uid'));
        $agent_user = (new SystemAdmin())
            ->whereIn('id', $userIds)
            ->where('id', '<>', $uid)
            ->where('role_id', '<', 7)
            ->find();
        if ($agent_user) {
            $device_ids = array_values(array_column($data['device'], 'device_id'));
            (new \app\index\model\MachineDevice())->whereIn('id', $device_ids)->update(['uid' => $agent_user['id']]);
        }
        return json(['code' => 200, 'msg' => '保存成功']);
    }

    //查看计划
    public function planDetail()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $login_user = $this->user;
        $planModel = new CommissionPlanModel();
        $userModel = new CommissionPlanUserModel();
        $deviceModel = new MachineCommissionModel();
        $plan = $planModel->where('id', $id)->find();
        $user = $userModel->alias('u')
            ->join('system_admin a', 'u.uid=a.id', 'left')
            ->join('system_role r', 'r.id=a.role_id', 'left')
            ->where('u.plan_id', $id)
            ->field('u.*,a.username,a.photo,r.name')
            ->select();
        foreach ($user as $k => $v) {
            if (!$v['photo'] || $v['photo'] == '') {
                $user[$k]['photo'] = 'https://api.feishi.vip/upload/photo.png';
            } else {
                $dirpath = $_SERVER['DOCUMENT_ROOT'] . '/';
                $photo = str_replace(Env::get('server.server_name', ''), $dirpath, $v['photo']);
                if (!file_exists($photo)) {
                    $user[$k]['photo'] = 'https://api.feishi.vip/upload/photo.png';
                }
            }
        }
        $deviceIds = $deviceModel->where('plan_id', $id)->column('device_id');
        $list = Db::name('machine_device')->alias('d')
            ->join('system_admin a', 'a.id=d.uid', 'left')
            ->whereIn('d.id', $deviceIds)
            ->where('d.delete_time', null)
            ->field('d.id,d.uid,d.device_sn,d.imei,d.device_name,a.username')
            ->select();
        $commission = $deviceModel->where('plan_id', $id)->select();
        foreach ($list as $k => $v) {
            //获取可分佣金额
            $money_data = $planModel->getMoney(100, $v['id']);
            $res = $this->getMoneyByUser($login_user['id'], $money_data);
            $money = $res['code'] == 1 ? $res['money'] : 0;
            $ratio = $deviceModel
                ->where(['device_id' => $v['id'], 'add_user' => $login_user['id'], 'uid' => $login_user['id']])
                ->value('ratio');
            if ($ratio) {
                $money = round($money / ($ratio / 100), 2);
            }
            $list[$k]['money'] = $money;
            $list[$k]['money'] = $money;
            foreach ($user as $x => $y) {
                //获取单独分佣设备的分佣比例
                $commissionRatio = $this->getCommissionByUser($id, $v['id'], $y['uid'], $commission);

                if ($commissionRatio['code'] == 1) {
                    $list[$k][$y['uid']] = ['ratio' => $commissionRatio['ratio'], 'money' => round($money * 100 * $commissionRatio['ratio'] / 100) / 100];
                } else {
                    $list[$k][$y['uid']] = ['ratio' => $y['ratio'], 'money' => round($money * 100 * $y['ratio'] / 100) / 100];
                }
            }
        }
        $data = [
            'plan' => $plan,
            'user' => $user,
            'device' => $list
        ];
        return json(['code' => 200, 'data' => $data]);
    }

    //删除计划
    public function delPlan()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        Db::name('machine_commission')->where('plan_id', $id)->delete();
        Db::name('commission_plan')->where('id', $id)->delete();
        Db::name('commission_plan_user')->where('plan_id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }
}
