<?php

namespace app\applet\controller;

use think\Controller;
use think\Env;

class Feedback extends Controller
{
    public function index(){
        $post = $this->request->post();
        $result = (new \app\index\model\OperateFeedbackModel())->save($post);
        if ($result){
            return json(['code'=>200,'msg'=>'反馈成功']);
        }else{
            return json(['code'=>100,'msg'=>'反馈失败']);
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
