<?php

namespace app\android\controller;

use app\index\model\AppRulesModel;
use app\index\model\MachineDevice;

class AfterRules
{
    public function getRules()
    {
        $imei = request()->get('imei', '');
        if (empty($imei)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $uid = (new MachineDevice())->where('imei', $imei)->value('uid');
        $uid = [$uid, 1];
        $row = (new AppRulesModel())->whereIn('uid', $uid)->order('uid desc')->find();
        return json(['code' => 200, 'data' => $row]);
    }
}