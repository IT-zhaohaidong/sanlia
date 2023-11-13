<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

use think\Env;

if (!function_exists('password')) {

    /**
     * 密码加密算法
     * @param $value 需要加密的值
     * @param $type  加密类型，默认为md5 （md5, hash）
     * @return mixed
     */
    function password($value)
    {
        $value = sha1('blog_') . md5($value) . md5('_encrypt') . sha1($value);
        return sha1($value);
    }

}

if (!function_exists('getRand')) {

    /**
     * 随机字符串
     * @param $length 需要的字符串長度
     * @return mixed
     */
    // 32为加密
    function getRand($length = 32)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;

        for ($i = 0; $i < $length; $i++) {
            $str .= $strPol[rand(0, $max)]; //rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }

        return $str;
    }

}

if (!function_exists('qrcode')) {

    /**
     * 随机字符串
     * @param $length 需要的字符串長度
     * @return mixed
     */
    // 32为加密
    /**
     * 生成二维码
     */
    function qrcode($msg, $type = '', $order_sn = '', $uid = '')
    {
        $res_msg = "device=" . $msg . '&type=' . $type;
        if ($order_sn) {
            $res_msg = "device=" . $msg . '&order_sn=' . $order_sn;
        }
        if ($uid) {
            $res_msg = "uid=" . $uid;
        }
        $url = \think\Env::get('SERVER.SERVER_NAME');
        // 1. 生成原始的二维码(生成图片文件)=
        require_once $_SERVER['DOCUMENT_ROOT'] . '/static/phpqrcode.php';
        $value = $url . "public?" . $res_msg;;         //二维码内容
        $errorCorrectionLevel = 'L';  //容错级别
        $matrixPointSize = 10;      //生成图片大小
        //生成二维码图片
        $filename = $_SERVER['DOCUMENT_ROOT'] . '/upload/device_code/' . time() . '.png';
        $time = time() . rand(0, 9);
        $filename1 = $_SERVER['DOCUMENT_ROOT'] . '/upload/device_code/' . $time . '.png';
        \QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 4);

        $QR = $filename;        //已经生成的原始二维码图片文件
        $QR = imagecreatefromstring(file_get_contents($QR));
//        ob_start();
//
//        $ob_contents = ob_get_contents(); //读取缓存区数据
//        ob_end_clean();
//
//        $myImage = ImageCreate(245, 245); //参数为宽度和高度
//        $qr = imagecreatefromstring($ob_contents);
//        $logo = 'logo.png'; //需要显示在二维码中的Logo图像
//        $logo = imagecreatefromstring(file_get_contents($logo));
//        $qr_width = imagesx($qr);
//        $qr_height = imagesy($qr);
//        $logo_width = imagesx($logo);
//        $logo_height = imagesy($logo);
//        $logo_qr_width = $qr_width / 5;
//        $scale = $logo_width / $logo_qr_width;
//        $logo_qr_height = $logo_height / $scale;
//        $from_width = ($qr_width - $logo_qr_width) / 2;
//        imagecopyresampled($qr, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);


        //输出图片
        imagepng($QR, $filename);
        imagedestroy($QR);

        if ($order_sn) {
            $msg = '';
        }
        $fontPath = $_SERVER['DOCUMENT_ROOT'] . "/static/plugs/font-awesome-4.7.0/fonts/simkai.ttf";
        $obj = addFontToPic($filename, $fontPath, 18, $msg, 360, $filename1);
        return 'http://' . $_SERVER['SERVER_NAME'] . '/upload/device_code/' . $time . '.png';
    }

}
if (!function_exists('qrcode1')) {

    /**
     * 随机字符串
     * @param $length 需要的字符串長度
     * @return mixed
     */
    // 32为加密
    /**
     * 生成二维码
     */
    function qrcode1($msg, $type = '', $order_sn = '', $uid = '')
    {
        $res_msg = "device=" . $msg . '&type=' . $type;
        if ($order_sn) {
            $res_msg = "device=" . $msg . '&order_sn=' . $order_sn;
        }
        if ($uid) {
            $res_msg = "uid=" . $uid;
        }
        $url = \think\Env::get('SERVER.SERVER_NAME');
        // 1. 生成原始的二维码(生成图片文件)=
        require_once $_SERVER['DOCUMENT_ROOT'] . '/static/phpqrcode.php';
        $value = $url . "public?" . $res_msg;;         //二维码内容
        $errorCorrectionLevel = 'L';  //容错级别
        $matrixPointSize = 10;      //生成图片大小
        //生成二维码图片
        $filename = $_SERVER['DOCUMENT_ROOT'] . '/upload/device_code/' . time() . '.png';
        $time = time() . rand(0, 9);
        $filename1 = $_SERVER['DOCUMENT_ROOT'] . '/upload/device_code/' . $time . '.png';

        $QR = $filename;        //已经生成的原始二维码图片文件
//        $QR = imagecreatefromstring(file_get_contents($QR));
//        ob_start();
        \QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 4);
//
//        $ob_contents = ob_get_contents(); //读取缓存区数据
//        ob_end_clean();

        $myImage = ImageCreate(245, 125); //参数为宽度和高度
        $QR = imagecreatefromstring(file_get_contents($filename));
        $logo = 'https://pic56.photophoto.cn/20200821/0007020121978505_b.jpg'; //需要显示在二维码中的Logo图像
        $logo = imagecreatefromstring(file_get_contents($logo));
        $qr_width = imagesx($QR);
        $qr_height = imagesy($QR);
        $logo_width = imagesx($logo);
        $logo_height = imagesy($logo);
        $logo_qr_width = $qr_width / 5;
        $scale = $logo_width / $logo_qr_width;
        $logo_qr_height = $logo_height / $scale;
        $from_width = ($qr_width - $logo_qr_width) / 2;
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);


        //输出图片
        imagepng($QR, $filename);
        imagedestroy($QR);

        if ($order_sn) {
            $msg = '';
        }
        $fontPath = $_SERVER['DOCUMENT_ROOT'] . "/static/plugs/font-awesome-4.7.0/fonts/simkai.ttf";
        $obj = addFontToPic($filename, $fontPath, 100, $msg, 360, $filename1);
        return 'http://' . $_SERVER['SERVER_NAME'] . '/upload/device_code/' . $time . '.png';
    }

}

