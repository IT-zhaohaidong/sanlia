<?php

namespace app\index\common;


use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\IOFactory;

class ExportOrder
{
    public function order_outputProjectExcel($info)
    {
        $newExcel = new Spreadsheet();//创建一个新的excel文档
        $objSheet = $newExcel->getActiveSheet();//获取当前操作sheet的对象
        $date = date('YmdHis', time());
        $name = '订单信息表' . $date;
        $objSheet->setTitle($name);//设置当前sheet的标题
        //设置第一栏的中文标题
        $objSheet->setCellValue('A1', '订单号')
            ->setCellValue('B1', '设备名称')
            ->setCellValue('C1', '商品名称')
            ->setCellValue('D1', '设备号')
            ->setCellValue('E1', '所属人')
            ->setCellValue('F1', '价格')
            ->setCellValue('G1', '佣金')
            ->setCellValue('H1', '手机号')
            ->setCellValue('I1', '支付方式')
            ->setCellValue('J1', '订单状态')
            ->setCellValue('K1', '订单类型')
            ->setCellValue('L1', '支付时间')
            ->setCellValue('M1', '创建时间');

        //写入数据
        $dataCount = count($info);
        $k = 1;

        if ($dataCount == 0) {
            exit;
        } else {
            for ($i = 0; $i < $dataCount; $i++) {
                $k = $k + 1;
                $objSheet->setCellValue('A' . $k, (string)$info[$i]['order_sn'])
                    ->setCellValue('B' . $k, $info[$i]['device_name'])
                    ->setCellValue('C' . $k, $info[$i]['title'])
                    ->setCellValue('D' . $k, $info[$i]['device_sn'])
                    ->setCellValue('E' . $k, $info[$i]['username'])
                    ->setCellValue('F' . $k, $info[$i]['price'])
                    ->setCellValue('G' . $k, $info[$i]['commission'])
                    ->setCellValue('H' . $k, $info[$i]['phone'])
                    ->setCellValue('I' . $k, $info[$i]['pay_type'])
                    ->setCellValue('J' . $k, $info[$i]['status'])
                    ->setCellValue('K' . $k, $info[$i]['order_type'])
                    ->setCellValue('L' . $k, $info[$i]['pay_time'])
                    ->setCellValue('M' . $k, $info[$i]['create_time']);
            }
        }

        //设定样式
        //所有sheet的表头样式 加粗
        $font = [
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
        ];
        $objSheet->getStyle('A1:P1')->applyFromArray($font);

        //样式设置 - 水平、垂直居中
//        $styleArray = [
//            'alignment' => [
//                'horizontal' => Alignment::HORIZONTAL_CENTER,
//                'vertical' => Alignment::VERTICAL_CENTER
//            ],
//        ];
//        $objSheet->getStyle('A1:P2')->applyFromArray($styleArray);

        //所有sheet的内容样式-加黑色边框
//        $borders = [
//            'borders' => [
//                'outline' => [
//                    'borderStyle' => Border::BORDER_THIN,
//                    'color' => ['argb' => '000000'],
//                ],
//                'inside' => [
//                    'borderStyle' => Border::BORDER_THIN,
//                ]
//            ],
//        ];
//        $objSheet->getStyle('A1:P' . $k)->applyFromArray($borders);

        //设置宽度
        $cell = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'];
        foreach ($cell as $k => $v) {
            $objSheet->getColumnDimension($v)->setWidth(50);

            $objSheet->getColumnDimension($v)->setAutoSize(true);
        }
        return $this->downloadExcel($newExcel, $name, 'Xlsx');

    }


    //下载
    private function downloadExcel($newExcel, $filename, $format)
    {
        ob_end_clean();
        ob_start();
        // $format只能为 Xlsx 或 Xls
        if ($format == 'Xlsx') {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        } elseif ($format == 'Xls') {
            header('Content-Type: application/vnd.ms-excel');
        }
        //  strtolower($format)
        header("Content-Disposition: attachment;filename=" . $filename . '.' . strtolower($format));
        header('Cache-Control: max-age=0');
        $objWriter = IOFactory::createWriter($newExcel, $format);
//        $objWriter->save('php://output');
        //通过php保存在本地的时候需要用到
        $dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/public/upload';
        trace($dir . "/${filename}.xlsx", 'xls地址');
        \PhpOffice\PhpSpreadsheet\Shared\File::setUseUploadTempDirectory(true);
        $objWriter->save($dir . "/${filename}.xlsx");
        return 'https://api.hnchaohai.com/upload' . "/${filename}.xlsx";
//        exit;
        //以下为需要用到IE时候设置
        // If you're serving to IE 9, then the following may be needed
        //header('Cache-Control: max-age=1');
        // If you're serving to IE over SSL, then the following may be needed
        //header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        //header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        //header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        //header('Pragma: public'); // HTTP/1.0

    }
}
