<?php

namespace app\index\controller;

use app\index\common\Oss;
use app\index\model\CompanyWxModel;
use app\index\model\MallGoodsModel;
use app\index\model\SystemAdmin;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\Db;

class MallGoods extends BaseController
{
    public function getList()
    {
        $params = request()->post();
        $page = request()->post('page', 1);
        $limit = request()->post('limit', 15);
        $where = [];
        if (!empty($params['title'])) {
            $where['g.title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (isset($params['type']) && $params['type'] !== '') {
            $where['g.type'] = ['=', $params['type']];
        }
        if (!empty($params['cate_id'])) {
            $str = ',' . $params['cate_id'][count($params['cate_id']) - 1] . ',';
            $where['g.cate_ids'] = ['like', '%' . $str . '%'];
        }

        $count = Db::name('mall_goods')->alias('g')
            ->join('brand b', 'b.id=g.brand_id', 'left')
            ->where($where)
            ->where('g.delete_time', null)
            ->count();
        $list = Db::name('mall_goods')->alias('g')
            ->join('brand b', 'b.id=g.brand_id', 'left')
            ->where($where)
            ->where('g.delete_time', null)
            ->page($page)
            ->limit($limit)
            ->field('g.*,b.name brand_name')
            ->order('g.id desc')
            ->select();
        if ($list) {
            $cateModel = new \app\index\model\MallCate();
            $cateList = $cateModel
                ->where('status', 1)
                ->where('delete_time', null)
                ->column('name', 'id');
        } else {
            $list = [];
        }
        foreach ($list as $k => $v) {
            $list[$k]['cate_name'] = $cateModel->getName($cateList, $v['cate_ids']);
        }
        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }

    public function getAgentList()
    {
        $user = $this->user;
        $adminModel = new SystemAdmin();
        $agent = $adminModel
            ->where('delete_time', null)
            ->where('roleIds', 'like', '2,%')
            ->field('id,username,parent_id pid')
            ->select();
        if ($user['role_id'] == 1) {
            $user['id'] = 1;
        }
        $agent = $adminModel->getSon($agent, $user['id']);
        $data[] = ['id' => 0, 'username' => '全部'];
        if ($user['role_id'] == 1) {
            $data[] = ['id' => 1, 'username' => 'admin'];
        } else {
            $data[] = ['id' => $user['id'], 'username' => $user['username']];
        }
        $list = array_merge($data, $agent);
        return json(['code' => 200, 'data' => $list]);
    }

    public function save()
    {
        $data = request()->post();
        $model = new MallGoodsModel();
        $companyModel = new CompanyWxModel();
        $data['cate_ids'] = ',' . implode(',', $data['cate_ids']) . ',';
        if (empty($data['code']) || empty($data['title'])) {
            return json(['code' => 100, 'msg' => '请输入商品条形码和商品名称']);
        }
        $save_data = [
            'cate_ids' => $data['cate_ids'],
            'type' => $data['type'],
            'brand_id' => $data['brand_id'],
            'company_id' => $data['company_id'],
            'company_user_id' => $data['company_user_id'],
            'video_id' => $data['video_id'],
            'commission' => $data['commission'],
            'show_port' => $data['show_port'],
            'stock' => $data['stock'],
            'title' => trim($data['title']),
            'image' => $data['image'],
            'description' => $data['description'],
            'detail' => $data['detail'],
            'price' => $data['price'],
            'active_price' => $data['active_price'],
            'goods_code' => $data['goods_code'],
            'code' => $data['code'],
            'remark' => $data['remark']
        ];
        if (empty($data['id'])) {
            $save_data['create_time'] = time();
//            $save_data['uid'] = $user['id'];
            $row = $model->where('title', $save_data['title'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该商品名称已在商品库添加']);
            }
            $id = $model->insertGetId($save_data);
            if ($data['company_id'] && $data['company_user_id']) {
                $company = $companyModel->where('id', $data['company_id'])->find();
                if ($company) {
                    $companyWx = new \app\index\common\CompanyWX($company['corId'], $company['secret']);
                    $res = $companyWx->createCode($data['company_user_id'], $id);
                    if ($res['code'] == 100) {
                        return json(['code' => 100, 'msg' => '二维码创建失败']);
                    }
                    $update_data['qw_code'] = $res['data']['qr_code'];
                    $update_data['config_id'] = $res['data']['config_id'];

                }
            } else {
                $update_data['qw_code'] = '';
                $update_data['config_id'] = '';
                $update_data['company_id'] = '';
                $update_data['company_user_id'] = '';
            }
            $model->where('id', $id)->update($update_data);
        } else {
            $row = $model->where('id', '<>', $data['id'])->where('title', $save_data['title'])->find();
            if ($row) {
                return json(['code' => 100, 'msg' => '该商品名称已在商品库添加']);
            }
            $goods = $model->where('id', $data['id'])->find();
            if ($goods['company_id'] != $data['company_id'] || $goods['company_user_id'] != $data['company_user_id']) {
                $company = $companyModel->where('id', $goods['company_id'])->find();
                $companyWx = new \app\index\common\CompanyWX($company['corId'], $company['secret']);
                if ($goods['config_id']) {
                    $companyWx->delCode($goods['config_id']);
                }
                $company = $companyModel->where('id', $data['company_id'])->find();
                $companyWx = new \app\index\common\CompanyWX($company['corId'], $company['secret']);
                if ($company) {
                    if ($data['company_id'] && $data['company_user_id']) {
                        $res = $companyWx->createCode($data['company_user_id'], $data['id']);
                        if ($res['code'] == 100) {
                            return json(['code' => 100, 'msg' => $res['msg']]);
                        }
                        $save_data['qw_code'] = $res['data']['qr_code'];
                        $save_data['config_id'] = $res['data']['config_id'];
                    } else {
                        $save_data['qw_code'] = '';
                        $save_data['config_id'] = '';
                    }
                } else {
                    $save_data['qw_code'] = '';
                    $save_data['config_id'] = '';
                }
                //同步更新所有设备该商品的价格
                if ($goods['price'] != $save_data['price'] || $goods['active_price'] != $save_data['active_price']) {
                    (new \app\index\model\MachineGoods())
                        ->where('goods_id', $goods['id'])
                        ->update(['price' => $save_data['price'], 'active_price' => $save_data['active_price']]);
                }
            }
            $model->where('id', $data['id'])->update($save_data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function getOne()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = Db::name('mall_goods')->alias('g')
            ->join('system_admin a', 'g.uid=a.id', 'left')
            ->where('g.id', $id)
            ->field('g.*,a.username')
            ->find();
        $data['cate_ids'] = $data['cate_ids'] ? explode(',', substr($data['cate_ids'], 1, -1)) : [];
        return json(['code' => 200, 'data' => $data]);
    }

    public function changeMark()
    {
        $id = request()->get('id', '');
        $mark = request()->get('mark', '');
        if (!$id || $mark === '') {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        Db::name('mall_goods')->where('id', $id)->update(['mark' => $mark]);
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function del()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $row = (new \app\index\model\MachineGoods())->where('goods_id', $id)->find();
        if ($row) {
            return json(['code' => 100, 'msg' => '有设备正在售卖此商品,不可删除']);
        }
        Db::name('mall_goods')->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    public function import()
    {
        $cate_ids = request()->post('cate_ids/a');
        if (empty($cate_ids)) {
            return json(['code' => 100, 'msg' => '请选择分类']);
        }
        $cate_ids = ',' . implode(',', $cate_ids) . ',';
        if ($_FILES) {
            header("content-type:text/html;charset=utf8");//如果是接口,去掉
            date_default_timezone_set('PRC');

            $path = dirname(dirname(dirname(dirname(__FILE__))));
            $geshi = array("xls", "xlsx");
            if ($_FILES['file']["error"]) {
                $data = [
                    'code' => 100,
                    'msg' => '文件上传错误'
                ];
                return json($data);
            } else {
                $file_geshi = explode('.', $_FILES["file"]["name"]);
                $this_ge = array_pop($file_geshi);
                if (!in_array($this_ge, $geshi)) {
                    $data = [
                        'code' => 100,
                        'msg' => '文件格式不正确'
                    ];
                    return json($data);
                }
                $dirpath = $path . "/public/upload/excel/";
                if (!is_dir($dirpath)) {
                    mkdir($dirpath, 0777, true);
                }
                $filename = $path . "/public/upload/excel/" . time() . '.' . $this_ge;
                move_uploaded_file($_FILES["file"]["tmp_name"], $filename);
            }
            $file = $filename;//读取的excel文件
            $data = $this->importExcel($file);
            if ($data['code'] == 1) {
                unlink($filename);
                $data = [
                    'code' => 100,
                    'msg' => $data['msg']
                ];
                return json($data);
            }
            if ($data['data']) {
                $arr = [];
//                $servername = Env::get('server.servername', 'http://api.hnchaohai.com');
                $companyModel = new CompanyWxModel();
                $oss = new Oss();
                foreach ($data['data'] as $k => $v) {
                    if ($k == 0) {
                        if ($v[0] != '商品名称' || $v[2] != '描述' || $v[3] != '价格' || $v[4] != '活动价' || $v[5] != '商品图' || $v[6] != '详情图' || $v[7] != '品牌' || $v[8] != '企业名称' || $v[14] != '商品码') {
                            return json(['code' => 100, 'msg' => '请使用模板表格']);
                        }
                        continue;
                    }
                    $res = Db::name('mall_goods')->where('delete_time', null)->where(['code' => $v[14]])->find();
                    if (!$res && $v[0] && $v[14]) {
                        $brand_id = Db::name('brand')->where('name', $v[7])->value('id') ?? '';
                        $company_id = Db::name('company_wx')->where('company_name', $v[8])->value('id') ?? '';
                        $arr[$k]['title'] = $v[0];
                        $arr[$k]['type'] = $v[1];
                        $arr[$k]['description'] = $v[2];
                        $arr[$k]['price'] = $v[3];
                        $arr[$k]['active_price'] = $v[4];
                        $oss_image_path = $v[5] ? "material" . strrchr($v[5], "/") : '';
                        $oss_detail_path = $v[6] ? "material" . strrchr($v[6], "/") : '';
                        $arr[$k]['image'] = $v[5] ? ($oss->uploadToOss($oss_image_path, $v[5]) ?? '') : '';
                        $arr[$k]['detail'] = $v[6] ? ($oss->uploadToOss($oss_detail_path, $v[6]) ?? '') : '';
                        $arr[$k]['brand_id'] = $brand_id;
                        $arr[$k]['cate_ids'] = $cate_ids;
                        $arr[$k]['company_id'] = $company_id;
                        $arr[$k]['company_user_id'] = $v[9];
                        $arr[$k]['commission'] = $v[10];
                        $arr[$k]['goods_code'] = $v[11];
                        $arr[$k]['stock'] = $v[12];
                        $arr[$k]['show_port'] = $v[13];
                        $arr[$k]['code'] = $v[14];
                        $arr[$k]['remark'] = $v[15];
                        $arr[$k]['create_time'] = time();
                        $company = $companyModel->where('id', $company_id)->find();

                        $id = Db::name('mall_goods')->insertGetId($arr[$k]);
                        $update_data = [];
                        if ($company && $v[9]) {
                            $companyWx = new \app\index\common\CompanyWX($company['corId'], $company['secret']);
                            $res = $companyWx->createCode($v[9], $id);
                            if ($res['code'] == 200) {
                                $update_data['qw_code'] = $res['data']['qr_code'];
                                $update_data['config_id'] = $res['data']['config_id'];
                            } else {
                                $update_data['company_id'] = $company_id;
                                $update_data['company_user_id'] = $v[9];
                                $update_data['qw_code'] = '';
                                $update_data['config_id'] = '';
                            }
                        } else {
                            $update_data['company_id'] = $company_id;
                            $update_data['company_user_id'] = $v[9];
                            $update_data['qw_code'] = '';
                            $update_data['config_id'] = '';
                        }
                        Db::name('mall_goods')->where('id', $id)->update($update_data);
                    }
                }
                unlink($filename);
                $data = [
                    'code' => 200,
                    'msg' => '导入成功'
                ];
                return json($data);
            } else {
                unlink($filename);
                $data = [
                    'code' => 100,
                    'msg' => '导入失败,文件为空'
                ];
                return json($data);
            }
        } else {
            $data = [
                'code' => 100,
                'msg' => '请选择文件'
            ];
            return json($data);
        }


    }

    //解析Excel文件
    function importExcel($file = '', $sheet = 0, $columnCnt = 0, &$options = [])
    {
        /* 转码 */
//        $file = iconv("utf-8", "gb2312", $file);
        if (empty($file) or !file_exists($file)) {
            $res = [
                'code' => 1,
                'msg' => '文件不存在'
            ];
            return $res;
        }
//        include_once VENDOR_PATH . 'phpoffice/phpexcel/Classes/PHPExcel/IOFactory.php';
        $inputFileType = \PHPExcel_IOFactory::identify($file);
//        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
        $objReader = IOFactory::createReader('Xlsx');
        $objPHPExcel = $objReader->load($file);
        $sheet = $objPHPExcel->getSheet(0);
        $data = $sheet->toArray(); //该方法读取不到图片，图片需单独处理

        $path = dirname(dirname(dirname(dirname(__FILE__))));
        $name = "/public/upload/" . date('Ymd') . '/goods_image/';
        $imageFilePath = $path . $name;
        if (!file_exists($imageFilePath)) { //如果目录不存在则递归创建
            mkdir($imageFilePath, 0777, true);
        }
        foreach ($sheet->getDrawingCollection() as $drawing) {
            list($startColumn, $startRow) = Coordinate::coordinateFromString($drawing->getCoordinates());
            $imageFileName = $drawing->getCoordinates() . time() . mt_rand(1000, 9999);
//            if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing) {
            switch ($drawing->getExtension()) {
                case 'jpg':
                case 'jpeg':
                    $imageFileName .= '.jpg';
                    $source = imagecreatefromjpeg($drawing->getPath());
                    imagejpeg($source, $imageFilePath . $imageFileName);
                    break;
                case 'gif':
                    $imageFileName .= '.gif';
                    $source = imagecreatefromgif($drawing->getPath());
                    imagegif($source, $imageFilePath . $imageFileName);
                    break;
                case 'png':
                    $imageFileName .= '.png';
                    $source = imagecreatefrompng($drawing->getPath());
                    imagepng($source, $imageFilePath . $imageFileName);
                    break;
            }
//            } else {
//                $zipReader = fopen($drawing->getPath(), 'r');
//                $imageContents = '';
//                while (!feof($zipReader)) {
//                    $imageContents .= fread($zipReader, 2048);
//                }
//                fclose($zipReader);
//                $imageFileName .= $drawing->getExtension();
//            }
//            ob_start();
//            call_user_func(
//                $drawing->getRenderingFunction(),
//                $drawing->getImageResource()
//            );
//            $imageContents = ob_get_contents();
//            file_put_contents($imageFilePath . $imageFileName, $imageContents); //把图片保存到本地（上方自定义的路径）
//            ob_end_clean();

            $startColumn = $this->ABC2decimal($startColumn);
            $data[$startRow - 1][$startColumn] = $imageFilePath . $imageFileName;
        }
        $res = [
            'code' => 0,
            'data' => $data
        ];
        return $res;

    }

    public function ABC2decimal($abc)
    {
        $ten = 0;
        $len = strlen($abc);
        for ($i = 1; $i <= $len; $i++) {
            $char = substr($abc, 0 - $i, 1);//反向获取单个字符
            $int = ord($char);
            $ten += ($int - 65) * pow(26, $i - 1);
        }
        return $ten;
    }
}
