<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class AppletNodeModel extends TimeModel
{
    protected $name = 'system_applet_node';
    protected $deleteTime = false;
}