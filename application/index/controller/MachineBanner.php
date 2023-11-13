<?php

namespace app\index\controller;

use app\index\model\AdverMaterialModel;
use app\index\model\MachineBannerModel;
use think\Db;

class MachineBanner extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 15);
        $model = new MachineBannerModel();
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['b.uid'] = ['=', $user['parent_id']];
            } else {
                $where['b.uid'] = ['=', $user['id']];

            }
        }
        if (!empty($params['name'])) {
            $where['b.name'] = ['like', '%' . $params['name'] . '%'];
        }
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        $count = $model->alias('b')
            ->join('system_admin a', 'a.id=b.uid', 'left')
            ->field('b.*,a.username')
            ->where($where)->count();
        $list = $model->alias('b')
            ->join('system_admin a', 'a.id=b.uid', 'left')
            ->field('b.*,a.username')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('b.id desc')
            ->select();
        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }

    public function getImageList()
    {
        $user = $this->user;
        $where = [];
        if ($user['id'] !== 1) {
            if ($user['role_id'] > 5) {
                $device_ids = Db::name('machine_device_partner')
                    ->where(['admin_id' => $user['parent_id'], 'uid' => $user['id']])
                    ->column('device_id');
                $device_ids = $device_ids ? array_values($device_ids) : [];
                $where['id'] = ['in', $device_ids];
            } else {
                $where['uid'] = ['=', $user['id']];
            }
        }
        $model = new AdverMaterialModel();
        $list = $model
            ->where($where)
            ->where('type', 1)
            ->where('delete_time', null)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function save()
    {
        $data = request()->post();
        if (empty($data['material_ids'])) {
            return json(['code' => 100, 'msg' => '请选择图片']);
        }
        $user = $this->user;
        $material_ids = $data['material_ids'];
        $materialModel = new AdverMaterialModel();
        $material = $materialModel->whereIn('id', $material_ids)->column('url');
        $images = implode(',', $material);
        $bannerModel = new MachineBannerModel();
        if (empty($data['id'])) {
            $res = $materialModel->where('uid', $user['id'])->where('name', $data['name'])->find();
            if ($res) {
                return json(['code' => 100, 'msg' => '该名称已存在']);
            }
            $insert_data = [
                'uid' => $user['id'],
                'name' => $data['name'],
                'material_id' => implode(',', $material_ids),
                'material_image' => $images
            ];
            $bannerModel->save($insert_data);
        } else {
            $res = $materialModel
                ->where('id', '<>', $data['id'])
                ->where('uid', $user['id'])
                ->where('name', $data['name'])
                ->find();
            if ($res) {
                return json(['code' => 100, 'msg' => '该名称已存在']);
            }
            $update_data = [
                'name' => $data['name'],
                'material_id' => implode(',', $material_ids),
                'material_image' => $images
            ];
            $bannerModel->where('id', $data['id'])->update($update_data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    //绑定设备,获取设备列表
    public function getDeviceList()
    {
        $id = request()->get('id', '');
        $user = $this->user;
        $where = [];
        if ($user['id'] !== 1) {
            if ($user['role_id'] > 5) {
                $device_ids = Db::name('machine_device_partner')
                    ->where(['admin_id' => $user['parent_id'], 'uid' => $user['id']])
                    ->column('device_id');
                $device_ids = $device_ids ? array_values($device_ids) : [];;
                $where['id'] = ['in', $device_ids];
            } else {
                $where['uid'] = ['=', $user['id']];
            }
        }
        $deviceModel = new \app\index\model\MachineDevice();
        $list = $deviceModel
            ->where($where)
            ->where('delete_time', null)
            ->field('id,device_sn,device_name')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['device'] = $v['device_sn'] . '(' . $v['device_name'] . ')';
        }
        $check = $deviceModel->whereIn('banner_id', $id)->column('id');
        $check = $check ? array_values($check) : [];
        return json(['code' => 200, 'data' => $list, 'check' => $check]);
    }

    public function bindDevice()
    {
        $id = request()->post('id', '');
        $device_id = request()->post('device_id/a');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $deviceModel = new \app\index\model\MachineDevice();
        $deviceModel->whereNotIn('id', $device_id)->where('banner_id', $id)->update(['banner_id' => '']);
        $deviceModel->whereIn('id', $device_id)->update(['banner_id' => $id]);
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function del()
    {
        $id = request()->get('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $deviceModel = new \app\index\model\MachineDevice();
        $res = $deviceModel->where('banner_id', $id)->where('delete_time', null)->find();
        if ($res) {
            return json(['code' => 100, 'msg' => '不可删除,该素材已绑定设备']);
        }
        $bannerModel = new MachineBannerModel();
        $bannerModel->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }
}