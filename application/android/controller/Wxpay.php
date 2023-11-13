<?php


namespace app\android\controller;


use app\index\model\FinanceOrder;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use app\index\model\OrderGoods;
use think\Db;

/**
 * 微信支付
 */
class Wxpay
{
    /**
     * 初始化
     */
    protected $config;

    public function __construct()
    {
        header("Content-type: text/html; charset=utf-8");
        $this->config ['appid'] = ""; // 微信公众号身份的唯一标识
        $this->config ['appsecret'] = ""; // appsecret
        $this->config ['mchid'] = ""; // 商户ID
        $this->config ['key'] = ""; // 商户支付密钥Key
        $this->config ['notifyurl'] = "https://api.hnchaohai.com/android/Wxpay/payNotify"; //支付成功后通知的url
    }

    /**
     * 预支付请求接口(POST)
     * @param string $openid openid
     * @param string $body 商品简单描述
     * @param string $order_sn 订单编号
     * @param string $total_fee 金额
     * @return  json的数据
     */
    public function prepay($openid, $order_sn, $total_fee, $type = "NATIVE")
    {
        //$openid = "用户的openid"; //或者从自己的数据中进行读取
        //$order_sn = "微信的订单号"; // 自己定义
        //$total_fee = "总价钱"; // 单位分
        //统一下单参数构造
        $unifiedorder = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mchid'],
            'nonce_str' => self::getNonceStr(),
            'body' => "匪石科技",
            'out_trade_no' => $order_sn,
            'total_fee' => $total_fee * 100,
            'spbill_create_ip' => $_SERVER["REMOTE_ADDR"],
            'notify_url' => $this->config['notifyurl'],
            'trade_type' => $type, // JSAPI为公众号支付 NATIVE 为扫码支付
        );
        if ($openid) {
            $unifiedorder['openid'] = $openid;
        }
        $unifiedorder['sign'] = self::makeSign($unifiedorder);

