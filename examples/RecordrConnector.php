<?php

require_once __DIR__ . '/../vendor/autoload.php';


use LiveStream\LiveStream;
use LiveStream\PlatformFactory;
use LiveStream\Platforms\PlatformManager;
use LiveStream\Recording\PendingRecorder;
use LiveStream\Config\RecordingOptions;
use LiveStream\Recording\RecordrConnector;


$platformManager = new PlatformManager(new PlatformFactory());

$platform = $platformManager->driver('https://live.douyin.com/853987232769');

    // 创建自定义的录制选项，指定保存路径
    $options = new RecordingOptions();

    $options->setSavePath('/app/downloads');
    $options->setQuality(\LiveStream\Enum\Quality::HIGH);
    $options->setFormat(\LiveStream\Enum\OutputFormat::MP4);
    $options->set([
        'timeout' => 0,
        'max_retries'=>0,
        'custom_headers'=>[
            'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer'=>'https://www.douyin.com/',
        ],
    ]);

    $recordrConnector = new RecordrConnector();

    $recordrConnector->withConfig($options);

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

    $result = $recordrConnector->handle($platform, function (string $type, string $buffer) {
        echo "\n[FFmpeg $type]: {$buffer}" . trim($buffer);
    });
