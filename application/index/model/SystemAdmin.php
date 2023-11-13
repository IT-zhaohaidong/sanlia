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

class SystemAdmin extends TimeModel
{
    protected $deleteTime = 'delete_time';

    public function getList()
    {
        $rows = self::where('delete_time', null)->select();
        return $rows;
    }

    public function getOne($username)
    {
        $rows = self::alias('a')
            ->join('system_role r', 'a.role_id=r.id')
            ->where('a.delete_time', null)
            ->where('a.username|a.phone', $username)
            ->field('a.*,r.name')
            ->find();
        return $rows;
    }


    public function getId($id, $list)
    {
        $arr = [];
        foreach ($list as $k => $v) {
            if ($v['parent_id'] == $id) {
                $arr[] = $v['id'];
                $children = $this->getId($v['id'], $list);
                $arr = array_merge($arr, $children);
            }
        }
        return $arr;
    }

    public function getParents($id, $index)
    {
        $arr = [];
        $row = self::where('id', $id)->field('id,parent_id,username')->find();
        if ($index > 0) {
            $arr[] = $row;
        }
        $index++;
        if ($row['parent_id'] > 0) {
            $res = $this->getParents($row['parent_id'], $index);
            $arr = array_merge($arr, $res);
        }
        return $arr;
    }

    public function getSon($list, $pid)
    {
        $arr = [];
        foreach ($list as $k => $v) {
            if ($v['pid'] == $pid) {
                $arr[] = $v;
                $son_arr = $this->getSon($list, $v['id']);
                $arr = array_merge($son_arr, $arr);
            }
        }
        return $arr;
    }

    public function getAdminSonId($list, $pid)
    {
        $arr = [];
        foreach ($list as $k => $v) {
            if ($v['pid'] == $pid) {
                $arr[] = $v['id'];
                $son_arr = $this->getSon($list, $v['id']);
                $arr = array_merge($son_arr, $arr);
            }
        }
        return $arr;
    }

    /**
     * @param $list 列表
     * @param int $pid 父id
     * @param int $id 自己的id
     * @param int $did 自己的孩子的pid
     * @return array
     */
    public function tree($list, $pid = 0, $id = 0)
    {
        $item = [];
        foreach ($list as $k => $v) {
            if ($id > 0 && $id == $v['id']) {
                $children = $this->tree($list, $v['id']);
                if ($children) {
                    $v['children'] = $children;
                }
                $item[] = $v;
                break;
            }
            if ($v['parent_id'] == $pid) {
                $children = $this->tree($list, $v['id']);
                if ($children) {
                    $v['children'] = $children;
                }
                $item[] = $v;
            }
        }
        return $item;
    }
}