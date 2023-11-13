<?php

namespace app\index\controller;

use app\index\common\TixianController;
use app\index\model\FinanceWithdraw;
use app\index\model\MchidModel;
use app\index\model\SystemAdmin;

class Withdraw extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $status = request()->get('status', '');
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $user['id'] = $user['parent_id'];
            }
            $where['c.uid'] = $user['id'];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = $params['username'];
        }
        if ($status !== '') {
            $where['c.status'] = $status;
        }
        if (!empty($params['start_time'])) {
            $where['c.create_time'] = ['>=', strtotime($params['start_time'])];
        }
        if (!empty($params['end_time'])) {
            $where['c.create_time'] = ['<', strtotime($params['end_time']) + 3600 * 24];
        }
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $where['c.create_time'] = ['between', [strtotime($params['start_time']), strtotime($params['end_time']) + 3600 * 24], 'AND'];
        }
        $model = new \app\index\model\FinanceWithdraw();
        $count = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->field('c.*,a.username')
            ->order(['c.id desc', 'c.status asc'])
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['result_time'] = $v['result_time'] ? date('Y-m-d H:i:s', $v['result_time']) : '';
        }

        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }

    public function refuse()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }

        $model = new FinanceWithdraw();
        $row = $model->where('id', $id)->find();
        $admin = (new SystemAdmin())->where('id', $row['uid'])->find();
        $model->where('id', $id)->update(['status' => 2, 'result_time' => time()]);
        $money = $row['money'];
        if ($row['type'] == 1) {
            $data = ['system_balance' => $admin['system_balance'] + $money];
        }
        if ($row['type'] == 2) {
            $data = ['agent_wx_balance' => $admin['agent_wx_balance'] + $money];
        }
        if ($row['type'] == 3) {
            $data = ['agent_ali_balance' => $admin['agent_ali_balance'] + $money];
        }
        (new SystemAdmin())->where('id', $row['uid'])->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function agree()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new FinanceWithdraw();
        $row = $model->where('id', $id)->find();
        $admin = (new SystemAdmin())->where('id', $row['uid'])->find();
        if (!$admin) {
            return json(['code' => 100, 'msg' => '数据错误!']);
        }
        if ($row['type'] == 1 || $row['type'] == 2) {
            if (empty($admin['openid'])) {
                return json(['code' => 100, 'msg' => '请先绑定提现微信']);
            }
            if ($row['type'] == 2) {
                //当设备所属人未开通商户号时,判断父亲代理商是否开通,若未开通,用系统支付,若开通,用父亲代理商商户号进行支付
                $parentId = [];
                $parentUser = (new SystemAdmin())->getParents($row['uid'], 1);
                foreach ($parentUser as $k => $v) {
                    $parentId[] = $v['id'];
                }
                $userList = (new SystemAdmin())->whereIn('id', $parentId)->select();
                $is_set_mchid = false;
                $mchid_uid = 0;
                $wx_mchid_id = 0;
                foreach ($userList as $k => $v) {
                    if ($v['is_wx_mchid'] == 1 && $v['wx_mchid_id']) {
                        $is_set_mchid = true;
                        $mchid_uid = $v['id'];
                        $wx_mchid_id = $v['wx_mchid_id'];
                        break;
                    }
                }
//                $mchid_info = (new SystemAdmin())->alias('a')
//                    ->join('mchid_apply m', 'a.wx_mchid_id=m.id', 'left')
//                    ->where('a.id', $admin['parent_id'])
//                    ->field('m.*')->find();
                if (!$is_set_mchid || !$wx_mchid_id) {
                    return json(['code' => 100, 'msg' => '代理商商户号信息异常!']);
                }
                $mchid_info = (new MchidModel())->where('id', $wx_mchid_id)->find();
                $mchid_detail = [
                    'appid' => "wx6fd3c40b45928f43",
                    'key' => $mchid_info['key'],
                    'mchid' => $mchid_info['mchid'],
                ];
            } else {
                $mchid_detail = [
                    'appid' => "wx6fd3c40b45928f43"
                ];
            }
            $obj = new TixianController();
            $xml = $obj->txFunc($admin['openid'], $row['order_sn'], $row['arrival_amount'], " 用户提现", $mchid_detail);
            $info = $obj->xmlToArray($xml);
            if ($info["result_code"] == "SUCCESS") {
                //提现成功逻辑
                $model->where('id', $id)->update(['status' => 1]);
                $data = ["code" => 200, "msg" => "提现成功"];
                return $data;
            } else {
                $data = ["code" => 100, "msg" => "提现失败"];
                return $data;
            }
        } else {
            return json(['code' => 100, 'msg' => '该功能尚未开放']);
//            if (empty($admin['ali_openid'])) {
//                return json(['code' => 100, 'msg' => '请先绑定提现支付宝']);
//            }
//            include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/AopCertClient.php';
//            include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/request/AlipayFundTransUniTransferRequest.php';
//            $app_id = '2021003143688161';
//            //应用私钥
//            $privateKeyPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/api.hnchaohai.com_私钥.txt';
//            $privateKey = file_get_contents($privateKeyPath);
//
//            $aop = new \AopCertClient ();
//            $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
//            $aop->appId = $app_id;
//            $aop->rsaPrivateKey = $privateKey;
//            //支付宝公钥证书
//            $aop->alipayPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/alipayCertPublicKey_RSA2.crt';
//
//            $aop->apiVersion = '1.0';
//            $aop->signType = 'RSA2';
//            $aop->postCharset = 'UTF-8';
//            $aop->format = 'json';
//            //调用getCertSN获取证书序列号
//            $appPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/appCertPublicKey_2021003143688161.crt";
//            $aop->appCertSN = $aop->getCertSN($appPublicKey);
//            //支付宝公钥证书地址
//            $aliPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";;
//            $aop->alipayCertSN = $aop->getCertSN($aliPublicKey);
//            //调用getRootCertSN获取支付宝根证书序列号
//            $rootCert = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";
//            $aop->alipayRootCertSN = $aop->getRootCertSN($rootCert);
//
//            $object = new \stdClass();
//            $object->out_biz_no = $row['order_sn'];
//            $object->trans_amount = $row['monry'];
//            $object->order_title = '用户提现';
//            $object->product_code = 'TRANS_ACCOUNT_NO_PWD';
//            $object->biz_scene = 'DIRECT_TRANSFER';
//            $object->payee_info = [
//                'identity'=>$admin['ali_openid'],
//                'identity_type'=>$admin['ALIPAY_USER_ID']
//            ];
//            $json = json_encode($object);
//            $request = new \AlipayFundTransUniTransferRequest();
//            $request->setNotifyUrl('http://api.hnchaohai.com/applet/ali_pay/systemNotify');
//            $request->setBizContent($json);
//
//            $result = $aop->execute($request);
        }
    }
}
