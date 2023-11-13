<?php

namespace app\meituan\controller;

use app\index\model\MtShopModel;
use think\Cache;
use think\Controller;
use function app\index\controller\send_post;

class MeiTuan extends Controller
{
    //美团错误返回  {"data":"ng","error":{"msg":"药品分类不存在","code":3003}}

    public function callback()
    {
        $params = request()->param();
        trace($params, '美团回调');
        return json(['code' => 200, 'msg' => '成功']);
    }

    //获取店铺分类列表
    public function getCatList($app_poi_code)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'app_poi_code' => $app_poi_code,
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/medicineCat/list';
        $url = $this->postSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        return $result;
    }

    //创建分类
    public function createCat($params)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'app_id' => $app_id,
            'timestamp' => $timestamp,
            'app_poi_code' => $params['app_poi_code'],
            'category_name' => $params['category_code'],
            'category_code' => $params['category_code'],
            'sequence' => $params['sequence'],
            'access_token' => $this->getAccessToken($params['app_poi_code'])
        ];

        $url = 'https://waimaiopen.meituan.com/api/v1/medicineCat/save';
        $url = $this->postSign($url, $data, $appSecret);
        $result = $this->send_post($url, $data);
        return json_decode($result, true);
    }

    //修改分类
    public function updateCat($params)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'app_id' => $app_id,
            'timestamp' => $timestamp,
            'app_poi_code' => $params['app_poi_code'],
            'category_name' => $params['category_name'],
            'category_name_old' => $params['category_name_old'],
            'category_code' => $params['category_code'],
            'sequence' => $params['sequence'],
            'access_token' => $this->getAccessToken($params['app_poi_code'])
        ];

        $url = 'https://waimaiopen.meituan.com/api/v1/medicineCat/update';
        $url = $this->postSign($url, $data, $appSecret);
        $result = $this->send_post($url, $data);
        return json_decode($result, true);
    }

    //删除分类
    public function delCate($cate_name, $app_poi_code)
    {
        $timestamp = time();
//        $timestamp = '1669630740';
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
//        $cate_name = '消化系统';
//        $cate_name = urlencode($cate_name);

        $data = [
            'app_id' => $app_id,
            'timestamp' => $timestamp,
            'app_poi_code' => $app_poi_code,
            'category_name' => $cate_name,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];

        $url = 'https://waimaiopen.meituan.com/api/v1/medicineCat/delete';
        $url = $this->postSign($url, $data, $appSecret);
        $result = $this->send_post($url, $data);
        return json_decode($result, true);
    }

    //获取门店药品列表
    public function medicineList($app_poi_code)
    {
        $timestamp = time();
        $app_id = '119561';
        $data = [
            'app_id' => $app_id,
            'timestamp' => $timestamp,
            'app_poi_code' => $app_poi_code,
            'offset' => 0,
            'limit' => 200,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $url = 'https://waimaiopen.meituan.com/api/v1/medicine/list';
        $url = $this->postSign($url, $data, $appSecret);
        $result = $this->sendGet($url, $data);
        return json_decode($result, true);
    }

    //创建药品
    public function createMedicine($params)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
//        $app_medicine_code = '1';//APP方药品id，可使用商家中台系统里药品的编码
//        $upc = '6932067401497';//药品UPC码，同一门店内药品UPC码不允许重复
//        $medicine_no = 'Z22025164';//药品的批准文号（国药准字号） 可选
//        $spec = '';//药品的批准文号（国药准字号） 可选
//        $price = '9.90';//药品售卖价格，单位是元
//        $stock = '20';//库存
//        $category_code = '090100';//分类code
//        $is_sold_out = '0';//药品上下架状态 0-上架，1-下架 默认为下架
        $data = [
            'app_id' => $app_id,
            'timestamp' => $timestamp,
            'app_medicine_code' => $params['app_medicine_code'],
            'app_poi_code' => $params['app_poi_code'],
            'category_code' => $params['category_code'],
            'is_sold_out' => $params['is_sold_out'],
            'upc' => $params['upc'],
            'medicine_no' => $params['medicine_no'],
            'spec' => $params['spec'],
            'price' => $params['price'],
            'stock' => $params['stock'],
            'sequence' => 1,
            'access_token' => $this->getAccessToken($params['app_poi_code'])
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/medicine/save';
        $url = $this->postSign($url, $data, $appSecret);
        //var_dump($url);die();
        $result = $this->sendPost($url, $data);
        return json_decode($result, true);
    }

    public function updateMedicine($params)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'app_id' => $app_id,
            'timestamp' => $timestamp,
            'app_medicine_code' => $params['app_medicine_code'],
            'app_poi_code' => $params['app_poi_code'],
            'category_code' => $params['category_code'],
            'is_sold_out' => $params['is_sold_out'],
            'upc' => $params['upc'],
            'medicine_no' => $params['medicine_no'],
            'spec' => $params['spec'],
            'price' => $params['price'],
            'stock' => $params['stock'],
            'sequence' => 1,
            'access_token' => $this->getAccessToken($params['app_poi_code'])
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/medicine/update';
        $url = $this->postSign($url, $data, $appSecret);
        $result = $this->sendPost($url, $data);
        return json_decode($result, true);
    }

    //商品上/下架
    public function soldOut($params, $app_poi_code)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