/**
 * 生成售药机二维码
 * type 0:售药机二维码 1:骑手码
 */
function medicineQrCode($msg, $type = 0)
{
    $res_msg = "device=" . $msg . '&type=' . $type;
    $url = \think\Env::get('SERVER.SERVER_NAME');
    // 1. 生成原始的二维码(生成图片文件)=
    require_once $_SERVER['DOCUMENT_ROOT'] . '/static/phpqrcode.php';
    $value = $url . "medicine?" . $res_msg;;         //二维码内容
    $errorCorrectionLevel = 'L';  //容错级别
    $matrixPointSize = 10;      //生成图片大小
    //生成二维码图片
    $filename = $_SERVER['DOCUMENT_ROOT'] . '/upload/device_code/' . time() . '.png';
    $time = time() . rand(100, 999);
    $filename1 = $_SERVER['DOCUMENT_ROOT'] . '/upload/device_code/' . $time . '.png';
    \QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 4);

    $QR = $filename;        //已经生成的原始二维码图片文件
    $QR = imagecreatefromstring(file_get_contents($QR));
    //输出图片
    imagepng($QR, $filename);
    imagedestroy($QR);

    $fontPath = $_SERVER['DOCUMENT_ROOT'] . "/static/plugs/font-awesome-4.7.0/fonts/simkai.ttf";
    $obj = addFontToPic($filename, $fontPath, 18, $msg, 360, $filename1);
    return 'http://' . $_SERVER['SERVER_NAME'] . '/upload/device_code/' . $time . '.png';
}


