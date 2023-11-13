<?php

namespace app\meituan\controller;

use app\index\model\MtDeviceGoodsModel;
use app\index\model\MtGoodsModel;
use app\index\model\MtOrderModel;
use app\index\model\MtRefundLogModel;
use app\index\model\MtShopModel;

class Test
{
    //绑定/解绑店铺回调
    public function bindShop()
    {
        $params = request()->post();
        trace($params, '绑定店铺');
        return json(['data' => 'ok']);
    }

    //更改商品--商品总部
    public function changeGoods()
    {
        $params = request()->post();
        trace($params, '更改商品');
        return json(['data' => 'ok']);
    }

    //修改商品
    public function updateGoods()
    {
        $params = request()->post();
        trace($params, '修改商品');
        $medicine_data = json_decode(urldecode($params['medicine_data']), true);
        trace($medicine_data, '修改商品详情');
        $keywords = [
            'price', 'stock', 'category_code', 'category_name', 'is_sold_out', 'sequence', 'expiry_date'
        ];
        $model = new MtGoodsModel();
        foreach ($medicine_data as $k => $v) {
            $row = $model->where('app_poi_code', $v['app_poi_code'])->where('id|app_medicine_code', $v['app_medicine_code'])->find();
            if ($row) {
                $data = [];
                if (!empty($v['diff_contents'])) {
                    foreach ($v['diff_contents'] as $x => $y) {
                        if ($k == 'skus') {
                            foreach ($v['diff_contents']['skus'] as $j => $i) {
                                if (isset($i['diffContentMap'])) {
                                    foreach ($i['diffContentMap'] as $a => $b) {
                                        if (in_array($a, $keywords)) {
                                            $data[$a] = $b['result'];
                                            if ($a == 'category_name') {
                                                $data['category_code'] = $this->findCode($b['result']);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if ($data) {
                    $model->where('id', $row['id'])->update($data);

                }
            }

        }

        return json(['data' => 'ok']);
    }

    public function findCode($name)
    {
        $cate = [
            '090100' => '感冒用药', '090200' => '清热解毒', '090300' => '呼吸系统', '090400' => '消化系统', '090500' => '妇科用药', '090600' => '儿童用药', '090700' => '滋养调补', '090800' => '男科用药', '090900' => '中药饮片', '091000' => '性福生活', '091100' => '皮肤用药', '091200' => '五官用药', '091300' => '营养保健', '091400' => '内分泌系统', '091500' => '医疗器械', '091600' => '养心安神', '091700' => '风湿骨伤', '091800' => '心脑血管用药', '092000' => '家庭常备', '092100' => '泌尿系统', '092200' => '神经用药', '092300' => '肿瘤用药', '092400' => '其他',
        ];
        $code = '';
        foreach ($cate as $k => $v) {
            if ($v == $name) {
                $code = $k;
                break;
            }
        }
        return $code;
    }

    //添加商品
    public function addGoods()
    {
        $params = request()->post();
        trace($params, '添加商品');
        $medicine_data = json_decode(urldecode($params['medicine_data']), true);
//        var_dump($medicine_data);die();
        trace($medicine_data, '添加商品详情');
        $app_poi_code = $medicine_data[0]['app_poi_code'];
        $model = new MtGoodsModel();
        $rows = $model->where('app_poi_code', $app_poi_code)->column('upc,name', 'upc');
        $data = (new MeiTuan())->medicineList($app_poi_code);
        $insert_data = [];
        foreach ($data['data'] as $k => $v) {
            if (!isset($rows[$v['upc']])) {
                $insert_data[] = [
                    'app_poi_code' => $app_poi_code,
                    'goods_id' => $v['id'],
                    'name' => $v['name'],
                    'upc' => $v['upc'],
                    'app_medicine_code' => $v['app_medicine_code'],
                    'medicine_no' => $v['medicine_no'],
                    'spec' => $v['spec'],
                    'price' => $v['price'],
                    'stock' => $v['stock'],
                    'category_code' => $v['category_code'],
                    'category_name' => $v['category_name'],
                    'is_sold_out' => $v['is_sold_out'],
                    'sequence' => $v['sequence'],
                    'medicine_type' => $v['medicine_type'],
                    'expiry_date' => $v['expiry_date']
                ];
            }
        }
        $model->saveAll($insert_data);
        return json(['data' => 'ok']);
    }

    //删除商品
    public function delGoods()
    {
        $params = request()->post();
        trace($params, '删除商品');
        $medicine_data = json_decode(urldecode($params['medicine_data']), true);
        trace($medicine_data, '删除商品详情');
        $model = new MtGoodsModel();
        $goods_ids = [];
        foreach ($medicine_data as $k => $v) {
            $id = $model->where('app_poi_code', $v['app_poi_code'])->where('name', $v['name'])->value('id');
            $model->where('app_poi_code', $v['app_poi_code'])->where('name', $v['name'])->delete();
            $goods_id = (new MtDeviceGoodsModel())->where('goods_id', $id)->column('id');
            $goods_ids = array_merge($goods_ids, $goods_id);
        }
        $data = [
            'goods_id' => '',
            'stock' => '',
            'price' => 0,
        ];
        (new MtDeviceGoodsModel())->whereIn('id', $goods_ids)->update($data);
        return json(['data' => 'ok']);
    }

    //推送已支付订单
    public function getPayOrder()
    {
        $params = request()->post();
        $test = urldecode(json_encode($params));
        trace($test, '获取已支付订单');
        if (!empty($params['order_id'])) {
            $order_id = $params['order_id'];
            $model = new MtOrderModel();
            $order = $model->where('order_id', $order_id)->find();
            if (!$order) {
                $data = [
                    'order_id' => $params['order_id'],
                    'original_price' => $params['original_price'],
//                    'order_tag_list' => '',
                    'wm_order_id_view' => $params['wm_order_id_view'],
                    'app_poi_code' => $params['app_poi_code'],
                    'wm_poi_name' => $params['wm_poi_name'],
                    'wm_poi_address' => urldecode($params['wm_poi_address']),
                    'recipient_address' => urldecode($params['recipient_address']),
                    'recipient_phone' => $params['recipient_phone'],
                    'backup_recipient_phone' => urldecode($params['backup_recipient_phone']),
                    'recipient_name' => urldecode($params['recipient_name']),
                    'shipping_fee' => $params['shipping_fee'],
                    'total' => $params['total'],
                    'caution' => urldecode($params['caution']),
                    'shipper_phone' => $params['shipper_phone'],
                    'status' => $params['status'],
                    'delivery_time' => $params['delivery_time'],
                    'is_third_shipping' => $params['is_third_shipping'],
                    'pay_type' => $params['pay_type'],
                    'detail' => urldecode($params['detail'])
                ];
                $model->save($data);
            } else {
                $data = [
                    'status' => $params['status'],
                    'shipper_phone' => $params['shipper_phone'],
                ];
                $model->where('order_id', $order_id)->update($data);
            }
        }
        return json(['data' => 'ok']);
    }


    //取消订单回调
    public function cancelOrder()
    {
        $params = request()->post();
        $test = urldecode(json_encode($params));
        trace($test, '获取取消订单');
        if (!empty($params['order_id'])) {
            $order_id = $params['order_id'];
            $data = [
                'status' => 9,
                'shipper_phone' => isset($params['shipper_phone']) ? $params['shipper_phone'] : '',
            ];
            $model = new MtOrderModel();
            $model->where('order_id', $order_id)->update($data);
        }

        return json(['data' => 'ok']);
    }

    //全额退款
    public function fullRefund()
    {
        $params = request()->post();
        trace($params, '获取全额退款');

        if (!empty($params['order_id'])) {
            //待退款
            $arr1 = [0];
            //已同意退款
            $arr2 = [2, 4, 5, 6];
            //已驳回
            $arr3 = [1, 3];
            //已取消申请
            $arr4 = [7, 8];
            $data = [];
            if (in_array($params['res_type'], $arr1)) {
                $data['status'] = 10;
                $data['reason'] = urldecode($params['reason']);
            }
            if (in_array($params['res_type'], $arr2)) {
                $data['status'] = 11;
            }
            if (in_array($params['res_type'], $arr3)) {
                $data['status'] = 12;
            }
            if (in_array($params['res_type'], $arr4)) {
                $data['status'] = 13;
            }
            (new MtOrderModel())->where('order_id', $params['order_id'])->update($data);
            $log = [
                'order_id' => $params['order_id'],
                'app_poi_code' => $params['app_poi_code'],
                'notify_type' => $params['notify_type'],
                'refund_id' => $params['refund_id'],
                'reason' => $params['reason'],
                'res_type' => $params['res_type'],
                'is_appeal' => $params['is_appeal'],
                'pictures' => $params['pictures'],
            ];
            (new MtRefundLogModel())->save($log);
        }
        return json(['data' => 'ok']);
    }

    //部分退款
    public function partRefund()
    {
        $params = request()->post();
        trace($params, '获取部分退款');
        return json(['data' => 'ok']);
    }

    //退款状态
    public function refundStatus()
    {
        $params = request()->post();
        trace($params, '获取退款状态');
        if (!empty($params['order_id'])) {
            if (in_array($params['refund_status'], [3, 13])) {
                $data['status'] = 14;
            } else {
                $data['status'] = 15;
            }
            if (!empty($params['money'])) {
                $data['money'] = $params['money'];
            }
            (new MtOrderModel())->where('order_id', $params['order_id'])->update($data);
            $log = [
                'order_id' => $params['order_id'],
                'app_poi_code' => $params['app_poi_code'],
                'notify_type' => '',
                'refund_id' => $params['refund_id'],
                'reason' => '',
                'res_type' => $data['status'] == 14 ? 9 : 10,
                'is_appeal' => '',
                'pictures' => '',
            ];
            (new MtRefundLogModel())->save($log);
        }
        return json(['data' => 'ok']);
    }

    //店铺状态
    public function shopStatus()
    {
        $params = request()->post();
        trace($params, '获取店铺状态');
        if (!empty($params)) {
            $model = new MtShopModel();
            $data = [];
            if ($params['poi_status'] == 121) {
                $data['open_level'] = 1;
            } elseif ($params['poi_status'] == 120) {
                $data['open_level'] = 3;
            } elseif ($params['poi_status'] == 18) {
                $data['is_online'] = 1;
            } elseif ($params['poi_status'] == 19) {
                $data['is_online'] = 0;
            }
            $model->where('app_poi_code', $params['app_poi_code'])->update($data);
        }
        return json(['data' => 'ok']);
    }

    //确认订单
    public function confirmOrder()
    {
        $params = request()->post();
        trace($params, '确认订单回调');
        if (!empty($params['order_id'])) {
            (new MtOrderModel())->where('order_id', $params['order_id'])->update(['status' => 4]);
        }
        return json(['data' => 'ok']);
    }

    //已完成订单
    public function completeOrder()
    {
        $params = request()->post();
        trace($params, '完成订单回调');
        if (!empty($params['order_id'])) {
            (new MtOrderModel())->where('order_id', $params['order_id'])->update(['status' => 8]);
        }
        return json(['data' => 'ok']);
    }
}