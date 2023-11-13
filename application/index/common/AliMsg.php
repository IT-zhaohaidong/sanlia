<?php

namespace app\index\common;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Dysmsapi\Dysmsapi;
use think\Cache;
use think\Db;

/*
 * 1.从企业微信获取secret和corId
 * 2.在企业微信管理后台的“客户联系-客户”页面，点开“API”小按钮，再点击“接收事件服务器”配置 事件回调
 */

class AliMsg
{
    /**
     * 发送验证码短信
     * @param string[] $args
     * @return void
     */
    function sendSms($mobile, $code, $tempId,$signName='匪石科技')
    {
        $access_key_id = 'LTAI5tAxWs8oqKfdQnY6tYZs';
        $access_key_secret = 'WREttlDj3Z4hAXApvbGnxSNG6D5e9t';
        AlibabaCloud::accessKeyClient($access_key_id, $access_key_secret)
            ->regionId('cn-hangzhou') //replace regionId as you need（这个地方是发短信的节点，默认即可，或者换成你想要的）
            ->asGlobalClient();

        $data = [];
        try {
            $result = AlibabaCloud::rpcRequest()
                ->product('Dysmsapi')
                ->version('2017-05-25')
                ->action('SendSms')
                ->method('POST')
                ->options([
                    'query' => [
                        'PhoneNumbers' => $mobile,
                        'SignName' => $signName,//签名
                        'TemplateCode' => $tempId,//模板id
                        'TemplateParam' => json_encode(['code' => $code]),
                    ],
                ])
                ->request();
            $res = $result->toArray();
            trace($res, '短信发送结果');
            if ($res['Code'] == 'OK') {
                $data['status'] = 1;
                $data['info'] = $res['Message'];
            } else {
                $data['status'] = 0;
                $data['info'] = $res['Message'];
            }
            return $data;
        } catch (ClientException $e) {
            $data['status'] = 0;
            $data['info'] = $e->getErrorMessage();
            return $data;
        } catch (ServerException $e) {
            $data['status'] = 0;
            $data['info'] = $e->getErrorMessage();
            return $data;

        }

    }

}
