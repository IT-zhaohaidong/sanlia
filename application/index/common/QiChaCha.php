<?php

namespace app\index\common;

use think\Cache;
use think\Db;

/*
 * 1.从企业微信获取secret和corId
 * 2.在企业微信管理后台的“客户联系-客户”页面，点开“API”小按钮，再点击“接收事件服务器”配置 事件回调
 */

class QiChaCha
{
    private $key = '2efe2f0eb3a04cec8f809978c2e6ca65';
    private $secretKey = 'E76FDF35F3CC95BC05F1A11508768480';

    //企业三要素核验
    public function verifyCompany($creditCode, $companyName, $operName)
    {
        $url = "https://api.qichacha.com/ECIThreeElVerify/GetInfo?key={$this->key}&creditCode={$creditCode}&companyName={$companyName}&operName={$operName}";
        $timespan = time();
        $token = $this->getToken($timespan);
        $header = [
            'Token:' . $token,
            'Timespan:' . $timespan
        ];
        return https_request($url, [], $header);
    }

    //企业工商照面
    public function getBasicDetails($searchKey)
    {
        $url = "https://api.qichacha.com/ECIV4/GetBasicDetailsByName?key={$this->key}&keyword={$searchKey}";
        $timespan = time();
        $token = $this->getToken($timespan);
        $header = [
            'Token:' . $token,
            'Timespan:' . $timespan
        ];
        return https_request($url, [], $header);
    }

    //模糊查询
    public function fuzzySearch($searchKey)
    {
        $url = "https://api.qichacha.com/FuzzySearch/GetList?key={$this->key}&searchKey={$searchKey}&pageSize=20";
        $timespan = time();
        $token = $this->getToken($timespan);
        $header = [
            'Token:' . $token,
            'Timespan:' . $timespan
        ];
        return https_request($url, [], $header);
    }


    private function getToken($timespan)
    {
        $secretKey = $this->secretKey;
        $key = $this->key;
        return strtoupper(md5($key . $timespan . $secretKey));
    }
}