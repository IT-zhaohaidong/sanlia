<?php

namespace app\appletadmin\controller;

use app\index\controller\BaseController;
use app\index\model\MallCate;
use app\index\model\MallGoodsModel;
use app\index\model\OperateGoodsModel;
use app\index\model\OperateStockLogModel;
use app\index\model\ShipGoodsModel;
use app\index\model\ShipOrderModel;
use think\Db;

class ShipOrder extends BaseController
{
    //创建发货单,获取商品列表
    public function getGoods()
    {
        $params = request()->get();
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 10);
        $user = $this->user;
        $where = [];
        if (!empty($params['title'])) {
            $where['title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (isset($params['cate_id']) && $params['cate_id'] !== '') {
            $str = ',' . $params['cate_id'] . ',';
            $where['cate_ids'] = ['like', '%' . $str . '%'];
        }
        $u_where = '';
        if ($user['role_id'] != 1) {
            $cate_ids = explode(',', $user['cate_ids']);
            if ($cate_ids) {
                foreach ($cate_ids as $k => $v) {
                    if ($u_where) {
                        $u_where .= ' OR ';
                    }
                    $u_where .= 'cate_ids like "%,' . $v . ',%"';
                }
            } else {
                return json(['code' => 200, 'data' => [], 'params' => $params, 'count' => 0]);
            }
        }
        $count = Db::name('mall_goods')->alias('sg')
            ->join('brand b', 'b.id=sg.brand_id', 'left')
            ->where($where)
            ->where($u_where)
            ->where('sg.delete_time', null)
            ->field('sg.*,b.name brand_name')
            ->count();

        $list = Db::name('mall_goods')->alias('sg')
            ->join('brand b', 'b.id=sg.brand_id', 'left')
            ->where($where)
            ->where($u_where)
            ->where('sg.delete_time', null)
            ->field('sg.*,b.name brand_name')
            ->page($page)
            ->limit($limit)
            ->order('sg.id desc')
            ->select();
        trace($count, '总数量');
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //创建发货单,获取商品分类
    public function getGoodsCate()
    {
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            $cate_ids = explode(',', $user['cate_ids']);
            $where['id'] = ['in', $cate_ids];
        }
        $model = new MallCate();
        $list = $model
            ->where('pid', 0)
            ->where($where)
            ->field('id,name')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //创建发货单
    public function createShipOrder()
    {
        $params = request()->post();
        $user = $this->user;
        if (empty($params['goods'])) {
            return json(['code' => 100, 'msg' => '请选择商品']);
        }
        //goods
//        [
//            ['goods_id'=>1,'count'=>10],
//            ['goods_id'=>2,'count'=>10],
//            ['goods_id'=>3,'count'=>15],
//        ];
        $goods_ids = array_column($params['goods'], 'goods_id');
        $goods = (new MallGoodsModel())
            ->whereIn('id', $goods_ids)
            ->column('stock,id,title,price', 'id');
        $outStock = false;
        $title = '';
        foreach ($params['goods'] as $k => $v) {
            if ($v['count'] > $goods[$v['goods_id']]['stock']) {
                $outStock = true;
                $title = $goods[$v['goods_id']]['title'];
                break;
            }
        }
        if ($outStock) {
            return json(['code' => 100, 'msg' => $title . '库存不足']);
        }
        $address = $params['area'] . $params['detail'];
        $order_sn = time() . rand(1000, 9999) . $user['id'];
        $order_data = [
            'uid' => $user['id'],
            'phone' => $params['phone'],
            'name' => $params['name'],
            'address' => $address,
            'order_sn' => $order_sn,
            'status' => 0,
            'create_time' => time()
        ];
        $model = new ShipOrderModel();
        $id = $model->insertGetId($order_data);
        $order_goods = [];
        foreach ($params['goods'] as $k => $v) {
            $order_goods[] = [
                'order_id' => $id,
                'goods_id' => $v['goods_id'],
                'price' => $goods[$v['goods_id']]['price'],
                'count' => $v['count'],
                'total_price' => $goods[$v['goods_id']]['price'] * $v['count'],
            ];
        }
        (new ShipGoodsModel())->saveAll($order_goods);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //发货单列表
    public function getShipOrderList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 15 : $params['limit'];
        $user = $this->user;
        $where = [];
        if ($user['role_id'] != 1) {
            if ($user['role_id'] > 5) {
                $where['o.uid'] = $user['parent_id'];
            } else {
                $where['o.uid'] = $user['id'];
            }
        }
        if (!empty($params['keywords'])) {
            $where['a.username'] = ['like', '%' . $params['keywords'] . '%'];
        }
        $model = new ShipOrderModel();
        $count = $model->alias('o')
            ->join('system_admin a', 'o.uid=a.id', 'left')
            ->where($where)
            ->count();
        $list = $model->alias('o')
            ->join('system_admin a', 'o.uid=a.id', 'left')
            ->field('o.*,a.username')
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('o.id desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['send_time'] = $v['send_time'] ? date('Y-m-d H:i:s', $v['send_time']) : '';
            $list[$k]['check_time'] = $v['check_time'] ? date('Y-m-d H:i:s', $v['check_time']) : '';
            $list[$k]['receive_time'] = $v['send_time'] ? date('Y-m-d H:i:s', $v['receive_time']) : '';
            $list[$k]['back_time'] = $v['send_time'] ? date('Y-m-d H:i:s', $v['back_time']) : '';
            $list[$k]['complete_time'] = $v['send_time'] ? date('Y-m-d H:i:s', $v['complete_time']) : '';
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //获取发货单商品
    public function getOrderGoods()
    {
        $id = request()->get('id', '');
        $orderModel = new ShipOrderModel();
        $order = $orderModel->where('id', $id)->find();
//        if (!$order || $order['status'] != 4) {
//            return json(['code' => 100, 'msg' => '该订单暂不可验收']);
//        }
        $goodsModel = new ShipGoodsModel();
        $goodsList = $goodsModel->alias('og')
            ->join('mall_goods g', 'g.id=og.goods_id', 'left')
            ->where('og.order_id', $order['id'])
            ->field('og.*,g.image,g.title,g.code')
            ->select();
        return json(['code' => 200, 'data' => $goodsList]);
    }

    //无运损一键入库
    public function stockIn()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $shipGoodsModel = new ShipGoodsModel();
        $row = $shipGoodsModel
            ->where('id', $id)
            ->find();
        if (!$row) {
            return json(['code' => 100, 'msg' => '该商品不存在']);
        }
        $order = (new ShipOrderModel())
            ->where('id', $row['order_id'])
            ->find();
        $goodsModel = new OperateGoodsModel();
        //添加/修改运营库库存
        $goods = $goodsModel
            ->where(['goods_id' => $row['goods_id'], 'uid' => $order['uid']])
            ->find();
        if ($goods) {
            $goodsModel
                ->where('id', $goods['id'])
                ->update(['stock' => $goods['stock'] + $row['count']]);
        } else {
            $data = [
                'goods_id' => $row['goods_id'],
                'uid' => $order['uid'],
                'stock' => $row['count']
            ];
            $goodsModel->save($data);
        }
        //更改该商品状态为已验收,并更新验收信息
        $shipGoodsModel
            ->where('id', $id)
            ->update(['loss_num' => 0, 'take_num' => $row['count'], 'images' => '', 'status' => 1]);
        //判断是否是最后一个验收的,如果是,则更改该订单为已验收状态
        $no_check = $shipGoodsModel
            ->where('order_id', $row['order_id'])
            ->where('status', 0)
            ->find();
        if (!$no_check) {
            $isDamaged = $shipGoodsModel
                ->where('order_id', $row['order_id'])
                ->where('loss_num', '>', 0)->find();
            $status = $isDamaged ? 3 : 5;
            $data = ['status' => $status, 'receive_time' => time()];
            if ($status == 5) {
                $data['complete_time'] = time();
            }
            (new ShipOrderModel())
                ->where('id', $row['order_id'])
                ->update($data);
        }
        //添加入库记录
        $log = [
            'uid' => $this->user['id'],
            'uuid' => $order['uid'],
            'goods_id' => $row['goods_id'],
            'count' => $row['count'],
            'type' => 1,
        ];
        (new OperateStockLogModel())->save($log);
        return json(['code' => 200, 'msg' => '入库成功']);
    }

    //获取已上报货损,用于回显
    public function getDamaged()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $shipGoodsModel = new ShipGoodsModel();
        $row = $shipGoodsModel->where('id', $params['id'])->field('id,count,loss_num,images')->find();
        $row['images'] = $row['images'] ? explode(',', $row['images']) : [];
        return json(['code' => 200, 'data' => $row]);
    }

    //有货损 上传货损数量及保存图片
    public function reportDamaged()
    {
        $params = request()->post();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if ($params['loss_num'] < 0) {
            return json(['code' => 100, 'msg' => '运损件数必须大于0']);
        }
        if (count($params['images']) < 3) {
            return json(['code' => 100, 'msg' => '请上传3张图片']);
        }
        $images = implode(',', $params['images']);
        $shipGoodsModel = new ShipGoodsModel();
        $row = $shipGoodsModel->where('id', $params['id'])->find();
        if ($row['count'] < $params['loss_num']) {
            return json(['code' => 100, 'msg' => '运损数量不能大于发货数量']);
        }
        $shipGoodsModel->where('id', $params['id'])->update(['images' => $images, 'loss_num' => $params['loss_num']]);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //入库确认,获取入库数量
    public function confirmStockIn()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $shipGoodsModel = new ShipGoodsModel();
        $row = $shipGoodsModel->alias('sg')
            ->join('mall_goods g', 'g.id=sg.goods_id', 'left')
            ->where('sg.id', $params['id'])
            ->field('sg.id,sg.count,sg.loss_num,sg.goods_id,g.title,g.code,g.image')
            ->find();
        $row['take_num'] = $row['count'] - $row['loss_num'];
        return json(['code' => 200, 'data' => $row]);
    }

    //有货损,一键入库
    public function damagedStockIn()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $shipGoodsModel = new ShipGoodsModel();
        $row = $shipGoodsModel->where('id', $params['id'])->field('id,count,loss_num,goods_id,images,order_id')->find();
        $take_num = $row['count'] - $row['loss_num'];
        $shipGoodsModel->where('id', $params['id'])->update(['take_num' => $take_num, 'status' => 1]);
        //添加/修改运营库库存
        $goodsModel = new OperateGoodsModel();
        $order = (new ShipOrderModel())
            ->where('id', $row['order_id'])
            ->find();
        $goods = $goodsModel
            ->where(['goods_id' => $row['goods_id'], 'uid' => $order['uid']])
            ->find();
        if ($goods) {
            $goodsModel
                ->where('id', $goods['id'])
                ->update(['stock' => $goods['stock'] + $take_num]);
        } else {
            $data = [
                'goods_id' => $row['goods_id'],
                'uid' => $order['uid'],
                'stock' => $take_num
            ];
            $goodsModel->save($data);
        }
        //添加入库记录
        $log = [
            'uid' => $this->user['id'],
            'uuid' => $order['uid'],
            'goods_id' => $row['goods_id'],
            'count' => $take_num,
            'type' => 1,
        ];
        (new OperateStockLogModel())->save($log);
        //判断是否是最后一个验收的,如果是,则更改该订单为已验收状态
        $no_check = $shipGoodsModel
            ->where('order_id', $row['order_id'])
            ->where('status', 0)
            ->find();
        if (!$no_check) {
            $isDamaged = $shipGoodsModel
                ->where('order_id', $row['order_id'])
                ->where('loss_num', '>', 0)->find();
            $status = $isDamaged ? 3 : 5;
            $data = ['status' => $status, 'receive_time' => time()];
            if ($status == 5) {
                $data['complete_time'] = time();
            }
            (new ShipOrderModel())
                ->where('id', $row['order_id'])
                ->update($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    //扫码入库
    public function codeStockIn()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $shipGoodsModel = new ShipGoodsModel();
        $row = $shipGoodsModel->where('id', $params['id'])->field('id,count,loss_num,goods_id,images,order_id')->find();
        $take_num = $row['count'] - $row['loss_num'];
        if ($params['take_num'] != $take_num) {
            return json(['code' => 100, 'msg' => '数量不匹配']);
        }
        $shipGoodsModel->where('id', $params['id'])->update(['take_num' => $take_num, 'status' => 1]);
        //添加/修改运营库库存
        $goodsModel = new OperateGoodsModel();
        $order = (new ShipOrderModel())
            ->where('id', $row['order_id'])
            ->find();
        $goods = $goodsModel
            ->where(['goods_id' => $row['goods_id'], 'uid' => $order['uid']])
            ->find();
        if ($goods) {
            $goodsModel
                ->where('id', $goods['id'])
                ->update(['stock' => $goods['stock'] + $take_num]);
        } else {
            $data = [
                'goods_id' => $row['goods_id'],
                'uid' => $order['uid'],
                'stock' => $take_num
            ];
            $goodsModel->save($data);
        }
        //添加入库记录
        $log = [
            'uid' => $this->user['id'],
            'uuid' => $order['uid'],
            'goods_id' => $row['goods_id'],
            'count' => $take_num,
            'type' => 1,
        ];
        (new OperateStockLogModel())->save($log);
        //判断是否是最后一个验收的,如果是,则更改该订单为已验收状态
        $no_check = $shipGoodsModel
            ->where('order_id', $row['order_id'])
            ->where('status', 0)
            ->find();
        if (!$no_check) {
            $isDamaged = $shipGoodsModel
                ->where('order_id', $row['order_id'])
                ->where('loss_num', '>', 0)->find();
            $status = $isDamaged ? 3 : 5;
            $data = ['status' => $status, 'receive_time' => time()];
            if ($status == 5) {
                $data['complete_time'] = time();
            }
            (new ShipOrderModel())
                ->where('id', $row['order_id'])
                ->update($data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    //保存退货运单号
    public function saveBackWaybill()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['back_waybill_no'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = [
            'back_time' => time(),
            'back_waybill_no' => $params['back_waybill_no'],
            'status' => 6
        ];
        (new ShipOrderModel())
            ->where('id', $params['id'])
            ->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }

    //无需退货
    public function completeOrder()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = [
            'complete_time' => time(),
            'status' => 5
        ];
        (new ShipOrderModel())
            ->where('id', $params['id'])
            ->update($data);
        return json(['code' => 200, 'msg' => '成功']);
    }
}
