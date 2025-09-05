<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AlibabaCloud\SDK\Tingwu\V20230930\Models\CreateTaskRequest;
use AlibabaCloud\SDK\Tingwu\V20230930\Models\CreateTaskRequest\input as TWInput;
use AlibabaCloud\SDK\Tingwu\V20230930\Models\CreateTaskRequest\parameters as TWParams;
use AlibabaCloud\SDK\Tingwu\V20230930\Tingwu;
use Darabonba\OpenApi\Models\Config as OpenApiConfig;
use OSS\Core\OssException;
use OSS\OssClient;

// 示例：将本地文件上传到阿里云 OSS，生成签名 URL，使用听悟(tingwu-20230930) 创建离线转写任务并轮询输出转写文本。
// 依赖：
//   composer require aliyuncs/oss-sdk-php:^2.7 alibabacloud/tingwu-20230930:^1 alibabacloud/darabonba-openapi:^2

[$script, $localFile, $bucket, $objectKey, $endpoint] = array_pad($argv, 5, null);

if ($localFile === null || $bucket === null || $objectKey === null || $endpoint === null) {
    fwrite(STDERR, "Usage: php examples/aliyun-oss-tingwu-example.php <localFile> <bucket> <objectKey> <oss-endpoint>\n");
    exit(64); // EX_USAGE
}

if (!is_file($localFile)) {
    fwrite(STDERR, "Local file not found: {$localFile}\n");
    exit(66); // EX_NOINPUT
}

// 读取 OSS 配置（必须由环境变量提供）
$accessKeyId = (string) getenv('OSS_ACCESS_KEY_ID');
$accessKeySecret = (string) getenv('OSS_ACCESS_KEY_SECRET');
$securityToken = getenv('OSS_SECURITY_TOKEN') ?: null; // 可为空

if ($accessKeyId === '' || $accessKeySecret === '') {
    fwrite(STDERR, "Missing OSS credentials: set OSS_ACCESS_KEY_ID / OSS_ACCESS_KEY_SECRET env vars.\n");
    exit(78); // EX_CONFIG
}

// 读取 听悟 配置（建议使用单独 AK/SK；如未设置则终止）
$twAk = (string) getenv('TINGWU_ACCESS_KEY_ID');
$twSk = (string) getenv('TINGWU_ACCESS_KEY_SECRET');
$twRegion = (string) (getenv('TINGWU_REGION') ?: 'cn-shanghai');
$twEndpoint = (string) (getenv('TINGWU_ENDPOINT') ?: 'tingwu.cn-shanghai.aliyuncs.com');
$twAppKey = (string) getenv('TINGWU_APP_KEY');

if ($twAk === '' || $twSk === '' || $twAppKey === '') {
    fwrite(STDERR, "Missing Tingwu config: set TINGWU_ACCESS_KEY_ID / TINGWU_ACCESS_KEY_SECRET / TINGWU_APP_KEY.\n");
    exit(78); // EX_CONFIG
}

try {
    // 1) 上传到 OSS
    $ossClient = $securityToken
        ? new OssClient($accessKeyId, $accessKeySecret, $endpoint, false, $securityToken)
        : new OssClient($accessKeyId, $accessKeySecret, $endpoint);

    if (!$ossClient->doesBucketExist($bucket)) {
        $ossClient->createBucket($bucket);
    }

    $ossClient->uploadFile($bucket, $objectKey, $localFile);
    $signedUrl = $ossClient->signUrl($bucket, $objectKey, 3600, 'GET');
    fwrite(STDOUT, "Uploaded to OSS, signed url: {$signedUrl}\n");

    // 2) 调用 听悟 SDK 创建离线转写任务
    $cfg = new OpenApiConfig([
        'accessKeyId' => $twAk,
        'accessKeySecret' => $twSk,
        'regionId' => $twRegion,
    ]);
    $cfg->endpoint = $twEndpoint;

    $tingwu = new Tingwu($cfg);

    $req = new CreateTaskRequest([
        'appKey' => $twAppKey,
        'type' => 'offline',
        'input' => new TWInput([
            'fileUrl' => $signedUrl,
            'format' => pathinfo($objectKey, PATHINFO_EXTENSION) ?: 'wav',
            'sampleRate' => 16000,
            'sourceLanguage' => 'cn',
            'audioChannelMode' => 'mono',
            'multipleStreamsEnabled' => false,
        ]),
        'parameters' => new TWParams([
            'textPolishEnabled' => true,
        ]),
    ]);

    $created = $tingwu->createTask($req);
    $taskId = (string) ($created->body->data->taskId ?? '');
    if ($taskId === '') {
        $msg = $created->body->message ?? 'createTask failed';
        throw new RuntimeException((string) $msg);
    }
    fwrite(STDOUT, "Tingwu task created: {$taskId}\n");

    // 3) 轮询任务直至完成
    $deadline = time() + 900; // 15 min 超时
    while (time() < $deadline) {
        $info = $tingwu->getTaskInfo($taskId);
        $status = (string) ($info->body->data->taskStatus ?? '');
        if ($status === 'COMPLETED') {
            $res = $info->body->data->result;
            $textField = (string) ($res->transcription ?? '');
            $text = $textField;

            $trim = trim($textField);
            if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $decoded = json_decode($trim, true);
                if (is_array($decoded) && isset($decoded['text']) && is_string($decoded['text'])) {
                    $text = (string) $decoded['text'];
                }
            }

            $txtPath = sys_get_temp_dir() . '/' . basename($objectKey) . '.txt';
            file_put_contents($txtPath, (string) $text);
            fwrite(STDOUT, "Transcript saved: {$txtPath}\n");
            exit(0);
        }
        if ($status === 'FAILED' || $status === 'ERROR') {
            $err = (string) ($info->body->data->errorMessage ?? 'failed');
            throw new RuntimeException('Tingwu task failed: ' . $err);
        }
        sleep(3);
    }

    throw new RuntimeException('Timeout waiting tingwu task');
} catch (OssException $e) {
    fwrite(STDERR, "OSS error: " . $e->getMessage() . "\n");
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(3);
}
