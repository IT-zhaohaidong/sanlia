<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class AppCateModel extends TimeModel
{
    protected $name = 'app_cate';
    protected $deleteTime = false;
}