<?php


namespace app\index\controller;


use app\index\common\Wxpay;
use app\index\model\PackageOrderModel;
use think\Db;

class PackageOrder extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $limit = request()->get('limit', 15);
        $page = request()->get('page', 1);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $user['id'] = $user['parent_id'];
            }
            $where['o.uid'] = ['=', $user['id']];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (!empty($params['device_sn'])) {
            $where['o.device_sn'] = ['like', '%' . $params['device_sn'] . '%'];
        }
        if (!empty($params['status']) && $params['status'] > 0) {
            $where['o.status'] = ['=', $params['status']];
        }
        $model = new PackageOrderModel();
        $count = $model->alias('o')
            ->join('system_admin a', 'a.id=o.uid', 'left')
            ->where('o.status', '>', 0)
            ->where($where)
            ->field('o.*,a.username')->count();
        $list = $model->alias('o')
            ->join('system_admin a', 'a.id=o.uid', 'left')
            ->field('o.*,a.username')
            ->where('o.status', '>', 0)
            ->where($where)
            ->order('o.id desc')
            ->page($page)
            ->limit($limit)
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['pay_time'] = date('Y-m-d H:i:s', $v['pay_time']);
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //完成充值
    public function complate()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new PackageOrderModel();
        $row = $model->where('id', $id)->find();
        if ($row['status'] == 2) {
            return json(['code' => 100, 'msg' => '不可重复操作']);
        }
        $model->where('id', $id)->update(['status' => 2]);
        $data = ["code" => 200, "msg" => "操作成功"];
        return json($data);
    }

    //购买套餐
    public function buyPackage()
    {
        $post = request()->post();
        if (empty($post['id'] || empty($post['package_id']))) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new \app\index\model\MachineDevice())->find($post['id']);
        if (empty($device['iccid']) || $device['iccid'] == '0000000000') {
            return json(['code' => 100, 'msg' => '未配置iccid']);
        }
        $package = Db::name('package')->find($post['package_id']);
        $uid = $this->user['id'];
        $order_sn = time() . $uid . rand(1000, 9999);
        $data = [
            'order_sn' => $order_sn,
            'price' => $package['price'],
            'iccid' => $device['iccid'],
            'device_sn' => $device['device_sn'],
            'uid' => $uid,
            'title' => $package['title'],
            'package_id' => $package['id'],
            'status' => 0,
        ];
        $order_id = (new PackageOrderModel())->insertGetId($data);
        $pay = new Wxpay();
        $order_sn = $data['order_sn'];
        $notify_url = 'https://api.hnchaohai.com/applet/wxpay/packageNotify';
        $result = $pay->prepay('', $order_sn, $package['price'], $notify_url, 'NATIVE');
        if ($result['return_code'] == 'SUCCESS') {
            $data = [
                'order_id' => $order_id,
                'order_sn' => $order_sn,
                'money' => $package['price'],
                'url' => $result['code_url']
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'msg' => '创建支付失败']);
        }
    }


    //获取付款进度
    public function getOrderStatus()
    {
        $id = request()->post('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new PackageOrderModel();
        $order = $model->where('id', $id)->find();
        if ($order['status'] > 0) {
            return json(['code' => 200, 'msg' => '付款成功']);
        } else {
            $result = (new Wxpay())->orderInfo($order['order_sn']);
            trace($result, '购买套餐支付结果');
            if (!empty($result['trade_state']) && $result['trade_state'] == 'SUCCESS') {
                $result['out_trade_no'] = $order['order_sn'];
                (new \app\applet\controller\Wxpay())->packageDeal($result);
                return json(['code' => 200, 'msg' => '付款成功']);
            }
            return json(['code' => 100, 'msg' => '暂未付款']);
        }
    }

    //重新获取二维码
    public function getUrl()
    {
        $id = request()->post('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new PackageOrderModel();
        $order = $model->where('id', $id)->find();
        $notify_url = 'https://api.hnchaohai.com/applet/wxpay/packageNotify';
        $pay = new Wxpay();
        $result = $pay->prepay('', $order['order_sn'], $order['price'], $notify_url, 'NATIVE');
        if ($result['return_code'] == 'SUCCESS') {
            $data = [
                'order_id' => $id,
                'url' => $result['code_url']
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'msg' => $result['return_msg']]);
        }
    }
}
