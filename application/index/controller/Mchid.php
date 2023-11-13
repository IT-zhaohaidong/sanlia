<?php

namespace app\index\controller;

use app\index\common\Wxpay;
use app\index\model\MchidModel;
use app\index\model\SystemAdmin;
use think\Db;
use think\Env;

class Mchid extends BaseController
{
    //添加申请
    public function save()
    {
        $data = request()->post();
        $user = $this->user;
        $data['uid'] = $user['id'];
        $data['status'] = 0;
        $order_sn = 'M' . time() . mt_rand(1000, 9999);
        $data['order_sn'] = $order_sn;
        $total_fee = 5000;
        $data['money'] = $total_fee;
        $data['create_time'] = time();
        $data['ssl_cert_path']=
        $model = new MchidModel();
        $order_id = $model->insertGetId($data);
        $pay = new Wxpay();
        $notify_url = 'https://api.hnchaohai.com/index/mchid/notify';
        $result = $pay->prepay('', $order_sn, $total_fee, $notify_url, 'NATIVE');
        if ($result['return_code'] == 'SUCCESS') {
            $data = [
                'order_id' => $order_id,
                'order_sn' => $order_sn,
                'money' => $total_fee,
                'url' => $result['code_url']
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'msg' => $result['return_msg']]);
        }
    }

    //重新获取二维码
    public function getUrl()
    {
        $id = request()->post('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MchidModel();
        $order = $model->where('id', $id)->find();
        $notify_url = 'https://api.hnchaohai.com/index/mchid/notify';
        $pay = new Wxpay();
        $result = $pay->prepay('', $order['order_sn'], $order['money'], $notify_url, 'NATIVE');
        if ($result['return_code'] == 'SUCCESS') {
            $data = [
                'order_id' => $id,
                'url' => $result['code_url']
            ];
            return json(['code' => 200, 'data' => $data]);
        } else {
            return json(['code' => 100, 'msg' => $result['return_msg']]);
        }
    }

    //获取付款进度
    public function getOrderStatus()
    {
        $id = request()->post('id', '');
        if (empty($id)) {
            return json(['code' => 100, 'msg' => '缺少参数']);
        }
        $model = new MchidModel();
        $order = $model->where('id', $id)->find();
        if ($order['status'] > 0) {
            return json(['code' => 200, 'msg' => '付款成功']);
        } else {
            $result = (new Wxpay())->orderInfo($order['order_sn']);
            trace($result, '商户号申请支付结果');
            if (!empty($result['trade_state']) && $result['trade_state'] == 'SUCCESS') {
                $model->where('id', $id)->update(['status' => 1, 'transaction_id' => $result['transaction_id']]);
                return json(['code' => 200, 'msg' => '付款成功']);
            }
            return json(['code' => 100, 'msg' => '暂未付款']);
        }
    }

    //商户申请记录
    public function getApplyListByUser()
    {
        $user = $this->user;
        $model = new MchidModel();
        $list = $model
            ->where('uid', $user['id'])
            ->where('status', '>', 0)
            ->order('id desc')->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function uploadCert()
    {
        $file = $_FILES['file'];
        $file_name = $file['name'];//获取缓存区图片,格式不能变
        $type = array("pem");//允许选择的文件类型
        $ext = explode(".", $file_name);//拆分获取图片名
        $ext = $ext[count($ext) - 1];//取图片的后缀名
        $path = dirname(dirname(dirname(dirname(__FILE__))));
        if (in_array($ext, $type)) {
            if ($_FILES["file"]["size"] / 1024 / 1024 > 1) {
                return json(['code' => 100, 'msg' => '上传文件不可大于1M!']);
            }
            $name = "/public/upload/cert/";
            $dirpath = $path . $name;
            if (!is_dir($dirpath)) {
                mkdir($dirpath, 0777, true);
            }
            $time = time().rand(100,999);
            $filename = $dirpath . $time . '.' . $ext;
            move_uploaded_file($file["tmp_name"], $filename);
            $filename = Env::get('server.servername', 'http://api.hnchaohai.com') . '/upload/cert/' . $time . '.' . $ext;
            $data = ['code' => 200, 'data' => ['filename' => $filename]];
            return json($data);
        } else {
            return json(['code' => 100, 'msg' => '文件类型错误!']);
        }
    }

//------------------------------------------------商户号审核----------------------------------------------
    public function getList()
    {
        $params = request()->get();
        $limit = request()->get('limit', 15);
        $page = request()->get('page', 1);
        $where = [];
        if (!empty($params['username'])) {
            $where['a.username'] = ['like', '%' . $params['username'] . '%'];
        }
        if (isset($params['status']) && $params['status']) {
            $where['m.status'] = ['=', $params['status']];
        }
        $model = new MchidModel();
        $count = $model->alias('m')
            ->join('system_admin a', 'm.uid=a.id', 'left')
            ->where('m.status', '>', 0)
            ->where($where)->count();
        $list = $model->alias('m')
            ->join('system_admin a', 'm.uid=a.id', 'left')
            ->where($where)
            ->where('m.status', '>', 0)
            ->field('m.*,a.username')
            ->page($page)
            ->limit($limit)
            ->select();
        return json(['code' => 200, 'data' => $list, 'count' => $count, 'params' => $params]);
    }

    public function check()
    {
        $params = request()->get();
        if (empty($params['id']) || empty($params['status'])) {
            return json(['code' => 100, 'msg' => '缺少参数!']);
        }
        $model = new MchidModel();
        $model->where('id', $params['id'])->update(['status' => $params['status']]);
        if ($params['status'] == 2) {
            $row = $model->where('id', $params['id'])->field('uid,mch_type')->find();
            if ($row['mch_type'] == 1) {
                $data['wx_mchid_id'] = $params['id'];
            } else {
                $data['ali_mchid_id'] = $params['id'];
            }
            (new SystemAdmin())->where('id', $row['uid'])->update($data);
        }
        return json(['code' => 200, 'msg' => '操作成功']);
    }

    //支付回调
    public function notify()
    {
        $xml = file_get_contents("php://input");
        //将服务器返回的XML数据转化为数组
        $data = self::xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $pay = new Wxpay();
        $sign = $pay->makeSign($data, 'wgduhzmxasi8ogjetftyio111imljs2j');

        // 判断签名是否正确  判断支付状态
        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS')) {
            $result = $data;
            //获取服务器返回的数据
            $out_trade_no = $data['out_trade_no'];        //订单单号
            $openid = $data['openid'];                    //付款人openID
            $total_fee = $data['total_fee'];            //付款金额
            $transaction_id = $data['transaction_id'];    //微信支付流水号
            //支付成功的业务逻辑
            (new MchidModel())
                ->where('order_sn', $out_trade_no)
                ->save(['status' => 1, 'transaction_id' => $transaction_id]);

        } else {
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result) {
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        } else {
            $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        return $result;
    }

    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    protected function array2xml($arr, $level = 1)
    {
        $s = $level == 1 ? "<xml>" : '';
        foreach ($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s . "</xml>" : $s;
    }

    /**
     * 将xml转为array
     * @param string $xml xml字符串
     * @return array    转换得到的数组
     */
    protected function xml2array($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }

    /**
     * 错误返回提示
     * @param string $errMsg 错误信息
     * @param string $status 错误码
     * @return  json的数据
     */
    protected function return_err($errMsg = 'error', $status = 0)
    {
        exit(json_encode(array('status' => $status, 'result' => 'fail', 'errmsg' => $errMsg)));
    }
}