        // 统一下单接口 请求数据
        $xmldata = self::array2xml($unifiedorder);
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $res = self::https_request($url, $xmldata);
        if (!$res) {
            self::return_err("Can't connect the server");
        }
        $content = self::xml2array($res);
        if (!empty($content['result_code'])) {
            if (strval($content['result_code']) == 'FAIL') {
                self::return_err(strval($content['err_code_des']));
            }
        }
        $content['time'] = time();
        return $content;
    }

    /**
     * 进行微信支付回调后的处理
     * TODO 此处的后续处理为本人的专属逻辑，请自行补充对应的业务逻辑即可
     * @param $result
     * TODO 强烈建议将其转化为 json 字符串形式，保存在数据表中，方便后期的微信退款操作
     * tip:$wx_pay_result_json = json_encode($result);
     */
    public function payNotifyOrderDeal($result)
    {
        // 自己的逻辑
        // 将订单状态改为已付款
        $order = Db::name('finance_order')
            ->where('order_sn', $result['out_trade_no'])
            ->find();
        if ($order['status'] > 0) {
            return false;
        }
        Db::name('finance_order')
            ->where('id', $order['id'])
            ->update(['openid' => $result['openid'], 'pay_time' => time(), 'status' => 1, 'pay_sn' => $result['transaction_id']]);
        //管理员增加余额
        $uid = $order['uid'];
        $total_fee = $result['total_fee'] / 100;
        $data = [
            'uid' => $uid,
            'type' => 1,
            'price' => $total_fee,
            'order_sn' => $result['out_trade_no'],
            'create_time' => time()
        ];
        Db::name('finance_cash')->insert($data);
        $admin = Db::name('system_admin')->where('id', $uid)->find();
        Db::name('system_admin')->where('id', $uid)->update(['balance' => $admin['balance'] + $total_fee]);
        $result = $this->Shipment($result['out_trade_no']);
    }

    /**
     * 出货
     */
    public function Shipment($order_sn)
    {
        $order_obj = new FinanceOrder();
        $order_info = $order_obj->where("order_sn", $order_sn)->find();
        $imei = $order_info['imei'];
        if (!empty($imei)) {
            //出货

            if (true) {
                //减库存
                $orderGoods = (new OrderGoods())->where('order_id', $order_info['id'])->select();
                $num = [];
                foreach ($orderGoods as $k => $v) {
                    $num[] = $v['num'];
                }
                $machineGoods = (new MachineGoods())->where('imei', $imei)->whereIn('num', $num)->column('stock,id', 'num');
                $data = [];
                foreach ($orderGoods as $k => $v) {
                    $data[] = [
                        'id' => $machineGoods[$v['num']]['id'],
                        'stock' => $machineGoods[$v['num']]['stock'] - $v['count'],
                    ];
                }
                (new MachineGoods())->isUpdate(true)->saveAll($data);
                return ['code' => 200, 'msg' => '出货成功'];
            } else {
                return ['code' => 100, 'msg' => '出货失败'];
            }
        } else {
            return ['code' => 200, 'msg' => '出货成功'];
        }

    }

    /**
     * 微信支付回调验证
     * @return array|bool
     */
    public function PayNotify()
    {
        $xml = file_get_contents("php://input");
        //将服务器返回的XML数据转化为数组
        $data = self::xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $sign = self::makeSign($data);
//
//         判断签名是否正确  判断支付状态
        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS')) {

            $result = $data;
            //获取服务器返回的数据
            $out_trade_no = $data['out_trade_no'];        //订单单号
            $openid = $data['openid'];                    //付款人openID
            $total_fee = $data['total_fee'];            //付款金额
            $transaction_id = $data['transaction_id'];    //微信支付流水号
            //支付成功的业务逻辑
            $this->payNotifyOrderDeal($result);

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
     * 退款请求接口(POST)
     * @param string $openid openid
     * @param string $order_sn 订单编号
     * @param string $total_fee 金额
     * @return  json的数据
     */
    public function refund($out_trade_no, $price, $remark, $uid)
    {
        $total_fee = $price * 100;
        $unifiedorder = array(
            'out_trade_no' => $out_trade_no,
            'out_refund_no' => time() . rand(1000, 9999),
            'reason' => $remark,
            'notify_url' => "",
            'amount' => [
                'refund' => $total_fee,
                'total' => $total_fee,
                'currency' => 'CNY',//人民币
            ]
        );
        $unifiedorder['sign'] = self::makeSign($unifiedorder);

        // 退款接口 请求数据
        $xmldata = json_encode($unifiedorder);
        $url = 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds';
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        $res = self::https_request($url, $xmldata, $headers);
        if (!$res) {
            self::return_err("Can't connect the server");
        }

        trace($res, "退款返回结果");
        $content = (array)json_decode($res);
        var_dump($res);
        die();
        if (!empty($content['result_code'])) {
            if (strval($content['result_code']) == 'FAIL') {
                self::return_err(strval($content['err_code_des']));
            }
        }
        $content['time'] = time();
        return $content;
    }
    //---------------------------------------------------------------用到的函数------------------------------------------------------------

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


    /**
     * 正确返回
     * @param array $data 要返回的数组
     * @return  json的数据
     */
    protected function return_data($data = array())
    {
        exit(json_encode(array('status' => 1, 'result' => 'success', 'data' => $data)));
    }

    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    protected function array2xml($arr, $level = 1)
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
    protected function xml2array($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
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
     * 生成签名
     * @return 签名
     */
    public function makeSign($data)
    {
        //获取微信支付秘钥
        $key = $this->config['key'];
        // 去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入KEY
        //$config=$this->config;
        $string_sign_temp = $string_a . "&key=" . $key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($sign);
        return $result;
    }


    function https_request($url, $data = null, $header = NULL)
    {
        // 1. 初始化一个 cURL 对象
        $curl = curl_init();
        // 2.设置你需要抓取的URL
        curl_setopt($curl, CURLOPT_URL, $url);
        // (可选)设置头 阿里云的许多接口需要在头上传输秘钥
        if (!empty($header)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        // 3.https必须加这个，不加不好使（不多加解释，东西太多了）
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //对认证证书进行检验
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        // 4.设置post数据
        if (!empty($data)) {//post方式，否则是get方式
            //设置模拟post方式
            curl_setopt($curl, CURLOPT_POST, 1);
            //传数据，get方式是直接在地址栏传的，这是post传参的解决方式
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);//$data可以是数组，json
        }
        // 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。1是保存，0是输出
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 让curl跟随页面重定向
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        // 5. 运行cURL，请求网页
        $output = curl_exec($curl);
        // 6. 关闭CURL请求
        curl_close($curl);
        return $output;
    }
}
