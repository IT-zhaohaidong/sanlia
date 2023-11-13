<?php

namespace app\index\controller;

use app\index\common\Email;
use app\index\common\Oss;
use app\index\model\DevicePartnerModel;
use app\index\model\FinanceWithdraw;
use app\index\model\SystemAdmin;
use app\index\model\SystemRole;
use think\Db;
use think\Env;

class Admin extends BaseController
{
    public function getList()
    {
        $prams = input('post.', '');
        $user = $this->user;
        $where = [];
        $adminModel = new SystemAdmin();
        $list = $adminModel->alias('a')
            ->join('operate_user u', 'a.openid=u.openid', 'left')
            ->where('a.delete_time', null)
            ->where($where)
            ->order('a.parent_id asc')
            ->field('a.*,u.nickname')
            ->select();
        $role = (new SystemRole())->column('name', 'id');
        $uids = [];
        foreach ($list as $k => $v) {
            $ids = explode(',', $v['role_id']);
            $list[$k]['role_name'] = $this->getRoleName($ids, $role);
            $list[$k]['balance'] = round($v['system_balance'] + $v['agent_wx_balance'] + $v['agent_ali_balance'], 2);
            if (!$v['photo']) {
                $list[$k]['photo'] = 'https://api.hnchaohai.com/upload/photo.png';
            } else {
                $dirpath = $_SERVER['DOCUMENT_ROOT'] . '/';
                $photo = str_replace(Env::get('server.server_name', ''), $dirpath, $v['photo']);
                if (!file_exists($photo)) {
                    $list[$k]['photo'] = 'https://api.hnchaohai.com/upload/photo.png';
                }
            }
            if ($v['role_id'] < 7) {
                $uids[] = $v['id'];
            }
        }
        $device_count = (new \app\index\model\MachineDevice())->whereIn('uid', $uids)->group('uid')->column('count(id) count', 'uid');
        foreach ($list as $k => $v) {
            if ($v['role_id'] < 7) {
                $list[$k]['device_num'] = isset($device_count[$v['id']]) ? $device_count[$v['id']] : 0;
            } else {
                $list[$k]['device_num'] = 0;
            }
        }
        if ($user['role_id'] == 1) {
            $pid = 0;
        } else {
            $pid = $user['id'];
        }
        $list = (new SystemAdmin())->tree($list, $pid, $pid);
        return json(['code' => 200, 'data' => $list, 'prams' => $prams]);
    }

