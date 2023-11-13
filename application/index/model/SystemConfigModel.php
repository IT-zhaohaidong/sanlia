<?php

namespace app\index\model;


use app\index\common\TimeModel;
use think\Db;

class SystemConfigModel extends TimeModel
{
    protected $name = 'system_config';
    protected $deleteTime = false;
}