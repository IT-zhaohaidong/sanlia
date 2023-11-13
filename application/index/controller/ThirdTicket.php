<?php


namespace app\index\controller;

//视频播放日志
use app\index\model\ThirdTicketModel;
use think\Cache;

class ThirdTicket extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 15 : $params['limit'];
        $where = [];
        if (!empty($params['title'])) {
            $where['title'] = ['like', '%' . $params['title'] . '%'];
        }
        $model = new ThirdTicketModel();
        $count = $model
            ->where($where)
            ->count();
        $list = $model
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['area_check'] = json_decode($v['area_check'], true);
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function getArea()
    {
        $area = Cache::store('redis')->get('area_txt');
        if ($area) {
            return json(['code' => 200, 'data' => $area]);
        } else {
            $url = "https://apis.map.qq.com/ws/district/v1/list?key=B4DBZ-2CM34-YFZUV-K6SHN-ODP36-NTFQJ";
            $res = https_request($url);
            $data = json_decode($res, true);
            if ($data['status'] == 0) {
                $result = $data['result'];
                $area = $result[0];
                foreach ($area as $k => $v) {
                    $province_code = substr($v['id'], 0, 2);
                    $city_arr = [];
                    foreach ($result[1] as $x => $y) {
                        if (substr($y['id'], 0, 2) == $province_code) {
                            $city_code = substr($y['id'], 0, 4);
                            $area_item = [];
                            foreach ($result[2] as $j => $l) {
                                $area_code = substr($l['id'], 0, 4);
                                if ($area_code == $city_code) {
                                    $area_item[] = $l;
                                    unset($result[2][$j]);
                                }
                            }
                            if ($area_item) {
                                $y['children'] = $area_item;
                            }
                            $city_arr[] = $y;
                            unset($result[1][$x]);
                        }
                    }
                    if ($city_arr) {
                        $area[$k]['children'] = $city_arr;
                    }
                }
                $area1 = [
                    [
                        'id' => "0",
                        'fullname' => '不限区域'
                    ]
                ];
                $area = array_merge($area1, $area);
                Cache::store('redis')->set('area_txt', $area);
                return json(['code' => 200, 'data' => $area]);
            } else {
                return json(['code' => 100, 'msg' => '获取失败']);
            }
        }
    }

    public function addTicket()
    {
        $params = request()->post();
        $model = new ThirdTicketModel();
        $area = [];
        foreach ($params['area_check'] as $k => $v) {
            $area = array_merge($area, $v);
        }
        $area = implode(',', array_unique($area));
        $data = [
            'title' => $params['title'],
            'appid' => $params['appid'],
            'image' => $params['image'],
            'path' => $params['path'],
            'area' => $area,
            'area_check' => json_encode($params['area_check']),
            'status' => $params['status'],
            'type' => $params['type'],
        ];
        if (!empty($params['id'])) {
            $model->where('id', $params['id'])->update($data);
        } else {
            $model->save($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function del()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new ThirdTicketModel();
        $model->where('id', $params['id'])->delete();
        return json(['code' => 200, 'msg' => '缺少参数']);
    }
}
