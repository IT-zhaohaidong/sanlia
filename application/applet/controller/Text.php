<?php

namespace app\applet\controller;

use app\index\common\AliMsg;
use app\index\common\CompanyWX;
use app\index\common\Email;
use app\index\common\Oss;
use app\index\common\Yuepai;
use app\index\model\CompanyWxModel;
use app\index\model\MallGoodsModel;
use PHPMailer\PHPMailer\PHPMailer;
use think\Cache;
use think\Controller;
use think\Db;

class Text extends Controller
{
    //16
//    public function index()
//    {
//        $str = 'JQRVNG,LQSTXX,RQMPCQ,GVIRLQ,GNOLRD,LCHXGP,VPSBAL,MRNBQK,GAVKXN,YZXHBC,IFKHUA,UMTGDB,YZUYDB,GIQQOL,TVLRLM,HUOFOW,MZUWKK,RCUMCN,PXDLIZ,ABBUII,IWNQCN,IVUMST,SYILRP,JCAPFF,OPXVTI,NBUTML,AZEGUS,ZRJOFR,IXDAYD,EIKDTP,WIHXVD,KSPYPE,BABBHO,MWYLGZ,ODTXVT,ZTJUDG,YVTAJV,YQBKNY,XYPLKF,XUGGLZ,XRZJGS,VZJNGJ,VHLSDM,UWTNIS,UKPNEN,TZFLPI,TENRBB,SVOSWY,SUHJIY,SFADNS,RZZPYK,PKMYBG,PBFOUR,OWSOZF,NTZMKP,NBZLIG,MGWPVA,KLWXQG,KLINOG,KDXGET,JYMJOX,JSYATG,IHGXAL,IFHGWT,HKECLO,GXBNSY,GWLMUM,FUNFIW,FKCUSB,FCDOYL,EWPXKK,DPTQWY,DMXUOQ,DHPJKQ,CUZVEJ,CPDART,BSBVRN,BKEGPZ,BEJZZV,AZQPNN';
//        $devices = explode(',', $str);
//        $data = [];
//        foreach ($devices as $k => $v) {
//            $qrcode = qrcode($v, $k);
//            $data[] = [
//                'device_sn'=>$v,
//                'uid'=>16,
//                'imei'=>$v,
//                'transfer'=>0,
//                'type_id'=>1,
//                'has_moniter'=>1,
//                'device_type'=>0,
//                'num'=>3,
//                'is_bind'=>1,
//                'is_lock'=>1,
//                'qr_code'=>$qrcode,
//                'supplier_type'=>1,
//                'card_type'=>1,
//                'remark'=>'佐康'.($k+1),
//
//            ];
//        }
//        (new MachineDevice())->saveAll($data);
//        return json(['code'=>200,'msg'=>'成功']);
//    }

    public function send_email()
    {
        $mail = new PHPMailer(true);
        $address = '551863917@aqq.com';
        $mail->IsSMTP(); // 使用SMTP方式发送
        $mail->CharSet = 'UTF-8';// 设置邮件的字符编码
        $mail->Host = "smtp.qq.com"; // 您的企业邮局域名
        $mail->SMTPAuth = true; // 启用SMTP验证功能
        $mail->Port = 25; //SMTP端口
        $mail->Username = '460610610@qq.com'; // 邮局用户名(请填写完整的email地址)
        $mail->Password = 'jrtzpltuzxkreahh'; // 邮局密码 邮箱授权码
        $mail->From = '460610610@qq.com'; //邮件发送者email地址
        $mail->FromName = "匪石科技";
        $mail->AddAddress($address, "");//收件人地址，可以替换成任何想要接收邮件的email信箱,格式是AddAddress("收件人email","收件人姓名")
//$mail->AddReplyTo("", "");
//$mail->AddAttachment("/var/tmp/file.tar.gz"); // 添加附件
        $mail->IsHTML(true); // set email format to HTML //是否使用HTML格式
        $mail->Subject = "测试"; //邮件标题
        $mail->Body = "Hello,这是测试邮件<br/>
        第二行"; //邮件内容
        if (!$mail->Send()) {
            echo "邮件发送失败. <p>";
            echo "错误原因: " . $mail->ErrorInfo;
            exit;
        }
        echo "邮件发送成功";
    }

    public function onlineEmail()
    {
        $date = date('Y-m-d H:i:s');
        $title = '套餐升级提醒';
        $info = [
            'username' => 'admin',
            'device_sn' => '123456789',
        ];
        $body = "有新的套餐升级订单提交,请尽快处理!<br>用户名:" .
            $info['username'] . ',' .
            '<br>设备号:' . $info['device_sn'] .
            '<br>时间:' . $date;
        $address = '2388747604@qq.com';
        $res = (new Email())->send_email($title, $body, $address);
        var_dump($res);
        die();
    }

