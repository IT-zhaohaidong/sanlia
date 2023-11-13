<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class AppRulesModel extends TimeModel
{
    protected $name = 'app_rules';
    protected $deleteTime = false;
}