<?php

namespace app\index\controller;

use app\index\common\Oss;
use app\index\model\AppCateModel;
use app\index\model\AppVersionModel;
use think\Db;
use think\Env;

class AppVersion extends BaseController
{
    public function getList()
    {
        $page = request()->get('page');
        $limit = request()->get('limit');
        $model = new AppVersionModel();
        $count = $model->where('delete_time', null)->count();
        $list = $model->alias('v')
            ->join('app_cate c','c.app_key=v.app_key','left')
            ->where('v.delete_time', null)
            ->page($page)->limit($limit)
            ->field('v.*,c.cate_name')
            ->order('v.id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['upload_time'] = date('Y-m-d H:i:s', $v['upload_time']);
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count]);
    }

    public function save()
    {
        $data = request()->post();
        $model = new AppVersionModel();
        if (empty($data['id'])) {
            $data['upload_time'] = time();
            $data['apk_md5'] = md5($data['app_key']);
            $model->save($data);
        } else {
            $model->where(['id' => $data['id']])->update($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function uploadApk()
    {
        $file = $_FILES['file'];
        $file_name = $file['name'];//获取缓存区图片,格式不能变
        $type = array("apk");//允许选择的文件类型
        $ext = explode(".", $file_name);//拆分获取图片名
        $ext = $ext[count($ext) - 1];//取图片的后缀名
        $path = dirname(dirname(dirname(dirname(__FILE__))));
        if (in_array($ext, $type)) {
            $name = "/public/upload/apk/";
            $dirpath = $path . $name;
            if (!is_dir($dirpath)) {
                mkdir($dirpath, 0777, true);
            }
            $time = time();
            $filename = $dirpath . $time . '.' . $ext;
            move_uploaded_file($file["tmp_name"], $filename);
            $fileSize = filesize($filename);
            $fileSize /= pow(2, 20);
            $fileSize = number_format($fileSize, 3);
            $ossFile = "apk/" . $time . rand(1000, 9999) . '.' . $ext;
            $url = (new Oss())->uploadToOss($ossFile, $filename);
//            $filename = Env::get('server.servername', 'http://api.hnchaohai.com') . '/upload/apk/' . $time . '.' . $ext;
            $data = ['code' => 200, 'data' => ['filename' => $url, 'fileSize' => $fileSize]];
            return json($data);
        } else {
            return json(['code' => 100, 'msg' => '文件类型错误!']);
        }
    }

    public function del()
    {
        $id = request()->get('id', '');
        $model = new AppVersionModel();
        $model->where('id', $id)->update(['delete_time' => time()]);
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    public function createCate()
    {
        $post = request()->post();
        if (empty($post['cate_name'])||empty($post['app_key'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new AppCateModel();
        $row = $model->where('app_key', $post['app_key'])->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该APP类别已存在']);
        }
        $data = [
            'cate_name' => $post['cate_name'],
            'app_key' => $post['app_key'],
            'remark' => $post['remark'],
        ];
        $model->save($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function updateCate()
    {
        $post = request()->post();
        if (empty($post['id']) || empty($post['cate_name']) || empty($post['app_key'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new AppCateModel();
        $row = $model->where('id', '<>', $post['id'])->where('cate_name', $post['cate_name'])->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该APP类别已存在']);
        }
        $data = [
            'cate_name' => $post['cate_name'],
            'app_key' => $post['app_key'],
            'remark' => $post['remark'],
        ];
        $model->where('id', $post['id'])->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function getCateList()
    {
        $model = new AppCateModel();
        $list = $model->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function delCate()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new AppCateModel();
        $versionModel = new AppVersionModel();
        $app_key=$model->where('id',$id)->value('app_key');
        $row = $versionModel->where('app_key', $app_key)->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '不可删除,该分类下存在版本']);
        }
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }
}
