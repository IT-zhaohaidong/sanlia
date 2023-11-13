<?php

namespace app\index\model;


use app\index\common\TimeModel;
use think\Db;

class NoticeReadModel extends TimeModel
{
    protected $name = 'system_notice_read';
    protected $deleteTime = false;
}