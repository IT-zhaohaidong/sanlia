<?php

namespace app\index\controller;

use app\applet\controller\Wxpay;
use app\index\common\ExportOrder;
use app\index\model\FinanceCash;
use app\index\model\MchidModel;
use app\index\model\OperateUserModel;
use app\index\model\OrderGoods;
use app\index\model\SystemAdmin;
use think\Cache;
use think\Db;

class FinanceOrder extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $where['o.device_sn'] = $this->getBuHuoWhere();
            } else {
                $where['o.uid'] = $user['id'];
            }
        } else {
            if (!empty($params['uid'])) {
                $where['o.uid'] = $params['uid'];
            }
        }
        if (!empty($params['type_id'])) {
            $where['device.type_id'] = $params['type_id'];
        }
        if (!empty($params['title'])) {
            $where['g.title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['order_sn'])) {
            $where['o.order_sn'] = ['like', '%' . $params['order_sn'] . '%'];
        }
        if (!empty($params['device_sn'])) {
            $where['o.device_sn'] = ['like', '%' . $params['device_sn'] . '%'];
        }
        if (!empty($params['device_name'])) {
            $where['device.device_name'] = ['like', '%' . $params['device_name'] . '%'];
        }
        if (!empty($params['title'])) {
            $where['g.title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['start_time'])) {
            $where['o.create_time'] = ['>=', strtotime($params['start_time'])];
        }
        if (!empty($params['end_time'])) {
            $where['o.create_time'] = ['<', strtotime($params['end_time']) + 3600 * 24];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (isset($params['order_type']) && $params['order_type'] !== '') {
            $where['o.order_type'] = ['=', $params['order_type']];
        }
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $where['o.create_time'] = ['between', [strtotime($params['start_time']), strtotime($params['end_time']) + 3600 * 24], 'AND'];
        }
        $where['o.status'] = ['>', 0];
        $model = new \app\index\model\FinanceOrder();
        $day = strtotime(date('Y-m-d'));
        $month = strtotime(date('Y-m-01'));
        $day = $this->getOrderNum($day);
        $month = $this->getOrderNum($month);
        $total = $this->getOrderNum();
        $tot = [
            'day' => $day,
            'month' => $month,
            'total' => $total
        ];
        $count = $model->alias('o')
            ->join('system_admin a', 'o.uid=a.id', 'left')
            ->join('machine_device device', 'device.device_sn=o.device_sn', 'left')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods g', 'og.goods_id=g.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('o')
            ->join('system_admin a', 'o.uid=a.id', 'left')
            ->join('machine_device device', 'device.device_sn=o.device_sn', 'left')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods g', 'og.goods_id=g.id', 'left')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->field('o.*,a.username,device.device_name,g.title,g.image')
            ->order('id desc')
            ->select();
        $openids = [];
        foreach ($list as $k => $v) {
            $openids[] = $v['openid'];
        }
        $user = (new OperateUserModel())->whereIn('openid', $openids)->column('phone,id,openid', 'openid');
        foreach ($list as $k => $v) {
            $list[$k]['pay_time'] = date('Y-m-d H:i:s', $v['pay_time']);
            $list[$k]['phone'] = isset($user[$v['openid']]) ? $user[$v['openid']]['phone'] : '';
        }
        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count, 'tot' => $tot]);
    }

    public function getOrderNum($time = '')
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $where['device_sn'] = $this->getBuHuoWhere();
            } else {
                $where['uid'] = $user['id'];
            }
        }
        $time_where = [];
        if ($time) {
            $time_where['pay_time'] = ['>', $time];
        }
        $model = new \app\index\model\FinanceOrder();
        $num = $model->where($where)->where($time_where)->where('status', 1)->count() ?? 0;
        $money = $model
            ->where($where)
            ->where($time_where)
            ->where('status', 1)->field('sum(price) total_price,sum(commission) total_commission')
            ->group('status')
            ->find();
        return ['num' => $num, 'money' => $money['total_price'] ?? '0.00', 'total_commission' => $money['total_commission'] ?? '0.00'];
    }

    public function orderDetail()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OrderGoods();
        $order = (new \app\index\model\FinanceOrder())->alias('o')
            ->join('system_admin a', 'o.uid=a.id', 'left')
            ->where('o.id', $id)
            ->field('o.*,a.username')
            ->find();
        $order['pay_time'] = $order['pay_time'] ? date('Y-m-d H:i:s', $order['pay_time']) : '';
        $list = $model->alias('o')
            ->join('mall_goods g', 'o.goods_id=g.id', 'left')
            ->where('o.order_id', $id)
            ->field('o.*,g.title,g.image')
            ->select();
        return json(['code' => 200, 'data' => $list, 'order' => $order]);
    }

    //导出获取全部数据
    public function importGetList()
    {
        $params = request()->get();
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $where['o.device_sn'] = $this->getBuHuoWhere();
            } else {
                $where['o.uid'] = $user['id'];
            }
        } else {
            if (!empty($params['uid'])) {
                $where['o.uid'] = $params['uid'];
            }
        }
        if (!empty($params['type_id'])) {
            $where['device.type_id'] = $params['type_id'];
        }
        if (!empty($params['order_sn'])) {
            $where['o.order_sn|o.device_sn'] = ['like', '%' . $params['order_sn'] . '%'];
        }
        if (!empty($params['device_sn'])) {
            $where['o.device_sn'] = ['like', '%' . $params['device_sn'] . '%'];
        }
        if (!empty($params['device_name'])) {
            $where['device.device_name'] = ['like', '%' . $params['device_name'] . '%'];
        }

        if (!empty($params['idcard'])) {
            $where['o.idcard'] = ['like', '%' . $params['idcard'] . '%'];
        }
        if (!empty($params['title'])) {
            $where['g.title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (isset($params['order_type']) && $params['order_type'] !== '') {
            $where['o.order_type'] = ['=', $params['order_type']];
        }
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $where['o.create_time'] = ['between', [strtotime($params['start_time']), strtotime($params['end_time']) + 3600 * 24], 'AND'];
        }
        $where['o.status'] = ['>', 0];
        $model = new \app\index\model\FinanceOrder();
        $count = $model->alias('o')
            ->join('system_admin a', 'o.uid=a.id', 'left')
            ->join('machine_device device', 'device.device_sn=o.device_sn', 'left')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods g', 'og.goods_id=g.id', 'left')
            ->where($where)
            ->count();
        $total_page = ceil($count / 1000);
        $data = [];
        for ($i = 1; $i <= $total_page; $i++) {
            $list = $model->alias('o')
                ->join('system_admin a', 'o.uid=a.id', 'left')
                ->join('machine_device device', 'device.device_sn=o.device_sn', 'left')
                ->join('order_goods og', 'o.id=og.order_id', 'left')
                ->join('mall_goods g', 'og.goods_id=g.id', 'left')
                ->where($where)
                ->field('o.*,a.username,device.device_name,g.title')
                ->order('id desc')
                ->page($i)
                ->limit(1000)
                ->select();
            $openids = [];
            foreach ($list as $k => $v) {
                $openids[] = $v['openid'];
            }
            $user = (new OperateUserModel())->whereIn('openid', $openids)->column('phone,id,openid', 'openid');
            foreach ($list as $k => $v) {
                $list[$k]['pay_time'] = date('Y-m-d H:i:s', $v['pay_time']);
                $list[$k]['phone'] = isset($user[$v['openid']]) ? $user[$v['openid']]['phone'] : '';
            }
            $data = array_merge($data, $list);
        }

        return json(['code' => 200, 'data' => $data, 'params' => $params]);
    }

    //小程序订单导出
    public function exportOrder()
    {
        $params = request()->get();
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $where['o.device_sn'] = $this->getBuHuoWhere();
            } else {
                $where['o.uid'] = $user['id'];
            }
        } else {
            if (!empty($params['uid'])) {
                $where['o.uid'] = $params['uid'];
            }
        }
        if (!empty($params['type_id'])) {
            $where['device.type_id'] = $params['type_id'];
        }
        if (!empty($params['title'])) {
            $where['g.title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['order_sn'])) {
            $where['o.order_sn'] = ['like', '%' . $params['order_sn'] . '%'];
        }
        if (!empty($params['device_sn'])) {
            $where['o.device_sn'] = ['like', '%' . $params['device_sn'] . '%'];
        }
        if (!empty($params['device_name'])) {
            $where['device.device_name'] = ['like', '%' . $params['device_name'] . '%'];
        }
        if (!empty($params['title'])) {
            $where['g.title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['start_time'])) {
            $where['o.create_time'] = ['>=', strtotime($params['start_time'])];
        }
        if (!empty($params['end_time'])) {
            $where['o.create_time'] = ['<', strtotime($params['end_time']) + 3600 * 24];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (isset($params['order_type']) && $params['order_type'] !== '') {
            $where['o.order_type'] = ['=', $params['order_type']];
        }
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $where['o.create_time'] = ['between', [strtotime($params['start_time']), strtotime($params['end_time']) + 3600 * 24], 'AND'];
        }
        $where['o.status'] = ['>', 0];
        $model = new \app\index\model\FinanceOrder();
        $count = $model->alias('o')
            ->join('system_admin a', 'o.uid=a.id', 'left')
            ->join('machine_device device', 'device.device_sn=o.device_sn', 'left')
            ->join('order_goods og', 'o.id=og.order_id', 'left')
            ->join('mall_goods g', 'og.goods_id=g.id', 'left')
            ->where($where)
            ->count();
        $info = [];
        for ($i = 1; $i <= ceil($count / 1000); $i++) {
            $list = $model->alias('o')
                ->join('system_admin a', 'o.uid=a.id', 'left')
                ->join('machine_device device', 'device.device_sn=o.device_sn', 'left')
                ->join('order_goods og', 'o.id=og.order_id', 'left')
                ->join('mall_goods g', 'og.goods_id=g.id', 'left')
                ->where($where)
                ->page($i)
                ->limit(1000)
                ->field('o.*,a.username,device.device_name,g.title,g.image')
                ->order('id desc')
                ->select();
            $info = array_merge($info, $list);
        }

        $openids = [];
        foreach ($info as $k => $v) {
            $openids[] = $v['openid'];
        }
        $user = (new OperateUserModel())->whereIn('openid', $openids)->column('phone,id,openid', 'openid');
        $order_type = [
            0 => '普通订单',
            1 => '雀客'
        ];
        $pay_type = [
            1 => '系统微信支付',
            2 => '系统支付宝支付',
            3 => '用户微信支付',
            4 => '用户支付宝支付'
        ];
        $status = [
            0 => '待支付',
            1 => '已完成',
            2 => '已退款',
            3 => '异常',
            4 => '已支付待出货'
        ];
        foreach ($info as $k => $v) {
            $info[$k]['pay_time'] = date('Y-m-d H:i:s', $v['pay_time']);
            $info[$k]['phone'] = isset($user[$v['openid']]) ? $user[$v['openid']]['phone'] : '';
            $info[$k]['order_type'] = isset($order_type[$v['order_type']]) ? $order_type[$v['order_type']] : '';
            $info[$k]['pay_type'] = isset($pay_type[$v['pay_type']]) ? $pay_type[$v['pay_type']] : '';
            $info[$k]['status'] = isset($status[$v['status']]) ? $status[$v['status']] : '';
        }
        $res = (new ExportOrder())->order_outputProjectExcel($info);
        return json(['code' => 200, 'msg' => '导出成功', 'file' => $res]);
    }

    //退款/异常 订单列表
    public function errOrderList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $status = request()->get('status', '');//2退款 3异常
        if (!$status) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $where['o.device_sn'] = $this->getBuHuoWhere();
            } else {
                $where['o.uid'] = $user['id'];
            }
        } else {
            if (!empty($params['uid'])) {
                $where['o.uid'] = $params['uid'];
            }
        }
        if (!empty($params['order_sn'])) {
            $where['o.order_sn'] = ['like', '%' . $params['order_sn'] . '%'];
        }
        if (!empty($params['start_time'])) {
            $where['o.create_time'] = ['>=', strtotime($params['start_time'])];
        }
        if (!empty($params['end_time'])) {
            $where['o.create_time'] = ['<', strtotime($params['end_time']) + 3600 * 24];
        }
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $where['o.create_time'] = ['between', [strtotime($params['start_time']), strtotime($params['end_time']) + 3600 * 24], 'AND'];
        }
        if (!empty($params['device_sn'])) {
            $where['o.device_sn'] = ['like', '%' . $params['device_sn'] . '%'];
        }
        $where['o.status'] = $status;
        $model = new \app\index\model\FinanceOrder();
        $count = $model->alias('o')
            ->join('system_admin a', 'o.uid=a.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('o')
            ->join('system_admin a', 'o.uid=a.id', 'left')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('o.id desc')
            ->field('o.*,a.username')
            ->select();

        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }

    public function history()
    {
        $year = request()->get('year', 0);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $where['o.device_sn'] = $this->getBuHuoWhere();
            } else {
                $where['o.uid'] = $user['id'];
            }
        }
        $big_month = [1, 3, 5, 7, 8, 10, 12];
        $total_month = ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'];
        $data = [];
        $mon = $year == date('Y') ? date('m') : 12;
        for ($i = 1; $i <= $mon; $i++) {
            $month = $i >= 10 ? $i : '0' . $i;
            $last_day = $i == 2 ? ($year % 4 == 0 ? 29 : 28) : (in_array($i, $big_month) ? 31 : 30);
            $start_time = strtotime($year . '-' . $month . '-01');
            $end_time = strtotime($year . '-' . $month . '-' . $last_day) + 24 * 3600;
            $row = Db::name('finance_order')
                ->where($where)
                ->where('status', 1)
                ->where('pay_time', '>=', $start_time)
                ->where('pay_time', '<', $end_time)
                ->field('count(id) order_count,sum(price) total_price')
                ->find();
            $row['month'] = $total_month[$i - 1];
            $row['total_price'] = $row['total_price'] ?? "0.00";
            $data[] = $row;
        }
        return json(['code' => 200, 'data' => $data]);
    }

    //退款
    public function refund()
    {
        $id = request()->get('id');
        $reason = request()->get('reason');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if (empty($reason)) {
            return json(['code' => 100, 'msg' => '请输入退款原因']);
        }
        $order = (new \app\index\model\FinanceOrder())->where('id', $id)->find();
        if ($order['status'] != 1 && $order['status'] != 3 && $order['status'] != 4) {
            return json(['code' => 100, 'msg' => '只能对已完成或异常订单退款']);
        }
        //锁定异常货道
        $num = (new OrderGoods())->where('order_id', $id)->value('num');
        (new \app\index\model\MachineGoods())->where(['device_sn' => $order['device_sn'], 'num' => $num])->update(['err_lock' => 1]);
        if ($order['pay_type'] == 2) {
            include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/AopCertClient.php';
            include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/request/AlipayTradeRefundRequest.php';
            $app_id = '2021003143688161';
            //应用私钥
            $privateKeyPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/api.hnchaohai.com_私钥.txt';
            $privateKey = file_get_contents($privateKeyPath);

            $aop = new \AopCertClient ();
            $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
            $aop->appId = $app_id;
            $aop->rsaPrivateKey = $privateKey;
            //支付宝公钥证书
            $aop->alipayPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/alipayCertPublicKey_RSA2.crt';

            $aop->apiVersion = '1.0';
            $aop->signType = 'RSA2';
            $aop->postCharset = 'UTF-8';
            $aop->format = 'json';
            //调用getCertSN获取证书序列号
            $appPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/appCertPublicKey_2021003143688161.crt";
            $aop->appCertSN = $aop->getCertSN($appPublicKey);
            //支付宝公钥证书地址
            $aliPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";
            $aop->alipayCertSN = $aop->getCertSN($aliPublicKey);
            //调用getRootCertSN获取支付宝根证书序列号
            $rootCert = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";
            $aop->alipayRootCertSN = $aop->getRootCertSN($rootCert);

            $object = new \stdClass();
            $object->trade_no = $order['transaction_id'];
            $object->refund_amount = $order['price'];
            $object->refund_reason = $reason;

            $json = json_encode($object);
            $request = new \AlipayTradeRefundRequest();
            $request->setBizContent($json);

            $result = $aop->execute($request);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if (!empty($resultCode) && $resultCode == 10000) {
                $this->refundDeal($id, $reason);

                return json(['code' => 200, 'msg' => '退款成功']);
            } else {
                return json(['code' => 100, 'msg' => $result->$responseNode->sub_msg]);
            }

        } elseif ($order['pay_type'] == 1) {
            $res = $this->systemWxRefund($id, $reason, $order);
            if ($res['code'] == 100) {
                return json(['code' => 100, 'msg' => $res['msg']]);
            }
            return json(['code' => 200, 'msg' => '退款成功']);
        } elseif ($order['pay_type'] == 3) {
            $res = $this->agentWxRefund($id, $reason, $order);
            if ($res['code'] == 100) {
                return json(['code' => 100, 'msg' => $res['msg']]);
            }
            return json(['code' => 200, 'msg' => '退款成功']);
        } else {
            return json(['code' => 100, 'msg' => '只能对系统支付宝/系统微信订单进行退款,其他类型订单的退款功能暂未开放']);
        }
    }

    private function agentWxRefund($id, $reason, $order)
    {
        $orderModel = new \app\index\model\FinanceOrder();
        if ($order['refund_sn']) {
            $refund_sn = $order['refund_sn'];
        } else {
            $refund_sn = 'R' . time() . rand(1000, 9999);
            $orderModel->where('id', $id)->update(['refund_reason' => $reason, 'refund_sn' => $refund_sn]);
        }
        $mchid_info = (new MchidModel())->where('uid', $order['uid'])->where('status', 2)->find();
        $url = 'https://api.hnchaohai.com/applet/goods/refund_notify';
        $data = [
            'appid' => 'wx6fd3c40b45928f43',
            'mch_id' => $mchid_info['mchid'],
            'nonce_str' => getRand(32),
            'out_trade_no' => $order['order_sn'],
            'total_fee' => ceil($order['price'] * 100),
            'refund_fee' => ceil($order['price'] * 100),
            'out_refund_no' => $refund_sn,
            'notify_url' => $url,
        ];
        Cache::store('redis')->set('mchid_key', $mchid_info['key'], 300);
        $res = (new Wxpay())->refund($data, $mchid_info['key']);
        if ($res['return_code'] == 'SUCCESS') {
            return ['code' => 200, 'msg' => '退款提交成功,请稍后刷新查看'];
        } else {
            return ['code' => 100, 'msg' => '退款失败'];
        }
    }

    private function systemWxRefund($id, $reason, $order)
    {
        $orderModel = new \app\index\model\FinanceOrder();
        if ($order['refund_sn']) {
            $refund_sn = $order['refund_sn'];
        } else {
            $refund_sn = 'R' . time() . rand(1000, 9999);
            $orderModel->where('id', $id)->update(['refund_reason' => $reason, 'refund_sn' => $refund_sn]);
        }
//        if ($order['price'] < 0.3) {
//            $data = ["code" => 100, "msg" => "退款金额不能小于0.3元"];
//            return $data;
//        }
//        $obj = new TixianController();
//        $xml = $obj->txFunc($order['openid'], $refund_sn, $order['price'], "退款原因:" . $reason, "wx6fd3c40b45928f43");
//        $info = $obj->xmlToArray($xml);
//        if ($info["result_code"] == "SUCCESS") {
//            $this->refundDeal($order['id'], $reason = '');
//            $data = ["code" => 200, "msg" => "退款成功"];
//            return $data;
//        } else {
//            $data = ["code" => 100, "msg" => "退款失败"];
//            return $data;
//        }


        $url = 'https://api.hnchaohai.com/applet/goods/refund_notify';
        $data = [
            'appid' => 'wxfef945a30f78c17c',
            'mch_id' => '1642723027',
            'nonce_str' => getRand(32),
            'out_trade_no' => $order['order_sn'],
            'total_fee' => ceil($order['price'] * 100),
            'refund_fee' => ceil($order['price'] * 100),
            'out_refund_no' => $refund_sn,
            'notify_url' => $url,
        ];
        $res = (new Wxpay())->refund($data, 'Yxc15943579579Yxc15943579579Yxc1');
        if ($res['result_code'] == 'SUCCESS') {
            return ['code' => 200, 'msg' => '退款提交成功,请稍后刷新查看'];
        } else {
            return ['code' => 100, 'msg' => $res['err_code_des']];
        }
//        // 当前时间
//        $time = time();
//        // 生成随机字符串
//        $nonceStr = md5($time . $refund_sn);
//        // API参数
//        $params = [
//            'appid' => 'wx6fd3c40b45928f43',
//            'mch_id' => '1538520381',
//            'nonce_str' => $nonceStr,
//            'out_trade_no' => $order['order_sn'],
//            'out_refund_no' => $refund_sn,
//            'total_fee' => $order['price'] * 100,
//            'refund_fee' => $order['price'] * 100,
//            'notify_url' => 'https://api.hnchaohai.com/index/refund_notify/systemWxNotify',//退款回调地址
//        ];
//        // 生成签名
//        $params['sign'] = $this->makeSign($params);
//
//
//        // 请求API
//        $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
//        $result = $this->post($url, $this->toXml($params), true, $this->getCertPem());
////        // 请求失败
//        if (empty($result)) {
//            return ['code' => 1, 'msg' => '退款请求失败'];
//        }
//        // 格式化返回结果
//        $prepay = $this->fromXml($result);
//        trace('微信发送退款请求结果===>' . json_encode($prepay), 'refund');
//        return ['code' => 0, 'msg' => '成功'];
    }

    private function refundDeal($order_id, $reason = '')
    {
        $orderModel = new \app\index\model\FinanceOrder();
        //修改订单状态
        $update_data = [
            'status' => 2,
            'refund_time' => time()
        ];
        if ($reason) {
            $update_data['refund_reason'] = $reason;
        }
        $orderModel->where('id', $order_id)->update($update_data);
        $order_sn = $orderModel->where('id', $order_id)->value('order_sn');
        //修改代理商和分润人员余额
        $cashModel = new FinanceCash();
        $cash = $cashModel->where('order_sn', $order_sn)->select();
        $adminModel = new SystemAdmin();
        $uid = [];
        foreach ($cash as $k => $v) {
            $uid[] = $v['uid'];
        }
        $admin = $adminModel->whereIn('id', $uid)->column('system_balance', 'id');
        $cash_data = [];
        foreach ($cash as $k => $v) {
            $money = $admin[$v['uid']] - $v['price'];
            $adminModel->where('id', $v['uid'])->update(['system_balance' => $money]);
            $cash_data[] = [
                'uid' => $v['uid'],
                'order_sn' => $order_sn,
                'price' => 0 - $v['price'],
                'type' => 2
            ];
        }
        //添加余额修改记录
        $cashModel->saveAll($cash_data);
    }


    //--------------------------------用到的函数---------------------------------
    public function post($url, $data = [], $useCert = false, $sslCert = [])
    {
//        $header = [
//            'Content-type: application/json;'
//        ];
        $action = curl_init();
//        curl_setopt($curl, CURLOPT_URL, $url);
////        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
//        curl_setopt($curl, CURLOPT_HEADER, false);
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
//        curl_setopt($curl, CURLOPT_POST, TRUE);
//        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
//        if ($useCert == true) {
//            // 设置证书：cert 与 key 分别属于两个.pem文件
//            curl_setopt($curl, CURLOPT_SSLCERTTYPE, 'PEM');
//            curl_setopt($curl, CURLOPT_SSLCERT, $sslCert['certPem']);
//            curl_setopt($curl, CURLOPT_SSLKEYTYPE, 'PEM');
//            curl_setopt($curl, CURLOPT_SSLKEY, $sslCert['keyPem']);
//        }

        curl_setopt($action, CURLOPT_URL, $url);
        curl_setopt($action, CURLOPT_HEADER, 0);
        curl_setopt($action, CURLOPT_POST, 1);
        curl_setopt($action, CURLOPT_POSTFIELDS, $data);
        curl_setopt($action, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($action, CURLOPT_SSLCERTTYPE, 'pem');
        curl_setopt($action, CURLOPT_SSLCERT, $sslCert['certPem']);
        curl_setopt($action, CURLOPT_SSLKEYTYPE, 'pem');
        curl_setopt($action, CURLOPT_SSLKEY, $sslCert['keyPem']);
        curl_setopt($action, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($action, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($action, CURLOPT_CONNECTTIMEOUT, 60);


        $result = curl_exec($action);
        curl_close($action);
        return $result;
    }

    /**
     * 生成签名
     * @param $values
     * @return string 本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    private function makeSign($values)
    {
        //签名步骤一：按字典序排序参数
        ksort($values);
        $string = $this->toUrlParams($values);
        //签名步骤二：在string后加入KEY
        $string = $string . '&key=' . config('pay.KEY');
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    private function ToUrlParams($array)
    {
        $buff = "";
        foreach ($array as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 输出xml字符
     * @param $values
     * @return bool|string
     */
    private function toXml($values)
    {
        if (!is_array($values) || count($values) <= 0) {
            return false;
        }
        $xml = "<xml>";
        foreach ($values as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param $xml
     * @return mixed
     */
    private function fromXml($xml)
    {
        // 禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

    /**
     * 获取cert证书文件
     * @return array
     * @throws BaseException
     */
    private function getCertPem()
    {
        // cert目录
        return [
            'certPem' => $_SERVER['DOCUMENT_ROOT'] . 'apiclient_cert.pem',
            'keyPem' => $_SERVER['DOCUMENT_ROOT'] . '/apiclient_key.pem'
        ];
    }

    /**
     * 生成商户订单号
     */
    public function get_orderssh()
    {
        return date("YmdHis") . mt_rand(10000000, 99999999);
    }
}
