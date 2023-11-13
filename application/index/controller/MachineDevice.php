<?php

namespace app\index\controller;

use app\index\common\Oss;
use app\index\model\AdverMaterialModel;
use app\index\model\AppLogModel;
use app\index\model\MachineCommissionModel;
use app\index\model\MachineOnlineLogModel;
use app\index\model\MachineOutLogModel;
use app\index\model\OutVideoModel;
use app\index\model\SystemAdmin;
use think\Cache;
use think\Db;
use think\Env;

class MachineDevice extends BaseController
{
    public function getList()
    {
        $prams = input('post.', '');
        $limit = input('post.limit', 10);
        $page = input('post.page', 1);
        $user = $this->user;
        $where = [];

        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $device_ids = Db::name('machine_device_partner')
                    ->where(['admin_id' => $user['parent_id'], 'uid' => $user['id']])
                    ->column('device_id');
                $device_ids = $device_ids ? array_values($device_ids) : [];
                $where['d.id'] = ['in', $device_ids];
            } else {
                $admin = (new SystemAdmin())->where('id', '>', $user['id'])
                    ->where('role_id', '<', 7)
                    ->column('id,parent_id pid,username', 'id');
                $ids = (new SystemAdmin())->getAdminSonId($admin, $user['id']);
                $ids[] = $user['id'];
                $where['d.uid'] = ['in', $ids];
            }
        } else {
            if (!empty($prams['uid'])) {
                $where['d.uid'] = $prams['uid'];
            }
        }
        if (!empty($prams['imei'])) {
            $where['d.imei'] = ['like', '%' . $prams['imei'] . '%'];
        }
        if (!empty($prams['device_sn'])) {
            $where['d.device_sn'] = ['like', '%' . $prams['device_sn'] . '%'];
        }
        if (!empty($prams['device_name'])) {
            $where['d.device_name'] = ['like', '%' . $prams['device_name'] . '%'];
        }

        if (!empty($prams['keyword'])) {
            $where['d.device_name|d.imei|d.device_sn|a.username'] = ['like', '%' . $prams['keyword'] . '%'];
        }
        if (!empty($prams['type_id'])) {
            $where['d.type_id'] = $prams['type_id'];
        }

        if (isset($prams['is_lock']) && $prams['is_lock'] !== '') {
            $where['d.is_lock'] = $prams['is_lock'];
        }

        if (isset($prams['status']) && $prams['status'] !== '') {
            $where['d.status'] = $prams['status'];
        }
        if (isset($prams['transfer']) && $prams['transfer'] !== '') {
            $where['d.transfer'] = $prams['transfer'];
        }
        if (isset($prams['remark']) && $prams['remark'] !== '') {
            $where['d.remark'] = ['like', '%' . $prams['remark'] . '%'];
        }
        if (isset($prams['username']) && $prams['username'] !== '') {
            $where['a.username'] = ['like', '%' . $prams['username'] . '%'];
        }
        if (isset($prams['no_medicine']) && $prams['no_medicine'] == 1) {
            //不显示售药机设备
            $where['d.device_type'] = ['=', 0];
        }

        $model = new \app\index\model\MachineDevice();
        $count = $model->alias('d')
            ->join('system_admin a', 'a.id=d.uid', 'left')
            ->join('machine_type t', 't.id=d.type_id', 'left')
            ->join('index_template i', 'i.id=d.index_id', 'left')
            ->join('operate_service s', 's.id=d.sid', 'left')
//            ->join('machine_android an', 'an.device_sn=d.device_sn', 'left')
            ->where('d.delete_time', null)
            ->where($where)->count();
        $list = $model->alias('d')
            ->join('system_admin a', 'a.id=d.uid', 'left')
            ->join('machine_type t', 't.id=d.type_id', 'left')
            ->join('index_template i', 'i.id=d.index_id', 'left')
            ->join('operate_service s', 's.id=d.sid', 'left')
