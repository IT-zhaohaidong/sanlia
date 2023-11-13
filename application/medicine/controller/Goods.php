<?php

namespace app\medicine\controller;

use app\index\model\MachineDevice;
use app\index\model\MedicineOrderGoodsModel;
use app\index\model\MtDeviceGoodsModel;
use app\index\model\MtGoodsModel;
use app\index\model\OperateSearchModel;
use think\Controller;
use think\Db;

//绑定设备版本
class Goods extends Controller
{
    private static $cate = [
        '090100' => '感冒用药',
        '090200' => '清热解毒',
        '090300' => '呼吸系统',
        '090400' => '消化系统',
        '090500' => '妇科用药',
        '090600' => '儿童用药',
        '090700' => '滋养调补',
        '090800' => '男科用药',
        '090900' => '中药饮片',
        '091000' => '性福生活',
        '091100' => '皮肤用药',
        '091200' => '五官用药',
        '091300' => '营养保健',
        '091400' => '内分泌系统',
        '091500' => '医疗器械',
        '091600' => '养心安神',
        '091700' => '风湿骨伤',
        '091800' => '心脑血管用药',
        '092000' => '家庭常备',
        '092100' => '泌尿系统',
        '092200' => '神经用药',
        '092300' => '肿瘤用药',
        '092400' => '其他',
    ];

    private static $cate_image = [
        '090100' => 'https://api.hnchaohai.com/upload/medicine_cate/ganmao.png',
        '090200' => 'https://api.hnchaohai.com/upload/medicine_cate/qingre.png',
        '090300' => 'https://api.hnchaohai.com/upload/medicine_cate/huxi.png',
        '090400' => 'https://api.hnchaohai.com/upload/medicine_cate/xiaohua.png',
        '090500' => 'https://api.hnchaohai.com/upload/medicine_cate/fuke.png',
        '090600' => 'https://api.hnchaohai.com/upload/medicine_cate/ertong.png',
        '090700' => 'https://api.hnchaohai.com/upload/medicine_cate/zibu.png',
        '090800' => 'https://api.hnchaohai.com/upload/medicine_cate/nanke.png',
        '090900' => '中药饮片',
        '091000' => 'https://api.hnchaohai.com/upload/medicine_cate/chengren.png',
        '091100' => 'https://api.hnchaohai.com/upload/medicine_cate/pifu.png',
        '091200' => 'https://api.hnchaohai.com/upload/medicine_cate/wuguan.png',
        '091300' => 'https://api.hnchaohai.com/upload/medicine_cate/yingyang.png',
        '091400' => 'https://api.hnchaohai.com/upload/medicine_cate/neifenmi.png',
        '091500' => '医疗器械',
        '091600' => 'https://api.hnchaohai.com/upload/medicine_cate/yangxin.png',
        '091700' => 'https://api.hnchaohai.com/upload/medicine_cate/fengshi.png',
        '091800' => '心脑血管用药',
        '092000' => 'https://api.hnchaohai.com/upload/medicine_cate/home.png',
        '092100' => 'https://api.hnchaohai.com/upload/medicine_cate/miniao.png',
        '092200' => 'https://api.hnchaohai.com/upload/medicine_cate/shenjing.png',
        '092300' => '肿瘤用药',
        '092400' => '其他',
    ];

