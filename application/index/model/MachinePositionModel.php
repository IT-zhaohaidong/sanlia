<?php

namespace app\index\model;


use app\index\common\TimeModel;

class MachinePositionModel extends TimeModel
{
    protected $name = 'machine_position';
    protected $deleteTime = 'delete_time';
}