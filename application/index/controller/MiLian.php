<?php

namespace app\index\controller;

use app\index\common\Wxpay;
use app\index\model\AdverMaterialModel;
use app\index\model\MchidModel;
use app\index\model\MilianBannerModel;
use app\index\model\MilianVideoModel;
use app\index\model\SystemAdmin;
use think\Db;

class MiLian extends BaseController
{
    //设备列表
    public function deviceList()
    {
        $prams = request()->post();
        $limit = request()->post('limit', 10);
        $page = request()->post('page', 1);
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
                $where['d.uid'] = ['=', $user['id']];
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

        if (!empty($prams['keywords'])) {
            $where['d.device_name|d.imei'] = ['like', '%' . $prams['keywords'] . '%'];
        }

        $model = new \app\index\model\MachineDevice();
        $model = $model->alias('d')
            ->join('system_admin a', 'a.id=d.uid', 'left')
            ->where('d.delete_time', null)
            ->where('d.supply_id', 3)
            ->where($where);
        $count = $model->count();
        $list = $model->alias('d')
            ->join('system_admin a', 'a.id=d.uid', 'left')
            ->where('d.supply_id', 3)
            ->where('d.delete_time', null)
            ->where($where)
            ->field('d.*,a.username')
            ->limit($limit)
            ->page($page)
            ->order('id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['remain_time'] = ($v['expire_time'] > time()) ? ceil(($v['expire_time'] - time()) / (3600 * 24)) : '已过期';
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $prams]);
    }

    //获取设备绑定图片
    public function getBanner()
    {
        $device_id = request()->get("id", "");
        $bannerModel = new MilianBannerModel();
//        $device_id = (new \app\index\model\MachineDevice())->where("device_sn", $device_sn)->value("id");
//        $ab_ids = $bannerModel->where("device_id", $device_id)->value("banner_ids");
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['uid'] = ['=', $user['parent_id']];
            } else {
                $where['uid'] = ['=', $user['id']];
            }
        }
        $bannerData = (new AdverMaterialModel())->where($where)->where('type', 1)->order('id desc')->select();
        $arr = [
            "bannerData" => $bannerData,
            "ids" => []
        ];
        return json(['code' => 200, 'data' => $arr]);
    }

    //绑定图片
    public function binding()
    {
        $device_id = request()->post("id", "");
        $name = request()->post("name", "");
        $start = request()->post("start_date", "");
        $end = request()->post("end_date", "");
        $device_sn = (new \app\index\model\MachineDevice())->where("id", $device_id)->value("device_sn");

        $ids = request()->post("ids/a", []);
//        if (count($ids) == 0) {
//            $result = (new MilianBannerModel())->where("device_id", $device_id)->delete();
//            if ($result) {
//                return json(['code' => 200, 'msg' => '绑定成功']);
//            } else {
//                return json(['code' => 100, 'msg' => '绑定失败']);
//            }
//        }
        $bannerModel = new AdverMaterialModel();
        $banner_data = $bannerModel->where("id", "in", $ids)->column('url', 'id');
        foreach ($ids as $k => $v) {
            if (!$v) {
                continue;
            }
//            $ids = implode(",", $ids);


            $img = [$banner_data[$v]];
            $pictureList = $img;
            $device_arr[] = $device_sn;
            $dateStart = strtotime($start) * 1000;
            $dateEnd = strtotime($end) * 1000;
            $taskId = $this->addImage($device_arr, $name, $pictureList, $dateStart, $dateEnd);
            $img = implode("|", $img);
            $arr = [
                "device_id" => $device_id,
                "banner_images" => $img,
                "banner_ids" => $ids,
                'task_id' => $taskId,
                'task_name' => $name,
                'start_time' => strtotime($start),
                'end_time' => strtotime($end),
            ];
//            $obj = new MilianBannerModel();
//            $row = $obj->where("device_id", $device_id)->find();
//            if ($row) {
//                $device_name = Db::name('machine_device')->where('id', $device_id)->value('device_name');
//                if ($row['task_id']) {
//                    $this->delImage($row['task_id'], $device_name, 'DELETE');
//                }
//                $result = (new MilianBannerModel())->where("id", $row['id'])->update($arr);
//            } else {
            $result = (new MilianBannerModel())->save($arr);
//            }
        }
        return json(['code' => 200, 'msg' => '绑定成功']);

    }


    //获取已绑定图片
    public function getBindImg()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MilianBannerModel();
        $list = $model->where('device_id', $id)->select();
        foreach ($list as $k => $v) {
            $list[$k]['start_time'] = $v['start_time'] ? date('Y-m-d', $v['start_time']) : '';
            $list[$k]['end_time'] = $v['end_time'] ? date('Y-m-d', $v['end_time']) : '';
            $list[$k]['banner_images'] = explode('|', $v['banner_images'])[0];
        }
        return json(['code' => 200, 'data' => $list]);
    }

    //解绑图片 单独版
    public function cancelBind()
    {
        $id = request()->get("id", "");
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $row = (new MilianBannerModel())->where("id", $id)->find();
        if ($row['task_id']) {
            $this->delImage($row['task_id'], [], 'DELETE');
        }
        $result = (new MilianBannerModel())->where("id", $id)->delete();
        if ($result) {
            return json(['code' => 200, 'msg' => '解绑成功']);
        } else {
            return json(['code' => 100, 'msg' => '解绑失败']);
        }
    }

    //解绑图片 设备版
    public function del()
    {
        $id = request()->get("id", "");
        $row = (new MilianBannerModel())->where("device_id", $id)->find();
        if ($row['task_id']) {
            $this->delImage($row['task_id'], [], 'DELETE');
        }
        $result = (new MilianBannerModel())->where("device_id", $id)->delete();
        if ($result) {
            return json(['code' => 200, 'msg' => '解绑成功']);
        } else {
            return json(['code' => 100, 'msg' => '解绑失败']);
        }
    }

    /**
     * 绑定视频页
     */
    public function getVideo()
    {
        $id = request()->get("id", "");
        $device_info = (new \app\index\model\MachineDevice)->field(true)->where("id", $id)->find();
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['uid'] = ['=', $user['parent_id']];
            } else {
                $where['uid'] = ['=', $user['id']];
            }
        }
        $video_arr = (new AdverMaterialModel())->where($where)->where('type', 2)->order('id desc')->select();
        $data = ['device_info' => $device_info, 'video_arr' => $video_arr];
        return json(['code' => 200, 'data' => $data]);
    }

    public function bindVideo()
    {
        $post = request()->post();
        $time_arr = explode("|", $post["date"]);
        $star_time = strtotime($time_arr[0]) * 1000;
        $end_time = strtotime($time_arr[1]) * 1000;
        $video = (new AdverMaterialModel())->where("id", $post["video_id"])->find();
        $mediaUrl = $video['url'];
        $taskId = $this->addVideo([$post['device_sn']], $post["task_name"], $mediaUrl, $star_time, $end_time);
        if (!$taskId) {
            return json(['code' => 100, 'msg' => '绑定失败']);
        }
        $data = [
            "device_sn" => $post['device_sn'],
            "device_name" => $post['device_name'],
            "task_name" => $post["task_name"],
            "video_id" => $post["video_id"],
            "date_start" => $star_time,
            "dateEnd" => $end_time,
            "taskId" => $taskId
        ];
        $obj = new MilianVideoModel();
        $result = $obj->save($data);
        if ($result) {
            return json(['code' => 200, 'msg' => '绑定成功']);
        } else {
            return json(['code' => 100, 'msg' => '绑定失败']);
        }

    }

    /**
     * 查看详情
     */
    public function details()
    {
        $device_sn = request()->get("device_sn", "");
        $data = (new MilianVideoModel())->where('device_sn', $device_sn)->select();
        foreach ($data as $k => $v) {
            $data[$k]['dateEnd'] = date('Y-m-d H:i:s', $v['dateEnd'] / 1000);
            $data[$k]['date_start'] = date('Y-m-d H:i:s', $v['date_start'] / 1000);
        }
        return json(['code' => 200, 'data' => $data]);
    }

    /**
     * 解绑视频
     */
    public function delVideo()
    {
        $id = request()->get("id", "");
        $obj = new MilianVideoModel();
        $info = $obj->where("id", $id)->find();
        $device_name_arr = $obj->where("taskId", $info['taskId'])->column("device_name");
        $result = MilianVideoModel::destroy($id);
        if (count($device_name_arr) == 1) {
            $this->delVide($info['taskId'], [], "DELETE");
        } else {
            unset($device_name_arr[$info['device_name']]);
            foreach ($device_name_arr as $k => $v) {
                $arr[] = $v;
            }
            $this->delVide($info['taskId'], $arr, "PUT");
        }

        if ($result) {
            return json(['code' => 200, 'msg' => '删除成功']);
        } else {
            return json(['code' => 100, 'msg' => '删除失败']);
        }
    }




    //-------------------------------蜜连接口-------------------------------------------------------

    /**
     * 添加图片任务
     */
    public function addImage($device_arr, $name, $pictureList, $dateStart, $dateEnd)
    {
        $json = [
            "nonceStr" => time(),
            "name" => $name,
            "pictureList" => $pictureList,
            'interval' => 5,//图片播放间隔时间(秒)
            "dateStart" => $dateStart,
            "dateEnd" => $dateEnd,
            "timeStart" => 0,
            "timeEnd" => 2359,
            "agentSn" => $device_arr
        ];
        $data = json_encode($json);


        $post_data = array(
            'data' => $data
        );
        $taskId = $this->send_postss('https://mqtt.ibeelink.com/api/ext/tissue/ad/task',
            $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        if ($taskId) {
            return $this->upImage($taskId);
        } else {
            return false;
        }
    }

    private function send_postss($url, $post_data, $token)
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

    /**
     * 上线视频任务
     */
    public function upImage($taskId)
    {
        $json = [
            "nonceStr" => time(),
            "taskId" => $taskId,
            "selected" => true,
        ];
        $data = json_encode($json);


        $post_data = array(
            'data' => $data
        );
        $this->send_post_upss("https://mqtt.ibeelink.com/api/ext/tissue/ad/task/up-down/" . $taskId,
            $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        return $taskId;
    }

    private function send_post_upss($url, $post_data, $token)
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
    }

    /**
     * 删除视频任务
     */
    public function delImage($taskId, $agentSn, $type)
    {
        $json = [
            "nonceStr" => time(),
            "taskId" => $taskId,
            "agentSn" => $agentSn,
        ];
        $data = json_encode($json);
        $post_data = array(
            'data' => $data
        );
        return send_post_del("https://mqtt.ibeelink.com/api/ext/tissue/ad/task/{$taskId}",
            $post_data, md5($data . 'D902530082e570917F645F755AE17183'), $type);
    }

    /**
     * 添加视频任务
     */
    public function addVideo($device_arr, $name, $mediaUrl, $dateStart, $dateEnd)
    {
        $json = [
            "nonceStr" => time(),
            "name" => $name,
            "mediaUrl" => $mediaUrl,
            "dateStart" => $dateStart,
            "dateEnd" => $dateEnd,
            "timeStart" => 0,
            "timeEnd" => 2359,
            "agentSn" => $device_arr
        ];
        $data = json_encode($json);
        function send_post($url, $post_data, $token)
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
        $taskId = \app\index\controller\send_post('https://mqtt.ibeelink.com/api/ext/tissue/ad/task',
            $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        if ($taskId) {
            return $this->upVideo($taskId);
        } else {
            return false;
        }
    }

    /**
     * 上线视频任务
     */
    public function upVideo($taskId)
    {
        $json = [
            "nonceStr" => time(),
            "taskId" => $taskId,
            "selected" => true,
        ];
        $data = json_encode($json);
        function send_post_up($url, $post_data, $token)
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
        }

        $post_data = array(
            'data' => $data
        );

        \app\index\controller\send_post_up("https://mqtt.ibeelink.com/api/ext/tissue/ad/task/up-down/" . $taskId,
            $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        return $taskId;
    }

    /**
     * 删除视频任务
     */
    public function delVide($taskId, $agentSn, $type)
    {
        $json = [
            "nonceStr" => time(),
            "taskId" => $taskId,
            "agentSn" => $agentSn,
        ];
        $data = json_encode($json);
        $post_data = array(
            'data' => $data
        );
        return send_post_del("https://mqtt.ibeelink.com/api/ext/tissue/ad/task/{$taskId}",
            $post_data, md5($data . 'D902530082e570917F645F755AE17183'), $type);
    }


    /**
     *
     * @param $device_arr
     * @param $taskId_arr
     * @return bool
     */
    public function editVideo($device_arr, $taskId_arr)
    {
        foreach ($taskId_arr as $k => $v) {
            //该任务下的所有设备号
            $device = (new MilianVideoModel())->where("taskId", $v)->column("device_name");
            $device_int = array_intersect($device_arr, $device);
            $device_dif = array_diff($device, $device_int);
            $arr = [];
            if (count($device_dif) == 0) {
                $this->delVide($v, $arr, "DELETE");
            } else {
                foreach ($device_dif as $kk => $vv) {
                    $arr[] = $vv;
                }
                $this->delVide($v, $arr, "PUT");
            }
        }
    }

}
