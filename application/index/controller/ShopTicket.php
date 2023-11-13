<?php

namespace app\index\controller;


use app\index\model\ShopTicketModel;

class ShopTicket extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 1 : $params['limit'];
        $where = [];
        $model = new ShopTicketModel();
        $count = $model->where($where)->count();
        $list = $model
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function saveCate()
    {
        $params = request()->post();
        $data = [
            'full' => $params['full'] ?? 0,
            'type' => $params['type'],
            'reduce' => $params['reduce'] ?? 0,
            'timing' => $params['timing'],
            'status' => $params['status'] ?? 1
        ];
        if ($params['type'] == 1 && $params['reduce'] >= $params['full']) {
            return json(['code' => 100, 'msg' => '减免金额必须小于满减金额']);
        }
        $model = new ShopTicketModel();
        if (!empty($params['id'])) {
            $model->where('id', $params['id'])->update($data);
        } else {
            $model->save($data);
        }
        return json(['code' => 200, 'msg' => '保存成功']);
    }

    public function del()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new ShopTicketModel();
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

}