    public function useCompanyWx()
    {
        $companyWx = new CompanyWX();
        $companyWx->secret = 'OEsJouXDJYE4wv3iDvxx5eeTBH-qhVBwK29lMzX2Eak';
        $companyWx->test_params();
    }

    public function createCode()
    {
        $token = "jhcggM6XX5fx_lmDc4VGPbRbkD1yvty4S7NITJG_pjjmn-jCgG56dzZUHuOS8G85f5_KKnGdHmATsjQRbaar3E6xtuSsGsCzKwtIJzpGs5INkL18-8OrAra2TziFDgp4Z72Pw_7V0zAA4RC-vTKxp8NkA74Zn6v6610EBQjP0IO65cIsDhPQ9iUiGcni4bvWqTCMWJwXJOjPirJhaJhUbg";
//        $secret = 'OEsJouXDJYE4wv3iDvxx5eeTBH-qhVBwK29lMzX2Eak';
//        $corId = 'ww401187b7a2743e65';
//        $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$corId}&corpsecret={$secret}&debug=1";
//        $res = https_request($url);
//        var_dump($res);
        $data = [
            "type" => 1,
            "scene" => 2,
            "remark" => "渠道客户",
            "state" => "teststate",
            "user" => ["Mi"],
            "is_temp" => true,
            "expires_in" => 7200,
            "chat_expires_in" => 7200,
            "is_exclusive" => true,
//            "conclusions" =>
//                [
//                    "text" =>
//                        [
//                            "content" => "点击卡片进行购买"
//                        ],
//                    "miniprogram" =>
//                        [
//                            "title" => "消息标题",
//                            "pic_media_id" => "MEDIA_ID",
//                            "appid" => "wx8bd80126147dfAAA",
//                            "page" => "/path/index.html"
//                        ]
//                ]
        ];
        $url = "https://qyapi.weixin.qq.com/cgi-bin/externalcontact/add_contact_way?access_token=$token";
        $res = https_request($url, $data);
        var_dump($res);

    }

    public function getUser()
    {
        $token = "jhcggM6XX5fx_lmDc4VGPbRbkD1yvty4S7NITJG_pjjmn-jCgG56dzZUHuOS8G85f5_KKnGdHmATsjQRbaar3E6xtuSsGsCzKwtIJzpGs5INkL18-8OrAra2TziFDgp4Z72Pw_7V0zAA4RC-vTKxp8NkA74Zn6v6610EBQjP0IO65cIsDhPQ9iUiGcni4bvWqTCMWJwXJOjPirJhaJhUbg";
        $url = "https://qyapi.weixin.qq.com/cgi-bin/externalcontact/get_follow_user_list?access_token=$token";
        $res = https_request($url);
        var_dump($res);
        die();
    }

    public function text()
    {
        $money = 59.7;
        $device_id = 1;
        $uid = 2;//设备所属人
        $list = Db::name('fenyong')->where('device_id', $device_id)->order('add_user asc')->select();
        $uids = Db::name('fenyong')->where('device_id', $device_id)->column('uid');
        $fenyong_user = Db::name('system_admin')->whereIn('id', array_unique($uids))->column('username,role_id', 'id');
        if ($list) {
            $new_list = $this->getTree($list);
            $add_user = array_keys($new_list)[0];
            $data = $this->getResult($new_list, $add_user, $fenyong_user, $money);
            $total = 0;
            foreach ($data as $k => $v) {
                $total += $v['money'];
            }
            if ($total != $money) {
                foreach ($data as $k => $v) {
                    if ($v['uid'] == $uid) {
                        $data[$k]['money'] = round($v['money'] + $money - $total, 2);
                    }
                }
            }
            var_dump(array_column($data, 'money', 'uid'));
            die();
            return $data;
        } else {
            $data = [['uid' => $uid, 'money' => $money]];
            return $data;
        }

    }

    public function getResult($list, $add_user, $fenyong_user, $money)
    {
        $arr = [];
        foreach ($list[$add_user] as $k => $v) {
            if (($v['uid'] != $add_user && $fenyong_user[$v['uid']]['role_id'] > 5) || ($v['uid'] == $add_user && $fenyong_user[$v['uid']]['role_id'] < 7)) {
                //下级代理商进不来,下级代理商通过else继续分佣
                $ratio_money = round(100 * $money * $v['ratio'] / 100) / 100;
                if ($ratio_money > 0) {
                    $arr[] = ['uid' => $v['uid'], 'money' => $ratio_money];
                }
            } else {
                $ratio_money = round(100 * $money * $v['ratio'] / 100) / 100;
                if ($ratio_money > 0) {
                    $data = $this->getResult($list, $v['uid'], $fenyong_user, $ratio_money);
                    $arr = array_merge($arr, $data);
                }
            }
        }
        return $arr;
    }

