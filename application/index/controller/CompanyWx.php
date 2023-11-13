<?php

namespace app\index\controller;

use app\index\model\CompanyQrcodeModel;
use app\index\model\CompanyWxModel;
use think\Db;
use think\Env;

class CompanyWx extends BaseController
{
    //企业列表
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $where['c.device_sn'] = $this->getBuHuoWhere();
            } else {
                $where['c.uid'] = $user['id'];
            }
        }
        if (!empty($params['company_name'])) {
            $where['c.company_name'] = ['like', '%' . $params['company_name'] . '%'];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (!empty($params['corId'])) {
            $where['c.corId'] = ['like', '%' . $params['corId'] . '%'];
        }
        $model = new CompanyWxModel();
        $count = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id', 'left')
            ->where($where)
            ->limit($limit)
            ->page($page)
            ->order('id desc')
            ->field('c.*,a.username')
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //添加企业
    public function addCompany()
    {
        $post = request()->post();
        $user = $this->user;
        $model = new CompanyWxModel();
        $row = $model->where('corId', trim($post['corId']))->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '该企业微信已被使用']);
        }
        $data = [
            'company_name' => $post['company_name'],
            'corId' => trim($post['corId']),
            'secret' => trim($post['secret']),
            'token' => trim($post['token']),
            'encodingAesKey' => $post['encodingAesKey'],
            'uid' => $user['id'],
            'is_notify' => 2
        ];
        $model->where(['is_notify' => 2])->update(['is_notify' => 0]);
        $model->save($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //获取二维码列表
    public function getQrcodeList()
    {
        $params = request()->get();
        $corId = request()->get('corId');
        if (empty($corId)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $company = (new CompanyWxModel())->where('corId', $corId)->find();
        if (!$company) {
            return json(['code' => 100, 'msg' => '企业不存在']);
        }
        if (!$company['secret']) {
            return json(['code' => 100, 'msg' => '企业信息不完整']);
        }
        if ($company['is_notify'] == 0) {
            return json(['code' => 100, 'msg' => '请先联系开发人员,配置回调']);
        }
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $model = new CompanyQrcodeModel();
        $count = $model
            ->where('corId', $corId)
            ->count();
        $list = $model
            ->where('corId', $corId)
            ->page($page)->limit($limit)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'count' => $count, 'data' => $list, 'params' => $params]);
    }

    //删除二维码
    public function delQrCode()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new CompanyQrcodeModel();
        $row = $model->where('id', $id)->find();
        $company = (new CompanyWxModel())->where('corId', $row['corId'])->find();
        $companyWx = new \app\index\common\CompanyWX($row['corId'], $company['secret']);
        $res = $companyWx->delCode($row['config_id']);
        if ($res['code'] == 100) {
            return json(['code' => 100, 'msg' => '删除失败']);
        }
        $model->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功,请清除设备上的二维码']);
    }

    //获取企业用户列表
    public function getCompanyUser()
    {
        $corId = request()->get('corId');
        if (empty($corId)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $company = (new CompanyWxModel())->where('corId', $corId)->find();
//        $company['secret'] = 'OEsJouXDJYE4wv3iDvxx5eeTBH-qhVBwK29lMzX2Eak';
        if (!$company) {
            return json(['code' => 100, 'msg' => '企业不存在']);
        }
        if (!$company['secret']) {
            return json(['code' => 100, 'msg' => '企业信息不完整']);
        }
        $companyWx = new \app\index\common\CompanyWX($corId, $company['secret']);
        $res = $companyWx->getUser();
        if ($res['code'] == 100) {
            return json(['code' => 100, 'msg' => '用户获取失败']);
        }
//        $qrcodeModel = new CompanyQrcodeModel();
//        $user = $qrcodeModel->where('corId', $corId)->column('userId');
//        $userId = array_values($user);
//        $arr = array_diff($res['list'], $userId);
        return json(['code' => 200, 'data' => $res['list']]);
    }

    //获取设备列表
    public function getDevice()
    {
        $id = request()->get('id', '');

        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $companyModel = new CompanyWxModel();
        $deviceModel = new \app\index\model\MachineDevice();
        $companyQrcodeModel = new CompanyQrcodeModel();
        $row = $companyModel->where('id', $id)->find();
        if (!$row) {
            return json(['code' => 100, 'msg' => '企业不存在']);
        }
        $user = $this->user;
        $device = $deviceModel->where('uid', $user['id'])->column('device_sn');
        $qrcodeDevice = $companyQrcodeModel->where('corId', $row['corId'])->column('device_sn');
        $device_sns = array_values($device);
        $qrcodeDeviceSns = array_values($qrcodeDevice);
        $device_sn = array_diff($device_sns, $qrcodeDeviceSns);
        $device = $deviceModel
            ->where('uid', $user['id'])
            ->whereIn('device_sn', $device_sn)
            ->field('device_sn,device_name')
            ->select();
        $arr = [];
        foreach ($device as $k => $v) {
            $arr[] = ['value' => $v['device_sn'], 'label' => $v['device_sn'] . '(' . $v['device_name'] . ')'];
        }
        return json(['code' => 200, 'data' => $arr]);
    }

    //创建二维码
    public function createUserQrcode()
    {
        $post = request()->post();
        if (empty($post['corId']) || empty($post['userId']) || empty($post['device_sn'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $companyModel = new CompanyWxModel();
        $companyQrcodeModel = new CompanyQrcodeModel();
        $data = [
            'corId' => $post['corId'],
            'userId' => $post['userId'],
            'device_sn' => $post['device_sn'],
        ];
        $company = $companyModel->where('corId', $post['corId'])->find();
        if (!$company) {
            return json(['code' => 100, 'msg' => '企业不存在']);
        }
        if (!$company['media_id']) {
            return json(['code' => 100, 'msg' => '请先配置素材图']);
        }
        $companyWx = new \app\index\common\CompanyWX($company['corId'], $company['secret']);
        $res = $companyWx->createCode($post['userId'], $post['device_sn']);
        if ($res['code'] == 100) {
            return json(['code' => 100, 'msg' => '二维码创建失败']);
        }
        $data['qr_code'] = $res['data']['qr_code'];
        $data['config_id'] = $res['data']['config_id'];
        $companyQrcodeModel->save($data);
        return json(['code' => 200, 'msg' => '创建成功']);
    }

    //上传到企业微信获取永久素材
    public function uploadimg()
    {
        $file = $_FILES['file'];
        $id = request()->post('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $file_name = $file['name'];//获取缓存区图片,格式不能变
        $type = array("jpg", "jpeg", 'png');//允许选择的文件类型
        $ext = explode(".", $file_name);//拆分获取图片名
        $ext = $ext[count($ext) - 1];//取图片的后缀名
        $path = dirname(dirname(dirname(dirname(__FILE__))));
        if (in_array($ext, $type)) {
            if ($_FILES["file"]["size"] / 1024 / 1024 > 2) {
                return json(['code' => 100, 'msg' => '上传文件不可大于2M!']);
            }
            $name = "/public/upload/" . date('Ymd') . '/';
            $dirpath = $path . $name;
            if (!is_dir($dirpath)) {
                mkdir($dirpath, 0777, true);
            }
            $time = time();
            $filename = $dirpath . $time . '.' . $ext;
            move_uploaded_file($file["tmp_name"], $filename);
            $param['filename'] = $filename;
            $company = (new CompanyWxModel())->where('id', $id)->find();
            $companyWx = new \app\index\common\CompanyWX($company['corId'], $company['secret']);
            $media_id = $companyWx->uploadImg($filename);
            $filename = Env::get('server.servername', 'http://api.hnchaohai.com') . '/upload/' . date('Ymd') . '/' . $time . '.' . $ext;

            $data = ['code' => 200, 'data' => ['filename' => $filename, 'media_id' => $media_id]];
            return json($data);
        } else {
            return json(['code' => 100, 'msg' => '文件类型错误!']);
        }
    }

    //更新素材图
    public function saveImage()
    {
        $post = request()->post();
        if (empty($post['id']) || empty($post['media_id']) || empty($post['image'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new CompanyWxModel();
        $model->where('id', $post['id'])->update(['image' => $post['image'], 'media_id' => $post['media_id']]);
        return json(['code' => 200, 'msg' => '保存成功']);
    }

    //开始配置回调
    public function startNotify()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new CompanyWxModel();
        $model->where(['is_notify' => 2])->update(['is_notify' => 0]);
        $model->where(['id' => $id])->update(['is_notify' => 2]);
        return json(['code' => 200, 'msg' => '已开始,请在企微后台保存回调地址']);
    }
}
