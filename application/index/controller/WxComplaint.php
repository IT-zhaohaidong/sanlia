<?php

namespace app\index\controller;


use app\index\common\Wxpay;
use app\index\common\WxV3Pay;
use think\Cache;
use think\Env;

class WxComplaint extends BaseController
{
    /**
     * 初始化
     */
    protected $mchid = "1642723027";
    protected $key = "Yxc15943579579Yxc15943579579Yxc1";
    protected $v3key = "";

    public function getList()
    {
        $params = request()->get();
        $limit = request()->get('limit', 15);
        $page = request()->get('page', 1);
        $start_time = strtotime($params['begin_date']);
        $end_time = strtotime($params['end_date']);
        $day = ($end_time - $start_time) / (3600 * 24);
        if ($day > 30) {
            return json(['code' => 100, 'msg' => '查询日期跨度不得超过30天']);
        }
        $data = [
            'limit' => $limit,
            'offset' => $limit * ($page - 1),
            'begin_date' => date('Y-m-d', $start_time),
            'end_date' => date('Y-m-d', $end_time),
            'complainted_mchid' => $this->mchid
        ];
        $v3Pay = new WxV3Pay();
        $url = "https://api.mch.weixin.qq.com/v3/merchant-service/complaints-v2";
        $res = $v3Pay->wx_get($url, json_encode($data));
        $res = json_decode($res, true);
        if (!empty($res['data'])) {
            foreach ($res['data'] as $k => $v) {
                $res['data'][$k]['payer_phone'] = !empty($v['payer_phone']) ? self::getEncrypt($v['payer_phone']) : '';
            }
        }

        if (!$res) {
            return json(['code' => 100, 'msg' => "Can't connect the server"]);
        }
        if (!empty($res['code'])) {
            return json(['code' => 100, 'msg' => $res['message']]);
        }
        return json(['code' => 200, 'data' => $res, 'params' => $params]);
    }

    private function getEncrypt($str)
    {
        //$str是待加密字符串
        $public_key_path = $_SERVER['DOCUMENT_ROOT'] . '/apiclient_key.pem';
        $public_key = openssl_get_privatekey(file_get_contents($public_key_path));
        $encrypted = '';
        if (openssl_private_decrypt(base64_decode($str), $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING)) {
            //base64编码
            $sign = $encrypted;
        } else {
            throw new \Exception('decrypt failed');
        }
        return $sign;
    }

