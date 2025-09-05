<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use OSS\Core\OssException;
use OSS\OssClient;

// 用法：
// 1) 先设置凭据（推荐环境变量）
//    export OSS_ACCESS_KEY_ID=your-ak
//    export OSS_ACCESS_KEY_SECRET=your-sk
//    # 可选：如果使用 STS 临时凭证
//    export OSS_SECURITY_TOKEN=your-token
//
// 2) 运行示例（参数：本地文件 路径、Bucket 名、对象键、Endpoint）
//    php examples/oss-upload.php /path/to/local-file.mp4 your-bucket uploads/2025/01/local-file.mp4 https://oss-cn-hangzhou.aliyuncs.com

[$script, $localFile, $bucket, $objectKey, $endpoint] = array_pad($argv, 5, null);

if ($localFile === null || $bucket === null || $objectKey === null || $endpoint === null) {
    fwrite(STDERR, "Usage: php examples/oss-upload.php <localFile> <bucket> <objectKey> <endpoint>\n");
    exit(64); // EX_USAGE
}

if (!is_file($localFile)) {
    fwrite(STDERR, "Local file not found: {$localFile}\n");
    exit(66); // EX_NOINPUT
}

$accessKeyId = (string) getenv('OSS_ACCESS_KEY_ID');
$accessKeySecret = (string) getenv('OSS_ACCESS_KEY_SECRET');
$securityToken = getenv('OSS_SECURITY_TOKEN') ?: null; // 可为空

if ($accessKeyId === '' || $accessKeySecret === '') {
    fwrite(STDERR, "Missing credentials: set OSS_ACCESS_KEY_ID / OSS_ACCESS_KEY_SECRET env vars.\n");
    exit(78); // EX_CONFIG
}

try {
    $ossClient = $securityToken
        ? new OssClient($accessKeyId, $accessKeySecret, $endpoint, false, $securityToken)
        : new OssClient($accessKeyId, $accessKeySecret, $endpoint);

    // 如果 bucket 不存在，可按需创建（需具备权限）
    if (!$ossClient->doesBucketExist($bucket)) {
        $ossClient->createBucket($bucket);
    }

    // 上传本地文件到 OSS（推荐 uploadFile，避免一次性读入内存）
    $ossClient->uploadFile($bucket, $objectKey, $localFile);

    // 拼接可访问地址（若配置了 CDN 域名可用其拼接）
    $cdn = (string) (getenv('OSS_CDN_DOMAIN') ?: '');
    if ($cdn !== '') {
        $url = rtrim($cdn, '/') . '/' . ltrim($objectKey, '/');
    } else {
        $host = parse_url($endpoint, PHP_URL_HOST) ?: $endpoint;
        $scheme = str_starts_with($endpoint, 'http://') ? 'http' : 'https';
        $url = sprintf('%s://%s.%s/%s', $scheme, $bucket, $host, ltrim($objectKey, '/'));
    }

    fwrite(STDOUT, "Upload success: {$url}\n");
} catch (OssException $e) {
    fwrite(STDERR, "OSS error: " . $e->getMessage() . "\n");
    exit(74); // EX_IOERR
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
