<?php

namespace app\index\model;


use app\index\common\TimeModel;
use think\Db;

class SystemNode extends TimeModel
{
    public function __construct($data = [])
    {
        parent::__construct($data);
    }

    public function getList($type = 0)
    {
        $where = [];
        if ($type > 0) {
            $where['device_type'] = ['like', '%,' . $type . ',%'];
        }

        $rows = self::where('delete_time', null)
            ->where($where)
            ->order('pid asc')
            ->order('sort desc')
            ->select();
        foreach ($rows as $k => $v) {
            $rows[$k]['alwaysShow'] = $v['alwaysShow'] == 1 ? true : false;
            $rows[$k]['hidden'] = $v['hidden'] == 1 ? true : false;
        }
        return $rows;
    }

    public function tree($list, $pid = 0)
    {
        $item = [];
        foreach ($list as $k => $v) {
            if ($v['pid'] == $pid) {
                $children = $this->tree($list, $v['id']);
                if ($children) {
                    $v['children'] = $children;
                }
                $item[] = $v;
            }
        }
        return $item;
    }

    public function saveMenus($data, $rows = [], $pid = 0)
    {
        if (!$rows) {
            $rows = self::where('delete_time', null)->where('name', '<>', '')->column('id', 'name');
        }
        $update_data = [];
        foreach ($data as $k => $v) {
            $children = empty($v['children']) ? [] : $v['children'];
            unset($v['children']);
            unset($v['label']);
            $v['alwaysShow'] = empty($v['alwaysShow']) ? 0 : 1;
            $v['hidden'] = empty($v['hidden']) ? 0 : 1;
            if (isset($rows[$v['name']])) {
                $id = $rows[$v['name']];
                $v['pid'] = $pid;
                $v['id'] = $id;
                $update_data[] = $v;
            } else {
                $v['pid'] = $pid;
                $id = self::insertGetId($v);
            }
            if ($children) {
                $this->saveMenus($children, $rows, $id);
            } else {
                $row = self::where('pid', $id)->where('delete_time', null)->find();
                if (!$row) {
                    $idus = [
                        [
                            'alwaysShow' => 0,
                            'hidden' => 0,
                            'pid' => $id,
                            'permissionValue' => $v['name'] . '.list',
                            'title' => '查看',
                            'type' => 2,
                        ], [
                            'alwaysShow' => 0,
                            'hidden' => 0,
                            'pid' => $id,
                            'permissionValue' => $v['name'] . '.add',
                            'title' => '新增',
                            'type' => 2,
                        ], [
                            'alwaysShow' => 0,
                            'hidden' => 0,
                            'pid' => $id,
                            'permissionValue' => $v['name'] . '.edit',
                            'title' => '编辑',
                            'type' => 2,
                        ], [
                            'alwaysShow' => 0,
                            'hidden' => 0,
                            'pid' => $id,
                            'permissionValue' => $v['name'] . '.remove',
                            'title' => '删除',
                            'type' => 2,
                        ],
                    ];
                    self::saveAll($idus);
                }
            }
        }
        self::saveAll($update_data);
    }

    public function delMenus($data)
    {
        $name = $this->menusGetName($data);
        $rows = self::where('delete_time', null)->column('id', 'name');
        $ids = [];
        foreach ($rows as $k => $v) {
            if (!in_array($k, $name)) {
                $ids[] = $v;
            }
        }
        if ($ids) {
            self::whereIn('id', $ids)->update(['delete_time' => time()]);
        }
    }

    public function menusGetName($data)
    {
        $item = [];
        foreach ($data as $k => $v) {
            $item[] = $v['name'];
            if (!empty($v['children'])) {
                $name = $this->menusGetName($v['children']);
                $item = array_merge($item, $name);
            }
        }
        return $item;
    }

    public function getSonsId($data, $pid)
    {
        $item = [];
        foreach ($data as $k => $v) {
            if ($v['pid'] == $pid) {
                $item[] = $v['id'];
                $children = $this->getSonsId($data, $v['id']);
                $item = array_merge($item, $children);
            }
        }
        return $item;
    }

}