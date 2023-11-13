<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class AdverMaterialModel extends TimeModel
{
    use SoftDelete;
    protected $name = 'adver_material';
    protected $deleteTime = 'delete_time';
}