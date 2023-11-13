<?php

namespace app\applet\controller;

use app\box\controller\ApiV2;
use app\index\common\Yuepai;
use app\index\controller\MallGoods;
use app\index\model\CommissionPlanModel;
use app\index\model\DeviceConfigModel;
use app\index\model\FinanceCash;
use app\index\model\FinanceOrder;
use app\index\model\MachineCardModel;
use app\index\model\MachineCart;
use app\index\model\MachineDevice;
use app\index\model\MachineDeviceErrorModel;
use app\index\model\MachineGoods;
use app\index\model\MachineOutLogModel;
use app\index\model\MachinePositionModel;
use app\index\model\MachineStockLogModel;
use app\index\model\MallGoodsModel;
use app\index\model\MchidModel;
use app\index\model\OperateUserModel;
use app\index\model\OrderGoods;
use app\index\model\ShopOperateCommissionModel;
use app\index\model\ShopOrderModel;
use app\index\model\ShopThirdCommissionModel;
use app\index\model\SystemAdmin;
use app\index\model\ThirdPlatformModel;
use think\Cache;
use think\Controller;
use think\Db;

class Goods extends Controller
{
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
            ->field('d.device_sn,d.device_name,b.phone')
            ->find();
        return json(['code' => 200, 'data' => $images, 'device' => $device]);
    }

    //获取商品列表
    public function getList()
    {
        $params = request()->get();
        $device_sn = request()->get('device_sn', '');
        if (empty($device_sn) || empty($params['port'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new \app\index\model\MachineDevice())->where('device_sn', $device_sn)->find();
        $where = [];
        if (!empty($params['port'])) {
            $port = [0, $params['port']];
            $where['g.port'] = ['in', $port];
        }
        $data = (new \app\index\model\MachineGoods())->alias("g")
            ->join("mall_goods s", "g.goods_id=s.id", "LEFT")
            ->field("g.id,g.goods_id,sum(g.stock) stock,g.price,g.active_price,s.image,s.detail,s.description,s.title,s.goods_code,s.qw_code,s.company_user_id")
            ->where("g.device_sn", $device_sn)
            ->where($where)
            ->where('num', '<=', $device['num'])
            ->where('g.goods_id', '>', 0)
            ->order('g.num asc')
            ->group('g.goods_id')
            ->select();
        foreach ($data as $k => $v) {
            if (!$v['goods_id']) {
                unset($data[$k]);
                continue;
            }
            $data[$k]['active_price'] = $v['active_price'] ? $v['active_price'] : 0;
        }
        return json(['code' => 200, 'data' => $data]);
    }

    //获取商品列表
    public function getAllList()
    {
        $params = request()->get();
        $device_sn = request()->get('device_sn', '');
        if (empty($device_sn) || empty($params['port'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $device = (new \app\index\model\MachineDevice())->where('device_sn', $device_sn)->find();
        $where = [];
        $data = (new \app\index\model\MachineGoods())->alias("g")
            ->join("mall_goods s", "g.goods_id=s.id", "LEFT")
            ->field("g.id,g.goods_id,sum(g.stock) stock,g.price,g.port,s.type,g.active_price,s.image,s.detail,s.description,s.title,s.goods_code,s.qw_code,s.company_user_id")
            ->where("g.device_sn", $device_sn)
            ->where($where)
            ->where('num', '<=', $device['num'])
            ->where('g.goods_id', '>', 0)
            ->order('g.num asc')
            ->group('g.goods_id')
            ->select();
        foreach ($data as $k => $v) {
            if (!$v['goods_id']) {
                unset($data[$k]);
                continue;
            }
            $data[$k]['active_price'] = $v['active_price'] ? $v['active_price'] : 0;
        }
        return json(['code' => 200, 'data' => $data]);
    }

    /**
     * 微信小程序端支付接口  一商品多货道
     */
    public function createOrder()
    {
        $post = $this->request->post();
        trace($post, '预支付参数');
//        $post['order_time'] = time();
        $post['status'] = 0;
        $order_sn = time() . mt_rand(1000, 9999);
        $post['order_sn'] = $order_sn;
        $device = (new MachineDevice())->where("device_sn", $post['device_sn'])->field("imei,supply_id,uid,status,is_lock")->find();
        (new OperateUserModel())->where('openid', $post['openid'])->update(['uid' => $device['uid']]);
//        if (in_array($device['uid'], [72, 90, 104])) {
//            $row = (new FinanceOrder())
//                ->where(['openid' => $post['openid'], 'device_sn' => $post['device_sn'], 'status' => 1])
//                ->find();
//            if ($row) {
//                return json(['code' => 100, 'msg' => '您的资格已用尽']);
//            }
//        }

        if ($device['supply_id'] == 3) {
            $bool = $this->device_status($post['device_sn']);
            if (!$bool) {
                return json(["code" => 100, "msg" => "设备不在线"]);
            }
        } else {
            if ($device['status'] != 1) {
                return json(['code' => 100, 'msg' => '设备不在线,请联系客服处理!']);
            }
        }

        if ($device['is_lock'] < 1) {
            return json(['code' => 100, 'msg' => '设备已禁用']);
        }

        //合并商品
        if (!isset($post['goods_id']) || $post['goods_id'] < 1) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $amount = (new MachineGoods())
            ->where(['device_sn' => $post['device_sn'], 'goods_id' => $post['goods_id']])
            ->where('err_lock', 0)
            ->group('goods_id')
            ->field('sum(stock) total_stock')->find();
        if ($amount['total_stock'] < 1) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }

        $goods = (new MachineGoods())
            ->where(['device_sn' => $post['device_sn'], 'goods_id' => $post['goods_id']])
            ->where('stock', '>', 0)
            ->find();
        $post['num'] = $goods['num'];
        unset($post['goods_id']);
        $post['price'] = $goods['active_price'] > 0 ? $goods['active_price'] : $goods['price'];
        if ($post['price'] <= 0) {
            return json(['code' => 100, 'msg' => '订单金额必须大于0']);
        }
        //判断是否有用户在购买
        $str = 'buying' . $post['device_sn'];
        $res = Cache::store('redis')->get($str);
        trace($res, '用户正在购买');
        if ($res == 1) {
            return json(['code' => 100, 'msg' => '有其他用户正在购买,请稍后重试']);
        } else {
            Cache::store('redis')->set($str, 1, 120);
        }
        $uid = (new MachineDevice())
            ->where("device_sn", $post['device_sn'])
            ->value("uid");
        //当设备所属人未开通商户号时,判断父亲代理商是否开通,若未开通,用系统支付,若开通,用父亲代理商商户号进行支付
        $parentId = [];
        $parentUser = (new SystemAdmin())->getParents($uid, 1);
        foreach ($parentUser as $k => $v) {
            $parentId[] = $v['id'];
        }
        $userList = (new SystemAdmin())->whereIn('id', $parentId)->select();
        $is_set_mchid = false;
        $wx_mchid_id = 0;
        foreach ($userList as $k => $v) {
            if ($v['is_wx_mchid'] == 1 && $v['wx_mchid_id']) {
                $is_set_mchid = true;
                $wx_mchid_id = $v['wx_mchid_id'];
                break;
            }
        }

        $user = Db::name('system_admin')->where("id", $uid)->find();
        $post['uid'] = $uid;
        $post['count'] = 1;
        $post['create_time'] = time();
        $mall_goods = (new MallGoodsModel())->where('id', $goods['goods_id'])->field('commission')->find();
        $post['commission'] = $mall_goods['commission'];
        if ($is_set_mchid) {
            $mchid = (new MchidModel())->where('id', $wx_mchid_id)->field('mchid,key')->find();
            $pay = new Wxpay();
            $notify_url = 'https://api.hnchaohai.com/applet/goods/user_notify';
            $user['mchid'] = $mchid;
            $user['is_wx_mchid'] = 1;
            $user['wx_mchid_id'] = true;
            $prepay_id = $pay->prepay($post['openid'], $order_sn, $post['price'], $user, $notify_url);
            trace($prepay_id, '预支付');
            $order_obj = new FinanceOrder();
            $num = $post['num'];
            unset($post['num']);
            $post['pay_type'] = 3;
            $order_id = $order_obj->insertGetId($post);
            //添加订单商品
            $goods_data = [
                'order_id' => $order_id,
                'order_sn' => $order_sn . 'order1',
                'device_sn' => $goods['device_sn'],
                'num' => $num,
                'goods_id' => $goods['goods_id'],
                'price' => $goods['price'],
                'count' => 1,
                'total_price' => $goods['price'],
            ];
            (new OrderGoods())->save($goods_data);
            //小程序调用微信支付配置
            $data['appId'] = 'wxfef945a30f78c17c';
            $data['timeStamp'] = strval(time());
            $data['nonceStr'] = $pay->getNonceStr();
            $data['signType'] = "MD5";
            $data['package'] = "prepay_id=" . $prepay_id['prepay_id'];
            $data['paySign'] = $pay->makeSign($data, $mchid['key']);
            $data['order_sn'] = $order_sn;
            echo json_encode($data, 256);
        } else {
            $pay = new Wxpay();
            $notify_url = 'https://api.hnchaohai.com/applet/goods/system_notify';
            $prepay_id = $pay->prepay($post['openid'], $order_sn, $post['price'], $user, $notify_url);
            $order_obj = new FinanceOrder();
            $num = $post['num'];
            unset($post['num']);
            $post['pay_type'] = 1;
            $order_id = $order_obj->insertGetId($post);
            //添加订单商品
            $goods_data = [
                'order_id' => $order_id,
                'order_sn' => $order_sn . 'order1',
                'device_sn' => $goods['device_sn'],
                'num' => $num,
                'goods_id' => $goods['goods_id'],
                'price' => $goods['price'],
                'count' => 1,
                'total_price' => $goods['price']
            ];
            (new OrderGoods())->save($goods_data);
            //小程序调用微信支付配置
            $data['appId'] = "wxfef945a30f78c17c";
            $data['timeStamp'] = strval(time());
            $data['nonceStr'] = $pay->getNonceStr();
            $data['signType'] = "MD5";
            $data['package'] = "prepay_id=" . $prepay_id['prepay_id'];
            $data['paySign'] = $pay->makeSign($data, 'Yxc15943579579Yxc15943579579Yxc1');
            $data['order_sn'] = $order_sn;
            echo json_encode($data, 256);
        }
    }

    //小程序openid绑定微信
    public function openidBindWx()
    {
        $params = request()->get();
        if (empty($params['goods_id']) || empty($params['openid']) || empty($params['external_user_id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $companyWxUid = (new MallGoodsModel())
            ->where('id', $params['goods_id'])
            ->value('company_user_id');
        if (!$companyWxUid) {
            return json(['code' => 100, 'msg' => '企业用户不存在']);
        }
        $friend = Db::name('openid_bind_wx')
            ->where(['openid' => $params['openid'], 'company_user_id' => $companyWxUid])
            ->find();
        if ($friend) {
            return json(['code' => 200, 'msg' => '已经是好友了']);
        }
        $data = [
            'openid' => $params['openid'],
            'external_user_id' => $params['external_user_id'],
            'company_user_id' => $companyWxUid,
            'create_time' => time()
        ];
        Db::name('openid_bind_wx')->insert($data);
        return json(['code' => 200, 'msg' => '添加成功']);
    }

    //企微商品,判断是否可以购买
    public function checkBuy()
    {
        $params = request()->get();
        if (empty($params['goods_id']) || empty($params['openid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        //判断位置
        $device = (new MachineDevice())
            ->where('device_sn', $params['device_sn'])
            ->field('position_id,device_sn')
            ->find();
        if ($device['position_id'] && !empty($params['lng']) && !empty($params['lat'])) {
//            $near = $this->Convert_GCJ02_To_BD09($params['lat'], $params['lng']);
//            $me_lat = explode(",", $near)[1];
//            $me_lng = explode(",", $near)[0];
            $me_lat = $params['lat'];
            $me_lng = $params['lng'];
            $position = (new MachinePositionModel())
                ->where('id', $device['position_id'])
                ->field('lat,lng')
                ->find();
            $distance = $this->distanceBetween($me_lat, $me_lng, $position['lat'], $position['lng']);
            trace($distance, '两点距离');
            if ($distance > 500) {
                return json(['code' => 100, 'msg' => '您已超出设备范围']);
            }
        }
        $goods = (new MallGoodsModel())->alias('g')
            ->join('brand b', 'b.id=g.brand_id', 'left')
            ->where('g.id', $params['goods_id'])
            ->field('g.*,b.status brand_status,b.num')
            ->find();
        if (!$goods) {
            return json(['code' => 100, 'msg' => '商品不存在']);
        }
        if ($goods['type'] == 1) {
            if (!$goods['company_user_id'] || !$goods['qw_code']) {
                return json(['code' => 100, 'msg' => '缺失企微信息']);
            }
            $friend = Db::name('openid_bind_wx')
                ->where(['openid' => $params['openid'], 'company_user_id' => $goods['company_user_id']])
                ->find();
            if (!$friend) {
                return json(['code' => 101, 'msg' => '未添加好友,若已添加,请点击小程序卡片']);
            }

            if ($goods['brand_status'] == 0) {
                $row = (new FinanceOrder())->alias('o')
                    ->join('order_goods og', 'o.id=og.order_id', 'left')
                    ->join('mall_goods g', 'og.goods_id=g.id', 'left')
                    ->where(['g.brand_id' => $goods['brand_id'], 'openid' => $params['openid']])
                    ->where('o.status', '>', 0)
                    ->find();
            } elseif ($goods['brand_status'] == 1) {
                //所有商品都可购买一次
                $row = (new FinanceOrder())->alias('o')
                    ->join('order_goods og', 'o.id=og.order_id', 'left')
                    ->where(['og.goods_id' => $goods['id'], 'openid' => $params['openid']])
                    ->where('o.status', '>', 0)
                    ->find();
            } else {
                return json(['code' => 100, 'msg' => '商品配置错误,请联系管理客服处理']);
            }
            if ($row) {
                return json(['code' => 100, 'msg' => '该品牌产品购买次数已用尽']);
            }

        } else {
            $user = (new OperateUserModel())->where('openid', $params['openid'])->field('phone')->find();
            $openids = (new OperateUserModel())->where('phone', $user['phone'])->column('openid');
            if ($goods['brand_status'] == 3 || $goods['brand_status'] == 4) {
                //单个品牌一个月最高只能买购2次
                //获取本月1号时间戳
                $month_time = strtotime(date('Y-m-1'));
                $count = (new FinanceOrder())->alias('o')
                    ->join('order_goods og', 'o.id=og.order_id', 'left')
                    ->join('mall_goods g', 'og.goods_id=g.id', 'left')
                    ->whereIn('g.brand_id', $goods['brand_id'])
                    ->whereIn('o.openid', $openids)
                    ->where('o.status', '=', 1)
                    ->where('o.create_time', '>', $month_time)
                    ->count();
                if ($count >= 2) {
                    return json(['code' => 100, 'msg' => '该品牌本月购买次数已用尽']);
                }
                if ($goods['brand_status'] == 4) {
                    //一台派样机内非派样类品牌商品合计只能购买5次
                    $count = (new FinanceOrder())->alias('o')
                        ->join('order_goods og', 'o.id=og.order_id', 'left')
                        ->join('mall_goods g', 'og.goods_id=g.id', 'left')
                        ->whereIn('o.openid', $openids)
                        ->where('o.device_sn', $params['device_sn'])
                        ->where('g.type', 0)
                        ->where('o.status', '=', 1)
                        ->where('o.create_time', '>', $month_time)
                        ->count();
                    if ($count >= 5) {
                        return json(['code' => 100, 'msg' => '该设备本月购买次数已用尽']);
                    }
                }

            } else {
                $num = $goods['brand_status'] == 2 ? $goods['num'] : 5;
                //获取本周一时间戳
                $monday = strtotime("last Sunday+1days");
                $count = (new FinanceOrder())->alias('o')
                    ->join('order_goods og', 'o.id=og.order_id', 'left')
                    ->join('mall_goods g', 'g.id=og.goods_id', 'left')
                    ->whereIn('o.openid', $openids)
                    ->where('g.brand_id', $goods['brand_id'])
                    ->where('o.status', '=', 1)
                    ->where('o.create_time', '>', $monday)
                    ->count();
                if ($count >= $num) {
                    return json(['code' => 100, 'msg' => '该品牌本周购买次数已用尽']);
                }
            }

        }
        return json(['code' => 200, 'msg' => '核验通过']);
    }

    /**
     * 百度地图BD09坐标---->中国正常GCJ02坐标
     * 腾讯地图用的也是GCJ02坐标
     * @param double $lat 纬度
     * @param double $lng 经度
     * @return String;
     */

    public function Convert_BD09_To_GCJ02($lng, $lat)
    {
        $x_pi = 3.14159265358979324 * 3000.0 / 180.0;
        $x = $lng - 0.0065;
        $y = $lat - 0.006;
        $z = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * $x_pi);
        $theta = atan2($y, $x) - 0.000003 * cos($x * $x_pi);
        $lng = $z * cos($theta);
        $lat = $z * sin($theta);
        return $lng . "," . $lat;
    }

    /**
     * 中国正常GCJ02坐标---->百度地图BD09坐标
     * 腾讯地图用的也是GCJ02坐标
     * @param double $lat 纬度
     * @param double $lng 经度
     * @return String
     */
    public function Convert_GCJ02_To_BD09($lat, $lng)
    {
        $x_pi = 3.14159265358979324 * 3000.0 / 180.0;
        $x = $lng;
        $y = $lat;
        $z = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $x_pi);
        $theta = atan2($y, $x) + 0.000003 * cos($x * $x_pi);
        $lng = $z * cos($theta) + 0.0065;
        $lat = $z * sin($theta) + 0.006;
        return $lng . "," . $lat;
    }


    /**
     * 计算两个坐标之间的距离(米)
     * @param float $fP1Lat 起点(纬度)
     * @param float $fP1Lon 起点(经度)
     * @param float $fP2Lat 终点(纬度)
     * @param float $fP2Lon 终点(经度)
     * @return int
     */
    function distanceBetween($fP1Lat, $fP1Lon, $fP2Lat, $fP2Lon)
    {
        $fEARTH_RADIUS = 6378137;
        //角度换算成弧度
        $fRadLon1 = deg2rad($fP1Lon);
        $fRadLon2 = deg2rad($fP2Lon);
        $fRadLat1 = deg2rad($fP1Lat);
        $fRadLat2 = deg2rad($fP2Lat);
        //计算经纬度的差值
        $fD1 = abs($fRadLat1 - $fRadLat2);
        $fD2 = abs($fRadLon1 - $fRadLon2);
        //距离计算
        $fP = pow(sin($fD1 / 2), 2) +
            cos($fRadLat1) * cos($fRadLat2) * pow(sin($fD2 / 2), 2);
        return intval($fEARTH_RADIUS * 2 * asin(sqrt($fP)) + 0.5);
    }

    //取消支付
    public function cancelPay()
    {
        $device_sn = request()->get('device_sn', '');
        if (!$device_sn) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $str = 'buying' . $device_sn;
        $res = Cache::store('redis')->get($str);
        if ($res == 1) {
            Cache::store('redis')->rm($str);
        }
        return json(['code' => 200, 'msg' => '取消成功']);
    }

    /**
     * 安卓端微信支付
     * @param 'order_sn'
     * @param 'device_sn'
     * @param 'openid'
     */
    public function getPay()
    {
        $post = $this->request->post();
        if (empty($post['device_sn']) || empty($post['order_sn'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        trace($post, '预支付参数');
//        $post['order_time'] = time();
        $order = (new FinanceOrder())->where('order_sn', $post['order_sn'])->field('uid,price')->find();
        $uid = $order['uid'];
        $user = Db::name('system_admin')->where("id", $uid)->find();
        //当设备所属人未开通商户号时,判断父亲代理商是否开通,若未开通,用系统支付,若开通,用父亲代理商商户号进行支付
        $parentId = [];
        $parentUser = (new SystemAdmin())->getParents($uid, 1);
        foreach ($parentUser as $k => $v) {
            $parentId[] = $v['id'];
        }
        $userList = (new SystemAdmin())->whereIn('id', $parentId)->select();
        $is_set_mchid = false;
        $mchid_uid = 0;
        $wx_mchid_id = 0;
        foreach ($userList as $k => $v) {
            if ($v['is_wx_mchid'] == 1 && $v['wx_mchid_id']) {
                $is_set_mchid = true;
                $mchid_uid = $v['id'];
                $wx_mchid_id = $v['wx_mchid_id'];
                break;
            }
        }
        $pay = new Wxpay();
        if ($is_set_mchid) {
            $pay_type = 3;
            $notify_url = 'https://api.hnchaohai.com/applet/goods/user_notify;';
            $mchid = (new MchidModel())->where('id', $wx_mchid_id)->field('mchid,key')->find();
        } else {
            $pay_type = 1;
            $notify_url = 'https://api.hnchaohai.com/applet/goods/system_notify';
            $mchid['key'] = '';
        }
        (new FinanceOrder())->where('order_sn', $post['order_sn'])->update(['pay_type' => $pay_type]);
        $prepay_id = $pay->prepay($post['openid'], $post['order_sn'], $order['price'], $user, $notify_url);
        var_dump($prepay_id);
        die();
        //小程序调用微信支付配置
        $data['appId'] = '';
        $data['timeStamp'] = strval(time());
        $data['nonceStr'] = $pay->getNonceStr();
        $data['signType'] = "MD5";
        $data['package'] = "prepay_id=" . $prepay_id['prepay_id'];
        $data['paySign'] = $pay->makeSign($data, $mchid['key']);
        $data['order_sn'] = $post['order_sn'];
        echo json_encode($data, 256);
    }

    //系统支付回调
    public function system_notify()
    {
        $xml = request()->getContent();
        trace($xml, '微信支付毁掉');
        //将服务器返回的XML数据转化为数组
        $data = (new Wxpay())->xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $result = $data;
        //获取服务器返回的数据
        $out_trade_no = $data['out_trade_no'];        //订单单号
        $openid = $data['openid'];                    //付款人openID
        $total_fee = $data['total_fee'];            //付款金额
        $transaction_id = $data['transaction_id'];    //微信支付流水号

        // 返回状态给微信服务器
        if ($result) {
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        } else {
            $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        //TODO 此时可以根据自己的业务逻辑 进行数据库更新操作
        $order_str = 'wx_return' . $out_trade_no;
        $res = Cache::store('redis')->get($order_str);
        //判断是否接收到回调,避免重复执行
        if (!$res) {
            Cache::store('redis')->set($order_str, 1, 100);
            $this->orderDeal($result, 1);
        }
        return $result;
    }

    //代理商支付回调
    public function user_notify()
    {
        $xml = request()->getContent();
        //将服务器返回的XML数据转化为数组
        $data = (new Wxpay())->xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
//        $sign = (new Wxpay())->makeSign($data);

        // 判断签名是否正确  判断支付状态
//        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS')) {
        $result = $data;
        //获取服务器返回的数据
        $out_trade_no = $data['out_trade_no'];        //订单单号
        $openid = $data['openid'];                    //付款人openID
        $total_fee = $data['total_fee'];            //付款金额
        $transaction_id = $data['transaction_id'];    //微信支付流水号
        //TODO 此时可以根据自己的业务逻辑 进行数据库更新操作

//        } else {
//            $result = false;
//        }
        // 返回状态给微信服务器
        if ($result) {
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        } else {
            $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        $order_str = 'wx_return' . $out_trade_no;
        $res = Cache::store('redis')->get($order_str);
        //判断是否接收到回调,避免重复执行
        if (!$res) {
            Cache::store('redis')->set($order_str, 1, 100);
            $this->orderDeal($result, 3);
        }
        return $result;
    }

    public function orderDeal($data, $type, $status = 1)
    {
        $orderModel = new FinanceOrder();
        $update_data = [
            'pay_time' => time(),
            'pay_type' => $type,
            'transaction_id' => $data['transaction_id'],
            'status' => $status
        ];
        $orderModel->where('order_sn', $data['out_trade_no'])->update($update_data);
        $order = $orderModel->where('order_sn', $data['out_trade_no'])->field('id,uid,device_sn,price,count,pay_type,order_type,openid,create_time,order_sn')->find();
        if ($order['order_type'] == 1) {
            //todo 雀客
            $goods_code = (new OrderGoods())->alias('og')
                ->join('mall_goods g', 'og.goods_id=g.id', 'left')->value('g.goods_code');
            $point = (new MachineDevice())->alias('d')
                ->join('machine_position p', 'd.position_id=p.id', 'left')
                ->value('p.position');
            $order_data = [
                'payTime' => date('Y-m-d H:i:s'),
                'orderTime' => $order['create_time'],
                'outOrderId' => $order['id'],
                'goodsCode' => $goods_code,
                'orderAmount' => $order['price'],
                'userId' => $order['openid'],
                'point' => $point ?? '无点位',
                'order_sn' => $order['order_sn']
            ];
            (new Yuepai())->callBack($order_data);
        }
        $device = (new MachineDevice())->where('device_sn', $order['device_sn'])->field('id,supply_id')->find();
        $device_id = $device['id'];
//        $ratio = Db::name('machine_commission')
//            ->where(['device_id' => $device_id])
//            ->select();
        $goods = (new OrderGoods())->alias('og')
            ->join('mall_goods g', 'g.id=og.goods_id', 'left')
            ->where('og.order_id', $order['id'])
            ->field('g.commission,g.type')
            ->find();
//        if ($goods['type'] == 0) {
//            $goods['commission'] = $order['price'];
//        }
        if ($goods['commission'] > 0) {
            $adminModel = new SystemAdmin();
            $uid = [];
            $userCommission = (new CommissionPlanModel())->getMoney($goods['commission'], $device_id);
            foreach ($userCommission as $k => $v) {
                $uid[] = $v['uid'];
            }
            switch ($type) {
                case 1:
                    //系统微信
                    $admin_balance = $adminModel->whereIn('id', $uid)->column('system_balance', 'id');
                    break;
                case 2:
                    //系统支付宝
                    $admin_balance = $adminModel->whereIn('id', $uid)->column('system_balance', 'id');
                    break;
                case 3:
                    //用户微信
                    $admin_balance = $adminModel->whereIn('id', $uid)->column('agent_wx_balance', 'id');
                    break;
                case 4:
                    //用户支付宝
                    $admin_balance = $adminModel->whereIn('id', $uid)->column('agent_ali_balance', 'id');
                    break;
            }
            $total_price = 0;
            $cash_data = [];
            $userMoneyList = array_column($userCommission, 'money', 'uid');
            foreach ($userCommission as $k => $v) {
//            $price = floor(($order['price'] * $v['ratio'] / 100) * 100) / 100;
                //补货员系统余额更新
                if ($userMoneyList[$v['uid']] > 0) {
                    $balance = $userMoneyList[$v['uid']] + $admin_balance[$v['uid']];
                    $update = [];
                    switch ($type) {
                        case 1:
                            //系统微信
                            $update['system_balance'] = $balance;
                            break;
                        case 2:
                            //系统支付宝
                            $update['system_balance'] = $balance;
                            break;
                        case 3:
                            //用户微信
                            $update['agent_wx_balance'] = $balance;
                            break;
                        case 4:
                            //用户支付宝
                            $update['agent_ali_balance'] = $balance;
                            break;
                    }
                    trace($update, '错误更新数据');
                    $adminModel->where('id', $v['uid'])->update($update);
                }

                //统计用户订单收益数据
                $cash_data[] = [
                    'uid' => $v['uid'],
                    'order_sn' => $data['out_trade_no'],
                    'price' => $userMoneyList[$v['uid']],
                    'type' => 1,
                ];
//            //下级人员总收益
//            $total_price += $price;
            }
//        if ($type == 1 || $type == 2) {
//            //代理商余额更新
//            $balance = $order['price'] - $total_price + $admin_balance[$order['uid']];
//            $adminModel->where('id', $order['uid'])->update(['system_balance' => $balance]);
//        }
//        $cash_data[] = [
//            'uid' => $order['uid'],
//            'order_sn' => $data['out_trade_no'],
//            'price' => $order['price'] - $total_price,
//            'type' => 1
//        ];
            (new FinanceCash())->saveAll($cash_data);
        }
        //出货
        $order_goods = (new OrderGoods())->where('order_id', $order['id'])->select();
        if ($device['supply_id'] == 2) {
            $orderModel->where('order_sn', $data['out_trade_no'])->update(['status' => 4]);
        } else {
            $index = 1;
            $machineGoodsModel = new MachineGoods();
            foreach ($order_goods as $k => $v) {
                $goods = (new MachineGoods())
                    ->where(['device_sn' => $v['device_sn'], 'num' => $v['num']])
                    ->field('stock,goods_id')->find();
                if ($device['supply_id'] == 1) {
                    //中转板子出货
//                    for ($i = 0; $i < $v['count']; $i++) {
//                        $order_no = $data['out_trade_no'] . 'order' . $index;
                    $res = $this->goodsOut($v['device_sn'], $v['num'], $v['order_sn'], $v['goods_id']);
                    if ($res['code'] == 1) {
                        $status = (new FinanceOrder())->where('order_sn', $data['out_trade_no'])->value('status');
                        if ($status == 0) {
                            (new FinanceOrder())->where('order_sn', $data['out_trade_no'])->update(['status' => 3]);
                        }
                    } else {
                        if ($order['pay_type'] == 6) {
                            (new OperateUserModel())->where('openid', $order['openid'])->update(['is_free_by_company' => 1]);
                        }

                    }
                    $this->addStockLog($v['device_sn'], $v['num'], $v['order_sn'], 1, $goods);
                    $index++;
//                    }
                } elseif ($device['supply_id'] == 3) {
                    $out_log = [];
                    //蜜连出货
//                    for ($i = 0; $i < $v['count']; $i++) {
//                        $order_no = $data['out_trade_no'] . 'order' . $index;
                    $res = $this->shipment($v['device_sn'], $v['num'], $v['order_sn']);
                    if ($res['errorCode'] != 0) {
                        $status = $res['errorCode'] == 65020 ? 2 : 3;
                        $out_log[] = ["device_sn" => $v['device_sn'], "num" => $v['num'], "order_sn" => $v['order_sn'], 'status' => $status];
                        (new FinanceOrder())->where('order_sn', $data['out_trade_no'])->update(['status' => 3]);
                    } else {
                        $row = $machineGoodsModel->where(["device_sn" => $v['device_sn'], "num" => $v['num']])->field('id,stock')->find();
                        $machineGoodsModel->where('id', $row['id'])->update(['stock' => $row['stock'] - 1]);
                        if ($order['pay_type'] == 6) {
                            (new OperateUserModel())->where('openid', $order['openid'])->update(['is_free_by_company' => 1]);
                        }
                        $out_log[] = ["device_sn" => $v['device_sn'], "num" => $v['num'], "order_sn" => $v['order_sn'], 'status' => 0];
                        $this->addStockLog($v['device_sn'], $v['num'], $v['order_sn'], 1, $goods);
                    }
//                        $index++;
//                    }
                    (new MachineOutLogModel())->saveAll($out_log);
                } else {

                }
//                if (isset($res) && $res['code'] == 1) {
//                    continue;
//                }
            }
        }


        if (!empty($data['openid'])) {
            $user = (new OperateUserModel())->where('openid', $data['openid'])->field('id,buy_num')->find();
            if ($user) {
                (new OperateUserModel())->where('id', $user['id'])->update(['buy_num' => $user['buy_num'] + 1]);
            } else {
                (new OperateUserModel())->save(['openid' => $data['openid'], 'buy_num' => 1]);
            }
        }
        //购买结束
        $str = 'buying' . $order['device_sn'];
        Cache::store('redis')->rm($str);
    }

    public function out($device, $order_sn)
    {
        $data['out_trade_no'] = $order_sn;
        $str = 'out_' . $order_sn;
        $order = (new FinanceOrder())->where('order_sn', $order_sn)->field('id,pay_type,idcard,device_sn')->find();
        if ($device['supply_id'] == 1 || $device['supply_id'] == 3) {
//            $order_no = $data['out_trade_no'] . 'order' . 0;
//            $outingStr = 'outing_' . $device['device_sn'];
//            Cache::store('redis')->set($outingStr, 1, 30);
//            $result = $this->goodsOut($device['device_sn'], $num, $order_no);
//            trace($result, '最终出货结果');
//            if ($result['code'] == 1) {
//                Cache::store('redis')->set($str, 2);
//                (new FinanceOrder())->where('order_sn', $data['out_trade_no'])->update(['status' => 3]);
//            } else {
//                $this->addStockLog($device['device_sn'], $num, $data['out_trade_no'], 1, $goods);
//            }
            $order_id = $order['id'];
            $order_goods = (new OrderGoods())->where('order_id', $order_id)->select();
            $index = 1;
            foreach ($order_goods as $k => $v) {
                $goods = (new MachineGoods())
                    ->where(['device_sn' => $v['device_sn'], 'num' => $v['num']])
                    ->field('stock,goods_id')->find();
                if ($device['supply_id'] == 1) {
                    //中转板子出货
                    for ($i = 0; $i < $v['count']; $i++) {
                        $order_no = $data['out_trade_no'] . 'order' . $index;
                        $res = $this->goodsOut($v['device_sn'], $v['num'], $order_no, $v['goods_id']);
                        if ($res['code'] == 1) {
                            $status = (new FinanceOrder())->where('order_sn', $data['out_trade_no'])->value('status');
                            if ($status == 0) {
                                (new FinanceOrder())->where('order_sn', $data['out_trade_no'])->update(['status' => 3]);
                            }
                        } else {
                            if ($order['pay_type'] == 5) {
                                (new MachineCardModel())->where('idcard', $order['idcard'])->dec('num', 1)->update();
                            }

                        }
                        $this->addStockLog($v['device_sn'], $v['num'], $order_no, 1, $goods);
                        $index++;
                    }
                } elseif ($device['supply_id'] == 3) {
                    $out_log = [];
                    $machineGoodsModel = new MachineGoods();
                    //蜜连出货
                    for ($i = 0; $i < $v['count']; $i++) {
                        $order_no = $data['out_trade_no'] . 'order' . $index;
                        $res = $this->shipment($v['device_sn'], $v['num'], $order_no);
                        if ($res['errorCode'] != 0) {
                            $status = $res['errorCode'] == 65020 ? 2 : 3;
                            $out_log[] = ["device_sn" => $v['device_sn'], "num" => $v['num'], "order_sn" => $order_no, 'status' => $status];
                            (new FinanceOrder())->where('order_sn', $data['out_trade_no'])->update(['status' => 3]);
                        } else {
                            $row = $machineGoodsModel->where(["device_sn" => $v['device_sn'], "num" => $v['num']])->field('id,stock')->find();
                            $machineGoodsModel->where('id', $row['id'])->update(['stock' => $row['stock'] - 1]);
                            $out_log[] = ["device_sn" => $v['device_sn'], "num" => $v['num'], "order_sn" => $order_no, 'status' => 0];
                            $this->addStockLog($v['device_sn'], $v['num'], $order_no, 1, $goods);
                        }
                        $index++;
                    }
                    (new MachineOutLogModel())->saveAll($out_log);
                }
//                if (isset($res) && $res['code'] == 1) {
//                    continue;
//                }
            }
        } elseif ($device['supply_id'] == 2) {

        } else {
            //其他供应商出货
        }
        //购买结束
        $str = 'buying' . $order['device_sn'];
        Cache::store('redis')->rm($str);
    }

    /**
     * 出货
     */
    public function Shipment($device_sn = "", $huodao = "", $order_sn = "")
    {
        $rand_str = rand(10000, 99999);
        $data = '{"cmd": 1000, "data": {"digital": ' . $huodao . ', "msg": "run", "count": 1, "quantity": 1, "done": 1}, "sn": "' . $device_sn . '", "nonceStr": "' . $rand_str . '"}';


        $post_data = array(
            'data' => $data
        );
        $res = $this->send_pos('http://mqtt.ibeelink.com/api/ext/tissue/pub-cmd', $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        if ($res['errorCode'] == 0) {
            return ['errorCode' => 0, 'msg' => '操作成功'];
        } else {
            $imei = (new MachineDevice())->where('device_sn', $device_sn)->value('imei');
            $data = [
                "imei" => $imei,
                "device_sn" => $device_sn,
                "num" => $huodao,
                "order_sn" => explode('order', $order_sn)[0],
                "status" => 1,
            ];
            (new MachineDeviceErrorModel())->save($data);
        }
        return $res;
    }

    public function send_pos($url, $post_data, $token)
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

    public function addStockLog($device_sn, $num, $order_sn, $count, $goods)
    {
        $data = [
            'device_sn' => $device_sn,
            'num' => $num,
            'goods_id' => $goods['goods_id'],
            'old_stock' => $goods['stock'],
            'new_stock' => $goods['stock'] - $count,
            'change_detail' => '用户下单,库存减少' . $count . '件;订单号:' . $order_sn,
        ];
        (new MachineStockLogModel())->save($data);
    }

    public function goodsOut($device_sn, $num, $order_sn, $goods_id)
    {
        $imei = (new MachineDevice())->where('device_sn', $device_sn)->value('imei');
        $data = [
            "imei" => $imei,
            "deviceNumber" => $device_sn,
            "laneNumber" => $num,
            "laneType" => 1,
            "paymentType" => 1,
            "orderNo" => $order_sn,
            "timestamp" => time()
        ];
        $str = $device_sn . '_ip';
        $ip = Cache::store('redis')->get($str);
        trace($ip, '板子ip');
//        if ($ip == '47.96.15.3') {
//            $url = 'http://47.96.15.3:8899/api/vending/goodsOut';
//        } else {
        $url = 'http://feishi.feishi.vip:9100/api/vending/goodsOut';
//        }
        $result = https_request($url, $data);
        $result = json_decode($result, true);
        $result['order_sn'] = $order_sn;
        trace($data, '出货参数');
        trace($result, '出货指令结果');
        if ($result['code'] == 200) {
            $res = $this->isBack($order_sn, 1);
            if (!$res) {
                //没有反馈业务处理
                $str = 'out_' . $order_sn;
                trace($str, '查询订单号');
                $res_a = Cache::store('redis')->get($str);
                if ($res_a == 2 || !$res) {
                    $outingStr = 'outing_' . $device_sn;
                    Cache::store('redis')->rm($outingStr);
                    $status = $res_a == 2 ? 1 : 5;
                    $data = [
                        "imei" => $imei,
                        "device_sn" => $device_sn,
                        "num" => $num,
                        "order_sn" => explode('order', $order_sn)[0],
                        'goods_id' => $goods_id,
                        "status" => $status,
                    ];
                    (new MachineDeviceErrorModel())->save($data);
                    trace(1111, '没有出货反馈');
                    if ($status == 5) {
                        $log = ["device_sn" => $device_sn, "num" => $num, "order_sn" => $data['order_sn'], 'status' => 5];
                        (new MachineOutLogModel())->save($log);
                    }
                    //由于没有反馈不扣库存,实际出货成功;会使用户购买实际货道为空,系统有库存的商品;使点击空转;故出货失败也扣库存
                    if (strstr($order_sn, "mt_")) {
                        Db::name('mt_device_goods')->where("num", $num)->where("device_sn", $device_sn)->dec("stock")->update();
                    } else {
                        Db::name('machine_goods')->where("num", $num)->where("device_sn", $device_sn)->dec("stock")->update();
                    }
                    return ['code' => 1, 'msg' => '失败'];
                }
            }
            if (strstr($order_sn, "mt_")) {
                Db::name('mt_device_goods')->where("num", $num)->where("device_sn", $device_sn)->dec("stock")->update();
            } else {
                Db::name('machine_goods')->where("num", $num)->where("device_sn", $device_sn)->dec("stock")->update();
            }
            //
            return ['code' => 0, 'msg' => '成功'];

        } else {
            $save_data = [
                'device_sn' => $device_sn,
                'imei' => $imei,
                'num' => $num,
                'order_sn' => explode('order', $order_sn)[0],
                'goods_id' => $goods_id,
                'status' => 3,
            ];
            (new MachineDeviceErrorModel())->save($save_data);
            $log = ["device_sn" => $device_sn, "num" => $num, "order_sn" => $save_data['order_sn'], 'status' => 2];
            (new MachineOutLogModel())->save($log);
            return ['code' => 1, 'msg' => '失败'];
        }

    }

    public function isBack($order, $num)
    {
        if ($num <= 17) {
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
     * 查看设备状态
     */
    public function device_status($sn = '')
    {

//        $sn="ILJXJI";
        $nonceStr = time();
        $url = "https://mqtt.ibeelink.com/api/ext/tissue/device/info";
        $data = '{
                "sn":"' . $sn . '","nonceStr": "' . $nonceStr . '"}';
        function send_post($url, $post_data, $token)
        {
            $postdata = http_build_query($post_data);
            $options = array(
                'http' =>
                    array(
                        'method' => 'GET',
                        'header' => array('token:' . $token, 'chan:bee-CSQYUS', 'Content-type:application/x-www-form-urlencoded'),
                        'content' => $postdata,
                        'timeout' => 15 * 60
                    )
            );
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            return $result;
        }

        $post_data = array(
            'data' => $data
        );
        $res = send_post($url, $post_data, md5($data . 'D902530082e570917F645F755AE17183'));
        $res_arr = json_decode($res, true);
        return $res_arr['data']['online'];

    }

    public function refund_notify()
    {
        $xml = request()->getContent();
        trace($xml, '微信支付毁掉');
        //将服务器返回的XML数据转化为数组
        $pay = new Wxpay();
        $data = $pay->xml2array($xml);
//        // 保存微信服务器返回的签名sign
//        $data_sign = $data['req_info'];
//        // sign不参与签名算法
//        unset($data['req_info']);
        $mchid_key = Cache::store('redis')->get('mchid_key');
        $key = $mchid_key ? $mchid_key : 'Yxc15943579579Yxc15943579579Yxc1';
        trace($key, '退款回调秘钥');
        $key = MD5($key);

        $res = $this->refund_decrypt($data['req_info'], $key);
        $res = $pay->xml2array($res);
        trace($res, '退款解密后数据');
//        $sign = $pay->makeSign($data);
//
//        // 判断签名是否正确  判断支付状态
//        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS')) {
        //获取服务器返回的数据
//            $out_trade_no = $res['out_trade_no'];        //订单单号
//            $out_refund_no = $res['out_refund_no'];        //退款单号
//            $total_fee = $res['total_fee'];            //订单金额
//            $refund_fee = $res['refund_fee'];            //退款金额
//            $transaction_id = $res['transaction_id'];    //微信支付流水号
//        } else {
//            $result = false;
//        }
        // 返回状态给微信服务器
        $str = ' < xml><return_code ><![CDATA[SUCCESS]] ></return_code ><return_msg ><![CDATA[OK]] ></return_msg ></xml > ';

        echo $str;
        $this->refundDeal($res);
        Cache::store('redis')->rm('mchid_key');
    }

    public function shopNotify()
    {
        $xml = request()->getContent();
        trace($xml, '微信支付毁掉');
        //将服务器返回的XML数据转化为数组
        $pay = new Wxpay();
        $data = $pay->xml2array($xml);
//        // 保存微信服务器返回的签名sign
//        $data_sign = $data['req_info'];
//        // sign不参与签名算法
//        unset($data['req_info']);
        $key = 'Yxc15943579579Yxc15943579579Yxc1';
        $key = MD5($key);
        $res = $this->refund_decrypt($data['req_info'], $key);
        $res = $pay->xml2array($res);
        trace($res, '退款解密后数据');
//        $sign = $pay->makeSign($data);
//        // 判断签名是否正确  判断支付状态
//        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS')) {
        //获取服务器返回的数据
//            $out_trade_no = $res['out_trade_no'];        //订单单号
//            $out_refund_no = $res['out_refund_no'];        //退款单号
//            $total_fee = $res['total_fee'];            //订单金额
//            $refund_fee = $res['refund_fee'];            //退款金额
//            $transaction_id = $res['transaction_id'];    //微信支付流水号
//        } else {
//            $result = false;
//        }
        // 返回状态给微信服务器
        $str = ' < xml><return_code ><![CDATA[SUCCESS]] ></return_code ><return_msg ><![CDATA[OK]] ></return_msg ></xml > ';

        echo $str;
        $orderModel = new ShopOrderModel();
        $order = $orderModel->where('order_sn', $res['out_trade_no'])->field('id,status,is_vip,openid,pay_type')->find();

        if ($order['status'] == 6) {
            return false;
        }
        $order_id = $order['id'];
        //修改订单状态
        $update_data = [
            'status' => 6,
        ];
        $orderModel->where('id', $order_id)->update($update_data);
        if ($order['is_vip'] == 1) {
            (new OperateUserModel())->where('openid', $order['openid'])->update(['is_vip' => 0]);
        }
        //返还分佣
        $thirdCommissionModel = new ShopThirdCommissionModel();
        $third = $thirdCommissionModel->where('order_sn', $res['out_trade_no'])->find();
        if ($third) {
            $log = [
                'uid' => $third['uid'],
                'money' => $third['money'],
                'type' => 2,
                'order_sn' => $res['out_trade_no'],
            ];
            $thirdCommissionModel->save($log);
            $thirdModel = new ThirdPlatformModel();
            $balance = $thirdModel->where('id', $third['uid'])->value('balance');
            $thirdModel->where('id', $third['uid'])->update(['balance' => $balance - $third['money']]);
        } else {
            $operateCommission = new ShopOperateCommissionModel();
            $commission = $operateCommission->where('order_sn', $res['out_trade_no'])->find();
            if ($commission) {
                $log = [
                    'uid' => $commission['uid'],
                    'money' => $commission['money'],
                    'type' => 1,
                    'order_sn' => $res['out_trade_no'],
                ];
                $operateCommission->save($log);
                $adminModel = new SystemAdmin();
                $balance = $adminModel->where('id', $commission['uid'])->value('shop_balance');
                $adminModel->where('id', $commission['uid'])->update(['shop_balance' => $balance - $commission['money']]);
            }
        }
    }

    public function refund_decrypt($str, $key)
    {

        $str = base64_decode($str);

        $str = openssl_decrypt($str, 'AES-256-ECB', $key, OPENSSL_RAW_DATA);

        return $str;
    }

    public function refundDeal($data)
    {
        if (!$data) {
            return false;
        }
        $orderModel = new \app\index\model\FinanceOrder();
        $order = $orderModel->where('order_sn', $data['out_trade_no'])->field('id,status,pay_type')->find();
        if ($order['status'] == 2) {
            return false;
        }
        $order_id = $order['id'];
        //修改订单状态
        $update_data = [
            'status' => 2,
            'refund_time' => time()
        ];
//        if ($reason) {
//            $update_data['refund_reason'] = $reason;
//        }
        $orderModel->where('id', $order_id)->update($update_data);
        $order_sn = $orderModel->where('id', $order_id)->value('order_sn');
        //修改代理商和分润人员余额
        $cashModel = new FinanceCash();
        $cash = $cashModel->where('order_sn', $order_sn)->select();
        $adminModel = new SystemAdmin();
        $uid = [];
        foreach ($cash as $k => $v) {
            $uid[] = $v['uid'];
        }
        if ($order['pay_type'] == 1 || $order['pay_type'] == 2) {
            $admin = $adminModel->whereIn('id', $uid)->column('system_balance', 'id');
        } elseif ($order['pay_type'] == 3) {
            $admin = $adminModel->whereIn('id', $uid)->column('agent_wx_balance', 'id');
        }
        $cash_data = [];
        foreach ($cash as $k => $v) {
            $money = $admin[$v['uid']] - $v['price'];
            if ($order['pay_type'] == 1 || $order['pay_type'] == 2) {
                $adminModel->where('id', $v['uid'])->update(['system_balance' => $money]);
            } elseif ($order['pay_type'] == 3) {
                $adminModel->where('id', $v['uid'])->update(['agent_wx_balance' => $money]);
            }
            $cash_data[] = [
                'uid' => $v['uid'],
                'order_sn' => $order_sn,
                'price' => 0 - $v['price'],
                'type' => 2
            ];
        }
        //添加余额修改记录
        $cashModel->saveAll($cash_data);
    }
}
