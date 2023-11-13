<?php

namespace app\crontab\controller;

use think\Cache;
use think\Db;

class TestUser
{
    //删除过期测试号和数据
    public function deviceStatus()
    {
        $uids = Db::name('system_admin')
            ->where('account_type', 1)
            ->where('expire_time', '>', 0)
            ->where('expire_time', '<=', time())
            ->column('id');
        $uids = array_values($uids);
        if ($uids){
            Db::name('system_admin')
                ->whereIn('id', $uids)
                ->delete();
            //删除测试账号素材
            $adver = Db::name('adver_material')->where('uid', $uids)->select();
            if ($adver) {
                foreach ($adver as $k => $v) {
                    delMaterial($v['url']);
                }
                Db::name('adver_material')->where('uid', $uids)->delete();
            }
            //删除商品
            $goods = Db::name('mall_goods')
                ->whereIn('uid', $uids)
                ->select();
            if ($goods) {
                foreach ($goods as $k => $v) {
                    delMaterial($v['image']);
                    delMaterial($v['detail']);
                }
                Db::name('mall_goods')->whereIn('uid', $uids)->delete();
            }

            //删除下级
            Db::name('system_admin')->whereIn('parent_id', $uids)->delete();
            //删除分佣
            $plan_ids = Db::name('commission_plan')->whereIn('uid', $uids)->value('id');
            if ($plan_ids) {
                Db::name('commission_plan')->whereIn('id', $plan_ids)->delete();
                Db::name('commission_plan_user')->whereIn('plan_id', $plan_ids)->delete();
                Db::name('machine_commission')->whereIn('plan_id', $plan_ids)->delete();
            }
            //删除订单
            Db::name('finance_order')->whereIn('uid', $uids)->delete();
        }

    }
}