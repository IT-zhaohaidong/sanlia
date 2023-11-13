<?php

namespace app\applet\controller;

use app\index\common\AliMsg;
use app\index\common\QiChaCha;
use app\index\controller\CompanyInfo;
use app\index\model\FinanceOrder;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use app\index\model\OperateQuestionnaireModel;
use app\index\model\OperateUserModel;
use app\index\model\OrderGoods;
use think\Cache;
use think\Controller;
use think\Db;
use function AlibabaCloud\Client\value;

class Company extends Controller
{
    //获取提交的企业信息
    public function getCompany()
    {
        $post = request()->post();
        if (empty($post['code']) || empty($post['phone']) || empty($post['creditCode']) || empty($post['companyName']) || empty($post['operName'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $code = Cache::store('redis')->get($post['phone']);
        if (!$code) {
            return json(['code' => 100, 'msg' => '请重新获取验证码']);
        }
        if (!$post['code'] || $code != $post['code']) {
            return json(['code' => 100, 'msg' => '验证码错误']);
        } else {
            Cache::store('redis')->rm($post['phone']);
        }
        $uid = (new MachineDevice())->where('device_sn', $post['device_sn'])->value('uid');
        $res = (new OperateUserModel())
            ->where('creditCode', trim($post['creditCode']))
            ->where('status', 1)
            ->where('uid', $uid)
            ->find();
        if ($res) {
            return json(['code' => 100, 'msg' => '该企业已被使用']);
        }
        $user = (new OperateUserModel())->where('openid', $post['openid'])->find();
        if (!$user) {
            return json(['code' => 400, 'msg' => '未授权']);
        }
        if ($user['is_free_by_company'] == 1) {
            return json(['code' => 200, 'msg' => '您已经领取过了']);
        }

        $qiCc = new QiChaCha();
        $res = $qiCc->verifyCompany(trim($post['creditCode']), trim($post['companyName']), trim($post['operName']));
        trace($res, '企业核验结果');
        $result = json_decode($res, true);
        if (isset($result['Result']) && $result['Result']['VerifyResult'] == 1) {
            //查询企业信息
            $searchRes = $qiCc->getBasicDetails(trim($post['creditCode']));
            trace($searchRes, '企业工商照面查询结果');
            $searchResult = json_decode($searchRes, true);
            if ($searchResult['Status'] == 200) {
                $info = $searchResult['Result'];
                $data = [
                    'uid' => $uid,
                    'keyNo' => $info['KeyNo'],
                    'openid' => $post['openid'],
                    'company_name' => $info['Name'],
                    'credit_code' => $info['CreditCode'],
                    'start_date' => $info['StartDate'],
                    'operName' => $info['OperName'],
                    'company_status' => $info['Status'],
                    'no' => $info['No'],
                    'address' => $info['Address'],
                    'phone' => $post['phone'],
                    'status' => 1,
                    'belongOrg' => $info['BelongOrg'],
                    'end_date' => $info['EndDate'],
                    'province' => $info['Province'],
                    'register_capi' => $info['RegistCapi'],
                    'econKind' => $info['EconKind'],
                    'scope' => $info['Scope'],
                    'termStart' => $info['TermStart'],
                    'termEnd' => $info['TeamEnd'],
                    'checkDate' => $info['CheckDate'],
                    'orgNo' => $info['OrgNo'],
                    'isOnStock' => $info['IsOnStock'],
                    'stockNumber' => $info['StockNumber'],
                    'stockType' => $info['StockType'],
                    'imageUrl' => $info['ImageUrl'],
                    'recCap' => $info['RecCap'],
                    'create_time' => time()
                ];
                Db::name('operate_company')->insert($data);
                $res = $this->freeGet($post);
                return json($res);
            } else {
                $data = [
                    'uid' => $uid,
                    'credit_code' => $post['creditCode'],
                    'company_name' => $post['companyName'],
                    'operName' => $post['operName'],
                    'phone' => $post['phone'],
                    'openid' => $post['openid'],
                    'status' => 0,
                    'create_time' => time()
                ];
                Db::name('operate_company')->insert($data);
                return json(['code' => 100, 'msg' => $searchResult['Message']]);
            }
        } else {
            $data = [
                'uid' => $uid,
                'credit_code' => $post['creditCode'],
                'company_name' => $post['companyName'],
                'operName' => $post['operName'],
                'phone' => $post['phone'],
                'openid' => $post['openid'],
                'status' => $result['Result']['VerifyResult'],
                'create_time' => time()
            ];
            Db::name('operate_company')->insert($data);
            $msg = [
                0 => "统一社会信用代码有误",
                1 => "一致",
                2 => "企业名称不一致",
                3 => "法定代表人名称不一致"
            ];
            return json(['code' => 100, 'msg' => $msg[$result['Result']['VerifyResult']]]);
        }
    }

    //模糊搜索企业列表
    public function getCompanyList()
    {
        $company = request()->get('company', '');
        if (!$company) {
            return json(['code' => 100, 'msg' => '请输入公司名称']);
        }
        $qiCc = new QiChaCha();
        $res = $qiCc->fuzzySearch($company);
        trace($res, '企业核验结果');
        $result = json_decode($res, true);
        $list = $result['Result'];
        return json(['code' => 200, 'data' => $list]);
    }

    //保存问卷调查
    public function saveQuestionnaire()
    {
        $post = request()->post();
        $code = Cache::store('redis')->get($post['phone']);
        if (!$code) {
            return json(['code' => 100, 'msg' => '请重新获取验证码']);
        }
        if (!$post['device_sn']) {
            return json(['code' => 100, 'msg' => '设备信息缺失']);
        }
        if (!$post['code'] || $code != $post['code']) {
            return json(['code' => 100, 'msg' => '验证码错误']);
        }
        $uid = (new MachineDevice())->where('device_sn', $post['device_sn'])->value('uid');
        $model = new OperateQuestionnaireModel();
        $res = $model->where('phone', $post['phone'])->find();
        if ($res) {
            return json(['code' => 100, 'msg' => '您已经领取过了']);
        }
//        $user = (new OperateUserModel())->where('openid', $post['openid'])->find();
//        if (!$user) {
//            return json(['code' => 400, 'msg' => '未授权']);
//        }
//        if ($user['is_free_by_company'] == 1) {
//            return json(['code' => 200, 'msg' => '您已经领取过了']);
//        }
        Cache::store('redis')->rm($post['phone']);
        $data = [
            'openid' => $post['openid'],
            'name' => $post['name'],
            'phone' => $post['phone'],
            'company' => $post['company'],
            'business' => $post['business'],
            'job' => $post['job'],
            'need' => $post['need'],
            'remark' => $post['remark'],
            'device_sn' => $post['device_sn'],
            'uid' => $uid
        ];
        $model = new OperateQuestionnaireModel();
        $model->save($data);
        $res = $this->freeGet($post);
        return $res;
    }

    public function sendMsg()
    {
        $phone = request()->get('phone', '');
        if (!$phone) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $preg_phone = '/^1[3456789]\d{9}$/ims';
        if (!preg_match($preg_phone, $phone)) {
            return json(['code' => 100, 'msg' => '手机号不合法']);
        }
        $aliMsg = new AliMsg();
        $code = rand(10000, 99999);
        $tempId = 'SMS_230640135';
        $signName = '菲尼克斯电气';
        $res = $aliMsg->sendSms($phone, $code, $tempId, $signName);
        if ($res['status'] == 1) {
            Cache::store('redis')->set($phone, $code, 900);
            return json(['code' => 200, 'msg' => '发送成功']);
        } else {
            return json(['code' => 100, 'msg' => '发送失败']);
        }
    }

    //免费领取
    public function freeGet($post)
    {
        $device = (new MachineDevice())->where("device_sn", $post['device_sn'])->field("device_sn,imei,supply_id")->find();
        if ($device['supply_id'] == 3) {
            $bool = (new Goods())->device_status($post['device_sn']);
            if (!$bool) {
                return json(["code" => 100, "msg" => "设备不在线"]);
            }
        }
        $device_num = (new MachineGoods())->where(['device_sn' => $post['device_sn']])->where('stock', '>', 0)->find();
        if (!$device_num) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }
        if ($device['device_sn'] == 'TUIUOB') {

            $nums = (new MachineGoods())->where(['device_sn' => $post['device_sn']])->where('stock', '>', 0)->column('num');
            $last_order = (new FinanceOrder())->where(['device_sn' => $post['device_sn'], 'status' => 1])->order('id desc')->find();
            if (!$last_order) {
                $last_num = 2;
            } else {
                $last_num = (new OrderGoods())->where('order_id', $last_order['id'])->value('num');
            }
            $num = $this->getNum($last_num, $nums);
            $device_num = (new MachineGoods())->where(['device_sn' => $post['device_sn'],'num'=>$num])->find();
//            if ($device_num['stock'] < 1) {
//                $device_num = (new MachineGoods())->where(['device_sn' => $post['device_sn']])->where('num', 4)->find();
//                if ($device_num['stock'] < 1) {
//                    $device_num = (new MachineGoods())->where(['device_sn' => $post['device_sn']])->where('num', 3)->find();
//                    if ($device_num['stock'] < 1) {
//                        $device_num = (new MachineGoods())->where(['device_sn' => $post['device_sn']])->where('num', 2)->find();
//                        if ($device_num['stock'] < 1) {
//                            return json(['code' => 100, 'msg' => '库存不足']);
//                        }
//                    }
//                }
//            }
        }
        $uid = (new MachineDevice())->where("device_sn", $post['device_sn'])->value("uid");
        $order_data['uid'] = $uid;
        $order_data['price'] = 0;
        $order_data['device_sn'] = $post['device_sn'];
        $order_data['count'] = 1;
        $order_data['create_time'] = time();
        $num = $device_num['num'];
        $order_data['pay_type'] = 6;
        $order_data['openid'] = $post['openid'];
        $order_data['status'] = 4;
        $order_sn = time() . mt_rand(1000, 9999);
        $order_data['order_sn'] = $order_sn;
        $order_obj = new FinanceOrder();
        $order_id = $order_obj->insertGetId($order_data);
        //添加订单商品
        $goods_data = [
            'order_id' => $order_id,
            'device_sn' => $device_num['device_sn'],
            'num' => $num,
            'goods_id' => $device_num['goods_id'],
            'price' => 0,
            'count' => 1,
            'total_price' => 0
        ];
        (new OrderGoods())->save($goods_data);
        //出货
        $data = [
            'transaction_id' => '',
            'out_trade_no' => $order_sn,
        ];
        (new Goods())->orderDeal($data, 6);
        return json(['code' => 200, 'msg' => '领取成功']);
    }

    public function getNum($last_num, $nums)
    {
        $arr = [1, 4, 3, 2];
        $nums = array_values($nums);
        $key = -1;//此次出货货道
        foreach ($arr as $k => $v) {
            if ($last_num == $v) {
                $key = $k == 3 ? 0 : $k + 1;
                break;
            }
        }
        if (in_array($arr[$key], $nums)) {
            return $arr[$key];
        } else {
            return $this->getNum($arr[$key], $nums);
        }
    }
}