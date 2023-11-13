<?php

namespace app\medicine\controller;

use app\index\model\MachineDevice;
use app\index\model\OperateUserModel;
use think\Controller;
use think\Db;
use think\Env;


class User extends Controller
{
    /**
     * 获取用户信息
     */
    public function getUserInfo()
    {
        $openid = request()->get("openid", "");
        $device_sn = request()->get("device_sn", "");
        if (empty($openid)) {
            return json(['code' => 400, 'msg' => '缺少参数']);
        }
        $userObj = new OperateUserModel();
        $info = $userObj
            ->field("id,nickname,sex,photo,openid,uid")
            ->where("openid", $openid)->find();
        if ($info) {
            if (!empty($device_sn) && empty($info['uid'])) {
                $uid = (new MachineDevice())->where('device_sn', $device_sn)->value('uid');
                $userObj->where('id', $info['id'])->update(['uid' => $uid]);
            }
            $data = [
                "code" => 200,
                "msg" => "获取成功",
                "data" => $info
            ];
        } else {
            $data = [
                "code" => 400,
                "msg" => "用户不存在",
                "data" => $info
            ];
        }
        return json($data);
    }

    //反馈意见
    public function feedBack()
    {
        $post = $this->request->post();
        $post['device_type'] = 2;
        $result = (new \app\index\model\OperateFeedbackModel())->save($post);
        if ($result) {
            return json(['code' => 200, 'msg' => '反馈成功']);
        } else {
            return json(['code' => 100, 'msg' => '反馈失败']);
        }
    }

    /**
     * 上传文件
     */
    public function upload()
    {
        $file = $_FILES['file'];
        $file_name = $file['name'];//获取缓存区图片,格式不能变
        $type = array("jpg", "jpeg", "gif", 'png', 'bmp');//允许选择的文件类型
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
            move_uploaded_file($file["tmp_name"], $filename);
            $filename = Env::get('server.servername', 'http://api.hnchaohai.com') . '/upload/' . date('Ymd') . '/' . $time . '.' . $ext;
            $data = ['code' => 200, 'data' => ['filename' => $filename]];
            return json($data);
        } else {
            return json(['code' => 100, 'msg' => '文件类型错误!']);
        }
    }
}
