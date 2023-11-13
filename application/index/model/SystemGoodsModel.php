<?php

// +----------------------------------------------------------------------
// | EasyAdmin
// +----------------------------------------------------------------------
// | PHP交流群: 763822524
// +----------------------------------------------------------------------
// | 开源协议  https://mit-license.org
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zhongshaofa/EasyAdmin
// +----------------------------------------------------------------------

namespace app\index\model;


use app\index\common\TimeModel;

class SystemGoodsModel extends TimeModel
{
    protected $table = 'fs_system_goods';
    protected $deleteTime = 'delete_time';
}