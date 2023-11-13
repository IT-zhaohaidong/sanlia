<?php


namespace app\index\common;

use think\Db;

class TixianController
{

    /**
     * 企业支付（向微信发起企业支付到零钱的请求）
     * @param string $openid 用户openID
     * @param string $trade_no 单号
     * @param string $money 金额(单位分)
     * @param string $desc 描述
     * @param string $appid 协会appid
     * @return string   XML 结构的字符串
     **/
    public function txFunc($openid, $trade_no, $money, $desc, $mchid_info)
    {
        $mchid = empty($mchid_info['key']) ? '1642723027' : $mchid_info['mchid'];
        $data = array(
            'mch_appid' => $mchid_info['appid'],//Appid
            'mchid' => $mchid,//微信支付商户号
            'nonce_str' => $this->getNonceStr(), //随机字符串
            'partner_trade_no' => $trade_no, //商户订单号，需要唯一
            'openid' => $openid,
            'check_name' => 'NO_CHECK', //OPTION_CHECK不强制校验真实姓名, FORCE_CHECK：强制 NO_CHECK：
            'amount' => $money * 100, //付款金额单位为分
            'desc' => $desc,
            'spbill_create_ip' => get_client_ip(),
            //'re_user_name' => 'jorsh', //收款人用户姓名 *选填
            //'device_info' => '1000',  //设备号 *选填
        );
//        $device_sn = Db::name('finance_order')->where('order_sn', $trade_no)->value('device_sn');
//        $row = Db::name('machine_device')->alias('d')
//            ->leftJoin('system_admin a', 'd.uid=a.id')
//            ->where('d.device_sn', $device_sn)
//            ->field('a.withdraw_type,a.shop_code,a.key')
//            ->find();
//        if ($row && $row['withdraw_type'] == 2) {
//            $data['mchid'] = $row['shop_code'];
//            $data['sign'] = $this->makeSign($data, $row['key']);
//        }
        //生成签名
        if (empty($mchid_info['key'])) {
            $data['sign'] = $this->makeSign($data);
        } else {
            $data['sign'] = $this->makeSign($data, $mchid_info['key']);
        }
        //构造XML数据（数据包要以xml格式进行发送）
        $xmldata = $this->arrToXml($data);
        //请求url
        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        //发送post请求
        $res = $this->curl_post_ssl($url, $xmldata);
        return $res;
    }

//微信支付文档

    /**
     * 随机字符串
     * @param int $length
     * @return string
     */
    public function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 签名
     * @param $data
     * @return string
     */
    public function makeSign($data, $key = '')
    {
        // 关联排序
        ksort($data);
        // 字典排序
        $str = http_build_query($data);
        // 添加商户密钥
        $str .= '&key=' . $key;
        // 清理空格
        $str = urldecode($str);
        $str = md5($str);
        // 转换大写
        $result = strtoupper($str);
        return $result;
    }

    /**
     * 数组转XML
     * @param $data
     * @return string
     */
    public function arrToXml($data)
    {
        $xml = "<xml>";
        //  遍历组合
        foreach ($data as $k => $v) {
            $xml .= '<' . $k . '>' . $v . '</' . $k . '>';
        }
        $xml .= '</xml>';
        return $xml;
    }

    function arr_to_xml($data, $root = true)
    {
        $str = "";
        if ($root) {
            $str .= "<xml>";
        }
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $child = arr_to_xml($val, false);
                $str .= "<$key>$child</$key>";
            } else {
                $str .= "<$key><![CDATA[$val]]></$key>";
            }
        }
        if ($root) {
            $str .= "</xml>";
        }
        return $str;
    }

    /**
     * XML转数组
     * @param string
     * return $data
     * */
    public function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }



//证书

    /**
     * [curl_post_ssl 发送curl_post数据]
     * @param  [type]  $url     [发送地址]
     * @param  [type]  $xmldata [发送文件格式]
     * @param  [type]  $second [设置执行最长秒数]
     * @param  [type]  $aHeader [设置头部]
     * @return [type]           [description]
     */
    public function curl_post_ssl($url, $xmldata, $second = 30, $aHeader = array())
    {
        $isdir = $_SERVER['DOCUMENT_ROOT'] . "/";//证书位置;绝对路径
        $ch = curl_init();//初始化curl

        curl_setopt($ch, CURLOPT_TIMEOUT, $second);//设置执行最长秒数
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');//证书类型
        curl_setopt($ch, CURLOPT_SSLCERT, $isdir . 'apiclient_cert.pem');//证书位置
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');//CURLOPT_SSLKEY中规定的私钥的加密类型
        curl_setopt($ch, CURLOPT_SSLKEY, $isdir . 'apiclient_key.pem');//证书位置
//        curl_setopt($ch, CURLOPT_CAINFO, 'PEM');
//        curl_setopt($ch, CURLOPT_CAINFO, $isdir . 'rootca.pem');
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);//设置头部
        }
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmldata);//全部数据使用HTTP协议中的"POST"操作来发送


        $data = curl_exec($ch);//执行回话

        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }

}
