<?php

namespace app\index\common;

use OSS\Core\OssException;
use OSS\OssClient;
use think\Db;

class Oss
{
    private $accessKeyId = "";
    private $accessKeySecret = "";
    private $endpoint = "";
    // 填写Bucket名称，例如examplebucket。
    private $bucket = "ch-manghe";

    //上传文件到oss
    public function uploadToOss($object, $filePath)
    {
        try {
            $ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
            $res = $ossClient->uploadFile($this->bucket, $object, $filePath);
            @unlink($filePath);
            return $res['info']['url'];
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
    }

    //下载文件
    public function downLoad($object,$localfile)
    {
        //$object = "testfolder/exampleobject.txt";
        // 下载Object到本地文件examplefile.txt，并保存到指定的本地路径中（D:\\localpath）。如果指定的本地文件存在会覆盖，不存在则新建。
        // 如果未指定本地路径，则下载后的文件默认保存到示例程序所属项目对应本地路径中。
        //$localfile = "D:\\localpath\\examplefile.txt";
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $localfile
        );

        // 使用try catch捕获异常。如果捕获到异常，则说明下载失败；如果没有捕获到异常，则说明下载成功。
        try {
            $ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
            $ossClient->getObject($this->bucket, $object, $options);
            return true;
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
    }

    /**
     * 删除文件
     * @param $path
     * @return bool
     */
    public function delete($path)
    {
        $ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
        return $ossClient->deleteObject($this->bucket, $path) === null;
    }
}
