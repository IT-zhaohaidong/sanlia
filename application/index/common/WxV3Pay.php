<?php


namespace app\index\common;


/**
 * 微信支付
 */
class WxV3Pay
{
    private $mchid = '1538520381';
    private $serial = '167DCE6BA851318A63DF3CB22266B858F85B4032';


    //微信V3  Post请求
    public function wx_post($url, $param)
    {
        $authorization = $this->getUploadSign($url, "POST", $param);
        $param = json_decode($param, true);
        $headers = [
            'Authorization:' . $authorization,
            'Accept:application/json',
            'Content-Type:application/json',
            'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($param));
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        $res = curl_exec($curl);
        curl_close($curl);
        return $res;
    }

    //微信V3  get请求
    public function wx_get($url, $param = '')
    {
        $param = json_decode($param, true);
        $url = $param ? $this->getUrlStr($url, $param) : $url;
        $authorization = $this->getV3Sign($url, "GET", $param);
        $headers = [
            'Authorization:' . $authorization,
            'Accept:application/json',
            'Content-Type:application/json;charset=utf-8',
            'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $param);
        curl_setopt($curl, CURLOPT_POST, 0);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        $res = curl_exec($curl);
        curl_close($curl);
        return $res;
    }

    //微信V3  上传图片
    public function upload($url, $param)
    {
        $content = $param['content'];
        unset($param['content']);
        $param = json_encode($param);
        $authorization = $this->getUploadSign($url, "POST", $param);
        $body_arr = json_decode($param, true);
        $boundary = "feishikeji";
        $out = "--" . $boundary . "\r\n" .
            'Content-Disposition: form-data; name="meta";' . "\r\n" .
            "Content-Type:application/json" . "\r\n" .
            "\r\n" .
            $param . "\r\n" .
            "--" . $boundary . "\r\n" .
            'Content-Disposition: form-data; name="file"; filename="' . $body_arr['filename'] . '"' . "\r\n" .
            "Content-Type: image/jpg" . ';' . "\r\n" .
            "\r\n" .
            $content . "\r\n" .
            "--" . $boundary . "--" . "\r\n";
        $headers = [
            'Authorization:WECHATPAY2-SHA256-RSA2048 ' . $authorization,
            'Accept:application/json',
            'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
            'Content-Type: multipart/form-data;boundary=feishikeji',
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $out);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $res = curl_exec($curl);
        curl_close($curl);
        return $res;
    }

    private function getUrlStr($url, $param)
    {
        $str = '';
        foreach ($param as $k => $v) {
            if ($str) {
                $str .= '&';
            }
            $str .= $k . '=' . $v;
        }
        if ($str) {
            $url = $url . '?' . $str;
        }
        return $url;
    }

    public function getUploadSign($url, $http_method, $body)
    {
        $nonce = strtoupper($this->createNonceStr(32));
        $timestamp = time();
        $url_parts = parse_url($url);
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : ""));
        $sslKeyPath = $_SERVER['DOCUMENT_ROOT'] . '/apiclient_key.pem';
        //拼接参数
        /*$message = $http_method . "\n" .
             $canonical_url . "\n" .
             $timestamp . "\n" .
             $nonce . "\n" .
             $body . "\n";*/

        $private_key = $this->getPrivateKey($sslKeyPath);
        $requestSign = sprintf("%s\n%s\n%s\n%s\n%s\n", $http_method, $canonical_url, $timestamp, $nonce, $body);
        openssl_sign($requestSign, $raw_sign, $private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);
//        $token = sprintf('WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%s",serial_no="%s",signature="%s"', $this->mchid, $nonce, $timestamp, $this->serial, $sign);
        $wxMerchantId = $this->mchid;
        $token = $this->createToken($wxMerchantId, $nonce, $timestamp, $this->serial, $sign);
        return $token;
    }

    private function createToken($merchant_id, $nonce, $timestamp, $serial_no, $sign)
    {
        $schema = 'WECHATPAY2-SHA256-RSA2048';
        $token = sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $merchant_id, $nonce, $timestamp, $serial_no, $sign);
        return $token;
    }

    private function getPostSign($url, $http_method, $body)
    {
        $url_parts = parse_url($url);
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : ""));
        // 获取时间戳
        $timestamp = time();
        // 获取随机字符串
        $nonce = strtoupper($this->createNonceStr(32));
        // 拼接签名字段
        $message = $http_method . "\n" .
            $canonical_url . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $body . "\n";

        // 获取私钥文件
        $sslKeyPath = $_SERVER['DOCUMENT_ROOT'] . '/apiclient_key.pem';
//        $mch_private_key = file_get_contents('path');
        $mch_private_key = file_get_contents($sslKeyPath);
        // 生成签名
        openssl_sign($message, $raw_sign, $mch_private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);

        $token = sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $this->mchid, //商户ID
            $nonce,
            $timestamp,
            $this->serial, //商户证书序列号
            $sign
        );
        return $token;
    }

    private function getV3Sign($url, $http_method, $body)
    {
        $mchid = $this->mchid;
        $nonce = strtoupper($this->createNonceStr(32));
        $timestamp = time();
        $url_parts = parse_url($url);
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : ""));
        $sslKeyPath = $_SERVER['DOCUMENT_ROOT'] . '/apiclient_key.pem';
        $body = $http_method == 'GET' ? '' : $body;
        //拼接参数
        $message = $http_method . "\n" .
            $canonical_url . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $body . "\n";
        $private_key = $this->getPrivateKey($sslKeyPath);
        openssl_sign($message, $raw_sign, $private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);
//        var_dump($sign);die();
        $token = sprintf('WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%s",serial_no="%s",signature="%s"', $this->mchid, $nonce, $timestamp, $this->serial, $sign);
        return $token;
    }

    function createNonceStr($length = 16)
    { //生成随机16个字符的字符串
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getPrivateKey($filepath = '')
    {
        if (empty($filepath)) {
            $filepath = $_SERVER['DOCUMENT_ROOT'] . '/apiclient_key.pem';
        }
        return openssl_get_privatekey(file_get_contents($filepath));
    }
}