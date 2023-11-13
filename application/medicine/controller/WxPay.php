<?php


namespace app\medicine\controller;


use app\applet\controller\Goods;
use app\index\model\FinanceCash;
use app\index\model\MachineDevice;
use app\index\model\MachineDeviceErrorModel;
use app\index\model\MachineOutLogModel;
use app\index\model\MedicineOrderGoodsModel;
use app\index\model\MedicineOrderModel;
use app\index\model\MtGoodsModel;
use app\index\model\OperateUserModel;
use app\index\model\SystemAdmin;
use think\Cache;
use think\Db;

/**
 * 微信支付
 */
class WxPay
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
        $this->config ['notifyurl'] = "https://api.hnchaohai.com/box/Wxpay/payNotify"; //支付成功后通知的url
    }

    /**
     * 预支付请求接口(POST)
     * @param string $openid openid
     * @param string $body 商品简单描述
     * @param string $order_sn 订单编号
     * @param string $total_fee 金额
     * @return  json的数据
     */
    public function prepay($openid, $order_sn, $total_fee, $user, $notify_url)
    {
        //$openid = "用户的openid"; //或者从自己的数据中进行读取
        //$order_sn = "微信的订单号"; // 自己定义
        //$total_fee = "总价钱"; // 单位分
        //统一下单参数构造
        if ($user['is_wx_mchid'] == 1 && $user['wx_mchid_id']) {
            $this->config['mchid'] = $user['mchid']['mchid'];
            $this->config['key'] = $user['mchid']['key'];
        }
        $unifiedorder = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['mchid'],
            'nonce_str' => self::getNonceStr(),
            'body' => "匪石科技",
            'out_trade_no' => $order_sn,
            'total_fee' => $total_fee * 100,
            'spbill_create_ip' => $_SERVER["REMOTE_ADDR"],
            'notify_url' => $notify_url,
            'trade_type' => 'NATIVE', // JSAPI为公众号支付 NATIVE 为扫码支付
        );
        if ($openid) {
            $unifiedorder['openid'] = $openid;
            $unifiedorder['trade_type'] = 'JSAPI';
        }
        $unifiedorder['sign'] = self::makeSign($unifiedorder, $this->config['key']);

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
     * 微信支付回调验证
     * @return array|bool
     */
    public function payNotify()
    {
        $xml = file_get_contents("php://input");
        //将服务器返回的XML数据转化为数组
        $data = self::xml2array($xml);
        trace($data, '女生盒子支付回调');
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
//        $sign = self::makeSign($data);

        // 判断签名是否正确  判断支付状态
//        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS')) {
        $result = $data;
        //获取服务器返回的数据
        $out_trade_no = $data['out_trade_no'];        //订单单号
        $openid = $data['openid'];                    //付款人openID
        $total_fee = $data['total_fee'];            //付款金额
        $transaction_id = $data['transaction_id'];    //微信支付流水号
        $res = Cache::store('redis')->get('notify_' . $out_trade_no);
        if (!$res) {
            Cache::store('redis')->set('notify_' . $out_trade_no, 1, 300);
        } else {
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            echo $str;
            return $result;
        }

        //支付成功的业务逻辑
        (new MedicineOrderModel())->where('order_sn', $out_trade_no)->update(['openid' => $openid]);
        $this->orderDeal($result, 1, 1);

//        } else {
//            $result = false;
//        }
//        // 返回状态给微信服务器
//        if ($result) {
        $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
//        } else {
//            $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
//        }
        echo $str;
        return $result;
    }

    /**
     * 微信支付回调验证
     * @return array|bool
     */
    public function agentNotify()
    {
        $xml = file_get_contents("php://input");
        //将服务器返回的XML数据转化为数组
        $data = self::xml2array($xml);
        trace($data, '女生盒子支付回调');
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
//        $sign = self::makeSign($data);

        // 判断签名是否正确  判断支付状态
//        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS')) {
        $result = $data;
        //获取服务器返回的数据
        $out_trade_no = $data['out_trade_no'];        //订单单号
        $openid = $data['openid'];                    //付款人openID
        $total_fee = $data['total_fee'];            //付款金额
        $transaction_id = $data['transaction_id'];    //微信支付流水号
        //支付成功的业务逻辑

        $res = Cache::store('redis')->get('notify_' . $out_trade_no);
        if (!$res) {
            Cache::store('redis')->set('notify_' . $out_trade_no, 1, 300);
        } else {
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            echo $str;
            return $result;
        }
        (new MedicineOrderModel())->where('order_sn', $out_trade_no)->update(['openid' => $openid]);
        $this->orderDeal($result, 3, 1);

//        } else {
//            $result = false;
//        }
//        // 返回状态给微信服务器
//        if ($result) {
        $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
//        } else {
//            $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
//        }
        echo $str;
        return $result;
    }

    public function orderDeal($data, $type, $status = 1)
    {
        $orderModel = new MedicineOrderModel();
        $update_data = [
            'pay_time' => time(),
            'pay_type' => $type,
            'transaction_id' => $data['transaction_id'],
            'status' => $status
        ];
        $orderModel->where('order_sn', $data['out_trade_no'])->update($update_data);
        $order = $orderModel->where('order_sn', $data['out_trade_no'])->field('id,uid,device_sn,price,count')->find();
        $device = (new MachineDevice())->where('device_sn', $order['device_sn'])->field('id,supply_id')->find();
        $device_id = $device['id'];
        $ratio = Db::name('machine_commission')
            ->where(['device_id' => $device_id])
            ->select();
        $adminModel = new SystemAdmin();
        $uid = [$order['uid']];
        foreach ($ratio as $k => $v) {
            $uid[] = $v['uid'];
        }
        switch ($type) {
            case 1:
                //系统微信
                $admin_balance = $adminModel->whereIn('id', $uid)->column('system_balance', 'id');
                break;
            case 2:
                //系统支付宝
                $admin_balance = $adminModel->whereIn('id', $uid)->column('system_balance', 'id');
                break;
            case 3:
                //用户微信
                $admin_balance = $adminModel->whereIn('id', $uid)->column('agent_wx_balance', 'id');
                break;
            case 4:
                //用户支付宝
                $admin_balance = $adminModel->whereIn('id', $uid)->column('agent_ali_balance', 'id');
                break;
        }
        $total_price = 0;
        $cash_data = [];
        foreach ($ratio as $k => $v) {
            $price = floor(($order['price'] * $v['ratio'] / 100) * 100) / 100;
            //补货员系统余额更新
            $balance = $price + $admin_balance[$v['uid']];
            $update = [];
            switch ($type) {
                case 1:
                    //系统微信
                    $update['system_balance'] = $balance;
                    break;
                case 2:
                    //系统支付宝
                    $update['system_balance'] = $balance;
                    break;
                case 3:
                    //用户微信
                    $update['agent_wx_balance'] = $balance;
                    break;
                case 4:
                    //用户支付宝
                    $update['agent_ali_balance'] = $balance;
                    break;
            }
            trace($update, '错误更新数据');
            $adminModel->where('id', $v['uid'])->update($update);
            //统计用户订单收益数据
            $cash_data[] = [
                'uid' => $v['uid'],
                'order_sn' => $data['out_trade_no'],
                'price' => $price,
                'type' => 1,
            ];
            //下级人员总收益
            $total_price += $price;
        }
        if ($type == 1 || $type == 2) {
            //代理商余额更新
            $balance = $order['price'] - $total_price + $admin_balance[$order['uid']];
            $adminModel->where('id', $order['uid'])->update(['system_balance' => $balance]);
        }
        $cash_data[] = [
            'uid' => $order['uid'],
            'order_sn' => $data['out_trade_no'],
            'price' => $order['price'] - $total_price,
            'type' => 1
        ];
        (new FinanceCash())->saveAll($cash_data);
        //出货
        $order_goods = (new MedicineOrderGoodsModel())->where('order_id', $order['id'])->select();
        if ($device['supply_id'] == 2) {
            $orderModel->where('order_sn', $data['out_trade_no'])->update(['status' => 4]);
        } else {
            $index = 1;
            foreach ($order_goods as $k => $v) {
                $goods = (new MtGoodsModel())
                    ->where(['device_sn' => $v['device_sn'], 'num' => $v['num']])
                    ->field('stock,goods_id')->find();
                if ($device['supply_id'] == 1) {
                    //中转板子出货
                    for ($i = 0; $i < $v['count']; $i++) {
                        $order_no = $data['out_trade_no'] . 'order' . $index;
                        $res = $this->goodsOut($v['device_sn'], $v['num'], $order_no, $v['goods_id']);
                        if ($res['code'] == 1) {
                            (new MedicineOrderModel())->where('order_sn', $data['out_trade_no'])->update(['status' => 3]);
                        } else {
                            (new Goods())->addStockLog($v['device_sn'], $v['num'], $order_no, 1, $goods);
                        }
                        $index++;
                    }
                } elseif ($device['supply_id'] == 3) {
                    $out_log = [];
                    //蜜连出货
                    for ($i = 0; $i < $v['count']; $i++) {
                        $order_no = $data['out_trade_no'] . 'order' . $index;
                        $res = (new Goods())->Shipment($v['device_sn'], $v['num'], $order_no);
                        if ($res['errorCode'] != 0) {
                            $status = $res['errorCode'] == 65020 ? 2 : 3;
                            $out_log[] = ["device_sn" => $v['device_sn'], "num" => $v['num'], "order_sn" => $order_no, 'status' => $status];
                            (new MedicineOrderModel())->where('order_sn', $data['out_trade_no'])->update(['status' => 3]);
                        } else {
                            $out_log[] = ["device_sn" => $v['device_sn'], "num" => $v['num'], "order_sn" => $order_no, 'status' => 0];
                            (new Goods())->addStockLog($v['device_sn'], $v['num'], $order_no, 1, $goods);
                        }
                        $index++;
                    }
                    (new MachineOutLogModel())->saveAll($out_log);
                } else {

                }
//                if (isset($res) && $res['code'] == 1) {
//                    continue;
//                }
            }
        }


        if (!empty($data['openid'])) {
            $user = (new OperateUserModel())->where('openid', $data['openid'])->field('id,buy_num')->find();
            if ($user) {
                (new OperateUserModel())->where('id', $user['id'])->update(['buy_num' => $user['buy_num'] + 1]);
            } else {
                (new OperateUserModel())->save(['openid' => $data['openid'], 'buy_num' => 1]);
            }
        }
    }

    public function goodsOut($device_sn, $num, $order_sn, $goods_id)
    {
        $imei = (new MachineDevice())->where('device_sn', $device_sn)->value('imei');
        $data = [
            "imei" => $imei,
            "deviceNumber" => $device_sn,
            "laneNumber" => $num,
            "laneType" => 1,
            "paymentType" => 1,
            "orderNo" => $order_sn,
            "timestamp" => time()
        ];
        $str = $device_sn . '_ip';
        $ip = Cache::store('redis')->get($str);
        trace($ip, '板子ip');
//        if ($ip == '47.96.15.3') {
//            $url = 'http://47.96.15.3:8899/api/vending/goodsOut';
//        } else {
        $url = 'http://feishi.feishi.vip:9100/api/vending/goodsOut';
//        }
        $result = https_request($url, $data);
        $result = json_decode($result, true);
        $result['order_sn'] = $order_sn;
        trace($data, '出货参数');
        trace($result, '出货指令结果');
        if ($result['code'] == 200) {
            $res = $this->isBack($order_sn, 1);
            if (!$res) {
                //没有反馈业务处理
                $str = 'out_' . $order_sn;
                trace($str, '查询订单号');
                $res_a = Cache::store('redis')->get($str);
                if ($res_a == 2 || !$res) {
                    $outingStr = 'outing_' . $device_sn;
                    Cache::store('redis')->rm($outingStr);
                    $status = $res_a == 2 ? 1 : 5;
                    $data = [
                        "imei" => $imei,
                        "device_sn" => $device_sn,
                        "num" => $num,
                        "order_sn" => $order_sn,
                        'goods_id' => $goods_id,
                        "status" => $status,
                    ];
                    (new MachineDeviceErrorModel())->save($data);
                    trace(1111, '没有出货反馈');
                    if ($status == 5) {
                        $log = ["device_sn" => $device_sn,"num" => $num,"order_sn" => $data['order_sn'], 'status' => 5];
                        (new MachineOutLogModel())->save($log);
                    }
                    return ['code' => 1, 'msg' => '失败'];
                }
            }
            Db::name('mt_device_goods')->where("num", $num)->where("device_sn", $device_sn)->dec("stock")->update();
            return ['code' => 0, 'msg' => '成功'];

        } else {
            $save_data = [
                'device_sn' => $device_sn,
                'imei' => $imei,
                'num' => $num,
                'order_sn' => explode('order', $order_sn)[0],
                'goods_id' => $goods_id,
                'status' => 3,
            ];
            (new MachineDeviceErrorModel())->save($save_data);
            trace(1111, '出货失败');
            $log = ["device_sn" => $device_sn,"num" => $num,"order_sn" => $save_data['order_sn'], 'status' => 2];
            (new MachineOutLogModel())->save($log);
            return ['code' => 1, 'msg' => '失败'];
        }

    }

    public function isBack($order, $num)
    {
        if ($num <= 15) {
            $str = 'out_' . explode('order', $order)[0];
            trace($str, '查询订单号');
            $res = Cache::store('redis')->get($str);
            trace($res, '查询结果');
            if ($res == 1) {
                return true;
            } else {
                if ($res == 2) {
                    return false;
                }
                if ($num > 1) {
                    sleep(1);
                }
                $res = $this->isBack($order, $num + 1);
                return $res;
            }
        } else {
            return false;
        }
    }

    /**
     * 查看设备状态
     */
    public function device_status($sn = '')
    {

//        $sn="ILJXJI";
        $nonceStr = time();
        $url = "https://mqtt.ibeelink.com/api/ext/tissue/device/info";
        $data = '{
                "sn":"' . $sn . '","nonceStr": "' . $nonceStr . '"}';
        function medicine_send_post($url, $post_data, $token)
        {
            $postdata = http_build_query($post_data);
            $options = array(
                'http' =>
                    array(
                        'method' => 'GET',
                        'header' => array('token:' . $token, 'chan:bee - CSQYUS', 'Content - type:application / x - www - form - urlencoded'),
                        'content' => $postdata,
                        'timeout' => 15 * 60
                    )
            );
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            return $result;
        }

        $post_data = array(
            'data' => $data
        );
        $res = medicine_send_post($url, $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        $res_arr = json_decode($res, true);
        return $res_arr['data']['online'];

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
    public function makeSign($data, $key)
    {
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
