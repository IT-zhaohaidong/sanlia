<?php

namespace app\index\controller;


use app\index\model\SystemConfigModel;

class SystemConfig extends BaseController
{
    public function getConfig()
    {
        $model = new SystemConfigModel();
        $row = $model->where('id', 1)->find();
        return json(['code' => 200, 'data' => $row]);
    }

    public function saveConfig()
    {
        $params = request()->post();
        $data = [
            'card_notify' => $params['card_notify'],
            'withdraw_notify' => $params['withdraw_notify'],
            'vip_money' => $params['vip_money'],
            'vip_condition' => $params['vip_condition'],
//            'company_image' => $params['company_image'],
//            'company_media_id' => $params['company_media_id'],

        ];
        $model = new SystemConfigModel();
        $model->where('id', 1)->update($data);
        return json(['code' => 200, 'msg' => '保存成功']);
    }
}
