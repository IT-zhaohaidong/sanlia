<?php

namespace app\applet\controller;

use app\index\model\FinanceOrder;
use app\index\model\OperateAddressModel;
use app\index\model\OperateTicketModel;
use app\index\model\OperateUserModel;
use app\index\model\OrderAddressModel;
use app\index\model\ShopBannerModel;
use app\index\model\ShopCartModel;
use app\index\model\ShopGoodsCateModel;
use app\index\model\ShopGoodsModel;
use app\index\model\ShopOperateCommissionModel;
use app\index\model\ShopOrderGoodsModel;
use app\index\model\ShopOrderModel;
use app\index\model\ShopSalesCateModel;
use app\index\model\ShopThirdCommissionModel;
use app\index\model\SystemAdmin;
use app\index\model\SystemConfigModel;
use app\index\model\ThirdPlatformModel;
use app\index\model\ThirdTicketModel;
use think\Cache;
use think\Controller;
use think\Env;

class ShopGoods extends Controller
{
    //获取商城banner图
    public function getBanner()
    {
        $model = new ShopBannerModel();
        $list = $model
            ->where('status', 1)
            ->order('sort desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //获取分类
    public function getCate()
    {
        $cateModel = new ShopGoodsCateModel();
        $salesCateModel = new ShopSalesCateModel();
        $cateList = $cateModel
            ->order('sort desc')
            ->field('id,title,image,sort')
            ->select();
        $salesCateList = $salesCateModel
            ->order('sort desc')
            ->field('id,title,sort')
            ->select();
        $data = compact('cateList', 'salesCateList');
        return json(['code' => 200, 'data' => $data]);
    }

    //首页获取商品
    public function getGoodsList()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 10 : $params['limit'];
        if (!empty($params['sales_id'])) {
            $sales_id = $params['sales_id'];
        } else {
            $salesCateModel = new ShopSalesCateModel();
            $salesCate = $salesCateModel
                ->order('sort desc')
                ->find();
            $sales_id = $salesCate ? $salesCate['id'] : '';
        }
        $model = new ShopGoodsModel();
        $count = $model
            ->where('sales_id', $sales_id)
            ->where('putaway', 1)
            ->count();
        $list = $model
            ->where('sales_id', $sales_id)
            ->where('putaway', 1)
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        foreach ($list as $k => $v) {
            if (!$v['vip_price'] || $v['vip_price'] == 0) {
                $list[$k]['vip_price'] = 0.00;
            }
            if (!$v['old_price'] || $v['vip_price'] == 0) {
                $list[$k]['old_price'] = 0.00;
            }
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //搜索商品
    public function getGoodsByCate()
    {
        $params = request()->get();
        $page = empty($params['page']) ? 1 : $params['page'];
        $limit = empty($params['limit']) ? 10 : $params['limit'];
        $where = [];
        if (!empty($params['cate_id'])) {
            $where['cate_id'] = ['=', $params['cate_id']];
        }
        if (!empty($params['keywords'])) {
            $where['title|description'] = ['like', '%' . $params['keywords'] . '%'];
        }
        $model = new ShopGoodsModel();
        $count = $model
            ->where('putaway', 1)
            ->where($where)
            ->count();
        $list = $model
            ->where('putaway', 1)
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order('id desc')
            ->select();
        foreach ($list as $k => $v) {
            if (!$v['vip_price'] || $v['vip_price'] == 0) {
                $list[$k]['vip_price'] = 0.00;
            }
            if (!$v['old_price'] || $v['vip_price'] == 0) {
                $list[$k]['old_price'] = 0.00;
            }
        }
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    //单商品创建订单
    public function createOrder()
    {
        $params = request()->get();
        if (empty($params['goods_id'] || $params['openid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = (new OperateUserModel())->where('openid', $params['openid'])->find();
        if (!$user) {
            return json(['code' => 100, 'msg' => '未授权']);
        }
        $goodsModel = new ShopGoodsModel();
        $goods = $goodsModel->where('id', $params['goods_id'])->find();
        if (!$goods) {
            return json(['code' => 100, 'msg' => '商品不存在']);
        }
        if ($goods['putaway'] == 0) {
            return json(['code' => 100, 'msg' => '商品已下架']);
        }
        if ($goods['stock'] < 1) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }
        if ($goods['cate_id'] == 7) {
            $title = (new ShopGoodsCateModel())->where('id', 7)->value('title');
            return json(['code' => 100, 'msg' => '该类商品不可单买,' . $title . '类商品满三件发货']);
        }
        $price = $user['is_vip'] == 0 ? $goods['price'] : $goods['vip_price'];
        $price = $price <= 0 ? 0.01 : $price;
        $order_sn = time() . rand(1000, 9999);
        $order_data = [
            'order_sn' => $order_sn,
            'price' => $price,
            'pay_money' => $price,
            'openid' => $params['openid'],
            'status' => 0,
            'create_time' => time()
        ];
        if ($goods['sales_id'] == 1 & $user['is_vip'] == 0) {
            $config = (new SystemConfigModel())->where('id', 1)->find();
            if ($price >= $config['vip_condition']) {
                $order_data['is_vip'] = 1;
            }
        }
        $orderModel = new ShopOrderModel();
        $order_id = $orderModel->insertGetId($order_data);
        $order_goods = [
            'order_id' => $order_id,
            'goods_id' => $goods['id'],
            'count' => 1,
            'price' => $price == 0 ? 0.01 : $price,
            'total_price' => $price
        ];
        $orderGoodsModel = new ShopOrderGoodsModel();
        $orderGoodsModel->save($order_goods);
        //减库存
        $goodsModel->where('id', $params['goods_id'])->update(['stock' => $goods['stock'] - 1]);
        return json(['code' => 200, 'msg' => '订单创建成功', 'data' => ['id' => $order_id]]);
    }

    //购物车创建订单
    public function cartCreateOrder()
    {
        $params = request()->get();
        if (empty($params['openid'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = (new OperateUserModel())->where('openid', $params['openid'])->field('id,is_vip')->find();
        $uid = $user['id'];
        if (!$uid) {
            return json(['code' => 400, 'msg' => '未授权']);
        }
        $cartModel = new ShopCartModel();
        $cart = $cartModel
            ->where('uid', $uid)
            ->select();
        if (!$cart) {
            return json(['code' => 100, 'msg' => '购物车为空']);
        }
        $goods_ids = array_column($cart, 'goods_id');
        $goods = (new ShopGoodsModel())->whereIn('id', $goods_ids)->column('id,price,vip_price,cate_id,sales_id,stock,putaway,title,image', 'id');
        $out_stock = 0;
        $putaway = 0;
        $total_price = 0.00;
        $limit_goods_count = 0;
        $become_vip_money = 0;
        foreach ($cart as $k => $v) {
            if ($v['count'] > $goods[$v['goods_id']]['stock']) {
                $out_stock = 1;
                break;
            }
            if ($goods[$v['goods_id']]['putaway'] == 0) {
                $putaway = 1;
                break;
            }
            $price = $user['is_vip'] == 0 ? $goods[$v['goods_id']]['price'] : $goods[$v['goods_id']]['vip_price'];
            $total_price += $price * $v['count'];
            if ($goods[$v['goods_id']]['cate_id'] == 7) {
                $limit_goods_count += $v['count'];
            }
            if ($user['is_vip'] == 0 && $goods[$v['goods_id']]['sales_id'] == 1) {
                $become_vip_money += $v['count'] * $goods[$v['goods_id']]['price'];
            }
        }
        if ($limit_goods_count > 0 && $limit_goods_count < 3) {
            $title = (new ShopGoodsCateModel())->where('id', 7)->value('title');
            return json(['code' => 100, 'msg' => $title . '类商品,满三件可购买']);
        }
        $total_price = round($total_price * 100) / 100 == 0 ? 0.01 : $total_price;
        if ($out_stock == 1) {
            return json(['code' => 100, 'msg' => '存在库存不足的商品']);
        }
        if ($putaway == 1) {
            return json(['code' => 100, 'msg' => '存在已下架的商品']);
        }
        $order_sn = time() . rand(1000, 9999);
        $order_data = [
            'order_sn' => $order_sn,
            'price' => $total_price,
            'pay_money' => $total_price,
            'openid' => $params['openid'],
            'status' => 0,
            'create_time' => time()
        ];
        if ($user['is_vip'] == 0) {
            $config = (new SystemConfigModel())->where('id', 1)->find();
            if ($become_vip_money >= $config['vip_condition']) {
                $order_data['is_vip'] = 1;
            }
        }
        $orderModel = new ShopOrderModel();
        $order_id = $orderModel->insertGetId($order_data);
        $order_goods = [];
        $shopGoodsModel = new ShopGoodsModel();
        foreach ($cart as $k => $v) {
            $price = $user['is_vip'] == 0 ? $goods[$v['goods_id']]['price'] : $goods[$v['goods_id']]['vip_price'];
            $order_goods[] = [
                'order_id' => $order_id,
                'goods_id' => $v['goods_id'],
                'count' => $v['count'],
                'price' => $price,
                'total_price' => $price * $v['count']
            ];
            $shopGoodsModel->where('id',$v['goods_id'])->update(['stock' => $goods[$v['goods_id']]['stock'] - $v['count']]);
        }

        $orderGoodsModel = new ShopOrderGoodsModel();
        $orderGoodsModel->saveAll($order_goods);
        //清除购物车数据
        $cartModel
            ->where('uid', $uid)
            ->delete();
        return json(['code' => 200, 'msg' => '订单创建成功', 'data' => ['id' => $order_id]]);
    }

    //获取可用券
    public function getTicketList()
    {
        $order_id = request()->get('id');
        $order = (new ShopOrderModel())->where('id', $order_id)->find();
        $list = (new OperateTicketModel())
            ->where('openid', $order['openid'])
            ->where('full', '<=', $order['price'])
            ->where('status', 0)
            ->order('reduce desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //获取订单信息
    public function getOrderDetail()
    {
        $order_id = request()->get('id', '');
        if (!$order_id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $orderMode = new ShopOrderModel();
        $order = $orderMode->where('id', $order_id)->find();
        if ($order['status'] > 0) {
            return json(['code' => 100, 'msg' => '该订单无需支付']);
        }
        $user = (new OperateUserModel())->where('openid', $order['openid'])->find();
        if ($user['is_vip'] == 1) {
            $goods = (new ShopOrderGoodsModel())->alias('og')
                ->join('shop_goods g', 'g.id=og.goods_id', 'left')
                ->where('og.order_id', $order_id)
                ->field('og.*,g.title,g.image')
                ->select();
            $goods_ids = array_column($goods, 'goods_id');
            $shop_goods = (new ShopGoodsModel())
                ->whereIn('id', $goods_ids)
                ->column('price,vip_price,title', 'id');
            $total_price = 0;
            foreach ($goods as $k => $v) {
                $total_price += $v['count'] * $shop_goods[$v['goods_id']]['vip_price'];
            }
            $total_price = round($total_price * 100) / 100;
            $total_price = $total_price == 0 ? 0.01 : $total_price;
            if ($total_price != $order['price']) {
                foreach ($goods as $k => $v) {
                    $price = round($v['count'] * $shop_goods[$v['goods_id']]['vip_price'] * 100) / 100;
                    (new ShopOrderGoodsModel())->where('id', $v['id'])->update(['price' => $shop_goods[$v['goods_id']]['vip_price'], 'total_price' => $price]);
                }
                $orderMode->where('id', $order_id)->update(['price' => $total_price, 'pay_money' => $total_price, 'ticket_id' => 0]);
            }

        }
        $addressModel = new OperateAddressModel();
        $address = $addressModel->where('openid', $order['openid'])->order('is_default desc')->find();
        $order['address'] = $address;
        $goods = (new ShopOrderGoodsModel())->alias('og')
            ->join('shop_goods g', 'g.id=og.goods_id', 'left')
            ->where('og.order_id', $order_id)
            ->field('og.*,g.title,g.image')
            ->select();

        $order['goods'] = $goods;
        return json(['code' => 200, 'data' => $order]);
    }

    //支付订单
    public function payOrder()
    {
        $params = request()->get();
        if (empty($params['address_id']) || empty($params['order_id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $orderMode = new ShopOrderModel();
        $order = $orderMode->where('id', $params['order_id'])->find();
        if ($order['status'] > 0) {
            return json(['code' => 100, 'msg' => '该订单无需支付']);
        }

        $order_goods = (new ShopOrderGoodsModel())->where('order_id', $params['order_id'])->select();
        $goods_ids = array_column($order_goods, 'goods_id');
        $goods = (new ShopGoodsModel())->whereIn('id', $goods_ids)->column('id,price,vip_price,price,stock,putaway', 'id');
        $out_stock = 0;
        $putaway = 0;
        foreach ($order_goods as $k => $v) {
            if ($v['count'] > $goods[$v['goods_id']]['stock']) {
                $out_stock = 1;
                break;
            }
            if ($goods[$v['goods_id']]['putaway'] == 0) {
                $putaway = 1;
                break;
            }
        }
        if ($out_stock == 1) {
            return json(['code' => 100, 'msg' => '存在库存不足的商品']);
        }
        if ($putaway == 1) {
            return json(['code' => 100, 'msg' => '存在已下架的商品']);
        }
        //为避免价格改变,导致微信方订单号重复,每次付款改变订单号
        $order_sn = time() . rand(1000, 9999);
        (new ShopOrderModel())->where('id', $order['id'])->update(['order_sn' => $order_sn]);
        $order['order_sn'] = $order_sn;
        $addressModel = new OperateAddressModel();
        $address = $addressModel->where('id', $params['address_id'])->find();
        $order_address = [
            'order_id' => $params['order_id'],
            'province' => $address['province'],
            'city' => $address['city'],
            'area' => $address['area'],
            'name' => $address['name'],
            'phone' => $address['phone'],
            'detail' => $address['detail'],
        ];
        $orderAddressModel = new OrderAddressModel();
        $row = $orderAddressModel->where('order_id', $params['order_id'])->find();
        if ($row) {
            $orderAddressModel->where('id', $row['id'])->update($order_address);
        } else {
            $orderAddressModel->save($order_address);
        }
        if (!empty($params['ticket_id'])) {
            $ticket = (new OperateTicketModel())->where('id', $params['ticket_id'])->find();
            if ($ticket['status'] > 0) {
                $orderMode->where('id', $params['order_id'])->update(['ticket_id' => 0, 'pay_money' => $order['price']]);
            } else {
                $price = round(($order['price'] - $ticket['reduce']) * 100) / 100;
                $order['price'] = $price <= 0 ? 0.01 : $price;
                $orderMode->where('id', $params['order_id'])->update(['ticket_id' => $ticket['id'], 'pay_money' => $order['price']]);
            }
        }
        $pay = new Wxpay();

        $notify_url = Env::get('server.server_name') . 'applet/shop_goods/notify';
        $user['is_wx_mchid'] = 0;
        $prepay_id = $pay->prepay($order['openid'], $order_sn, $order['price'], $user, $notify_url);
        $post['pay_type'] = 1;

        //小程序调用微信支付配置
        $data = [];
        $data['appId'] = "wxfef945a30f78c17c";
        $data['timeStamp'] = strval(time());
        $data['nonceStr'] = $pay->getNonceStr();
        $data['signType'] = "MD5";
        $data['package'] = "prepay_id=" . $prepay_id['prepay_id'];
        $data['paySign'] = $pay->makeSign($data, 'Yxc15943579579Yxc15943579579Yxc1');
        $data['order_sn'] = $order_sn;
        return json(['code' => 200, 'data' => $data, 'msg' => '成功']);
    }

    //是否弹出会员引导图
    public function isPop()
    {
        $order_id = request()->get('order_id');
        if (!$order_id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $is_vip = (new ShopOrderModel())->alias('o')
            ->join('operate_user u', 'u.openid=o.openid', 'left')
            ->value('u.is_vip');
        if ($is_vip == 1) {
            return json(['code' => 200, 'msg' => '已经是vip']);
        }
        $goods = (new ShopOrderGoodsModel())->alias('og')
            ->join('shop_goods g', 'g.id=og.goods_id', 'left')
            ->where('og.order_id', $order_id)
            ->field('og.*,g.title,g.image,g.price,g.sales_id')
            ->select();
        $limit_goods_money = 0;
        foreach ($goods as $k => $v) {
            if ($v['sales_id'] == 1) {
                $limit_goods_money += $v['count'] * $v['price'];
            }
        }
        $limit_goods_money = round($limit_goods_money * 100) / 100;
        $config = (new SystemConfigModel())->where('id', 1)->find();
        if ($limit_goods_money < $config['vip_condition']) {
            return json(['code' => 101, 'msg' => '此订单不可成为vip']);
        } else {
            return json(['code' => 200, 'msg' => '此订单可成为vip']);
        }
    }

    //支付回调
    public function notify()
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
            $orderModel = new ShopOrderModel();
            $orderModel->where('order_sn', $out_trade_no)->update(['status' => 2, 'pay_time' => time(), 'pay_type' => 1]);
            $order = (new ShopOrderModel())->where('order_sn', $out_trade_no)->field('ticket_id,openid,is_vip')->find();
            $userModel = new OperateUserModel();
            if ($order['is_vip'] == 1) {
                $data = [
                    'is_vip' => 1,
                    'vip_expire_time' => strtotime("+12 month", time())
                ];
                $userModel->where('openid', $order['openid'])->update($data);
            }
            //将券变为已使用状态
            if ($order['ticket_id']) {
                (new OperateTicketModel())->where('id', $order['ticket_id'])->update(['status' => 1, 'use_time' => time()]);
            }
            //分佣
            $user = $userModel->where('openid', $order['openid'])->find();
            if ($user['uid'] || $user['third_id']) {
                $list = $orderModel->alias('o')
                    ->join('shop_order_goods og', 'o.id=og.order_id', 'left')
                    ->join('shop_goods g', 'g.id=og.goods_id', 'left')
                    ->where('o.order_sn', $out_trade_no)
                    ->field('og.count,g.id,g.commission')
                    ->select();
                $total_commission = 0;
                foreach ($list as $k => $v) {
                    $total_commission += round($v['count'] * $v['commission'] * 100);
                }
                $total_commission = $total_commission / 100;
            }
            if ($user['uid']) {
                $log = [
                    'uid' => $user['uid'],
                    'money' => $total_commission,
                    'type' => 0,
                    'order_sn' => $out_trade_no,
                ];
                (new ShopOperateCommissionModel())->save($log);
                $adminModel = new SystemAdmin();
                $row = $adminModel->where('id', $user['uid'])->find();
                $adminModel->where('id', $user['uid'])->update(['shop_balance' => $row['shop_balance'] + $total_commission]);
            } elseif ($user['third_id']) {
                $log = [
                    'uid' => $user['third_id'],
                    'money' => $total_commission,
                    'type' => 0,
                    'order_sn' => $out_trade_no,
                ];
                (new ShopThirdCommissionModel())->save($log);
                $thirdModel = new ThirdPlatformModel();
                $row = $thirdModel->where('id', $user['third_id'])->find();
                $thirdModel->where('id', $user['third_id'])->update(['balance' => $row['balance'] + $total_commission]);
            }

        }
        return $str;
    }

    //支付宝支付订单
    public function aliPay()
    {
        $params = request()->get();
        if (empty($params['address_id']) || empty($params['order_id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $orderMode = new ShopOrderModel();
        $order = $orderMode->where('id', $params['order_id'])->find();
        if ($order['status'] > 0) {
            return json(['code' => 100, 'msg' => '该订单无需支付']);
        }

        $order_goods = (new ShopOrderGoodsModel())
            ->where('order_id', $params['order_id'])
            ->select();
        $goods_ids = array_column($order_goods, 'goods_id');
        $goods = (new ShopGoodsModel())->whereIn('id', $goods_ids)->column('id,price,stock,putaway', 'id');
        $out_stock = 0;
        $putaway = 0;

        $total_price = 0.00;
        foreach ($order_goods as $k => $v) {
            if ($order_goods['count'] > $goods[$v['goods_id']]['stock']) {
                $out_stock = 1;
                break;
            }
            if ($goods[$v['goods_id']]['putaway'] == 0) {
                $putaway = 1;
                break;
            }
            $total_price += $goods[$v['goods_id']]['price'] * $v['count'];
        }
        if ($out_stock == 1) {
            return json(['code' => 100, 'msg' => '存在库存不足的商品']);
        }
        if ($putaway == 1) {
            return json(['code' => 100, 'msg' => '存在已下架的商品']);
        }
        $addressModel = new OperateAddressModel();
        $address = $addressModel->where('id', $params['address_id'])->find();
        $order_address = [
            'order_id' => $params['order_id'],
            'province' => $address['province'],
            'city' => $address['city'],
            'area' => $address['area'],
            'name' => $address['name'],
            'phone' => $address['phone'],
            'detail' => $address['detail'],
        ];
        $orderAddressModel = new OrderAddressModel();
        $row = $orderAddressModel->where('order_id', $params['order_id'])->find();
        if ($row) {
            $orderAddressModel->where('id', $row['id'])->update($order_address);
        } else {
            $orderAddressModel->save($order_address);
        }

        $app_id = '2021004100643073';
        //应用私钥
        $privateKeyPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/应用私钥RSA2048.txt';
        $privateKey = file_get_contents($privateKeyPath);

        $aop = new \AopCertClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $app_id;
        $aop->rsaPrivateKey = $privateKey;
        //支付宝公钥证书
        $aop->alipayPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/cert/alipayCertPublicKey_RSA2.crt';

        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'UTF-8';
        $aop->format = 'json';
        //调用getCertSN获取证书序列号
        $appPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/appCertPublicKey_2021004100643073.crt";
        $aop->appCertSN = $aop->getCertSN($appPublicKey);
        //支付宝公钥证书地址
        $aliPublicKey = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";;
        $aop->alipayCertSN = $aop->getCertSN($aliPublicKey);
        //调用getRootCertSN获取支付宝根证书序列号
        $rootCert = dirname(dirname(dirname(dirname(__FILE__)))) . "/public/cert/alipayRootCert.crt";
        $aop->alipayRootCertSN = $aop->getRootCertSN($rootCert);

        $object = new \stdClass();
        $object->out_trade_no = $order['order_sn'];
        $object->total_amount = $order['price'];
        $object->subject = '潮嗨试用中心订单';
        $object->buyer_id = $order['openid'];
        $object->timeout_express = '10m';

        $json = json_encode($object);
        $request = new \AlipayTradeCreateRequest();
        $request->setNotifyUrl('http://api.hnchaohai.com/applet/shop_goods/aliNotify');
        $request->setBizContent($json);

        $result = $aop->execute($request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        trace($result, '支付宝预支付结果');
        if (!empty($resultCode) && $resultCode == 10000) {
            $data = [
                'order_sn' => $order['order_sn'],
                'orderno' => $result->$responseNode->trade_no,
                'total_amount' => $order['price']
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'msg' => $result->$responseNode->sub_msg]);
        }
    }

    public function aliNotify()
    {
        $params = request()->post();
        if (empty($params)) {
            echo 'error';
            exit;
        }
        trace($params, '支付宝支付回调');
        $data = [
            'out_trade_no' => $params['out_trade_no'],
            'total_fee' => $params['total_amount'],
            'transaction_id' => $params['trade_no'],
            'openid' => $params['buyer_id']
        ];
        $res = Cache::store('redis')->get('notify_' . $params['out_trade_no']);
        if (!$res) {
            Cache::store('redis')->set('notify_' . $params['out_trade_no'], 1, 300);
        } else {
            echo 'success';
            exit;
        }
        echo 'success';
        (new FinanceOrder())
            ->where('order_sn', $data['out_trade_no'])
            ->update(['status' => 2, 'pay_time' => time(), 'pay_type' => 2, 'transaction_id' => $data['transaction_id']]);
        exit;
    }

    //支付成功,随机获取一个三方优惠券
    public function getTicket()
    {
        $type = request()->get('type', 0);
        $area = request()->get('area', '');
        $area_code = '';
        if ($area) {
            $url = 'https://apis.map.qq.com/ws/district/v1/search?key=B4DBZ-2CM34-YFZUV-K6SHN-ODP36-NTFQJ&keyword=' . $area;
            $res = https_request($url);
            $data = json_decode($res, true);
            if ($data['status'] == 0 && $data['result']) {
                $area_code = $data['result'][0][0]['id'];
            }
        }
        $model = new ThirdTicketModel();
        $ids = $model
            ->where('type', $type)
            ->where('status', 1)
            ->where(function ($query) use ($area_code) {
                if ($area_code) {
                    $query->where('area', 'like', '%' . $area_code . '%')
                        ->whereOr('area_check', 0);
                } else {
                    $query->where('area_check', 0);
                }
            })
            ->column('id');
        $ticket = [];
        if ($ids) {
            $ids = array_values($ids);
            shuffle($ids);
            $id = $ids[0];
            $ticket = $model->where('id', $id)->find();
        }
        return json(['code' => 200, 'data' => $ticket]);
    }

    public function getArea()
    {
        $lng = request()->get('lng');//经度
        $lat = request()->get('lat');//维度
        if (!$lng || !$lat) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $location = $lat . ',' . $lng;
        $url = "https://apis.map.qq.com/ws/geocoder/v1/?location={$location}&key=B4DBZ-2CM34-YFZUV-K6SHN-ODP36-NTFQJ";
        $res = https_request($url);
        $data = json_decode($res, true);
        if (isset($data['status']) && $data['status'] == 0) {
            $area = $data['result']['address_component']['district'];
            return json(['code' => 200, 'data' => ['area' => $area]]);
        }
        return json(['code' => 100, 'msg' => '获取失败']);
    }
}
