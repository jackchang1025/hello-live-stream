<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use LiveStream\LiveStream;
use LiveStream\PlatformFactory;
use LiveStream\Recording\PendingRecorder;
use LiveStream\Config\RecordingOptions;
use LiveStream\Recording\Advanced\PhpFFmpegRecorder;
use LiveStream\SpeechRecognition\VideoToTextService;
use LiveStream\Enum\Quality;
use LiveStream\Enum\OutputFormat;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * 直播录制 + 语音识别完整集成示例
 * 
 * 演示如何在录制完成后自动进行语音识别
 */
class LiveStreamWithSpeechRecognition
{
    private LiveStream $liveStream;
    private VideoToTextService $videoToTextService;
    private Logger $logger;
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        
        // 初始化日志
        $this->logger = new Logger('live-stream-speech');
        $this->logger->pushHandler(new StreamHandler('logs/live-stream-speech.log', Logger::DEBUG));
        
        // 初始化直播服务
        $this->liveStream = new LiveStream();
        
        // 初始化语音识别服务
        $this->videoToTextService = new VideoToTextService($config['speech_recognition'], null, null, $this->logger);
    }
    
    /**
     * 录制直播并进行语音识别
     */
    public function recordAndTranscribe(string $liveUrl, array $options = []): array
    {
        $this->logger->info('开始录制和语音识别流程', [
            'live_url' => $liveUrl,
            'options' => $options
        ]);
        
        try {
            // 1. 获取直播信息
            $liveData = $this->liveStream->getLiveData($liveUrl);
            
            if (!$liveData['is_live']) {
                throw new \Exception('直播间未开播');
            }
            
            $this->logger->info('直播信息获取成功', [
                'anchor_name' => $liveData['anchor_name'],
                'title' => $liveData['title']
            ]);
            
            // 2. 开始录制
            $recordingResult = $this->startRecording($liveUrl, $options);
            
            // 3. 等待录制完成（这里简化处理，实际应该监控录制状态）
            $this->waitForRecordingCompletion($recordingResult);
            
            // 4. 获取录制文件
            $recordedFiles = $this->getRecordedFiles($recordingResult);
            
            // 5. 进行语音识别
            $transcriptionResults = $this->transcribeRecordedFiles($recordedFiles, $options);
            
            // 6. 生成最终结果
            $finalResult = [
                'live_info' => $liveData,
                'recording_result' => $recordingResult,
                'recorded_files' => $recordedFiles,
                'transcription_results' => $transcriptionResults,
                'summary' => $this->generateSummary($transcriptionResults),
            ];
            
            $this->logger->info('录制和语音识别流程完成', [
                'recorded_files_count' => count($recordedFiles),
                'transcription_success_count' => count(array_filter($transcriptionResults))
            ]);
            
            return $finalResult;
            
        } catch (\Exception $e) {
            $this->logger->error('录制和语音识别流程失败', [
                'live_url' => $liveUrl,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 开始录制
     */
    private function startRecording(string $liveUrl, array $options): array
    {
        // 获取流地址
        $streamData = $this->liveStream->getStreamUrl($liveUrl, Quality::ORIGINAL->value);
        
        // 配置录制选项
        $recordingOptions = new RecordingOptions(
            splitTime: $options['split_time'] ?? 300, // 5分钟分段
            outputFormat: OutputFormat::MP4,
            quality: Quality::ORIGINAL
        );
        
        // 创建录制器
        $roomInfo = $this->createRoomInfo($streamData);
        $pendingRecorder = new PendingRecorder($roomInfo, $recordingOptions);
        $recorder = new PhpFFmpegRecorder();
        
        // 开始录制
        $recordHandle = $recorder->start($pendingRecorder);
        
        return [
            'record_handle' => $recordHandle,
            'pending_recorder' => $pendingRecorder,
            'start_time' => time(),
        ];
    }
    
    /**
     * 等待录制完成
     */
    private function waitForRecordingCompletion(array $recordingResult): void
    {
        // 这里简化处理，实际应该监控录制进程状态
        $maxWaitTime = $this->config['recording']['max_wait_time'] ?? 3600; // 1小时
        $startTime = $recordingResult['start_time'];
        
        while (time() - $startTime < $maxWaitTime) {
            // 检查录制状态
            if ($this->isRecordingCompleted($recordingResult)) {
                break;
            }
            
            sleep(10); // 每10秒检查一次
        }
    }
    
    /**
     * 检查录制是否完成
     */
    private function isRecordingCompleted(array $recordingResult): bool
    {
        // 简化实现，实际应该检查进程状态
        return true;
    }
    
    /**
     * 获取录制文件列表
     */
    private function getRecordedFiles(array $recordingResult): array
    {
        $outputPath = $recordingResult['pending_recorder']->getOutputPath();
        $outputDir = dirname($outputPath);
        
        // 扫描输出目录获取所有录制文件
        $files = glob($outputDir . '/*.mp4');
        
        // 按文件名排序（确保分段顺序正确）
        sort($files);
        
        return $files;
    }
    
    /**
     * 转录录制文件
     */
    private function transcribeRecordedFiles(array $recordedFiles, array $options): array
    {
        $this->logger->info('开始转录录制文件', [
            'file_count' => count($recordedFiles)
        ]);
        
        if (count($recordedFiles) === 1) {
            // 单个文件
            $result = $this->videoToTextService->processVideo($recordedFiles[0], $options);
            return [$recordedFiles[0] => $result];
        } else {
            // 多个分段文件
            $combinedResult = $this->videoToTextService->processSegmentedRecording(
                $recordedFiles,
                $options
            );
            
            return [
                'segments' => $combinedResult->segmentResults,
                'combined' => $combinedResult,
            ];
        }
    }
    
    /**
     * 生成摘要
     */
    private function generateSummary(array $transcriptionResults): array
    {
        $summary = [
            'total_duration' => 0,
            'total_text_length' => 0,
            'average_confidence' => 0,
            'keyword_highlights' => [],
        ];
        
        if (isset($transcriptionResults['combined'])) {
            // 分段录制的情况
            $combined = $transcriptionResults['combined']->combinedResult;
            $summary['total_duration'] = $combined->duration;
            $summary['total_text_length'] = strlen($combined->fullText);
            $summary['average_confidence'] = $combined->confidence;
            
            // 提取关键词
            $keywords = ['产品', '价格', '优惠', '活动', '直播'];
            $summary['keyword_highlights'] = $combined->searchKeywords($keywords);
            
        } else {
            // 单个文件的情况
            $result = reset($transcriptionResults);
            if ($result) {
                $speech = $result->speechRecognitionResult;
                $summary['total_duration'] = $speech->duration;
                $summary['total_text_length'] = strlen($speech->fullText);
                $summary['average_confidence'] = $speech->confidence;
                
                $keywords = ['产品', '价格', '优惠', '活动', '直播'];
                $summary['keyword_highlights'] = $speech->searchKeywords($keywords);
            }
        }
        
        return $summary;
    }
    
    /**
     * 创建房间信息（简化实现）
     */
    private function createRoomInfo(array $streamData): object
    {
        return new class($streamData) {
            private array $data;
            
            public function __construct(array $data) {
                $this->data = $data;
            }
            
            public function getRecordUrl(): string {
                return $this->data['record_url'] ?? $this->data['m3u8_url'];
            }
            
            public function getAnchorName(): string {
                return $this->data['anchor_name'] ?? 'Unknown';
            }
            
            public function getTitle(): string {
                return $this->data['title'] ?? 'Live Stream';
            }
        };
    }
}

// 使用示例
try {
    // 加载配置
    $config = [
        'speech_recognition' => require __DIR__ . '/config.example.php',
        'recording' => [
            'max_wait_time' => 3600,
        ],
    ];
    
    // 创建集成服务
    $service = new LiveStreamWithSpeechRecognition($config);
    
    // 录制并转录
    $liveUrl = 'https://live.douyin.com/123456789';
    
    $result = $service->recordAndTranscribe($liveUrl, [
        'split_time' => 300,        // 5分钟分段
        'sample_rate' => 16000,     // 音频采样率
        'channels' => 1,            // 单声道
        'cleanup_audio' => true,    // 清理临时音频文件
        'max_concurrent' => 2,      // 最大并发处理数
    ]);
    
    echo "🎉 录制和转录完成！\n";
    echo "主播: {$result['live_info']['anchor_name']}\n";
    echo "标题: {$result['live_info']['title']}\n";
    echo "录制文件数: " . count($result['recorded_files']) . "\n";
    echo "总时长: {$result['summary']['total_duration']:.1f}秒\n";
    echo "文本长度: {$result['summary']['total_text_length']}字符\n";
    echo "平均置信度: {$result['summary']['average_confidence']:.2f}\n";
    
    // 显示关键词亮点
    if (!empty($result['summary']['keyword_highlights'])) {
        echo "\n关键词亮点:\n";
        foreach ($result['summary']['keyword_highlights'] as $keyword => $positions) {
            echo "- {$keyword}: " . count($positions) . "次提及\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ 错误: {$e->getMessage()}\n";
}
