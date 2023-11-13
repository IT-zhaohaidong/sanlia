<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class CommissionPlanUserModel extends TimeModel
{
    protected $name = 'commission_plan_user';
    protected $deleteTime = 'delete_time';
}