<?php

namespace app\index\controller;

use app\index\model\AdverMaterialModel;
use think\Db;

class Adver extends BaseController
{
    public function getMaterialList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
//        $user = $this->user;
        $model = new AdverMaterialModel();
        $where = [];
        if (empty($params['type'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        } else {
            $where['i.type'] = ['=', $params['type']];
        }
//        if ($user['role_id'] != 1) {
//            $where['i.uid'] = ['=', $user['id']];
//        }
        if (!empty($params['name'])) {
            $where['i.name'] = ['like', '%' . $params['name'] . '%'];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        $count = $model->alias('i')
            ->join('system_admin a', 'i.uid=a.id', 'left')
            ->where($where)
            ->where('i.delete_time', null)
            ->count();
        $list = $model->alias('i')
            ->join('system_admin a', 'i.uid=a.id', 'left')
            ->where($where)
            ->field('i.*,a.username')
            ->where('i.delete_time', null)
            ->page($page)
            ->limit($limit)
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['start_time'] = $v['start_time'] ? date('Y-m-d', $v['start_time']) : '';
            $list[$k]['end_time'] = $v['start_time'] ? date('Y-m-d', $v['end_time']) : '';
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function saveMaterial()
    {
        $post = request()->post();
        $user = $this->user;
        $post['uid'] = $user['id'];
        if (empty($post['url'])) {
            return json(['code' => 100, 'msg' => '请选择图片']);
        }
        if (empty($post['type'])) {
            return json(['code' => 100, 'msg' => '缺失图片类型']);
        }
        $post['start_time'] = strtotime($post['start_time']);
        $post['end_time'] = strtotime($post['end_time']);
        $model = new AdverMaterialModel();
        if (empty($post['id'])) {
            $model->save($post);

        } else {
            $model->where('id', $post['id'])->update($post);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function delMaterial()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $row = Db::name('adver_material')->where('id', $id)->find();
        if ($row['type'] == 1) {
            $rows = Db::name('machine_banner')
                ->where('material_image', 'like', '%' . $row['url'] . '%')
                ->select();
            foreach ($rows as $k => $v) {
                //更新图片
                $arr = explode(',', $v['material_image']);
                $del_arr = [$row['url']];
                $material_image_arr = array_diff($arr, $del_arr);
                $material_image = implode(',', $material_image_arr);
                //更新id
                $id_arr = explode(',', $v['material_id']);
                $del_id = [$id];
                $material_id_arr = array_diff($id_arr, $del_id);
                $material_id = implode(',', $material_id_arr);
                Db::name('machine_banner')
                    ->where('id', $v['id'])
                    ->update(['material_image' => $material_image, 'material_id' => $material_id]);
            }
        }
        delMaterial($row['url']);
        Db::name('adver_material')->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '成功']);
    }

    //视频绑定设备,获取设备列表
    public function getDevice()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $deviceModel = new \app\index\model\MachineDevice();
        $list = $deviceModel->field('id,device_sn,device_name')->select();
        foreach ($list as $k => $v) {
            $list[$k]['name'] = $v['device_name'] . '(' . $v['device_sn'] . ')';
        }
        $check = Db::name('machine_video')->where('video_id', $id)->column('device_id');
        $check = $check ? array_values($check) : [];
        return json(['code' => 200, 'data' => $list, 'check' => $check]);
    }

    //保存绑定设备
    public function saveBindDevice()
    {
        $video_id = request()->post('video_id', '');
        $device_ids = request()->post('device_ids/a', []);
        if (empty($video_id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }

        //删除取消绑定的设备
        $old_delete_device = Db::name('machine_video')->where('video_id', $video_id)->whereNotin('device_id', $device_ids)->column('device_id');
        Db::name('machine_video')->where('video_id', $video_id)->whereNotin('device_id', $device_ids)->delete();
        //已经绑定的设备
        $rows = Db::name('machine_video')->where('video_id', $video_id)->column('device_id');
        //获取新绑定的设备id
        $bind_device = $rows ? array_values($rows) : [];
        $new_bind = array_diff($device_ids, $bind_device);
        $new_bind = $new_bind ? array_values($new_bind) : [];
        //创建插入数据库的数据
        $data = [];
        for ($i = 0; $i < count($new_bind); $i++) {
            $data[] = [
                'video_id' => $video_id,
                'device_id' => $new_bind[$i],
                'create_time' => time()
            ];
        }
        Db::name('machine_video')->insertAll($data);

        //给修改的设备更新数据
        $device_ids = array_merge($old_delete_device, $new_bind);
        $device = (new \app\index\model\MachineDevice())
            ->whereIn('id', $device_ids)
            ->field('device_sn,imei')
            ->select();
        foreach ($device as $k => $v) {
            $data = [
                "imei" => $v['imei'],
                "deviceNumber" => $v['device_sn'],
                "laneNumber" => -2,
                "laneType" => 1,
                "paymentType" => 1,
                "orderNo" => 'shuaXin' . time(),
                "timestamp" => time()
            ];
            $url = 'http://feishi.feishi.vip:9100/api/vending/goodsOut';
            $result = https_request($url, $data);
        }

        return json(['code' => 200, 'msg' => '绑定成功']);
    }
}