    public function adminList()
    {
        $params = request()->get();
        $page = $params['page'] ? $params['page'] : 1;
        $limit = $params['limit'] ? $params['limit'] : 15;
        $user = $this->user;
        Db::name('system_admin')
            ->where('account_type', 1)
            ->where('expire_time', '>', 0)
            ->where('expire_time', '<=', time())
            ->delete();
        $adminModel = new SystemAdmin();
        $list = $adminModel
            ->where('delete_time', null)
            ->order('parent_id asc')
            ->field('id,parent_id')
            ->select();
        $ids = $adminModel->getId($user['id'], $list);
        $ids[] = $user['id'];
        $count = $adminModel
            ->whereIn('id', $ids)->count();
        $list = $adminModel->alias('a')
            ->join('operate_user u', 'a.openid=u.openid', 'left')
            ->whereIn('a.id', $ids)
            ->where('a.delete_time', null)
            ->order('a.parent_id asc')
            ->field('a.*,u.nickname')
            ->page($page)->limit($limit)
            ->select();
        $role = (new SystemRole())->column('name', 'id');
        foreach ($list as $k => $v) {
            $ids = explode(',', $v['role_id']);
            $list[$k]['role_name'] = $this->getRoleName($ids, $role);
            $list[$k]['balance'] = $v['system_balance'] + $v['agent_wx_balance'] + $v['agent_ali_balance'];
        }
        $total_page = ceil($count / $limit);
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params, 'total_page' => $total_page]);
    }

    //获取可以有子账号的账号
    public function getUserList()
    {
        $user = $this->user;
        $adminModel = new SystemAdmin();
        $where = [];
        if ($user['role_id'] != 1) {
            $where['parent_id|id'] = $user['id'];
        }
        $list = $adminModel->where('delete_time', null)
            ->where($where)
            ->where('role_id', '<=', 5)
            ->order('parent_id asc')
            ->field('id value,username label,parent_id pid')
            ->select();
        if ($list) {
            $pid = $list[0]['pid'];
            $list = (new SystemRole())->tree($list, $pid);
        }
        return json(['code' => 200, 'data' => $list]);
    }

    public function resetPassword()
    {
        $id = request()->post('id', '');
        $password = request()->post('password', '');
        $repassword = request()->post('repassword', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if (empty($password) || empty($repassword)) {
            return json(['code' => 100, 'msg' => '密码和重复密码必填']);
        }
        if ($password != $repassword) {
            return json(['code' => 100, 'msg' => '密码不一致']);
        }
        (new SystemAdmin())->where('id', $id)->update(['password' => password($password)]);
        return json(['code' => 200, 'msg' => '重置成功']);
    }


    public function getRoleName($ids, $role)
    {
        $name = '';
        foreach ($ids as $v) {
            if ($name) {
                $name .= ',';
            }
            $name .= isset($role[$v]) ? $role[$v] : '';
        }
        return $name;
    }

    public function getUserInfo()
    {
        $id = input('get.id', '');
        $adminModel = new SystemAdmin();
        $list = $adminModel->where(['id' => $id])->find();
        $list['roleIds'] = explode(',', $list['roleIds']);
        $list['cate_ids'] = explode(',', $list['cate_ids']);
        unset($list['password']);
        return json(['code' => 200, 'data' => $list]);
    }

    /**
     * 获取顶级分类
     */
    public function getCateList()
    {
        $cateModel = new \app\index\model\MallCate();
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $cate_arr = explode(',', $user['cate_ids']);
            $where['id'] = ['in', $cate_arr];
        }
        $list = $cateModel->where($where)->where('pid', 0)->where('delete_time', null)->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //编辑/添加
    public function save()
    {
        $post = input('post.', [], 'trim');
        $user = $this->user;
        $post['role_id'] = $post['roleIds'][count($post['roleIds']) - 1];
        if (in_array(3, $post['roleIds']) || in_array(4, $post['roleIds']) || in_array(5, $post['roleIds'])) {
            if (in_array(2, $post['roleIds'])) {
                $post['roleIds'][] = 2;
            }
        }
        $post['roleIds'] = implode(',', array_unique($post['roleIds']));
        $post['cate_ids'] = implode(',', $post['cate_ids']);
        $post['device_type'] = implode(',', $post['device_type']);

        if (!empty($post['password'])) {
            $post['password'] = password($post['password']);
        } else {
            unset($post['password']);
        }
        $device = empty($post['device']) ? [] : $post['device'];
        unset($post['device']);
        $model = new SystemAdmin();
        if (empty($post['id'])) {
            $row = $model->where('username', $post['username'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该用户已存在']);
            }
            $post['parent_id'] = $user['id'];
            $post['parentIds'] = $user['id'];
            $post['create_time'] = time();
            $id = $model->insertGetId($post);
            $post['id'] = $id;
            $qr_code = qrcode('', '', '', $id);
            $model->where('id', $id)->update(['qr_code' => $qr_code]);
        } else {
            unset($post['delete_time']);
            if ($post['id'] != 1) {
                $post['parentIds'] = implode(',', $post['parent_id']);
                $post['parent_id'] = $post['parent_id'][count($post['parent_id']) - 1];
            }
            unset($post['create_time']);
            unset($post['update_time']);
            $model->where('id', $post['id'])->update($post);
        }
        if ($device && $post['id'] != $user['id']) {
            $bind_device = (new DevicePartnerModel())
                ->where('admin_id', $user['id'])
                ->where('uid', $post['id'])
                ->column('admin_id,ratio,id', 'device_id');
            $device_id = [];
            foreach ($device as $k => $v) {
                $device_id[] = $v['id'];
            }
            (new DevicePartnerModel())->where('uid', $post['id'])->whereNotIn('device_id', $device_id)->delete();
            foreach ($device as $k => $v) {
//                if (isset($bind_device[$v['id']]) && $bind_device[$v['id']]['ratio'] != $v['ratio']) {
//                    (new DevicePartnerModel())
//                        ->where('id', $bind_device[$v['id']]['id'])
//                        ->update(['ratio' => $v['ratio']]);
//                }
                if (!isset($bind_device[$v['id']])) {
                    $data = [
                        'device_id' => $v['id'],
                        'admin_id' => $user['id'],
                        'uid' => $post['id'],
//                        'ratio' => $v['ratio']
                    ];
                    (new DevicePartnerModel())->save($data);
                }

            }
        } else {
            (new DevicePartnerModel())->where('uid', $post['id'])->delete();
        }
        return json(['code' => 200, 'msg' => '成功']);
    }


    //获取绑定的设备
    public function getBindDevice()
    {
        $id = request()->get('id', '');
        $list = (new DevicePartnerModel())
            ->alias('p')
            ->join('machine_device d', 'd.id=p.device_id', 'left')
            ->where('p.uid', $id)
            ->field('d.id,d.uid,d.device_name,d.imei,p.ratio')
            ->select();
        foreach ($list as $k => $v) {
            $row = (new DevicePartnerModel())
                ->where('admin_id', $v['uid'])
                ->where('device_id', $v['id'])
                ->group('device_id')
                ->field('sum(ratio) total_ratio')->find();
            $list[$k]['total_ratio'] = $row ? 100 - $row['total_ratio'] : 100;
        }
        return json(['code' => 200, 'data' => $list]);
    }

    public function del()
    {
        $id = input('get.id', '');
        $adminModel = new SystemAdmin();
        $rows = $adminModel->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }


    /**
     * 上传文件
     */
    public function upload()
    {
        $file = $_FILES['file'];
        $file_name = $file['name'];//获取缓存区图片,格式不能变
        $type = array("jpg", "jpeg", "gif", 'png', 'bmp', 'mp4');//允许选择的文件类型
        $ext = explode(".", $file_name);//拆分获取图片名
        $ext = $ext[count($ext) - 1];//取图片的后缀名
        $path = dirname(dirname(dirname(dirname(__FILE__))));
        if (in_array($ext, $type)) {
            if ($_FILES["file"]["size"] / 1024 / 1024 > 10) {
                return json(['code' => 100, 'msg' => '上传文件不可大于10M!']);
            }
            $name = "/public/upload/" . date('Ymd') . '/';
            $dirpath = $path . $name;
            if (!is_dir($dirpath)) {
                mkdir($dirpath, 0777, true);
            }
            $time = time();
            $filename = $dirpath . $time . '.' . $ext;
            move_uploaded_file($file["tmp_name"], $filename);
            $ossFile = "material/" . $time . rand(1000, 9999) . '.' . $ext;
            $url = (new Oss())->uploadToOss($ossFile, $filename);
//            $filename = Env::get('server.servername', 'http://api.hnchaohai.com') . '/upload/' . date('Ymd') . '/' . $time . '.' . $ext;
            $data = ['code' => 200, 'data' => ['filename' => $url]];
            return json($data);
        } else {
            return json(['code' => 100, 'msg' => '文件类型错误!']);
        }
    }

    public function removeImage()
    {
        $url = request()->get('url', '');
        if (empty($url)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $dirpath = $_SERVER['DOCUMENT_ROOT'] . '/';
        $path = str_replace(Env::get('server.server_name', ''), $dirpath, $url);
        unlink($path);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //修改密码
    public function changePassword()
    {
        $post = request()->post();
        if (empty($post['password']) || empty($post['repassword']) || empty($post['old_password'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if ($post['password'] != $post['repassword']) {
            return json(['code' => 100, 'msg' => '密码不一致']);
        }
        $user = $this->user;
        if (password($post['old_password']) != $user['password']) {
            return json(['code' => 100, 'msg' => '原密码错误']);
        }
        (new SystemAdmin())->where('id', $user['id'])->update(['password' => password($post['password'])]);
        Db::name('system_login')->where('uid', $user['id'])->delete();
        return json(['code' => 200, 'msg' => '修改成功']);
    }

    //修改头像
    public function changePhoto()
    {
        $post = request()->post();
        if (empty($post['photo'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = $this->user;
        (new SystemAdmin())->where('id', $user['id'])->update(['photo' => $post['photo']]);
        return json(['code' => 200, 'msg' => '修改成功']);
    }

    //修改邮箱
    public function changeEmail()
    {
        $post = request()->post();
        $user = $this->user;
        (new SystemAdmin())->where('id', $user['id'])->update(['email' => $post['email']]);
        return json(['code' => 200, 'msg' => '修改成功']);
    }

    public function createQrCode()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new SystemAdmin();
        $qr_code = $model->where('id', $id)->value('qr_code');
        if (empty($qr_code)) {
            $qr_code = qrcode('', '', '', $id);
            $model->where('id', $id)->update(['qr_code' => $qr_code]);
        }
        return json(['code' => 200, 'msg' => '成功', 'qr_code' => $qr_code]);
    }

    public function withdraw()
    {
        $post = request()->post();
        if (empty($post['id']) || empty($post['type'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if ($post['money'] <= 1) {
            return json(['code' => 100, 'msg' => '提现金额必须大于1']);
        }
        $row = (new SystemAdmin())->where('id', $post['id'])->find();
//        if ($row['balance'] < $post['money']) {
//            return json(['code' => 100, 'msg' => '余额不足']);
//        }
        if ($post['type'] == 1) {
            if (empty($row['openid'])) {
                return json(['code' => 100, 'msg' => '请先绑定提现微信']);
            }
            if ($row['system_balance'] < $post['money']) {
                return json(['code' => 100, 'msg' => '余额不足']);
            }
            $balance = $row['system_balance'] - $post['money'];
            $data = ['system_balance' => $balance];
        }
        if ($post['type'] == 2) {
            if (empty($row['openid'])) {
                return json(['code' => 100, 'msg' => '请先绑定提现微信']);
            }
            if ($row['agent_wx_balance'] < $post['money']) {
                return json(['code' => 100, 'msg' => '余额不足']);
            }
            $balance = $row['agent_wx_balance'] - $post['money'];
            $data = ['agent_wx_balance' => $balance];
        }
        if ($post['type'] == 3) {
            if ($row['agent_ali_balance'] < $post['money']) {
                return json(['code' => 100, 'msg' => '余额不足']);
            }
            $balance = $row['agent_ali_balance'] - $post['money'];
            $data = ['agent_ali_balance' => $balance];
        }
        if ($post['money'] > 500) {
            return json(['code' => 100, 'msg' => '单次提现金额不能大于500']);
        }

        (new SystemAdmin())->where('id', $post['id'])->update($data);
        $order_sn = 'T' . time() . rand(1000, 9999);
        $service_fee = ceil(round($post['money'] * 100) * 0.006) / 100;
        $arrival_amount = round(($post['money'] - $service_fee) * 100) / 100;
        (new FinanceWithdraw())->save(['uid' => $post['id'], 'order_sn' => $order_sn, 'type' => $post['type'], 'money' => $post['money'], 'status' => 0, 'service_fee' => $service_fee, 'arrival_amount' => $arrival_amount]);
        $date = date('Y-m-d H:i:s');
        $title = '提现申请提醒';
        $username = (new SystemAdmin())->where('id', $post['id'])->value('username');
        $body = "有新的提现申请,请尽快审核!<br>提现用户:" .
            $username . ',' .
            '<br>时间:' . $date;
        $withdraw_config = Db::name('system_config')->where('id', 1)->value('withdraw_notify');
        $address = explode(';', $withdraw_config);
        foreach ($address as $k => $v) {
            (new Email())->send_email($title, $body, $v);
        }
        return json(['code' => 200, 'msg' => '提交成功']);
    }

    public function getTencentCode()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new SystemAdmin();
        $qr_code = $model->where('id', $id)->value('qr_code');
        if (empty($qr_code)) {
            $qr_code = qrcode('', '', '', $id);
            $model->where('id', $id)->update(['qr_code' => $qr_code]);
        }
        $tencent_openid = $model->where('id', $id)->value('tencent_openid');
        $data = [
            'status' => $tencent_openid ? 1 : 0,
            'qr_code' => $qr_code,
            'tencent_code' => 'https://tanhuang.feishikeji.cloud/upload/20220921/4ac571e148859c5648a51b0ad3f8b8e0.jpg'
        ];
        return json(['code' => 200, 'data' => $data]);
    }

    public function changeType()
    {
        $type = request()->get('type');
        $user = $this->user;
        $model = new SystemAdmin();
        $model->where('id', $user['id'])->update(['login_status' => $type]);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //创建测试账号
    public function createTestAccount()
    {
        $password = '123456';
        $model = new SystemAdmin();
        $row = Db::name('system_admin')->where('account_type', 1)
            ->where('username', 'like', '%test%')
            ->order('id desc')->find();
        if ($row) {
            $num = substr($row['username'], 4);
            $username = 'test' . ($num + 1);
        } else {
            $username = 'test1';
        }
        $data = [
            'username' => $username,
            'password' => password($password),
            'parent_id' => 1,
            'parentIds' => 1,
            'account_type' => 1,
            'cate_ids' => '1,4,5,6,8',
            'device_type' => '1,2',
            'role_id' => 3,
            'roleIds' => '2,3',
            'remark' => '测试账号',
            'create_time' => time()
        ];

        $id = $model->insertGetId($data);
        (new \app\index\model\MachineDevice())->whereIn('id', [163, 164, 165])->update(['uid' => $id]);
        $data = [
            'url' => 'https://manghe.feishi.vip',
            'username' => $username,
            'password' => $password
        ];
        return json(['code' => 200, 'data' => $data]);
    }
}
