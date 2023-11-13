<?php

namespace app\applet\controller;

use app\index\model\OperateUserModel;
use app\index\model\ShopCartModel;
use app\index\model\ShopGoodsModel;
use think\Controller;

class ShopCart extends Controller
{
    //获取购物车列表
    public function getList()
    {
        $openid = request()->get('openid', '');
        $user = (new OperateUserModel())->where('openid', $openid)->find();
        if (!$user) {
            return json(['code' => 400, 'msg' => '未授权']);
        }
        $cartModel = new ShopCartModel();
        $list = $cartModel->alias('c')
            ->join('shop_goods g', 'g.id=c.goods_id', 'left')
            ->where('c.uid', $user['id'])
            ->order('c.id desc')
            ->field('c.*,g.image,g.title,g.stock,g.price,g.old_price,g.vip_price')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //加入购物车
    public function addCart()
    {
        $params = request()->get();
        if (empty($params['openid']) || empty($params['goods_id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $user = (new OperateUserModel())
            ->where('openid', $params['openid'])
            ->find();
        if (!$user) {
            return json(['code' => 400, 'msg' => '未登录']);
        }
        $cartModel = new ShopCartModel();
        $cart = $cartModel
            ->where('uid', $user['id'])
            ->where('goods_id', $params['goods_id'])
            ->find();
        $goods = (new ShopGoodsModel())->where('id', $params['goods_id'])->find();
        if ($goods['stock'] < 1) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }
        if ($cart) {
            if ($goods['stock'] < $cart['count'] + 1) {
                return json(['code' => 100, 'msg' => '库存不足']);
            }
            $cartModel->where('id', $cart['id'])->update(['count' => $cart['count'] + 1]);
        } else {
            $data = [
                'uid' => $user['id'],
                'goods_id' => $params['goods_id'],
                'count' => 1
            ];
            $cartModel->save($data);
        }
        return json(['code' => 200, 'msg' => '加入成功']);
    }

    //修改购物车数量
    public function changeCount()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['count'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $cartModel = new ShopCartModel();
        $cart = $cartModel
            ->where('id', $params['id'])
            ->find();
        $goods = (new ShopGoodsModel())
            ->where('id', $cart['goods_id'])
            ->find();
        if ($goods['stock'] < $params['count']) {
            return json(['code' => 100, 'msg' => '库存不足']);
        }
        $cartModel->where('id', $params['id'])->update(['count' => $params['count']]);
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function delCart()
    {
        $params = request()->get();
        if (empty($params['id'])) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $cartModel = new ShopCartModel();
        $cart = $cartModel
            ->where('id', $params['id'])
            ->delete();
        return json(['code' => 200, 'msg' => '成功']);
    }
}
