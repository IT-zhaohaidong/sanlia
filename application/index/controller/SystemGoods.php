<?php

namespace app\index\controller;

use app\index\model\AdverMaterialModel;
use app\index\model\BrandModel;
use app\index\model\CompanyWxModel;
use app\index\model\SystemGoodsModel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\Db;
use think\Env;
use function AlibabaCloud\Client\value;

class SystemGoods extends BaseController
{
    public function getList()
    {
        $params = request()->post();
        $user = $this->user;
        $page = request()->post('page', 1);
        $limit = request()->post('limit', 15);
        $where = [];
        $u_where = '';
        if ($user['role_id'] != 1) {
            $cate_ids = explode(',', $user['cate_ids']);
            if ($cate_ids) {
                foreach ($cate_ids as $k => $v) {
                    if ($u_where) {
                        $u_where .= ' OR ';
                    }
                    $u_where .= 'cate_ids like "%,' . $v . ',%"';
                }
            } else {
                return json(['code' => 200, 'data' => [], 'params' => $params, 'count' => 0]);
            }
        }

        if (!empty($params['title'])) {
            $where['title'] = ['like', '%' . $params['title'] . '%'];
        }
        if (!empty($params['cate_id'])) {
            $str = ',' . $params['cate_id'][count($params['cate_id']) - 1] . ',';
            $where['cate_ids'] = ['like', '%' . $str . '%'];
        }
        if (!empty($params['mark'])) {
            $where['mark'] = ['=', $params['mark']];
        }
        $count = Db::name('system_goods')->alias('sg')
            ->join('brand b', 'b.id=sg.brand_id', 'left')
            ->where($where)
            ->where($u_where)
            ->where('sg.delete_time', null)
            ->field('sg.*,b.name brand_name')
            ->count();

        $list = Db::name('system_goods')->alias('sg')
            ->join('brand b', 'b.id=sg.brand_id', 'left')
            ->where($where)
            ->where($u_where)
            ->where('sg.delete_time', null)
            ->field('sg.*,b.name brand_name')
            ->page($page)
            ->limit($limit)
            ->order('sg.id desc')
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
            if (in_array($user['id'], explode(',', $v['uid']))) {
                //已加入我的商品库
                $list[$k]['is_join'] = 1;
            } else {
                //未加入我的商品库
                $list[$k]['is_join'] = 0;
            }
        }
        return json(['code' => 200, 'data' => $list, 'params' => $params, 'count' => $count]);
    }

    //获取品牌列表
    public function getBrandList()
    {
        $model = new BrandModel();
        $list = $model
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //获取企微列表
    public function getQwList()
    {
        $model = new CompanyWxModel();
        $list = $model
            ->where('is_notify', 1)
            ->order('id desc')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }

    //获取企微用户列表
    public function getQwUserList()
    {
        $id = request()->get('id');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $company = (new CompanyWxModel())->where('id', $id)->find();
//        $company['secret'] = 'OEsJouXDJYE4wv3iDvxx5eeTBH-qhVBwK29lMzX2Eak';
        if (!$company) {
            return json(['code' => 100, 'msg' => '企业不存在']);
        }
        if (!$company['secret']) {
            return json(['code' => 100, 'msg' => '企业信息不完整']);
        }
        $companyWx = new \app\index\common\CompanyWX($company['corId'], $company['secret']);
        $res = $companyWx->getUser();
        if ($res['code'] == 100) {
            return json(['code' => 100, 'msg' => '用户获取失败']);
        }
        return json(['code' => 200, 'data' => $res['list']]);
    }

    //获取视频列表
    public function getVideoList()
    {
        $model = new AdverMaterialModel();
        $list = $model
            ->where('type', 2)
            ->order('id desc')
            ->field('id,url,name')
            ->select();
        return json(['code' => 200, 'data' => $list]);
    }


    public function save()
    {
        $data = request()->post();
        $user = $this->user;
        $model = new \app\index\model\SystemGoodsModel();
        $companyModel = new CompanyWxModel();
        $data['cate_ids'] = ',' . implode(',', $data['cate_ids']) . ',';
        if (empty($data['code']) || empty($data['title'])) {
            return json(['code' => 100, 'msg' => '请输入商品条形码和商品名称']);
        }
        $save_data = [
            'cate_ids' => $data['cate_ids'],
            'brand_id' => $data['brand_id'],
            'company_id' => $data['company_id'],
            'company_user_id' => $data['company_user_id'],
            'video_id' => $data['video_id'],
            'commission' => $data['commission'],
            'show_port' => $data['show_port'],
            'stock' => $data['stock'],
            'title' => $data['title'],
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
            $save_data['uid'] = $user['id'];
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
            $goods = $model->where('id', $data['id'])->find();
            if ($goods['company_id'] != $data['company_id'] || $goods['company_user_id'] != $data['company_user_id']) {
                $company = $companyModel->where('id', $data['company_id'])->find();
                if ($company) {
                    $companyWx = new \app\index\common\CompanyWX($company['corId'], $company['secret']);
                    if ($goods['config_id']) {
                        $companyWx->delCode($goods['config_id']);
                    }
                    $res = $companyWx->createCode($data['company_user_id'], $data['id']);
                    if ($res['code'] == 100) {
                        return json(['code' => 100, 'msg' => '二维码创建失败']);
                    }
                    $save_data['qw_code'] = $res['data']['qr_code'];
                    $save_data['config_id'] = $res['data']['config_id'];
                } else {
                    $save_data['qw_code'] = '';
                    $save_data['config_id'] = '';
                }
            }
            $model->where('id', $data['id'])->update($save_data);
        }
        return json(['code' => 200, 'msg' => '成功']);
    }

    public function joinMyGoods()
    {
        $user = $this->user;
        $ids = request()->post('ids/a');
        if (!$ids) {
            return json(['code' => 100, 'msg' => '请选择商品!']);
        }
        $model = new SystemGoodsModel();
        $list = $model->whereIn('id', $ids)->select();
        $insert_arr = [];
        foreach ($list as $k => $v) {
            $item = [
                'uid' => $user['id'],
                'cate_ids' => $v['cate_ids'],
                'brand_id' => $v['brand_id'],
                'company_id' => $v['company_id'],
                'company_user_id' => $v['company_user_id'],
                'video_id' => $v['video_id'],
                'code' => $v['code'],
                'commission' => $v['commission'],
                'show_port' => $v['show_port'],
                'stock' => $v['stock'],
                'title' => $v['title'],
                'image' => $v['image'],
                'description' => $v['description'],
                'detail' => $v['detail'],
                'price' => $v['price'],
                'active_price' => $v['active_price'],
                'remark' => $v['remark'],
                'create_time' => time(),
                'qw_code' => $v['qw_code'],
                'config_id' => $v['config_id'],
                'goods_code' => $v['goods_code'],
            ];
            $insert_arr[] = $item;
            $uid = $v['uid'] ? $v['uid'] . $user['id'] . ',' : ',' . $user['id'] . ',';
            $model->where('id', $v['id'])->update(['uid' => $uid]);
        }
        Db::name('mall_goods')->insertAll($insert_arr);
        return json(['code' => 200, 'msg' => '加入成功']);
    }

    public function getOne()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $data = Db::name('system_goods')->alias('g')
            ->where('id', $id)
            ->find();
        $data['cate_ids'] = $data['cate_ids'] ? explode(',', substr($data['cate_ids'], 1, -1)) : [];
        return json(['code' => 200, 'data' => $data]);
    }

    public function del()
    {
        $id = request()->get('id', '');
        if (!$id) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        Db::name('system_goods')->where('id', $id)->update(['delete_time' => time()]);
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
            $user = $this->user;
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
                $servername = Env::get('server.servername', 'http://api.hnchaohai.com');
                $companyModel = new CompanyWxModel();
                foreach ($data['data'] as $k => $v) {
                    if ($k == 0) {
                        if ($v[0] != '商品名称' || $v[1] != '描述' || $v[2] != '价格' || $v[3] != '活动价' || $v[4] != '商品图' || $v[5] != '详情图' || $v[6] != '品牌' || $v[7] != '企业名称' || $v[13] != '商品码') {
                            return json(['code' => 100, 'msg' => '请使用模板表格']);
                        }
                        continue;
                    }
                    $res = Db::name('system_goods')->where('delete_time', null)->where(['code' => $v[13]])->find();
                    if (!$res && $v[0] && $v[13]) {
                        $brand_id = Db::name('brand')->where('name', $v[6])->value('id') ?? '';
                        $company_id = Db::name('company_wx')->where('company_name', $v[7])->value('id') ?? '';
                        $arr[$k]['title'] = $v[0];
                        $arr[$k]['description'] = $v[1];
                        $arr[$k]['price'] = $v[2];
                        $arr[$k]['active_price'] = $v[3];
                        $arr[$k]['image'] = $v[4] ? $servername . explode('/public', $v[4])[1] : '';
                        $arr[$k]['detail'] = $v[5] ? $servername . explode('/public', $v[5])[1] : '';
                        $arr[$k]['brand_id'] = $brand_id;
                        $arr[$k]['cate_ids'] = $cate_ids;
                        $arr[$k]['company_id'] = $company_id;
                        $arr[$k]['company_user_id'] = $v[8];
                        $arr[$k]['commission'] = $v[9];
                        $arr[$k]['goods_code'] = $v[10];
                        $arr[$k]['stock'] = $v[11];
                        $arr[$k]['show_port'] = $v[12];
                        $arr[$k]['code'] = $v[13];
                        $arr[$k]['remark'] = $v[14];
                        $arr[$k]['create_time'] = time();
                        $company = $companyModel->where('id', $company_id)->find();

                        $id = Db::name('system_goods')->insertGetId($arr[$k]);
                        $update_data = [];
                        if ($company && $v[8]) {
                            $companyWx = new \app\index\common\CompanyWX($company['corId'], $company['secret']);
                            $res = $companyWx->createCode($v[8], $id);
                            if ($res['code'] == 200) {
                                $update_data['qw_code'] = $res['data']['qr_code'];
                                $update_data['config_id'] = $res['data']['config_id'];
                            } else {
                                $update_data['company_id'] = $company_id;
                                $update_data['company_user_id'] = $v[8];
                                $update_data['qw_code'] = '';
                                $update_data['config_id'] = '';
                            }
                        } else {
                            $update_data['company_id'] = $company_id;
                            $update_data['company_user_id'] = $v[8];
                            $update_data['qw_code'] = '';
                            $update_data['config_id'] = '';
                        }
                        Db::name('system_goods')->where('id', $id)->update($update_data);
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
