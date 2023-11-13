<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class BrandModel extends TimeModel
{
    protected $name = 'brand';
    protected $deleteTime = 'delete_time';
}
