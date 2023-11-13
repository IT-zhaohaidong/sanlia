<?php

namespace app\crontab\controller;


use app\index\model\ShopGoodsModel;
use app\index\model\ShopOrderGoodsModel;
use app\index\model\ShopOrderModel;
use think\Db;

class ShopOrder
{
    //清除超过30分钟的待支付订单
    public function clearOrder()
    {
        $order_ids = Db::name('shop_order')
            ->where('status', 0)
            ->where('create_time', '<', time() - 1800)
            ->column('id');
        $order_goods = (new ShopOrderGoodsModel())
            ->whereIn('order_id', $order_ids)
            ->select();
        $goods_ids = array_column($order_goods, 'goods_id');
        $goodsModel = new ShopGoodsModel();
        $goods = $goodsModel
            ->whereIn('id', $goods_ids)
            ->column('id,title,stock', 'id');
        //还库存
        foreach ($order_goods as $k => $v) {
            $goodsModel
                ->where('id', $v['goods_id'])
                ->update(['stock' => $goods[$v['goods_id']]['stock'] + $v['count']]);
        }
        //删订单
        Db::name('shop_order')
            ->where('status', 0)
            ->where('create_time', '<', time() - 1800)
            ->delete();
    }
}
