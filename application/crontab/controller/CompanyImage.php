<?php

namespace app\crontab\controller;

use app\index\common\CompanyWX;
use app\index\common\Email;
use think\Cache;
use think\Db;
use think\Env;

class CompanyImage
{
    public function updateImage()
    {
        $rows = Db::name('company_wx')
            ->whereNotNull('media_id')
            ->where('media_id', '<>', '')
            ->select();
        $dirpath = $_SERVER['DOCUMENT_ROOT'] . '/';
        foreach ($rows as $k => $v) {
            if ($v['image']) {
                $path = str_replace(Env::get('server.server_name', ''), $dirpath, $v['image']);
                $companyWx = new CompanyWX($v['corId'], $v['secret']);
                $res = $companyWx->uploadImg($path);
                Db::name('company_wx')->where('id', $v['id'])->update(['media_id' => $res]);
            }
        }

    }
}