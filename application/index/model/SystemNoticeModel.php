<?php

namespace app\index\model;


use app\index\common\TimeModel;
use think\Db;

class SystemNoticeModel extends TimeModel
{
    protected $name = 'system_notice';
    protected $deleteTime = false;
}