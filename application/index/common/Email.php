<?php

namespace app\index\common;

use PHPMailer\PHPMailer\PHPMailer;
use think\Cache;
use think\Db;

class Email
{

    public function send_email($title, $body, $address)
    {
        $mail = new PHPMailer(true);
        $mail->IsSMTP(); // 使用SMTP方式发送
        $mail->CharSet = 'UTF-8';// 设置邮件的字符编码
        $mail->Host = "smtp.163.com"; // 您的企业邮局域名
        $mail->SMTPAuth = true; // 启用SMTP验证功能
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465; //SMTPS linux端口
//        $mail->Port = 25; //SMTP windows端口
        $mail->Username = ''; // 邮局用户名(请填写完整的email地址)
//        $mail->Password = 'jrtzpltuzxkreahh'; // 邮局密码 邮箱授权码
        $mail->Password = 'BONBHDQVLTVTANTQ'; // 邮局密码 邮箱授权码
        $mail->From = ''; //邮件发送者email地址
        $mail->FromName = "";
        $mail->AddAddress($address, "");//收件人地址，可以替换成任何想要接收邮件的email信箱,格式是AddAddress("收件人email","收件人姓名")
        $mail->IsHTML(true); // set email format to HTML //是否使用HTML格式
        $mail->Subject = $title; //邮件标题
        $mail->Body = $body; //邮件内容
        if (!$mail->Send()) {
            return ['code' => 100, 'msg' => $mail->ErrorInfo];
        } else {
            return ['code' => 200, 'msg' => '成功'];
        }
    }
}
