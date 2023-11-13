<?php

namespace app\applet\controller;

use app\index\common\CompanyWX;
use app\index\model\CompanyWxModel;
use app\index\model\MallGoodsModel;
use think\Db;

include_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/company/WXBizMsgCrypt.php';

/*
 * 企业微信事件回调
 */

class CompanyNotify
{
    public function index()
    {
        if (request()->isGet()) {
            //匪石科技 todo 当新企业注册时,更换以下三个数据
//            $token = 'J5d9Rz1q6WEXxvi2wFNOndUX99';
//            $encodingAesKey = '4poDo16mLN4LQLRmYGKIAKO6ehl8CkGwGnbiTE9PNv1';
//            $corpId = 'wwcc2e93ac5666bdd9';
            $company = (new CompanyWxModel())->where('is_notify', 2)->find();
            $token = $company['token'];
            $encodingAesKey = $company['encodingAesKey'];
            $corpId = $company['corId'];
            $wxcpt = new \WXBizMsgCrypt($token, $encodingAesKey, $corpId);
            $echostr = request()->get('echostr');
            $msg_signature = request()->get('msg_signature');
            $timestamp = request()->get('timestamp');
            $nonce = request()->get('nonce');
            $sEchoStr = "";
            $errCode = $wxcpt->VerifyURL($msg_signature, $timestamp, $nonce, $echostr, $sEchoStr);
            trace($errCode, '企业微信验证');
            if ($errCode == 0) {
                trace($sEchoStr, '验证返回值');
                (new CompanyWxModel())->where('corId', $corpId)->update(['is_notify' => 1]);
                // 验证URL成功，将sEchoStr返回
//                 HttpUtils.SetResponce($sEchoStr);
                echo $sEchoStr;
            } else {
                trace($errCode, 'url验证失败');
            }
        } else {
            $data = file_get_contents("php://input");
            trace($data, '事件回调');
            $url_params = $_SERVER['QUERY_STRING'];
            $params = $this->getParams($url_params);
            $data_array = $this->xml2array($data);
            $corId = $data_array['ToUserName'];
            $companyModel = new CompanyWxModel();
            $row = $companyModel->where('corId', $corId)->find();
            $wxcpt = new \WXBizMsgCrypt($row['token'], $row['encodingAesKey'], $row['corId']);
            $sMsg = "";  // 解析之后的明文
            $errCode = $wxcpt->DecryptMsg($params['msg_signature'], $params['timestamp'], $params['nonce'], $data, $sMsg);
            if ($errCode == 0) {
                // 解密成功，sMsg即为xml格式的明文
                trace($sMsg, '解密之后的xml');
                $res = $this->xml2array($sMsg);
                trace($res, 'xml转数组');
                $corId = $res['ToUserName'];
                $companyModel = new CompanyWxModel();
                $row = $companyModel->where('corId', $corId)->find();
                $companyWx = new CompanyWX($corId, $row['secret']);
                if (isset($res['Event']) && $res['Event'] == 'change_external_contact') {
                    trace('成功啦');
                    $userId = $res['UserID'];//企业服务人员id
                    $externalUserID = $res['ExternalUserID'];//外部联系人id
                    if (isset($res['WelcomeCode'])) {
                        //根据企业用户id获取设备号,构建小程序路径
                        $user = $companyWx->getClientDetail($externalUserID);
                        if ($user['code'] == 200) {
                            $goods_id = $this->getState($userId, $user['list']['follow_user']);
//                            $goods = (new MallGoodsModel())->where('id', $goods_id)->find();
//                            if ($goods['chat_id'] && $goods['group_code']) {
//                                $companyWx->sendGroup($res['WelcomeCode'], $goods['group_code']);
//                            } else {
                            $page = "/pages/goods/goods?goods_id={$goods_id}&is_company=1&external_user_id={$externalUserID}";
                            $media_id = $row['media_id'];
                            $companyWx->sendMsg($res['WelcomeCode'], $page, $media_id);
//                            }

                        }
                    }
                }
//                elseif (isset($res['Event']) && $res['Event'] == 'change_external_chat') {
//                    if (isset($res['UpdateDetail']) && $res['UpdateDetail'] == 'add_member' && $res['JoinScene'] == 3) {
//                        //扫码进群
//                        $detail = $companyWx->getGroupDetail($res['ChatId']);
//                        if ($detail['code'] == 200) {
//                            $res = $this->getNewPerson($detail['data']['member_list']);
//                            $goods_id = $res['state'];
//                            $userId = $res['userid'];
//                            $page = "/pages/goods/goods?goods_id={$goods_id}&is_company=1&external_user_id={$userId}";
//                            $media_id = $row['media_id'];
//                            $companyWx->sendGroupMsg($page, $media_id);
//                        }
//                    }
//                }
            } else {
                print("ERR: " . $errCode . "\n\n");
                trace($errCode, '事件回调解析失败');
            }
        }

    }

    //获取最新加入群聊的用户
    public function getNewPerson($list)
    {
        $arr = [];
        foreach ($list as $k => $v) {
            if (!$arr) {
                $arr = $v;
            } else {
                if ($arr['join_time'] < $v['join_time']) {
                    $arr = $v;
                }
            }
        }
        return $arr;
    }

    public function getState($userId, $user)
    {
        $state = '';
        foreach ($user as $k => $v) {
            if ($v['userid'] == $userId) {
                $state = $v['state'];
                break;
            }
        }
        return $state;
    }

    //----------------公用函数----------------------
    public function getParams($url_params)
    {
        $url = urldecode($url_params);
        $params = explode('&', $url);
        unset($params[0]);
        $arr = [];
        foreach ($params as $k => $v) {
            $single_params = explode('=', $v);
            if ($single_params[0] == 'echostr') {
                $arr[$single_params[0]] = $single_params[1] . '==';
            } else {
                $arr[$single_params[0]] = $single_params[1];
            }

        }
        return $arr;
    }

    /**
     * 将xml转为array
     * @param string $xml xml字符串
     * @return array    转换得到的数组
     */
    public function xml2array($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }
}
