<?php

namespace app\applet\controller;

use app\index\model\OperateTicketModel;
use app\index\model\ShopTicketModel;
use think\Controller;
use think\Db;


class Ticket extends Controller
{
    //获取券信息
    public function getTicket()
    {
        $id = request()->get('id', '');
        $model = new ShopTicketModel();
        $data = $model->where('id', $id)->find();
        if (!$data || $data['status'] == 0) {
            return json(['code' => 100, 'msg' => '该券已下架,请查看其他优惠']);
        }
        return json(['code' => 200, 'data' => $data]);
    }

    //领取券
    public function receiveTicket()
    {
        $params = request()->get();
        if (empty($params['openid']) || empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $ticket = (new ShopTicketModel())
            ->where('id', $params['id'])
            ->find();
        $row = Db::name('operate_ticket')->where(['openid' => $params['openid'], 'ticket_id' => $params['id']])->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '您已领取过该优惠,请查看其他优惠']);
        }
        $data = [
            'openid' => $params['openid'],
            'ticket_id' => $params['id'],
            'type' => $ticket['type'],
            'full' => $ticket['full'],
            'reduce' => $ticket['reduce'],
            'start_time' => time(),
            'end_time' => time() + $ticket['timing'] * 24 * 3600,
            'status' => 0,
        ];
        $model = new OperateTicketModel();
        $model->save($data);
        return json(['code' => 200, 'msg' => '领取成功']);
    }

    //我的券
    public function myTicket()
    {
        $params = request()->get();
        if (empty($params['openid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $model = new OperateTicketModel();
        $count = $model->where('openid', $params['openid'])->count();
        $list = $model->where('openid', $params['openid'])
            ->page($page)->limit($limit)
            ->order(['id desc', 'status asc'])
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['start_time'] = date('Y-m-d', $v['start_time']);
            $list[$k]['end_time'] = date('Y-m-d', $v['end_time']);
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }
}
