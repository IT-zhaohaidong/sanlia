<?php


namespace app\index\controller;


use app\index\model\PackageModel;
use app\index\model\VideoLogModel;

//视频播放日志
class VideoLog extends BaseController
{
    public function getList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 15 : $params['limit'];
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $where = [];
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $where['log.create_time'] = ['between', [strtotime($params['start_time']), strtotime($params['end_time']) + 3600 * 24], 'AND'];
        }
        $model = new VideoLogModel();
        $count = $model->alias('log')
            ->join('adver_material a', 'a.id=log.video_id', 'left')
            ->where('log.video_id', $params['id'])
            ->where($where)
            ->count();
        $list = $model->alias('log')
            ->join('adver_material a', 'a.id=log.video_id', 'left')
            ->where('log.video_id', $params['id'])
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->field('log.*,a.name')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['num'] = 1;
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //导出获取全部数据
    public function getAll()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $where = [];
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $where['log.create_time'] = ['between', [strtotime($params['start_time']), strtotime($params['end_time']) + 3600 * 24], 'AND'];
        }
        $model = new VideoLogModel();
        $count = $model->alias('log')
            ->join('adver_material a', 'a.id=log.video_id', 'left')
            ->where('log.video_id', $params['id'])
            ->where($where)
            ->count();
        $list = [];
        for ($i = 1; $i <= ceil($count / 1000); $i++) {
            $data = $model->alias('log')
                ->join('adver_material a', 'a.id=log.video_id', 'left')
                ->where('log.video_id', $params['id'])
                ->where($where)
                ->page($i)
                ->limit(1000)
                ->field('log.*,a.name')
                ->select();
            $list = array_merge($list, $data);
        }

        foreach ($list as $k => $v) {
            $list[$k]['num'] = 1;
        }
        return json(['code' => 200, 'data' => $list]);
    }

}
