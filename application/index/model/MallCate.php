<?php

// +----------------------------------------------------------------------
// | EasyAdmin
// +----------------------------------------------------------------------
// | PHP交流群: 763822524
// +----------------------------------------------------------------------
// | 开源协议  https://mit-license.org
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zhongshaofa/EasyAdmin
// +----------------------------------------------------------------------

namespace app\index\model;


use app\index\common\TimeModel;

class MallCate extends TimeModel
{

    public function getList()
    {
        $rows = self::where('delete_time', null)->where('status', '<', 3)->order('pid asc')->select();
        return $rows;
    }

    public function getOne()
    {
        $rows = self::where('delete_time', null)->find();
        return $rows;
    }

    public function getName($list, $str)
    {
        $arr = explode(',', $str);
        $arr = array_filter($arr);
        $name = '';
        foreach ($arr as $k => $v) {
            if ($name) {
                $name .= '/';
            }
            if (isset($list[$v])){
                $name .= $list[$v];
            }
        }
        return $name;
    }

    /**
     * @param $list 列表
     * @param int $pid 父id
     * @param int $id  自己的id
     * @param int $did 自己的孩子的pid
     * @return array
     */
    public function tree($list, $pid = 0, $id = 0)
    {
        $item = [];
        foreach ($list as $k => $v) {
            if ($v['pid'] == $pid) {
                $v['disabled'] = $id ? ($v['value'] == $id ? true : false) : false;
                $children = $this->tree($list, $v['value'], $id);
                if ($children) {
                    $v['children'] = $children;
                }
                $item[] = $v;
            }
        }
        return $item;
    }

}