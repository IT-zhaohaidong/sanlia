<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class AppLogModel extends TimeModel
{
    protected $name = 'app_log';
    protected $deleteTime = false;
}