    public function getDetail()
    {
        $params = request()->get();
        if (empty($params['complaint_id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        // 200000020220630130010155391
        $url = 'https://api.mch.weixin.qq.com/v3/merchant-service/complaints-v2/' . $params['complaint_id'];
        $v3Pay = new WxV3Pay();
        $res = $v3Pay->wx_get($url);
        $res = json_decode($res, true);
        if (!$res) {
            return json(['code' => 100, 'msg' => "Can't connect the server"]);
        }
        if (!empty($res['code'])) {
            return json(['code' => 100, 'msg' => $res['message']]);
        }
        $res['payer_phone'] = !empty($res['payer_phone']) ? self::getEncrypt($res['payer_phone']) : '';
        return json(['code' => 200, 'data' => $res]);
    }

    //回复用户
    public function response()
    {
        $complaint_id = request()->post('complaint_id', '');
        $response_content = request()->post('response_content', '');
        if (!$complaint_id || !$response_content) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if (strlen($response_content) >= 200) {
            return json(['code' => 100, 'msg' => '限制200个字符以内']);
        }

        $url = "https://api.mch.weixin.qq.com/v3/merchant-service/complaints-v2/{$complaint_id}/response";
        $v3Pay = new WxV3Pay();
        $data = json_encode(['complainted_mchid' => $this->mchid, 'response_content' => $response_content]);
        $res = $v3Pay->wx_post($url, $data);
        $res = json_decode($res, true);
        if (!empty($res['code'])) {
            return json(['code' => 100, 'msg' => $res['message']]);
        }
        return json(['code' => 200, 'msg' => '回复成功']);
    }

    //反馈处理完成
    public function dealComplete()
    {
        $complaint_id = request()->post('complaint_id', '');
        if (!$complaint_id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $url = "https://api.mch.weixin.qq.com/v3/merchant-service/complaints-v2/{$complaint_id}/complete";
        $v3Pay = new WxV3Pay();
        $data = json_encode(['complainted_mchid' => $this->mchid]);
        $res = $v3Pay->wx_post($url, $data);
        $res = json_decode($res, true);
        if (!$res) {
            return json(['code' => 100, 'msg' => "Can't connect the server"]);
        }
        if (!empty($res['code'])) {
            return json(['code' => 100, 'msg' => $res['message']]);
        }
        return json(['code' => 200, 'data' => $res]);
    }

    //投诉协商历史
    public function history()
    {
        $params = request()->get();
        $limit = request()->get('limit', 15);
        $page = request()->get('page', 1);
        $complaint_id = request()->get('complaint_id', '');
        if (!$complaint_id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = [
            'limit' => $limit,
            'offset' => $limit * ($page - 1),
        ];
        $url = "https://api.mch.weixin.qq.com/v3/merchant-service/complaints-v2/{$complaint_id}/negotiation-historys";
        $v3Pay = new WxV3Pay();
        $res = $v3Pay->wx_get($url, json_encode($data));
        $res = json_decode($res, true);
        if (!$res) {
            return json(['code' => 100, 'msg' => "Can't connect the server"]);
        }
        if (!empty($res['code'])) {
            return json(['code' => 100, 'msg' => $res['message']]);
        }
        return json(['code' => 200, 'data' => $res, 'params' => $params]);
    }


    public function uploadImage()
    {
        $file = $_FILES['file'];
        $file_name = $file['name'];//获取缓存区图片,格式不能变
        $type = array("jpg", "jpeg", "gif", 'png', 'bmp', 'mp4');//允许选择的文件类型
        $ext = explode(".", $file_name);//拆分获取图片名
        $ext = $ext[count($ext) - 1];//取图片的后缀名
        $path = dirname(dirname(dirname(dirname(__FILE__))));
        if (in_array($ext, $type)) {
            $name = "/public/upload/" . date('Ymd') . '/';
            $dirpath = $path . $name;
            if (!is_dir($dirpath)) {
                mkdir($dirpath, 0777, true);
            }
            $time = time();
            $filename = $dirpath . $time . '.' . $ext;
            $fp = fopen($file["tmp_name"], 'rb');
            $content = fread($fp, filesize($file["tmp_name"])); //二进制数据
            fclose($fp);
            move_uploaded_file($file["tmp_name"], $filename);
            $sha256 = hash_file("sha256", $filename);
            $image = $time . '.' . $ext;
            $data = ["filename" => $image, "sha256" => $sha256, 'content' => $content];
            $url = 'https://api.mch.weixin.qq.com/v3/merchant-service/images/upload';
            $v3Pay = new WxV3Pay();
            $res = $v3Pay->upload($url, $data);
            $res = json_decode($res, true);
            if (!$res) {
                return json(['code' => 100, 'msg' => "Can't connect the server"]);
            }
            if (!empty($res['code'])) {
                return json(['code' => 100, 'msg' => $res['message']]);
            }
            $data = ['code' => 200, 'data' => ['filename' => $time . '.' . $ext, 'media_id' => $res['media_id']]];
            return json($data);
        } else {
            return json(['code' => 100, 'msg' => '文件类型错误!']);
        }
    }

    public function createNotify()
    {
        $url = 'https://api.mch.weixin.qq.com/v3/merchant-service/complaint-notifications';
        $notify_url = 'https://api/feishi.vip/index/wx_complaint/notify';
        $params = ['uil' => $notify_url];
        $v3Pay = new WxV3Pay();
        $res = $v3Pay->wx_post($url, json_encode($params));
        $res = json_decode($res, true);
        if (!$res) {
            return json(['code' => 100, 'msg' => "Can't connect the server"]);
        }
        if (!empty($res['code'])) {
            return json(['code' => 100, 'msg' => $res['message']]);
        }
        return json(['code' => 200, 'data' => $res]);
    }

    public function notify()
    {
        $xml = file_get_contents("php://input");
        trace($xml, '投诉通知回调');
//        //将服务器返回的XML数据转化为数组
//        $data = (new OperateOffice)->xml2array($xml);
//        // 保存微信服务器返回的签名sign
//        $data_sign = $data['sign'];
        if (true) {
            $data = ['code' => 200, 'message' => ''];
        } else {
            $data = ['code' => 400, 'message' => '失败'];
        }
        return json($data);
    }
}
