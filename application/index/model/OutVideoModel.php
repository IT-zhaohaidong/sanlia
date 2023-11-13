<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class OutVideoModel extends TimeModel
{
    protected $name = 'out_video';
    protected $deleteTime = false;
}
