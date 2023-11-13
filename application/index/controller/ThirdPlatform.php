<?php


namespace app\index\controller;


use app\index\model\ShopThirdCommissionModel;
use app\index\model\ThirdPlatformModel;

class ThirdPlatform extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $model = new ThirdPlatformModel();
        $count = $model->count();
        $list = $model->page($page)->limit($limit)->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //添加/编辑三方平台
    public function savePlatform()
    {
        $params = request()->post();
        $data = [
            'platform_name' => $params['platform_name'],
            'name' => $params['name'],
            'phone' => $params['phone'],
            'remark' => $params['remark']
        ];
        $model = new ThirdPlatformModel();
        if (!empty($params['id'])) {
            $row = $model
                ->where('id', '<>', $params['id'])
                ->where('platform_name', $params['platform_name'])
                ->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该平台已存在']);
            }
            $model->where('id', $params['id'])->update($data);
        } else {
            $row = $model
                ->where('platform_name', $params['platform_name'])
                ->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该平台已存在']);
            }
            $data['create_time'] = time();
            $id = $model->insertGetId($data);
            $url = [
                'ticket_url' => "pagesB/tripartitePlatform/tripartitePlatform?platformId=" . $id . "&couponId={{潮嗨平台券id}}",
                'attract_url' => "pagesB/tripartitePlatform/tripartitePlatform?platformId=" . $id . "&couponId=0",
            ];
            $model->where('id', $id)->update($url);
        }
        return json(['code' => 200, 'msg' => '保存成功']);
    }

    public function delPlatform()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new ThirdPlatformModel();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    //打款
    public function remit()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['money'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if ($params['money'] <= 0) {
            return json(['code' => 100, 'msg' => '打款金额必须大于0']);
        }
        $row = (new ThirdPlatformModel())->where('id', $params['id'])->find();
        if ($row['balance'] < $params['money']) {
            return json(['code' => 100, 'msg' => '余额不足']);
        }
        $balance = round(($row['balance'] - $params['money']) * 100) / 100;
        (new ThirdPlatformModel())->where('id', $params['id'])->update(['balance' => $balance]);
        $log = [
            'uid' => $params['id'],
            'money' => 0 - $params['money'],
            'type' => 1
        ];
        (new ShopThirdCommissionModel())->save($log);
        return json(['code' => 200, 'msg' => '打款成功']);
    }

    //账户收益记录
    public function logList()
    {
        $params = request()->get();
        $page = !empty($params['page']) ? $params['page'] : 1;
        $limit = !empty($params['limit']) ? $params['limit'] : 15;
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new ShopThirdCommissionModel();
        $count = $model
            ->where('uid', $params['id'])
            ->count();
        $list = $model
            ->where('uid', $params['id'])
            ->page($page)->limit($limit)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }
}
