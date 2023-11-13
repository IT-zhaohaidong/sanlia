<?php

namespace app\applet\controller;

use think\Controller;
use think\Exception;

class AliLogin extends Controller
{
    public function index()
    {
        $params = request()->param();
        trace($params, '应用网关');
        return true;
    }

    public function getOpenid()
    {
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/AopCertClient.php';
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/request/AlipaySystemOauthTokenRequest.php';
        $code = request()->get('code', '');
        $app_id = '2021004100643073';
        //应用私钥
        $privateKeyPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/应用私钥RSA2048.txt';
        $privateKey = file_get_contents($privateKeyPath);

        $aop = new \AopCertClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $app_id;
        $aop->rsaPrivateKey = $privateKey;
        //支付宝公钥证书
        $aop->alipayPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/alipayCertPublicKey_RSA2.crt';

        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'UTF-8';
        $aop->format = 'json';
        //调用getCertSN获取证书序列号
        $appPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/appCertPublicKey_2021004100643073.crt";
        $aop->appCertSN = $aop->getCertSN($appPublicKey);
        //支付宝公钥证书地址
        $aliPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";;
        $aop->alipayCertSN = $aop->getCertSN($aliPublicKey);
        //调用getRootCertSN获取支付宝根证书序列号
        $rootCert = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";
        $aop->alipayRootCertSN = $aop->getRootCertSN($rootCert);

        $request = new \AlipaySystemOauthTokenRequest ();
        $request->setGrantType("authorization_code");
        $request->setCode($code);
        $result = $aop->execute($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        if (!empty($result->$responseNode->code) && $result->$responseNode->code != 10000) {
            return json(['code' => 200, 'msg' => '失败']);
        } else {
            return json(['code' => 200, 'data' => ['openid' => $result->alipay_system_oauth_token_response->user_id]]);
        }
    }

    public function getPublicKey()
    {
        include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/alipay/aop/AopCertClient.php';
        $aop = new \AopCertClient();
        $alipayCertPath = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/appCertPublicKey_2021004100643073.crt";
        $alipayrsaPublicKey = $aop->getPublicKey($alipayCertPath);
        $oldchar = array("", "　", "\t", "\n", "\r");
        $newchar = array("", "", "", "", "");
        $alipayrsaPublicKey = str_replace($oldchar, $newchar, $alipayrsaPublicKey);
        echo '支付宝公钥证书值' . $alipayrsaPublicKey;
    }

    public function getPhone()
    {
        $response = request()->post('response', '');
        if (!$response) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $aesKey = "A4GDuuvvz941BgOvbpQvMw==";
        //AES密钥
//        $content = "Ho5y3nixH0tSnYWw8p/HMTO1bIONKSYd7BfkbI+ww4qgJEXKMYFWnSwjXN2B9YwvsKBYykK2gva2v1jfnQVSNQ==";
        $result = openssl_decrypt(base64_decode($response), 'AES-128-CBC', base64_decode($aesKey), OPENSSL_RAW_DATA);
        $v = json_decode($result, true);
        if ($v['code'] == 10000) {
            return json(['code' => 200, 'data' => $v]);
        } else {
            trace($v, '手机号获取失败');
            return json(['code' => 100, 'msg' => $v['subMsg']]);
        }
    }
}
