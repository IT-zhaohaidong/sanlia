<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class AppVersionModel extends TimeModel
{
    use SoftDelete;
    protected $name = 'app_version_info';
    protected $deleteTime = false;
}