//            ->join('machine_android an', 'an.device_sn=d.device_sn', 'left')
            ->where('d.delete_time', null)
            ->where($where)
            ->field('d.*,a.username,t.title,i.title as template_name,s.title service_name')
            ->limit($limit)
            ->page($page)
            ->order('d.id desc')
            ->select();
        $lock_sns = [];
        foreach ($list as $k => $v) {
//            $row = (new DevicePartnerModel())
//                ->where('admin_id', $v['uid'])
//                ->where('device_id', $v['id'])
//                ->group('device_id')
//                ->field('sum(ratio) total_ratio')->find();
//            $list[$k]['total_ratio'] = $row ? 100 - $row['total_ratio'] : 100;
            $list[$k]['remain_time'] = ($v['expire_time'] > time()) ? ceil(($v['expire_time'] - time()) / (3600 * 24)) : '已过期';
            $list[$k]['expire_time'] = $v['expire_time'] ? date('Y-m-d H:i:s', $v['expire_time']) : '';
            if ($v['supply_id'] == 2) {
                $list[$k]['face_sn'] = $model->where('id', $v['id'])->value('face_sn');
            } elseif ($v['supply_id'] == 1) {
                $list[$k]['face_sn'] = Db::name('machine_android')->where('device_sn', $v['device_sn'])->value('face_sn');
            }
            $lock_sns[] = $v['device_sn'];
        }

        //货道锁定的设备
        $device_lock = (new \app\index\model\MachineGoods())->whereIn('device_sn', $lock_sns)->where('err_lock', 1)->column('device_sn');
        foreach ($list as $k => $v) {
            if (in_array($v['device_sn'], $device_lock)) {
                $list[$k]['num_lock'] = 1;
            } else {
                $list[$k]['num_lock'] = 0;
            }
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'prams' => $prams]);
    }

    public function getCateByUser()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $where['id'] = ['in', $user['device_type']];
        }
        $typeModel = new \app\index\model\MachineType();
        $list = $typeModel->where($where)->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function getDevice()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $where['uid'] = $user['id'];
        } else {
            if (!empty($prams['uid'])) {
                $where['uid'] = $prams['uid'];
            }
        }
        $model = new \app\index\model\MachineDevice();
        $list = $model
            ->where('delete_time', null)
            ->where($where)
            ->field('id,imei,device_name')
            ->select();
        foreach ($list as $k => $v) {
            if (empty($v['device_name'])) {
                $list[$k]['device_name'] = $v['imei'];
            }
        }
        $check = (new \app\index\model\MachineDevice())->where('index_id', $id)->where('delete_time', null)->column('id');
        $check = $check ? array_values($check) : [];
        return json(['code' => 200, 'data' => $list, 'check' => $check]);
    }

    public function save()
    {
        $post = request()->post();
        $data = [
            'device_sn' => $post['device_sn'],
            'imei' => $post['imei'],
            'device_name' => $post['device_name'],
            'type_id' => $post['type_id'],
            'num' => (int)$post['num'],
            'remark' => $post['remark'],
            'is_lock' => $post['is_lock'],
            'is_bind' => $post['is_bind'],
            'index_id' => empty($post['index_id']) ? '' : $post['index_id'],
            'supply_id' => $post['supply_id'],
            'screen_orientation' => empty($post['screen_orientation']) ? 0 : $post['screen_orientation'],
            'supplier_type' => $post['supplier_type'],
            'card_type' => $post['card_type'],
            'iccid' => $post['iccid'],
            'lock_num' => $post['lock_num'],
            'position_id' => $post['position_id']
        ];
        if (!empty($post['expire_time'])) {
            $data['expire_time'] = strtotime($post['expire_time']);
        }
        $model = new \app\index\model\MachineDevice();
        if (empty($post['id'])) {
            //添加
            if (!$post['imei']) {
                $row = $model->where('device_sn', $post['device_sn'])->find();
            } else {
                $row = $model->where('imei', $post['imei'])->find();
            }

            if ($row) {
                return json(['code' => 100, 'msg' => '该设备已存在']);
            }
            $data['qr_code'] = qrcode($data['device_sn'], $data['type_id']);
            $data['uid'] = $post['uid'] ?? 1;
            $model->save($data);
        } else {
            $data['uid'] = $post['uid'];
            $row = $model->where('id', '<>', $post['id'])->where('imei', $post['imei'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该设备已存在']);
            }
            $row = $model->where('id', $post['id'])->find();
            if ($row['device_sn'] != $post['device_sn'] || $row['type_id'] != $post['type_id']) {
                if ($row['qr_code']) {
                    $arr = explode('device_code/', $row['qr_code']);
                    $file = $_SERVER['DOCUMENT_ROOT'] . '/upload/device_code/' . $arr[1];
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
                $data['qr_code'] = qrcode($data['device_sn'], $post['type_id']);
            }
            $model->where('id', $post['id'])->update($data);
        }
        if (!($post['id'] && $row['is_lock'] == $data['is_lock'])) {
            $num = $data['is_lock'] == 1 ? -8 : -7;
            $data = [
                "imei" => $data['imei'],
                "deviceNumber" => $data['device_sn'],
                "laneNumber" => $num,
                "laneType" => 1,
                "paymentType" => 1,
                "orderNo" => '100test' . time(),
                "timestamp" => time()
            ];
            $url = 'http://feishi.feishi.vip:9100/api/vending/goodsOut';
            $result = https_request($url, $data);
        }

        return json(['code' => 200, 'msg' => '成功']);
    }

    public function del()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new \app\index\model\MachineDevice();
        $model->destroy($id);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //关机
    public function shutDown()
    {
        $id = request()->get('id', '');
        $data = (new \app\index\model\MachineDevice())->where('id', $id)->field('imei,device_sn')->find();
        if (!$data) {
            return json(['code' => 100, 'msg' => '设备不存在']);
        }
        $data = [
            "imei" => $data['imei'],
            "deviceNumber" => $data['device_sn'],
            "laneNumber" => -9,
            "laneType" => 1,
            "paymentType" => 1,
            "orderNo" => '100test' . time(),
            "timestamp" => time()
        ];
        $url = 'http://feishi.feishi.vip:9100/api/vending/goodsOut';
        $result = https_request($url, $data);
        $result = json_decode($result, true);
        if ($result['code'] == 200) {
            (new \app\index\model\MachineDevice())->where('id', $id)->update(['status' => 0, 'boot' => 0]);
            return json(['code' => 200, 'msg' => '已关机']);
        } else {
            return json($result);
        }
    }

    public function bindIndex()
    {
        $ids = request()->post('ids/a');
        $index_id = request()->post('index_id', 0);
        if (!$ids || !$index_id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new \app\index\model\MachineDevice();
        $model->whereIn('id', $ids)->update(['index_id' => $index_id]);
        return json(['code' => 200, 'msg' => '绑定成功']);
    }

    public function getTemplateList()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if (!in_array('2', explode(',', $user['roleIds']))) {
                $user['id'] = $user['parent_id'];
            }
            $where['uid'] = $user['id'];
        }
        $model = new \app\index\model\IndexTemplate();
        $list = $model
            ->where($where)
            ->where('delete_time', null)
            ->field('id,title')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //绑定视频,获取视频列表
    public function getVideo()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
//        $uid = (new \app\index\model\MachineDevice())->where('id', $id)->value('uid');
        $list = (new AdverMaterialModel())->where('type', 2)->select();
        $video = Db::name('machine_video')->where('device_id', $id)->column('video_id');
        if (empty($video)) {
            $check = [];
        } else {
            $check = array_values($video);
        }
        return json(['code' => 200, 'data' => $list, 'check' => $check]);
    }

    //绑定视频--盒子安卓
    public function bindVideo()
    {
        $device_id = request()->post('device_id', '');
        $video_ids = request()->post('video_ids/a', []);
        if (empty($device_id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }

        //删除取消绑定的视频
        Db::name('machine_video')->where('device_id', $device_id)->whereNotin('video_id', $video_ids)->delete();
        //已经绑定的视频
        $rows = Db::name('machine_video')->where('device_id', $device_id)->column('video_id');
        //获取新绑定的视频id
        $bind_video = $rows ? array_values($rows) : [];
        $new_bind = array_diff($video_ids, $bind_video);
        $new_bind = $new_bind ? array_values($new_bind) : [];
        //创建插入数据库的数据
        $data = [];
        for ($i = 0; $i < count($new_bind); $i++) {
            $data[] = [
                'device_id' => $device_id,
                'video_id' => $new_bind[$i],
                'create_time' => time()
            ];
        }
        Db::name('machine_video')->insertAll($data);
        $device = (new \app\index\model\MachineDevice())
            ->where('id', $device_id)
            ->field('device_sn,imei')
            ->find();
        $data = [
            "imei" => $device['imei'],
            "deviceNumber" => $device['device_sn'],
            "laneNumber" => -2,
            "laneType" => 1,
            "paymentType" => 1,
            "orderNo" => 'shuaXin' . time(),
            "timestamp" => time()
        ];
        $url = 'http://feishi.feishi.vip:9100/api/vending/goodsOut';
        $result = https_request($url, $data);

        return json(['code' => 200, 'msg' => '绑定成功']);
    }

    public function changeStatus()
    {
        $id = request()->get('id', '');
        $is_lock = request()->get('is_lock', '');
        if (!$id || $is_lock === '') {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        Db::name('machine_device')->where('id', $id)->update(['is_lock' => $is_lock]);
        $device = Db::name('machine_device')->where('id', $id)->field('device_sn,imei')->find();
        $num = $is_lock == 1 ? -8 : -7;
        $data = [
            "imei" => $device['imei'],
            "deviceNumber" => $device['device_sn'],
            "laneNumber" => $num,
            "laneType" => 1,
            "paymentType" => 1,
            "orderNo" => '100test' . time(),
            "timestamp" => time()
        ];
        $url = 'http://feishi.feishi.vip:9100/api/vending/goodsOut';
        $result = https_request($url, $data);
        return json(['code' => 200, 'msg' => '修改成功']);
    }

    public function changeIsBind()
    {
        $id = request()->get('id', '');
        $is_bind = request()->get('is_bind', 0);
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        Db::name('machine_device')->where('id', $id)->update(['is_bind' => $is_bind]);
        return json(['code' => 200, 'msg' => '修改成功']);
    }

    //绑定客服
    public function bindService()
    {
        $sid = request()->post('sid', '');
        $ids = request()->post('ids/a');
        if (!$ids) {
            return json(['code' => 100, 'msg' => '请选择设备']);
        }
        $model = new  \app\index\model\MachineDevice();
        $model->whereIn('id', $ids)->update(['sid' => $sid]);
        return json(['code' => 200, 'msg' => '绑定成功']);
    }

    //绑定地点
    public function bindLocation()
    {
        $data = request()->post();
        if (empty($data['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数!']);
        }
        $save = [
            'long' => $data['long'],
            'lat' => $data['lat'],
            'location' => $data['location'],
            'shop_name' => $data['shop_name']
        ];
        $model = new  \app\index\model\MachineDevice();
        $model->where('id', $data['id'])->update($save);
        return json(['code' => 200, 'msg' => '操作成功']);
    }

    //刷脸设备Sn
    public function faceSn()
    {
        $data = request()->post();
        if (empty($data['id']) || empty($data['face_sn'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new  \app\index\model\MachineDevice();
        $device = $model
            ->where('id', $data['id'])
            ->field('device_sn,supply_id')->find();
        if ($device['supply_id'] == 2) {
            $model->where('id', $data['id'])->update(['face_sn' => $data['face_sn']]);
        } else {
            $device_sn = $device['device_sn'];
            $row = Db::name('machine_android')->where('device_sn', $device_sn)->find();
            if (!$row) {
                return json(['code' => 100, 'msg' => '请先在安卓端绑定该设备']);
            }
            Db::name('machine_android')->where('device_sn', $device_sn)->update(['face_sn' => $data['face_sn']]);
        }

        return json(['code' => 200, 'msg' => '绑定成功']);
    }

    //查询物联网卡
    public function card()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $row = Db::name('machine_device')->where('id', $id)->field('iccid,card_type,supplier_type')->find();
        if (!$row || !$row['iccid']) {
            return json(['code' => 100, 'msg' => '未配置iccid']);
        }
        if ($row['supplier_type'] == 1) {
            $str = substr($row['iccid'], -1);
            if (!is_numeric($str)) {
                $row['iccid'] = substr($row['iccid'], 0, -1);
            }
            $data = [
                'iccid' => $row['iccid'],
                'operator' => $row['card_type'],
            ];
            $data['username'] = '杭州匪石科技有限公司';
            $data['timestamp'] = time();
            $data['method'] = 'sohan.m2m.iccidinfo.queryone';
            $data['sign'] = $this->makeSign($data);
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            $header = [
                'Content-Type: application/json'
            ];
            $res = https_request('https://apim2m.iot-sohan.cn/index/m2m/api/v1', $data, $header);
            $res = json_decode($res, true);
            if ((isset($res['errorCode']) && $res['errorCode'] != 'SUCCESS') || (isset($res['fail']) && $res['fail'] == true)) {
                if (isset($res['resutnMsg'])) {
                    $res['returnMsg'] = $res['resutnMsg'];
                }
                return json(['code' => 100, 'msg' => $res['returnMsg']]);
            }
            $info = $res['data'];
            $info['cardStatus'] = $info['cardStatus'] == 1 ? '可激活' : ($info['cardStatus'] == 2 ? '已激活' : ($info['cardStatus'] == 3 ? '已停用' : ($info['cardStatus'] == 4 ? '已失效' : '库存')));
            return json(['code' => 200, 'data' => $info]);

        } else {
            return json(['code' => 100, 'msg' => '该供应商不支持查询']);
        }

    }

    private function makeSign($data)
    {
        $key = 'vJIpl2lt5VNwW7l4OnWzKxAytCJINKJi';

        // 去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
//        $string_a = '';
//        foreach ($data as $k => $v) {
//            var_dump($k);
//            if ($string_a) {
//                $string_a .= '&';
//            }
//            $string_a .= $k . '=' . $v;
//        }
//var_dump($string_a);die();
        //签名步骤二：在string后加入KEY
        //$config=$this->config;
        $string_sign_temp = $string_a . "&key=" . $key;
        trace($string_sign_temp, '查询网卡参数');
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($sign);
        return $result;
    }

    //获取分佣列表
    public function getCommission()
    {
        $user = $this->user;
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $parentId = [];
        $uid = $user['id'];
        $parentUser = (new SystemAdmin())->getParents($uid, 0);
        foreach ($parentUser as $k => $v) {
            $parentId[] = $v['id'];
        }
        $model = new MachineCommissionModel();
        $data = $model->alias('c')
            ->join('system_admin a', 'c.uid=a.id')
            ->where('c.device_id', $id)
            ->field('c.*,a.username')
            ->select();
        foreach ($data as $k => $v) {
            if (in_array($v['uid'], $parentId)) {
                $data[$k]['is_do'] = 1;
            } else {
                $data[$k]['is_do'] = 0;
            }
        }
        return json(['code' => 200, 'data' => $data]);
    }

    //获取设备所属人上下级
    public function getUser()
    {
        $user = $this->user;
        $id = request()->get('id');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device_uid = Db::name('machine_device')->where('id', $id)->value('uid');
        $uid = $user['id'];
        $model = new SystemAdmin();
        $list = $model->field('id,parent_id pid,username')->select();
        $data = $model->getSon($list, $uid);
        if ($device_uid != $user['id']) {
            $self = $model->where('id', $uid)->select();
            $data = array_merge($data, $self);
        }
        return json(['code' => 200, 'data' => $data]);
    }

    //保存分佣
    public function saveCommission()
    {
        $params = request()->post();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
//        $data = [
//            ['uid' => 1, 'ratio' => 2],
//            ['uid' => 3, 'ratio' => 1],
//        ];
        $model = new MachineCommissionModel();
        $data = $params['data'];
        $ratio = 0;
        foreach ($data as $k => $v) {
            $ratio += $v['ratio'];
        }
        if ($ratio > 100) {
            return json(['code' => 100, 'msg' => '分佣比例之和不得大于100']);
        }
        $commission = $model->where('device_id', $params['id'])->delete();
        $insert = [];
        foreach ($data as $k => $v) {
            $insert[] = ['device_id' => $params['id'], 'uid' => $v['uid'], 'ratio' => $v['ratio']];
        }
        $model->saveAll($insert);
        return json(['code' => 200, 'msg' => '保存成功']);
    }

    //测试出货
    public function testOut()
    {
        $device_sn = request()->get("device_sn", "");
        $number = request()->get("number", "");
        $device = (new \app\index\model\MachineDevice())->where("device_sn", $device_sn)->field("supply_id,imei")->find();

        if ($device['supply_id'] == 3) {
            $result = $this->Shipment($device_sn, $number);
            if ($result['errorCode'] == 0) {
                $log = ["device_sn" => $device_sn, "num" => $number, "order_sn" => '', 'status' => 0];
                (new MachineOutLogModel())->save($log);
                return json(['code' => 200, 'msg' => '操作成功1']);
            } else {
                trace($result, '测试出货失败原因');
                $status = $result['errorCode'] == 65020 ? 2 : 3;
                $log = ["device_sn" => $device_sn, "num" => $number, "order_sn" => '', 'status' => $status];
                (new MachineOutLogModel())->save($log);
                return json(['code' => 100, 'msg' => '操作失败2']);
            }
        } else {
            usleep(500000);
            $res = $this->goodsOut($device_sn, $device['imei'], $number);
            return json($res);
        }
    }

    /**
     * 出货
     */
    private function Shipment($device_sn = "", $huodao = "")
    {
        $rand_str = rand(10000, 99999);
        $data = '{"cmd": 1000, "data": {"digital": ' . $huodao . ', "msg": "run", "count": 1, "quantity": 1, "done": 1}, "sn": "' . $device_sn . '", "nonceStr": "' . $rand_str . '"}';
        function send_post($url, $post_data, $token)
        {
            $postdata = http_build_query($post_data);
            $options = array(
                'http' =>
                    array(
                        'method' => 'POST',
                        'header' => array("token:" . $token, "chan:bee-CSQYUS", "Content-type:application/x-www-form-urlencoded"),
                        'content' => $postdata,
                        'timeout' => 15 * 60
                    )
            );
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $info = json_decode($result, true);
            trace($info, '出货信息');
            return $info;
        }

        $post_data = array(
            'data' => $data
        );
        $res = send_post('http://mqtt.ibeelink.com/api/ext/tissue/pub-cmd', $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        return $res;
    }

    /**
     * 出货指令
     */
    public function goodsOut($device_sn, $imei, $num)
    {
        $device = (new \app\index\model\MachineDevice())->where('device_sn', $device_sn)->find();
        if (!$device || $device['transfer'] == 1) {
            $url = "http://47.96.15.3:8899/api/vending/goodsOut";
        } else {
            $url = "http://feishi.feishi.vip:9100/api/vending/goodsOut";
        }
        $order_no = "100test" . time() . rand(1000, 9999);
        $data = [
            "Imei" => $imei,
            "deviceNumber" => $device_sn,
            "laneNumber" => $num,
            "laneType" => 0,
            "paymentType" => 1,
            "orderNo" => $order_no,
            "timestamp" => time(),
        ];
        $result = https_request($url, $data);
        if (!$device || $device['transfer'] == 1) {
            trace($result, '老中转出货结果');
        } else {
            trace($result, '新中转出货结果');
        }
        $result = json_decode($result, true);
        if ($result['code'] == 200) {
            $res = $this->isBack($order_no, 1);
            if (!$res) {
                //没有反馈业务处理
                $str = 'out_' . $order_no;
                trace($str, '查询订单号');
                $res_a = Cache::store('redis')->get($str);
                if ($res_a == 2 || !$res) {
                    $outingStr = 'outing_' . $device_sn;
                    Cache::store('redis')->rm($outingStr);
                    $status = $res_a == 2 ? 1 : 5;

                    if ($status == 5) {
                        $log = ["device_sn" => $device_sn, "num" => $num, "order_sn" => $order_no, 'status' => 5];
                        (new MachineOutLogModel())->save($log);
                        return ['code' => 100, 'msg' => '无反馈'];
                    }

                    return ['code' => 100, 'msg' => '出货失败'];
                }
            }
            return ['code' => 200, 'msg' => '出货成功'];

        } else {
            trace(1111, '出货失败');
            $log = ["device_sn" => $device_sn, "num" => $num, "order_sn" => $order_no, 'status' => 2, 'remark' => $result['msg']];
            (new MachineOutLogModel())->save($log);
            return $result;
        }
    }

    public function isBack($order, $num)
    {
        if ($num <= 15) {
            $str = 'out_' . $order;
            trace($str, '查询订单号');
            $res = Cache::store('redis')->get($str);
            trace($res, '查询结果');
            if ($res == 1) {
                return true;
            } else {
                if ($res == 2) {
                    return false;
                }
                if ($num > 1) {
                    sleep(1);
                }
                $res = $this->isBack($order, $num + 1);
                return $res;
            }
        } else {
            return false;
        }
    }

    /**
     * 设备参数设置
     */
    public function deviceConfig()
    {
        $params = request()->get();
        $vol = request()->get('vol', 0);
        $show_code = request()->get('show_code', 1);
        $detail = $this->getMiLianDevice($params['device_sn']);
        if (!$detail || !in_array($detail[0]['deviceType'], [210, 246, 255])) {
            return json(['code' => 100, 'msg' => '该设备不支持配置']);
        }
        $data = [
            'deviceType' => $detail[0]['deviceType'],
            'vol' => (int)$vol,
            'snList' => [$params['device_sn']],
            'nonceStr' => $this->getMillisecond()
        ];
        if (in_array($detail[0]['deviceType'], [246, 255])) {
            $data['qr_size'] = $show_code == 1 ? 120 : 1;
        } else {
            $data['QRCodeDisplay'] = $show_code == 1 ? 1 : 0;
        }
        $res = $this->configDevice($data);
        if ($res) {
            $data = [
                'vol' => $vol,
                'show_code' => $show_code,
            ];
            (new \app\index\model\MachineDevice())->where('device_sn', $params['device_sn'])->update($data);
            return json(['code' => 200, 'msg' => '配置成功']);
        } else {
            return json(['code' => 100, 'msg' => '配置失败']);
        }
    }

    private function getMillisecond()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    public function getMiLianDevice($device_sn)
    {
        $json = [
            "nonceStr" => time(),
            "snList" => [$device_sn],
        ];
        $data = json_encode($json);
        function get_postss($url, $post_data, $token)
        {
            $postdata = http_build_query($post_data);
            $options = array(
                'http' => array(
                    'method' => 'GET',
                    'header' => array("Content-type: application/x-www-form-urlencoded", 'token:' . $token, 'chan:bee-CSQYUS'),
                    'content' => $postdata,
                    'timeout' => 15 * 60 // 超时时间（单位:s）
                )
            );
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $data = json_decode($result, true);
            if ($data['errorCode'] == 0) {
                return $data['data'];
            } else {
                return false;
            }
        }

        $post_data = array(
            'data' => $data
        );
        $result = get_postss('https://mqtt.ibeelink.com/api/ext/tissue/devices/info',
            $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        return $result;
    }

    //配置蜜连设备
    public function configDevice($data)
    {
        $data = json_encode($data);
        trace($data, '配置设备');
        function send_postss($url, $post_data, $token)
        {
            $postdata = http_build_query($post_data);
            $options = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => array("Content-type: application/x-www-form-urlencoded", 'token:' . $token, 'chan:bee-CSQYUS'),
                    'content' => $postdata,
                    'timeout' => 15 * 60 // 超时时间（单位:s）
                )
            );
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $data = json_decode($result, true);
            if ($data['errorCode'] == 0) {
                return $data['data'];
            } else {
                return false;
            }
        }

        $post_data = array(
            'data' => $data
        );
        $result = send_postss('https://mqtt.ibeelink.com/api/ext/tissue/config/add-params/simplify',
            $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        return $result;
    }

    public function statusLog()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 1 : $params['limit'];
        $device_sn = request()->get('device_sn', '');
        if (empty($device_sn)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineOnlineLogModel();
        $count = $model->where('device_sn', $device_sn)->count();
        $list = $model
            ->where('device_sn', $device_sn)
            ->order('id desc')
            ->page($page)->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count]);
    }

    //出货日志
    public function outLog()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 1 : $params['limit'];
        $device_sn = request()->get('device_sn', '');
        if (empty($device_sn)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MachineOutLogModel();
        $count = $model->where('device_sn', $device_sn)->count();
        $list = $model
            ->where('device_sn', $device_sn)
            ->order('id desc')
            ->page($page)->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count]);
    }

    //出货视频
    public function outVideo()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 1 : $params['limit'];
        $imei = request()->get('imei', '');
        if (empty($imei)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new OutVideoModel();
        $count = $model->where('imei', $imei)->count();
        $list = $model
            ->where('imei', $imei)
            ->order('id desc')
            ->page($page)->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count]);
    }

    //app日志
    public function appLog()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 1 : $params['limit'];
        $imei = request()->get('imei', '');
        if (empty($imei)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new AppLogModel();
        $count = $model->where('imei', $imei)->count();
        $list = $model
            ->where('imei', $imei)
            ->order('id desc')
            ->page($page)->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count]);
    }

    //下载日志获取链接
    public function downLoadLog()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new AppLogModel();
        $row = $model->where('id', $id)->find();
        if (!$row['url']) {
            return json(['code' => 100, 'msg' => '文件丢失']);
        }
        if (strpos($row['url'], 'api.hnchaohai.com') !== false) {
            $url = $row['url'];
        } else {
            $filename = urlencode(basename($row['url']));
            $object = 'log/' . $filename;
            $dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/upload/download/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $localFile = $dir . $filename;
            (new Oss())->downLoad($object, $localFile);
            $url = Env::get('server.servername', 'http://api.hnchaohai.com') . '/upload/download/' . $filename;
        }
        return json(['code' => 200, 'data' => ['url' => $url]]);
    }

    //获取设备登录码
    public function getDeviceLoginCode()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device_sn = (new \app\index\model\MachineDevice())->where('id', $id)->value('device_sn');
        $str = $device_sn . '_loginCode';
        $res = Cache::store('redis')->get($str);
        if ($res) {
            return json(['code' => 200, 'data' => ['loginCode' => $res]]);
        } else {
            $code = rand(10000, 99999);
            Cache::store('redis')->set($str, $code, 300);
            return json(['code' => 200, 'data' => ['loginCode' => $code]]);
        }
    }
}
