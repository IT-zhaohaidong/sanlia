<?php


namespace app\index\controller;


use app\index\model\PackageModel;

class Package extends BaseController
{
    public function getList()
    {
        $list = (new PackageModel())->select();
        return json(['code' => 200, 'data' => $list]);
    }
}