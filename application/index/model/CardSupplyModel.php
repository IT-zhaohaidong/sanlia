<?php


namespace app\index\model;


use app\index\common\TimeModel;
use traits\model\SoftDelete;

class CardSupplyModel extends TimeModel
{
    use SoftDelete;
    protected $name = 'card_supply';
    protected $deleteTime = false;
}