    //商品列表
    public function goodsList()
    {
        $device_sn = request()->get('device_sn', '');
        if (empty($device_sn)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $deviceModel = new MachineDevice();
        $device = $deviceModel->where('device_sn', $device_sn)->find();
        if (!$device) {
            return json(['code' => 100, 'msg' => '设备不存在']);
        }
        $goodsModel = new MtDeviceGoodsModel();
        $goods = $goodsModel->alias('dg')
            ->join('mt_goods g', 'dg.goods_id=g.id', 'left')
            ->where('dg.device_id', $device['id'])
            ->where('dg.num', '<=', $device['num'])
            ->where('dg.goods_id', '>', 0)
            ->group('dg.goods_id')
            ->field('sum(dg.stock) total_stock,dg.price,dg.goods_id,g.category_code,g.spec,g.medicine_no,g.image,g.name,g.detail,g.detail_image')
            ->select();
        $cate = $goodsModel->alias('dg')
            ->join('mt_goods g', 'dg.goods_id=g.id', 'left')
            ->where('dg.device_id', $device['id'])
            ->where('dg.num', '<=', $device['num'])
            ->where('dg.goods_id', '>', 0)
            ->group('g.category_code')
            ->field('g.category_code')->select();
        $cates = self::$cate;
        $sort_arr = [];
        foreach ($cates as $k => $v) {
            foreach ($cate as $x => $y) {
                if ($k == $y['category_code']) {
                    $sort_arr[] = $y;
                    continue;
                }
            }
        }
        $cate_arr = [];
        foreach ($sort_arr as $k => $v) {
            $item = [];
            $item['category_name'] = self::$cate[$v['category_code']];
            $item['image'] = self::$cate_image[$v['category_code']];
            $item['category_code'] = $v['category_code'];
            $key = ceil(($k + 1) / 10) - 1;
            $cate_arr[$key][] = $item;
        }
        foreach ($sort_arr as $k => $v) {
            $sort_arr[$k]['category_name'] = self::$cate[$v['category_code']];
            $sort_arr[$k]['image'] = self::$cate_image[$v['category_code']];
            $item = [];
            foreach ($goods as $x => $y) {
                if ($y['category_code'] == $v['category_code']) {
                    $item[] = $y;
                }
            }
            $sort_arr[$k]['children'] = $item;
        }
        return json(['code' => 200, 'data' => $sort_arr, 'cate' => $cate_arr]);
    }

    //搜索商品--需登录
    public function searchGoods()
    {
        $device_sn = request()->get('device_sn', '');
        $keyword = request()->get('keyword', '');
        $openid = request()->get('openid', '');
        if (empty($device_sn) || empty($keyword) || empty($openid)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $deviceModel = new MachineDevice();
        $device = $deviceModel->where('device_sn', $device_sn)->find();
        if (!$device) {
            return json(['code' => 100, 'msg' => '设备不存在']);
        }
        $goodsModel = new MtDeviceGoodsModel();
        $goods = $goodsModel->alias('dg')
            ->join('mt_goods g', 'dg.goods_id=g.id', 'left')
            ->join('mt_cate c', 'g.category_code=c.category_code', 'left')
            ->where('dg.device_id', $device['id'])
            ->where('c.category_name|g.name|g.detail', 'like', '%' . $keyword . '%')
            ->group('dg.goods_id')
            ->field('sum(dg.stock) total_stock,dg.price,dg.goods_id,g.name,g.spec,g.category_code,g.category_code,g.image,g.detail,g.detail_image')
            ->select();
        $searchModel = new OperateSearchModel();
        $data = ['openid' => $openid, 'keyword' => $keyword, 'type' => 0];
        $row = $searchModel->where($data)->find();
        if (!$row) {
            $searchModel->save($data);
        }
        return json(['code' => 200, 'data' => $goods]);
    }

    //获取搜索历史
    public function getSearchHistory()
    {
        $openid = request()->get('openid', '');
        $searchModel = new OperateSearchModel();
        $list = $searchModel
            ->where(['openid' => $openid, 'type' => 0])
            ->order('id desc')
            ->field('id,keyword')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function getBanner()
    {
        $device_sn = request()->get('device_sn', '');
        $model = new  \app\index\model\MachineDevice();
        $image = $model->alias('d')
            ->join('machine_banner b', 'd.banner_id=b.id')
            ->where('d.device_sn', $device_sn)
            ->value('b.material_image');
        $images = $image ? explode(',', $image) : [];
        $device = $model->alias('d')
            ->join('operate_about b', 'd.uid=b.uid', 'left')
            ->where('d.device_sn', $device_sn)
            ->field('d.device_sn,d.device_name,b.phone')->find();
        return json(['code' => 200, 'data' => $images, 'device' => $device]);
    }

    //一商品多货道 购物车模式创建订单
    public function createOrder()
    {
        $post = request()->post();
        $device_sn = isset($post['device_sn']) ? $post['device_sn'] : '';
        $data = isset($post['data']) ? $post['data'] : [];
//        $data = [
//            [
//                'goods_id' => 1,//商品id
//                'count' => 2,//购买数量
//            ], [
//                'goods_id' => 3,//商品id
//                'count' => 2,//购买数量
//            ]
//        ];
        if (!$device_sn) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        if (empty($data)) {
            return json(['code' => 100, 'msg' => '请选择商品']);
        }
        if (empty($post['openid'])) {
            return json(['code' => 100, 'msg' => '未登录']);
        }
        $device = (new MachineDevice())->where('device_sn', $device_sn)->find();
        $machineGoodsModel = new MtDeviceGoodsModel();
        $orderGoods = [];
        $is_stock = 0;
        $goods_id = 0;
        $total_price = 0;
        $total_count = 0;
        foreach ($data as $k => $v) {
            $totalStock = $machineGoodsModel->where('device_id', $device['id'])->where('goods_id', $v['goods_id'])->sum('stock');
            if ($totalStock < $v['count']) {
                $is_stock = 1;
                $goods_id = $v['goods_id'];
                break;
            }
            $total_count += $v['count'];
            $machineGoods = $machineGoodsModel->where('device_id', $device['id'])->where('goods_id', $v['goods_id'])->field('price,num')->order('num asc')->find();
            $total_price = ($total_price * 100 + $machineGoods['price'] * 100 * $v['count']) / 100;
            $orderSingleGoods = $this->getOrderGoods($device['id'], $v['goods_id'], $machineGoods['price'], $v['count'], []);
            $orderGoods = array_merge($orderGoods, $orderSingleGoods);
        }
        if ($is_stock) {
            $title = (new MtGoodsModel())->where('id', $goods_id)->value('title');
            return json(['code' => 100, 'msg' => $title . " 库存不足"]);
        }
        if ($total_price < 0.01) {
            return json(['code' => 100, 'msg' => '付款金额必须大于0']);
        }
        $order_sn = time() . mt_rand(1000, 9999);
        $pay = new \app\medicine\controller\WxPay();
        $uid = (new MachineDevice())->where("device_sn", $device['device_sn'])->value("uid");
        $user = Db::name('system_admin')->where("id", $uid)->find();
        $data = [
            'order_sn' => $order_sn,
            'device_sn' => $device_sn,
            'uid' => $device['uid'],
//            'goods_id' => $goods['id'],
//            'route_number' => $goods['num'],
            'price' => $total_price,
            'count' => $total_count,
            'openid' => $post['openid'],
            'create_time' => time(),
        ];
        if ($user['is_wx_mchid'] == 1 && $user['wx_mchid_id']) {
            $data['pay_type'] = 3;
        } else {
            $data['pay_type'] = 1;
        }
        $order_id = Db::name('medicine_order')->insertGetId($data);
        $out_data = [];
        foreach ($orderGoods as $k => $v) {
            $orderGoods[$k]['order_id'] = $order_id;
            $out_data[] = ['num' => $v['num'], 'count' => $v['count']];
        }
        (new MedicineOrderGoodsModel())->saveAll($orderGoods);
        if ($user['is_wx_mchid'] == 1 && $user['wx_mchid_id']) {
            //代理商
            $key = $user['mchid']['key'];
            $notify_url = 'https://api.hnchaohai.com/medicine/Wxpay/agentNotify';
            $result = $pay->prepay($post['openid'], $order_sn, $total_price, $user, $notify_url);
        } else {
            //系统
            $key = 'wgduhzmxasi8ogjetftyio111imljs2j';
            $notify_url = 'https://api.hnchaohai.com/medicine/Wxpay/payNotify';
//            var_dump($total_fee);die();
            $result = $pay->prepay($post['openid'], $order_sn, $total_price, $user, $notify_url);
        }
        if ($result['return_code'] == 'SUCCESS') {
            $data = [
                'appId' => 'wx300f4ced661b5846',
                'timeStamp' => strval(time()),
                'nonceStr' => $pay->getNonceStr(),
                'signType' => 'MD5',
                'package' => "prepay_id=" . $result['prepay_id'],
                'paySign' => $pay->makeSign($data, $key),
                'order_sn' => $order_sn,
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'msg' => $result['return_msg']]);
        }
    }

    //$where不能用的id   $count剩余所需数量
    private function getOrderGoods($device_id, $goods_id, $price, $count, $where)
    {
        $item = [];
        $model = new MtDeviceGoodsModel();
        $goods = $model
            ->where('device_id', $device_id)
            ->where('goods_id', $goods_id)
            ->whereNotIn('id', $where)
            ->where('stock', '>', 0)
            ->order('num asc')
            ->field('id,stock,num')
            ->find();
        $device_sn = (new MachineDevice())->where('id', $device_id)->value('device_sn');
        if ($count > $goods['stock']) {
            $where[] = $goods['id'];
            $count = $count - $goods['stock'];
            $item[] = [
                'device_sn' => $device_sn,
                'num' => $goods['num'],
                'goods_id' => $goods_id,
                'price' => $price,
                'count' => $goods['stock'],
                'total_price' => $price * $goods['stock'],
            ];
            $data = $this->getOrderGoods($device_id, $goods_id, $price, $count, $where);
            $item = array_merge($item, $data);
            return $item;
        } else {
            $item[] = [
                'device_sn' => $device_sn,
                'num' => $goods['num'],
                'goods_id' => $goods_id,
                'price' => $price,
                'count' => $count,
                'total_price' => $price * $count,
            ];
            return $item;
        }
    }
}