    public function getTree($list)
    {
        $arr = [];
        foreach ($list as $k => $v) {
            $arr[$v['add_user']][] = $v;
        }
        return $arr;
    }

    public function sendMs()
    {
//        $res=(new AliMsg())->sendSms('15938829033','123456','SMS_230640135');
//        var_dump($res);die();
    }

    public function check()
    {
        $order_sn = '454564564564';
        $userId = '2088422420053556';
        $goodsCode = 'PY0298771221896';
        (new Yuepai())->check($order_sn, $userId, $goodsCode);
    }

    public function test1()
    {
        $res = "https://fs-manghe.oss-cn-hangzhou.aliyuncs.com/image/exampleobject.png";
        $url = dirname($res) . '/' . urlencode(basename($res));
        var_dump($url);
        die();
        (new Oss())->uploadToOss();
    }

    //获取群列表
    public function getGroup()
    {
        $companyWx = new CompanyWX('ww401187b7a2743e65', 'OEsJouXDJYE4wv3iDvxx5eeTBH-qhVBwK29lMzX2Eak');
        $res = $companyWx->getGroupList();
        if ($res['code'] == 200) {
            $list = $res['list'];
            foreach ($list as $k => $v) {
                $detail = $companyWx->getGroupDetail($v['chat_id']);

                if ($detail['code'] == 200) {

                    $list[$k]['name'] = $detail['data']['name'] ? $detail['data']['name'] : '未命名群';
                } else {
                    $list[$k]['name'] = '未命名';
                }
            }
            return json(['code' => 200, 'msg' => $list]);
        } else {
            return json(['code' => 100, 'msg' => $res['msg']]);
        }

    }

    public function groupQrCode()
    {
        $goods_id = request()->get('goods_id', '');
        $chat_id = request()->get('chat_id', '');
        if (!$goods_id || !$chat_id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MallGoodsModel();
        $goods = $model->where('id', $goods_id)->find();
        if (!$goods) {
            return json(['code' => 100, 'msg' => '商品不存在']);
        }
        if ($goods['type'] !== 1) {
            return json(['code' => 100, '普通售卖商品不可配置客户群']);
        }
        if (!$goods['company_id'] || !$goods['company_user_id']) {
            return json(['code' => 100, 'msg' => '请先配置企微用户!']);
        }
        $companyModel = new CompanyWxModel();
        $company = $companyModel->where('id', $goods['company_id'])->find();
        if (!$company) {
            return json(['code' => 100, 'msg' => '企业微信丢失']);
        }
        $companyWx = new CompanyWX($company['corId'], $company['secret']);
        $config = $companyWx->addJoinWay($goods_id, $chat_id);
        if ($config['code'] == 200) {
            $config_id = $config['data']['config_id'];
            //获取配置详情
            $detail = $companyWx->getJoinWay($config_id);
            if ($config['code'] == 200) {
                $qr_code = $detail['data']['qr_code'];
                $data = [
                    'group_config_id' => $config_id,
                    'chat_id' => $chat_id,
                    'group_code' => $qr_code
                ];
                $model->where('id', $goods_id)->update($data);
                return json(['code' => 200, 'msg' => '配置成功']);
            } else {
                return json($config);
            }
        } else {
            return json($config);
        }
    }

    public function testQrcode()
    {
        $file = qrcode1('123456', 1);
        var_dump($file);
    }

    public function getArea()
    {
        $area = Cache::store('redis')->get('area_txt');
        if ($area) {
            return json(['code' => 200, 'data' => $area]);
        } else {
            $url = "https://apis.map.qq.com/ws/district/v1/list?key=B4DBZ-2CM34-YFZUV-K6SHN-ODP36-NTFQJ";
            $res = https_request($url);
            $data = json_decode($res, true);
            if ($data['status'] == 0) {
                $result = $data['result'];
                $area = $result[0];
                foreach ($area as $k => $v) {
                    $province_code = substr($v['id'], 0, 2);
                    $city_arr = [];
                    foreach ($result[1] as $x => $y) {
                        if (substr($y['id'], 0, 2) == $province_code) {
                            $city_code = substr($y['id'], 0, 4);
                            $area_item = [];
                            foreach ($result[2] as $j => $l) {
                                $area_code = substr($l['id'], 0, 4);
                                if ($area_code == $city_code) {
                                    $area_item[] = $l;
                                    unset($result[2][$j]);
                                }
                            }
                            if ($area_item) {
                                $y['children'] = $area_item;
                            }
                            $city_arr[] = $y;
                            unset($result[1][$x]);
                        }
                    }
                    if ($city_arr) {
                        $area[$k]['children'] = $city_arr;

                    }
                }
                Cache::store('redis')->set('area_txt', $area);
                return json(['code' => 200, 'data' => $area]);
            } else {
                return json(['code' => 100, 'msg' => '获取失败']);
            }
        }
    }

}
