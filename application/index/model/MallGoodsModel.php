<?php

namespace app\index\model;


use app\index\common\TimeModel;

class MallGoodsModel extends TimeModel
{
    protected $name = 'mall_goods';

    public function hasGoods($uid, $title)
    {
        $row = self::where(['uid' => $uid, 'title' => $title])->find();
        return $row ? false : true;
    }
}