/**
 * 添加文字到图片上
 * @param $dstPath 目标图片
 * @param $fontPath 字体路径
 * @param $fontSize 字体大小
 * @param $text 文字内容
 * @param $dstY 文字Y坐标值
 * @param string $filename 输出文件名，为空则在浏览器上直接输出显示
 * @return string 返回文件名
 */
function addFontToPic($dstPath, $fontPath, $fontSize, $text, $dstY, $filename = '')
{
    //创建图片的实例
    $dst = imagecreatefromstring(file_get_contents($dstPath));
    //打上文字
    $fontColor = imagecolorallocate($dst, 0, 0, 0);//字体颜色
    $width = imagesx($dst);
    $height = imagesy($dst);
    $fontBox = imagettfbbox($fontSize, 0, $fontPath, $text);//文字水平居中实质
    imagettftext($dst, $fontSize, 0, ceil(($width - $fontBox[2]) / 2), $dstY, $fontColor, $fontPath, $text);
    //输出图片
    list($dst_w, $dst_h, $dst_type) = getimagesize($dstPath);
    switch ($dst_type) {
        case 1://GIF
            if (!$filename) {
                header('Content-Type: image/gif');
                imagegif($dst);
            } else {
                imagegif($dst, $filename);
            }
            break;
        case 2://JPG
            if (!$filename) {
                header('Content-Type: image/jpeg');
                imagejpeg($dst);
            } else {
                imagejpeg($dst, $filename);
            }
            break;
        case 3://PNG
            if (!$filename) {
                header('Content-Type: image/png');
                imagepng($dst);
            } else {
                imagepng($dst, $filename);
            }
            break;
        default:
            break;
    }
    imagedestroy($dst);
    unlink($dstPath);
    return $filename;
}

function get_client_ip($type = 0, $adv = false)
{
    return request()->ip($type, $adv);
}


function https_request($url, $data = null, $header = NULL)
{
    // 1. 初始化一个 cURL 对象
    $curl = curl_init();
    // 2.设置你需要抓取的URL
    curl_setopt($curl, CURLOPT_URL, $url);
    // (可选)设置头 阿里云的许多接口需要在头上传输秘钥
    if (!empty($header)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    }
    // 3.https必须加这个，不加不好使（不多加解释，东西太多了）
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //对认证证书进行检验
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    // 4.设置post数据
    if (!empty($data)) {//post方式，否则是get方式
        //设置模拟post方式
        curl_setopt($curl, CURLOPT_POST, 1);
        //传数据，get方式是直接在地址栏传的，这是post传参的解决方式
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);//$data可以是数组，json
    }
    // 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。1是保存，0是输出
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    // 让curl跟随页面重定向
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    // 5. 运行cURL，请求网页
    $output = curl_exec($curl);
    // 6. 关闭CURL请求
    curl_close($curl);
    return $output;
}


function send_post_del($url, $post_data, $token, $type)
{
    $postdata = http_build_query($post_data);
    $options = array(
        'http' => array(
            'method' => $type,
            'header' => array("Content-type: application/x-www-form-urlencoded", 'token:' . $token, 'chan:bee-CSQYUS'),
            'content' => $postdata,
            'timeout' => 15 * 60 // 超时时间（单位:s）
        )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return true;
}

function delMaterial($url)
{
    if (empty($url)) {
        return true;
    }
    if (strpos($url, 'api.hnchaohai.com') !== false) {
        $dirpath = $_SERVER['DOCUMENT_ROOT'] . '/';
        $path = str_replace(Env::get('server.server_name', ''), $dirpath, $url);
        if (file_exists($url)) {
            unlink($path);
        }
    } else {
        if (strpos($url, 'fs-manghe.oss-cn-hangzhou.aliyuncs.com') !== false) {
            $path = str_replace('https://ch-manghe.oss-cn-hangzhou.aliyuncs.com/', '', $url);
            (new \app\index\common\Oss())->delete($path);
        }

    }

    return true;
}

function getMillisecond()
{
    list($s1, $s2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
}
