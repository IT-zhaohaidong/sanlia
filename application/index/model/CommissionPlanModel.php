<?php


namespace app\index\model;


use app\index\common\TimeModel;
use think\Db;
use traits\model\SoftDelete;

class CommissionPlanModel extends TimeModel
{
    protected $name = 'commission_plan';
    protected $deleteTime = 'delete_time';

    //获取设备分佣金额
    public function getMoney($money, $device_id)
    {
        $uid = (new MachineDevice())->where('id', $device_id)->value('uid');//设备所属人
        $commissionModel = new MachineCommissionModel();
        $list = $commissionModel->where('device_id', $device_id)->order('add_user asc')->select();
        $uids = $commissionModel->where('device_id', $device_id)->column('uid');
        $fenyong_user = (new SystemAdmin())->whereIn('id', array_unique($uids))->column('username,role_id', 'id');
        if ($list) {
            $new_list = $this->getTree($list);
            $add_user = array_keys($new_list)[0];
            $data = $this->getResult($new_list, $add_user, $fenyong_user, $money);
            $total = 0;
            foreach ($data as $k => $v) {
                $total += $v['money'];
            }
            if ($total != $money) {
                foreach ($data as $k => $v) {
                    if ($v['uid'] == $uid) {
                        $data[$k]['money'] = round($v['money'] + $money - $total, 2);
                    }
                }
            }
            return $data;
        } else {
            //全部属于设备所属人
            $data[] = ['uid' => $uid, 'money' => $money];
            return $data;
        }

    }

    public function getResult($list, $add_user, $fenyong_user, $money)
    {
        $arr = [];
        foreach ($list[$add_user] as $k => $v) {
            if (($v['uid'] != $add_user && $fenyong_user[$v['uid']]['role_id'] > 5) || ($v['uid'] == $add_user && $fenyong_user[$v['uid']]['role_id'] < 7)) {
                //下级代理商进不来,下级代理商通过else继续分佣
                $ratio_money = round(100 * $money * $v['ratio'] / 100) / 100;
                if ($ratio_money > 0) {
                    $arr[] = ['uid' => $v['uid'], 'money' => $ratio_money];
                }
            } else {
                $ratio_money = round(100 * $money * $v['ratio'] / 100) / 100;
                if ($ratio_money > 0 && isset($list[$v['uid']])) {
                    $data = $this->getResult($list, $v['uid'], $fenyong_user, $ratio_money);
                    $arr = array_merge($arr, $data);
                }else{
                    $ratio_money = round(100 * $money * $v['ratio'] / 100) / 100;
                    $arr[] = ['uid' => $v['uid'], 'money' => $ratio_money];
                }
            }
        }
        return $arr;
    }

    public function getTree($list)
    {
        $arr = [];
        foreach ($list as $k => $v) {
            $arr[$v['add_user']][] = $v;
        }
        return $arr;
    }
}