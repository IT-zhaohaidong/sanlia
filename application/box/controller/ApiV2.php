<?php

namespace app\box\controller;


use app\index\model\AdverMaterialModel;
use app\index\model\MachineDevice;
use app\index\model\MachineGoods;
use app\index\model\VideoLogModel;
use think\Cache;
use think\Controller;
use think\Db;

//前端控制出货设备版本
class ApiV2 extends Controller
{
    public function getDeviceSn()
    {
        $str = 'FS' . date('Ymd');
        $device = Db::name('machine_device')
            ->where("device_sn", 'like', '%' . $str . '%')
            ->order('device_sn desc')
            ->field('device_sn')
            ->find();
        if ($device) {
            $num = substr($device['device_sn'], strlen($str)) + 1;
        } else {
            $num = 1000;
        }
        $device_sn = $str . $num;
        return $device_sn;
    }

    //获取广告
    public function getAdver()
    {
        $imei = $this->request->get("imei", "");
        if (!$imei) {
            return json(["code" => 100, "msg" => "imei号不能为空"]);
        }
        $device = Db::name('machine_device')->where(['imei' => $imei])->where('delete_time', null)->find();
        if (!$device) {
//            $device_sn = $this->getDeviceSn();
//            $data = [
//                'imei' => $imei,
//                'device_sn' => $device_sn,
//                'device_name' => $imei,
//                'qr_code' => qrcode($device_sn),
//                'official_code' => "",
//                'uid' => 1,
//                'supply_id' => 2
//            ];
//            $id = Db::name('machine_device')->insertGetId($data);
//            $device['id'] = $id;
            return json(['code' => 100, 'msg' => '设备不存在,稍后重试']);
        }

        $video = Db::name('machine_video')
            ->where(['device_id' => $device['id']])
            ->column('video_id');
        $videos = [];
        if ($video) {
            $videos = Db::name('adver_material')
                ->where('id', 'in', $video)
                ->where('start_time', '<=', time())
                ->where('end_time', '>=', time() - 24 * 3600)->column('url');
            $videos = array_values($videos);
        }
//        $model = new  \app\index\model\MachineDevice();
//        $image = $model->alias('d')
//            ->join('machine_banner b', 'd.banner_id=b.id', 'left')
//            ->where('d.device_sn', $device['device_sn'])
//            ->value('b.material_image');
//        $images = $image ? explode(',', $image) : [];
//        $wx_qr_code = (new CompanyQrcodeModel())->where('device_sn', $device['device_sn'])->value('qr_code');
        $data = ['video' => $videos, 'images' => [], 'qr_code' => $device['qr_code']];
        return json(["code" => 200, "data" => $data]);
    }

    public function addVideoLog()
    {
        $params = request()->get();
        $video_id = (new AdverMaterialModel())->where('type', 2)->where('url', $params['url'])->value('id');
        if (!$video_id) {
            return json(['code' => 100, 'msg' => '视频不存在']);
        }
        $device_sn = Db::name('machine_device')->where(['imei' => $params['imei']])->where('delete_time', null)->value('device_sn');
        if (!$device_sn) {
            return json(['code' => 100, 'msg' => '设备不存在']);
        }
        $data = [
            'device_sn' => $device_sn,
            'video_id' => $video_id
        ];
        (new VideoLogModel())->save($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //获取出货视频
    public function getOutVideo()
    {
        $imei = $this->request->get("imei", "");
        $num = $this->request->get("num", "");
        if (!$imei || !$num) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device_sn = (new MachineDevice())->where('imei', $imei)->value('device_sn');
        if (!$device_sn) {
            return json(['code' => 100, 'msg' => '设备不存在']);
        }
        $goods = (new MachineGoods())->alias('mg')
            ->join('mall_goods g', 'g.id=mg.goods_id', 'left')
            ->where(['mg.device_sn' => $device_sn, 'mg.num' => $num])
            ->field('g.id,g.video_id')
            ->find();
        if (!$goods || !$goods['video_id']) {
            return json(['code' => 100, 'msg' => '未配置视频']);
        }
        $video = (new AdverMaterialModel())
            ->where('id', $goods['video_id'])
            ->field('url')
            ->find();
        return json(['code' => 200, 'data' => $video]);
    }

    function qrcode($msg)
    {
        $res_msg = "device_sn=" . $msg;

        $url = 'http://korea.feishi.vip/#/';
        // 1. 生成原始的二维码(生成图片文件)=
        require_once $_SERVER['DOCUMENT_ROOT'] . '/static/phpqrcode.php';
        $value = $url . "?" . $res_msg;;         //二维码内容
        $errorCorrectionLevel = 'L';  //容错级别
        $matrixPointSize = 10;      //生成图片大小
        //生成二维码图片
        $filename = $_SERVER['DOCUMENT_ROOT'] . '/upload/device_code/' . time() . '.png';
        $time = time() . rand(0, 9);
        $filename1 = $_SERVER['DOCUMENT_ROOT'] . '/upload/device_code/' . $time . '.png';
        \QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 4);

        $QR = $filename;        //已经生成的原始二维码图片文件
        $QR = imagecreatefromstring(file_get_contents($QR));
        //输出图片
        imagepng($QR, $filename);
        imagedestroy($QR);

        $fontPath = $_SERVER['DOCUMENT_ROOT'] . "/static/plugs/font-awesome-4.7.0/fonts/simkai.ttf";
        $obj = addFontToPic($filename, $fontPath, 18, $msg, 360, $filename1);
        return 'http://' . $_SERVER['SERVER_NAME'] . '/upload/device_code/' . $time . '.png';
    }


    //设备登录验证
    public function deviceLogin()
    {
        $imei = request()->get('imei', '');
        $code = request()->get('code', '');
        if (!$imei) {
            return json(['code' => 100, 'msg' => 'imei号缺失']);
        }
        $device_sn = (new MachineDevice())->where('imei', $imei)->value('device_sn');
        $str = $device_sn . '_loginCode';
        $res = Cache::store('redis')->get($str);
        if (!$res) {
            return json(['code' => 100, 'msg' => '请先获取登录码']);
        }
        if ($code != $res) {
            return json(['code' => 100, 'msg' => '登录码错误']);
        }
        return json(['code' => 200, 'msg' => '验证成功']);
    }

}
