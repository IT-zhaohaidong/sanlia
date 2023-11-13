<?php

namespace app\index\controller;

use app\index\common\AesUtil;
use app\index\model\FinanceCash;
use app\index\model\SystemAdmin;

class RefundNotify
{
    public function systemWxNotify()
    {
        $data = file_get_contents('php://input');
        $data = $this->FromXml($data);
        trace(json_encode($data), '支付回调数据');
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $sign = $this->makeSign($data);//回调验证签名

        $str_success = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        $str_error = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';

        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS') && ($data['result_code'] == 'SUCCESS')) {
            // 支付成功 进行你的逻辑处理
        }
        echo $str_success;//str_error 告知微信 你已的逻辑处理完毕 不用再推送或再次推送你结果
    }

    private function refundDeal($order_id, $reason='')
    {
        $orderModel = new \app\index\model\FinanceOrder();
        //修改订单状态
        $update_data = [
            'status' => 2,
            'refund_time' => time()
        ];
        if ($reason){
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
}