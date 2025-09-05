<?php

declare(strict_types=1);

/**
 * 新架构使用示例
 * 
 * 演示如何使用重构后的录制系统
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LiveStream\Application\Services\RecordingService;
use LiveStream\Application\DTOs\RecordingRequest;
use LiveStream\Shared\Contracts\ContainerInterface;

try {
    // 1. 初始化容器
    /** @var ContainerInterface $container */
    $container = require __DIR__ . '/bootstrap.php';

    // 2. 获取录制服务
    $recordingService = $container->make(RecordingService::class);

    // 3. 创建录制请求
    $request = new RecordingRequest(
        url: 'https://live.douyin.com/123456',
        outputPath: './recordings/test.mp4',
        quality: 'origin',
        format: 'mp4',
        enableSplitting: true,
        splitDuration: 300 // 5分钟分割
    );

    echo "🎥 多平台直播录制系统 - 新架构演示\n\n";
    echo "录制配置:\n";
    echo "- URL: {$request->url}\n";
    echo "- 输出: {$request->outputPath}\n";
    echo "- 质量: {$request->quality}\n";
    echo "- 格式: {$request->format}\n";
    echo "- 分割: " . ($request->isSplittingEnabled() ? '启用' : '禁用') . "\n";
    
    if ($request->isSplittingEnabled()) {
        echo "- 分割时长: {$request->splitDuration}秒\n";
    }
    
    echo "\n";

    // 4. 设置进度回调
    $progressCallback = function (string $type, string $buffer): void {
        if ($type === 'stderr' && strpos($buffer, 'time=') !== false) {
            if (preg_match('/time=(\d+:\d+:\d+\.\d+)/', $buffer, $matches)) {
                echo "\r⏱️  录制进度: {$matches[1]}";
                flush();
            }
        }
    };

    // 5. 启动录制
    echo "🚀 启动录制...\n";
    $response = $recordingService->startRecording($request, $progressCallback);

    if ($response->isSuccessful()) {
        echo "\n✅ 录制启动成功!\n";
        echo "📝 录制ID: {$response->id->getValue()}\n";
        echo "🔗 句柄ID: {$response->getHandleId()}\n";
        echo "📊 状态: {$response->status->getDisplayName()}\n";
        
        // 6. 查看录制状态
        echo "\n📊 录制状态:\n";
        $status = $recordingService->getRecordingStatus($response->id);
        
        if ($status) {
            foreach ($status as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                echo "  {$key}: {$value}\n";
            }
        }

        // 7. 模拟录制过程（实际使用中会是真实的录制过程）
        echo "\n⏳ 录制中... (这是演示，实际录制会在后台进行)\n";
        sleep(2);

        // 8. 列出所有录制记录
        echo "\n📋 所有录制记录:\n";
        $recordings = $recordingService->listRecordings();
        
        foreach ($recordings as $recording) {
            echo "  - {$recording['id']}: {$recording['status_display']}\n";
        }

    } else {
        echo "\n❌ 录制启动失败!\n";
        echo "错误信息: {$response->message}\n";
        
        if (!empty($response->metadata)) {
            echo "详细信息:\n";
            foreach ($response->metadata as $key => $value) {
                echo "  {$key}: {$value}\n";
            }
        }
    }

} catch (Throwable $e) {
    echo "\n💥 发生异常: {$e->getMessage()}\n";
    echo "文件: {$e->getFile()}:{$e->getLine()}\n";
    
    if (isset($_ENV['DEBUG']) && $_ENV['DEBUG']) {
        echo "\n🐛 调试信息:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}

echo "\n🎉 演示完成!\n";
echo "\n💡 提示:\n";
echo "- 这是新架构的演示代码\n";
echo "- 实际的录制器实现需要进一步完善\n";
echo "- 可以通过扩展工厂来支持更多平台\n";
echo "- 使用依赖注入让测试变得简单\n";