<?php

use LiveStream\LiveStream;
use LiveStream\PlatformFactory;
use LiveStream\Platforms\PlatformManager;
use LiveStream\Recording\PendingRecorder;
use LiveStream\Config\RecordingOptions;
use LiveStream\Recording\RecordrConnector;


beforeEach(function () {
    $this->platformManager = new PlatformManager(new PlatformFactory());
});

test('test recordr connector', function () {

    $platform = $this->platformManager->driver('https://live.douyin.com/374806133859?activity_name=&anchor_id=58744272013&banner_type=recommend&category_name=all&page_type=live_main_page');

    // 创建自定义的录制选项，指定保存路径
    $options = new RecordingOptions(
        quality: \LiveStream\Enum\Quality::HIGH,
        format: \LiveStream\Enum\OutputFormat::MP4,
        savePath: '/app/downloads' // Docker 容器内的路径
    );

    $recordrConnector = new RecordrConnector();

    $recordrConnector->withOptions($options);

    $recordrConnector->middleware()->pipe(function(PendingRecorder $pendingRecorder, \Closure $next){

        // 显示录制信息
        echo "\n=== 录制信息 ===\n";
        echo "录制ID: " . $pendingRecorder->getRoomInfo()->getRoomId() . "\n";
        echo "主播: " . $pendingRecorder->getRoomInfo()->getAnchorName() . "\n";
        echo "标题: " . $pendingRecorder->getRoomInfo()->getTitle() . "\n";
        echo "输出路径: " . $pendingRecorder->savePath() . "\n";
        echo "流地址: " . $pendingRecorder->streamConfig()->getRecordUrl() . "\n";

        // 调试：显示完整的流配置
        echo "\n=== 调试信息 ===\n";
        echo "直播状态: " . ($pendingRecorder->getRoomInfo()->isLive() ? '直播中' : '未直播') . "\n";
        $streamConfig = $pendingRecorder->streamConfig();
        echo "M3U8 URL: " . ($streamConfig->m3u8Url ?? 'N/A') . "\n";
        echo "FLV URL: " . ($streamConfig->flvUrl ?? 'N/A') . "\n";
        echo "Record URL: " . ($streamConfig->getRecordUrl() ?? 'N/A') . "\n";

        return $next($pendingRecorder);
    });

    
    echo "\n=== 开始录制 ===\n";

    $result = $recordrConnector->handle($platform, function (string $type, string $buffer) {
        echo "\n[FFmpeg $type]: {$buffer}" . trim($buffer);
    });

    // expect($result)->toBeInstanceOf(RecordHandle::class);
});