//        $app_medicine_code = '1';//APP方药品id，可使用商家中台系统里药品的编码
        $data = [
            'app_id' => $app_id,
            'timestamp' => $timestamp,
            'medicine_data' => json_encode($params),
            'app_poi_code' => $app_poi_code,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/medicine/isSoldOut';
        $url = $this->postSign($url, $data, $appSecret);
        $result = $this->sendPost($url, $data);
        return json_decode($result, true);
    }


    //删除药品
    public function delMedicine($app_medicine_code, $app_poi_code)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
//        $app_medicine_code = '1';//APP方药品id，可使用商家中台系统里药品的编码
        $data = [
            'app_id' => $app_id,
            'timestamp' => $timestamp,
            'app_medicine_code' => $app_medicine_code,
            'app_poi_code' => $app_poi_code,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/medicine/delete';
        $url = $this->postSign($url, $data, $appSecret);
        $result = $this->sendPost($url, $data);
        return json_decode($result, true);
    }

    //获取店铺详情
    public function getShopDetail($app_poi_codes)
    {
//        $app_poi_codes = '119561_2701528';
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'app_poi_codes' => $app_poi_codes
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/poi/mget';
        $url = $this->postSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        return $result;
    }

    //门店设置为营业状态
    public function openShop($app_poi_code)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'app_poi_code' => $app_poi_code,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/poi/open';
        $url = $this->postSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        return $result;
    }

    //门店设置为休息状态
    public function closeShop($app_poi_code)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'app_poi_code' => $app_poi_code,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/poi/close';
        $url = $this->postSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        return $result;
    }


    //确认订单
    public function confirmOrder($order_id, $app_poi_code)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'order_id' => $order_id,
            'app_poi_code' => $app_poi_code,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/order/confirm';
        $url = $this->makeSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        trace($result, '确认订单');
        if ($result['data'] == 'ng') {
            if (isset($result['error']['code']) && $result['error']['code'] == 767) {
                $this->getAccessToken($app_poi_code, 1);
                $data = [
                    'data' => 'ng',
                    'error' => ['msg' => '请重试'],
                ];
                return $data;
            }
        }
        return $result;
    }

    //取消订单
    public function cancelOrder($order_id, $app_poi_code)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'order_id' => $order_id,
            'app_poi_code' => $app_poi_code,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/order/cancel';
        $url = $this->makeSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        trace($result, '取消订单结果');
        if ($result['data'] == 'ng') {
            if (isset($result['error']['code']) && $result['error']['code'] == 767) {
                $this->getAccessToken($app_poi_code, 1);
                $data = [
                    'data' => 'ng',
                    'error' => ['msg' => '请重试'],
                ];
                return $data;
            }
        }
        return $result;
    }

    //同意退款
    public function agreeRefund($order_id, $app_poi_code, $reason)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'order_id' => $order_id,
            'app_poi_code' => $app_poi_code,
            'reason' => $reason,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/order/refund/agree';
        $url = $this->makeSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        trace($result, '同意退款结果');
        return $result;
    }

    //拒绝退款
    public function refuseRefund($order_id, $app_poi_code, $reason)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'order_id' => $order_id,
            'app_poi_code' => $app_poi_code,
            'reason' => $reason,
            'access_token' => $this->getAccessToken($app_poi_code)
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/order/refund/reject';
        $url = $this->makeSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        trace($result, '拒绝退款结果');
        return $result;
    }

    //拉取用户真实手机号
    public function pullPhone()
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'app_poi_code' => '119561_2701528',
            'offset' => 1,
            'limit' => 1,
            'access_token' => $this->getAccessToken('119561_2701528')
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/order/batchPullPhoneNumber';
        $url = $this->postSign($url, $data, $appSecret);
        $result = $this->send_post($url, $data);
        $result = json_decode($result, true);
        trace($result, '拉取用户手机号');
        return $result;
    }

    //售后单（退款/退货退款）审核接口
    public function reviewAfterSales($app_poi_code, $wm_order_id_view)
    {
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'timestamp' => $timestamp,
            'app_id' => $app_id,
            'app_poi_code' => '119561_2701528',
            'wm_order_id_view' => $wm_order_id_view,
            'review_type' => 1,
            'access_token' => $this->getAccessToken('119561_2701528')
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/ecommerce/order/reviewAfterSales';
        $url = $this->postSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        trace($result, '售后单（退款/退货退款）审核接口');
        return $result;
    }

    //参数带中文的post请求
    function send_post($url, $post_data)
    {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded;charset=UTF-8',
                'content' => $post_data,
                'timeout' => 15 * 60
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }

    public function sendPost($url, $data)
    {
        $context = stream_context_create([
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $data
            ),
        ]);
        $res = file_get_contents($url, false, $context);
        return $res;
    }

    public function sendGet($url, $data)
    {
        $context = stream_context_create([
            'http' => array(
                'method' => 'GET',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $data
            ),
        ]);
        $res = file_get_contents($url, false, $context);
        return $res;
    }

    //获取accessToken
    public function getAccessToken($app_poi_code, $type = 0)
    {
        if ($type == 0) {
            $access_token = Cache::store('redis')->get($app_poi_code . '_token');
            if ($access_token) {
                return $access_token;
            }
            $access_token = (new MtShopModel())->where('app_poi_code', $app_poi_code)->value('access_token');
            if ($access_token) {
                Cache::store('redis')->set($app_poi_code . '_token', $access_token);
                return $access_token;
            }
        }
        $timestamp = time();
        $app_id = '119561';
        $appSecret = 'fe2c19951c41e239acff83b532017af8';
        $data = [
            'app_id' => $app_id,
            'app_poi_code' => $app_poi_code,
            'response_type' => 'token',
            'timestamp' => $timestamp
        ];
        $url = 'https://waimaiopen.meituan.com/api/v1/oauth/authorize';
        $url = $this->makeSign($url, $data, $appSecret);
        $result = https_request($url);
        $result = json_decode($result, true);
        trace($result, '美团token');
        if ($result['status'] == 0) {
            (new MtShopModel())->where('app_poi_code', $app_poi_code)->update(['access_token' => $result['access_token']]);
            Cache::store('redis')->set($app_poi_code . '_token', $result['access_token']);
            return $result['access_token'];
        } else {
            var_dump($result);
            echo json_encode(['code' => 100, 'msg' => 'token获取失败']);
            exit();
        }
    }

    public function makeSign($url, $data, $appSecret)
    {
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入KEY
        $params_url = $url . '?' . $string_a;
        $str = $params_url . $appSecret;
        $sig = md5($str);
        $url = $params_url . '&sig=' . $sig;
        return $url;
    }

    public function postSign($url, $params, $appSecret)
    {
        $params = array_filter($params);
        //签名步骤一：按字典序排序参数
        $signPars = "";
        ksort($params);
        foreach ($params as $k => $v) {
            if ("" !== $v && "sign" != $k) {
                if ($signPars) {
                    $signPars .= '&' . $k . '=' . $v;
                } else {
                    $signPars .= $k . '=' . $v;
                }

            }
        }
        $params_url = $url . '?' . $signPars;
        $signPars = $params_url . $appSecret;
//        var_dump('MD5数据===========>' . $signPars);
//        die();
        $sign = md5($signPars);
//        var_dump('签名=========>' . $sign);
//        var_dump('请求时间=========>' . date('Y-m-d H:i:s', time()));
        $signPars = '';
        foreach ($params as $k => $v) {
            if (preg_match("/([\x81-\xfe][\x40-\xfe])/", $v, $match)) {
                $v = urlencode($v);
            }
            if ("" !== $v && "sign" != $k) {
                if ($signPars) {
                    $signPars .= '&' . $k . '=' . $v;
                } else {
                    $signPars .= $k . '=' . $v;
                }
            }
        }
        $params_url = $url . '?' . $signPars;
        $url = $params_url . '&sig=' . $sign;
//        var_dump('请求url=========>' . $url);
        return $url;
    }

}