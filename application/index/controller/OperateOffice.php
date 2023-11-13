<?php

namespace app\index\controller;

use app\index\common\Tencent;
use app\index\common\Wxpay;
use app\index\model\OperateOfficialModel;

class OperateOffice extends BaseController
{
    public function getList()
    {
        $user = $this->user;
        $params = request()->get();
        $limit = request()->get('limit', 15);
        $page = request()->get('page', 1);
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['o.uid'] = $user['parent_id'];
            } else {
                $where['o.uid'] = $user['id'];
            }
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (!empty($params['name'])) {
            $where['o.name'] = $params['name'];
        }
        if (!empty($params['appid'])) {
            $where['o.appid'] = $params['name'];
        }
        $where['o.status'] = ['>', 0];
        $model = new OperateOfficialModel();
        $count = $model->alias('o')
            ->join('system_admin a', 'a.id=o.uid', 'left')
            ->where($where)->count();
        $list = $model->alias('o')
            ->join('system_admin a', 'a.id=o.uid', 'left')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->field('o.* ,a.username')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function save()
    {
        $user = $this->user;
        $data = request()->post();
        if (empty($data['appid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OperateOfficialModel();
        if (empty($data['id'])) {
            $count = $model
                ->where(['uid' => $user['id']])
                ->where('status', '>', 0)
                ->count();
            $total_fee = 0;
            if ($count > 0) {
                $data['order_sn'] = 'O' . time() . mt_rand(1000, 9999);
                $data['status'] = 0;
                $total_fee = 0.01;
            } else {
                $data['status'] = 1;
            }
            $data['money'] = $total_fee;
            $row = $model
                ->where(['uid' => $user['id'], 'appid' => $data['appid']])
                ->where('status', '>', 0)
                ->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该公众号已存在']);
            }
            $data['create_time'] = time();
            $data['uid'] = $user['id'];
            $order_id = $model->insertGetId($data);
            if ($count > 0) {
                $pay = new Wxpay();
                $order_sn = $data['order_sn'];
                $notify_url = 'https://api.hnchaohai.com/index/operate_office/notify';
                $result = $pay->prepay('', $data['order_sn'], $total_fee, $notify_url, 'NATIVE');
                if ($result['return_code'] == 'SUCCESS') {
                    $data = [
                        'order_id' => $order_id,
                        'order_sn' => $order_sn,
                        'money' => $total_fee,
                        'url' => $result['code_url']
                    ];
                    return json(['code' => 201, 'data' => $data]);
                }
            }
        } else {
            $model->where('id', $data['id'])->update($data);
        }

        return json(['code' => 200, 'msg' => '保存成功']);
    }

    //重新获取二维码
    public function getUrl()
    {
        $id = request()->post('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OperateOfficialModel();
        $order = $model->where('id', $id)->find();
        $notify_url = 'https://api.hnchaohai.com/index/operate_office/notify';
        $pay = new Wxpay();
        $result = $pay->prepay('', $order['order_sn'], $order['money'], $notify_url, 'NATIVE');
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

    public function getBindDevice()
    {
        $id = request()->get('id', '');
        $user = $this->user;
        $deviceModel = new \app\index\model\MachineDevice();
        $ids = $deviceModel->where('oid', $id)->column('id');
        $ids = $ids ? array_values($ids) : [];
        $where = [];
        if ($user['role_id'] > 1) {
            if ($user['role_id'] > 5) {
                $where['uid'] = $user['parent_id'];
            } else {
                $where['uid'] = $user['id'];
            }
        }
        $device = $deviceModel
            ->where($where)
            ->where('delete_time', null)
            ->field('id,device_sn,device_name')
            ->select();
        foreach ($device as $k => $v) {
            if (empty($v['device_name'])) {
                $device[$k]['device_name'] = $v['device_sn'];
            }
        }
        return json(['code' => 200, 'data' => $device, 'check' => $ids]);
    }

    public function bindDevice()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device_ids = request()->get('device_ids/a');
        $official = (new OperateOfficialModel())->find($id);
        if ($official['status'] == 0) {
            return json(['code' => 100, 'msg' => '该公众号暂未开放,请联系管理员进行处理']);
        }
        $deviceModel = new \app\index\model\MachineDevice();
        $deviceModel->where('oid', $id)->whereNotIn('id', $device_ids)->update(['oid' => '', 'official_code' => '']);
        $device = $deviceModel->whereIn('id', $device_ids)->column('device_sn', 'id');
        $tencent = new Tencent();
        foreach ($device_ids as $k => $v) {
            $official_code = $tencent->getImage($official['appid'], $official['appsecret'], $device[$v]);
            $deviceModel->where('id', $v)->update(['official_code' => $official_code, 'oid' => $id]);
        }
        return json(['code' => 200, 'msg' => '绑定成功']);
    }

    public function check()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['status'])) {
            return json(['code' => 100, 'msg' => '缺少参数!']);
        }
        $model = new OperateOfficialModel();
        $model->where('id', $params['id'])->update(['status' => $params['status']]);
        return json(['code' => 200, 'msg' => '操作成功']);
    }

    //获取付款进度
    public function getOrderStatus()
    {
        $id = request()->post('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OperateOfficialModel();
        $order = $model->where('id', $id)->find();
        if ($order['status'] > 0) {
            return json(['code' => 200, 'msg' => '付款成功']);
        } else {
            $result = (new Wxpay())->orderInfo($order['order_sn']);
            trace($result, '商户号申请支付结果');
            if (!empty($result['trade_state']) && $result['trade_state'] == 'SUCCESS') {
                $model->where('id', $id)->update(['status' => 1, 'transaction_id' => $result['transaction_id']]);
                return json(['code' => 200, 'msg' => '付款成功']);
            }
            return json(['code' => 100, 'msg' => '暂未付款']);
        }
    }

    //支付回调
    public function notify()
    {
        $xml = file_get_contents("php://input");
        //将服务器返回的XML数据转化为数组
        $data = self::xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $pay = new Wxpay();
        $sign = $pay->makeSign($data, 'wgduhzmxasi8ogjetftyio111imljs2j');

        // 判断签名是否正确  判断支付状态
        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS')) {
            $result = $data;
            //获取服务器返回的数据
            $out_trade_no = $data['out_trade_no'];        //订单单号
            $openid = $data['openid'];                    //付款人openID
            $total_fee = $data['total_fee'];            //付款金额
            $transaction_id = $data['transaction_id'];    //微信支付流水号
            //支付成功的业务逻辑
            (new OperateOfficialModel())
                ->where('order_sn', $out_trade_no)
                ->save(['status' => 1, 'transaction_id' => $transaction_id]);

        } else {
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result) {
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        } else {
            $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        return $result;
    }

    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    public function array2xml($arr, $level = 1)
    {
        $s = $level == 1 ? "<xml>" : '';
        foreach ($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s . "</xml>" : $s;
    }

    /**
     * 将xml转为array
     * @param string $xml xml字符串
     * @return array    转换得到的数组
     */
    public function xml2array($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }

    /**
     * 错误返回提示
     * @param string $errMsg 错误信息
     * @param string $status 错误码
     * @return  json的数据
     */
    protected function return_err($errMsg = 'error', $status = 0)
    {
        exit(json_encode(array('status' => $status, 'result' => 'fail', 'errmsg' => $errMsg)));
    }
}
