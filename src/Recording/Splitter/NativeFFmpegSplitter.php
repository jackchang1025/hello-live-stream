<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use LiveStream\Recording\PendingRecorder;
use LiveStream\Exceptions\RecordingException;
use LiveStream\Recording\Splitter\Contracts\SplitterInterface;
use LiveStream\Recording\Splitter\ValueObjects\SegmentInfo;
use LiveStream\Recording\Splitter\Collections\SegmentCollection;
use LiveStream\Recording\Splitter\Strategies\SplitStrategy;
use Throwable;

/**
 * 原生 FFmpeg 分割器
 * 
 * 基于 DouyinLiveRecorder 的成功实践，使用 FFmpeg 原生的 -f segment 功能
 * 实现真正的实时分割录制，无需额外内存和存储开销
 * 
 * 核心特点：
 * - 使用 FFmpeg 原生 -f segment 参数
 * - 真正的实时分割，无需后处理
 * - 支持多种格式（MP4、TS、MKV）
 * - 网络优化和自动重连
 * - 优雅的进程管理
 */
final class NativeFFmpegSplitter implements SplitterInterface
{
    private readonly SegmentCollection $segments;
    private bool $shouldStop = false;
    private $currentProcess = null;
    private array $currentPipes = [];

    public function __construct(
        private readonly FFMpeg $ffmpeg,
        private readonly FFProbe $ffprobe,
        private readonly PendingRecorder $pendingRecorder,
        private readonly SplitStrategy $splitStrategy
    ) {
        $this->segments = new SegmentCollection();
    }

    /**
     * 执行原生 FFmpeg 分割录制
     * 
     * @param callable|null $progressCallback 进度回调函数
     * @return PendingRecorder 录制结果
     * @throws RecordingException 当录制失败时
     */
    public function execute(?callable $progressCallback = null): PendingRecorder
    {
        try {
            $this->validateConfiguration();
            $this->setupSignalHandlers();

            echo "🎬 开始原生 FFmpeg 分割录制...\n";
            echo "策略: " . $this->splitStrategy->getDescription() . "\n";
            echo "实现: FFmpeg 原生 -f segment 参数\n\n";

            $this->executeNativeSegmentRecording($progressCallback);

            $this->logCompletionSummary();

            return $this->pendingRecorder;
        } catch (RecordingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw RecordingException::fromException($e, $this->pendingRecorder->getRecordId());
        } finally {
            $this->cleanup();
        }
    }

    /**
     * 获取分割信息
     * 
     * @return array 分割统计信息
     */
    public function getSegmentInfo(): array
    {
        return [
            'total_segments' => $this->segments->count(),
            'completed_segments' => $this->segments->countCompleted(),
            'failed_segments' => $this->segments->countFailed(),
            'total_duration' => $this->segments->getTotalDuration(),
            'total_size' => $this->segments->getTotalSize(),
            'strategy' => $this->splitStrategy->getDescription(),
            'implementation' => 'native-ffmpeg-segment',
        ];
    }

    /**
     * 停止录制
     */
    public function stop(): void
    {
        $this->shouldStop = true;
        echo "\n⏹️  录制停止信号已发送\n";

        if ($this->currentProcess && is_resource($this->currentProcess)) {
            $this->gracefulStopProcess();
        }
    }

    // ==================== 私有方法 ====================

    /**
     * 执行原生分段录制
     * 
     * @param callable|null $progressCallback 进度回调
     */
    private function executeNativeSegmentRecording(?callable $progressCallback = null): void
    {
        $streamUrl = $this->pendingRecorder->getStreamConfig()->getRecordUrl();
        $baseOutputPath = $this->pendingRecorder->getOutputPath();

        // 生成分段文件路径模式
        $segmentPathPattern = $this->generateSegmentPathPattern($baseOutputPath);

        // 构建原生 FFmpeg 分段命令
        $command = $this->buildNativeSegmentCommand($streamUrl, $segmentPathPattern);

        echo "🚀 启动 FFmpeg 原生分段录制...\n";
        echo "输出模式: " . basename($segmentPathPattern) . "\n";
        echo "分段时长: " . $this->splitStrategy->getMaxSegmentDuration() . " 秒\n\n";

        // 启动 FFmpeg 进程
        $this->startFFmpegProcess($command, $progressCallback);

        // 监控录制过程
        $this->monitorRecordingProcess($progressCallback);

        // 收集生成的分段文件
        $this->collectGeneratedSegments($baseOutputPath);
    }

    /**
     * 构建原生 FFmpeg 分段命令
     * 
     * @param string $streamUrl 流地址
     * @param string $segmentPathPattern 分段路径模式
     * @return array FFmpeg 命令数组
     */
    private function buildNativeSegmentCommand(string $streamUrl, string $segmentPathPattern): array
    {
        $options = $this->pendingRecorder->getOptions();

        // 基础命令 - 参考 DouyinLiveRecorder 的最佳实践
        $command = [
            'ffmpeg',
            '-y',
            '-v',
            'verbose',
            '-rw_timeout',
            '30000000',        // 30秒网络超时
            '-loglevel',
            'error',
            '-hide_banner',
            '-user_agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            '-protocol_whitelist',
            'rtmp,crypto,file,http,https,tcp,tls,udp,rtp,httpproxy',
            '-thread_queue_size',
            '1024',
            '-analyzeduration',
            '5000000',    // 5秒分析时长
            '-probesize',
            '10000000',         // 10MB 探测大小
            '-fflags',
            '+discardcorrupt',     // 丢弃损坏数据
            '-re',                            // 实时读取
            '-i',
            $streamUrl,                 // 输入流
            '-bufsize',
            '15000k',             // 缓冲区大小
            '-sn',
            '-dn',                     // 禁用字幕和数据流
            '-reconnect_delay_max',
            '60',     // 最大重连延迟
            '-reconnect_streamed',            // 流重连
            '-reconnect_at_eof',              // EOF时重连
            '-max_muxing_queue_size',
            '2048', // 最大复用队列
            '-correct_ts_overflow',
            '1',      // 修正时间戳溢出
            '-avoid_negative_ts',
            'make_zero' // 避免负时间戳
        ];

        // 添加海外优化选项
        $command = array_merge($command, [
            '-timeout',
            '10000000',       // 10秒超时
            '-reconnect',
            '1',            // 启用重连
            '-reconnect_delay_max',
            '120' // 最大重连延迟2分钟
        ]);

        // 添加格式特定参数
        $this->addFormatSpecificOptions($command, $options->format->value);

        // 添加分段参数
        $this->addSegmentOptions($command, $segmentPathPattern);

        return $command;
    }

    /**
     * 添加格式特定选项
     * 
     * @param array $command 命令数组
     * @param string $format 输出格式
     */
    private function addFormatSpecificOptions(array &$command, string $format): void
    {
        switch ($format) {
            case 'mp4':
                $command = array_merge($command, [
                    '-c:v',
                    'copy',                    // 视频流复制
                    '-c:a',
                    'aac',                     // 音频编码为 AAC
                    '-map',
                    '0'                        // 映射所有流
                ]);
                break;

            case 'ts':
                $command = array_merge($command, [
                    '-c:v',
                    'copy',                    // 视频流复制
                    '-c:a',
                    'copy',                    // 音频流复制
                    '-map',
                    '0'                        // 映射所有流
                ]);
                break;

            case 'mkv':
                $command = array_merge($command, [
                    '-flags',
                    'global_header',         // 全局头部
                    '-c:v',
                    'copy',                    // 视频流复制
                    '-c:a',
                    'aac',                     // 音频编码为 AAC
                    '-map',
                    '0'                        // 映射所有流
                ]);
                break;

            default:
                $command = array_merge($command, [
                    '-c:v',
                    'copy',
                    '-c:a',
                    'copy',
                    '-map',
                    '0'
                ]);
        }
    }

    /**
     * 添加分段选项
     * 
     * @param array $command 命令数组
     * @param string $segmentPathPattern 分段路径模式
     */
    private function addSegmentOptions(array &$command, string $segmentPathPattern): void
    {
        $options = $this->pendingRecorder->getOptions();
        $format = $options->format->value;

        // 分段基础参数
        $command = array_merge($command, [
            '-f',
            'segment',                           // 使用分段格式
            '-segment_time',
            (string)$this->splitStrategy->getMaxSegmentDuration(),
            '-reset_timestamps',
            '1'                   // 重置时间戳
        ]);

        // 格式特定的分段参数
        switch ($format) {
            case 'mp4':
                $command = array_merge($command, [
                    '-segment_format',
                    'mp4',
                    '-movflags',
                    '+frag_keyframe+empty_moov'  // MP4 优化
                ]);
                break;

            case 'ts':
                $command = array_merge($command, [
                    '-segment_format',
                    'mpegts'
                ]);
                break;

            case 'mkv':
                $command = array_merge($command, [
                    '-segment_format',
                    'matroska'
                ]);
                break;
        }

        // 输出路径模式
        $command[] = $segmentPathPattern;
    }

    /**
     * 生成分段路径模式
     * 
     * @param string $baseOutputPath 基础输出路径
     * @return string 分段路径模式
     */
    private function generateSegmentPathPattern(string $baseOutputPath): string
    {
        $pathInfo = pathinfo($baseOutputPath);
        $baseName = $pathInfo['dirname'] . '/' . $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? 'mp4';

        // 返回类似 DouyinLiveRecorder 的命名模式
        return sprintf('%s_%%03d.%s', $baseName, $extension);
    }

    /**
     * 启动 FFmpeg 进程
     * 
     * @param array $command FFmpeg 命令
     * @param callable|null $progressCallback 进度回调
     */
    private function startFFmpegProcess(array $command, ?callable $progressCallback = null): void
    {
        $this->ensureOutputDirectoryExists(dirname($command[array_key_last($command)]));

        // 启动进程
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $this->currentProcess = proc_open(
            implode(' ', array_map('escapeshellarg', $command)),
            $descriptorSpec,
            $this->currentPipes
        );

        if (!is_resource($this->currentProcess)) {
            throw new RecordingException('无法启动 FFmpeg 进程');
        }

        // 设置非阻塞模式
        stream_set_blocking($this->currentPipes[1], false);
        stream_set_blocking($this->currentPipes[2], false);
    }

    /**
     * 监控录制进程
     * 
     * @param callable|null $progressCallback 进度回调
     */
    private function monitorRecordingProcess(?callable $progressCallback = null): void
    {
        $startTime = time();
        $lastProgressUpdate = 0;

        while (!$this->shouldStop) {
            $status = proc_get_status($this->currentProcess);

            if (!$status['running']) {
                echo "\n📹 FFmpeg 进程已结束\n";
                break;
            }

            // 读取进程输出
            $output = stream_get_contents($this->currentPipes[2]);
            if ($output) {
                $this->parseFFmpegOutput($output);
            }

            // 更新进度信息
            $elapsed = time() - $startTime;
            if ($elapsed - $lastProgressUpdate >= 5) {  // 每5秒更新一次
                echo "\r⏱️  录制中... {$elapsed} 秒";
                $lastProgressUpdate = $elapsed;

                // 调用进度回调
                if ($progressCallback) {
                    call_user_func($progressCallback, $elapsed, $this->segments->count());
                }
            }

            // 检查停止条件
            if ($this->shouldCheckStopConditions($startTime)) {
                echo "\n⏰ 达到录制时间限制或直播结束\n";
                break;
            }

            usleep(500000); // 0.5秒检查一次
        }

        $this->gracefulStopProcess();
    }

    /**
     * 解析 FFmpeg 输出
     * 
     * @param string $output FFmpeg 输出内容
     */
    private function parseFFmpegOutput(string $output): void
    {
        // 解析分段信息，检测新生成的分段
        if (preg_match('/Opening \'([^\']+)\' for writing/', $output, $matches)) {
            $segmentPath = $matches[1];
            echo "\n📹 开始录制分段: " . basename($segmentPath) . "\n";
        }

        // 检测错误信息
        if (strpos($output, '[error]') !== false || strpos($output, 'Connection refused') !== false) {
            echo "\n⚠️  检测到网络错误，FFmpeg 将尝试重连...\n";
        }
    }

    /**
     * 收集生成的分段文件
     * 
     * @param string $baseOutputPath 基础输出路径
     */
    private function collectGeneratedSegments(string $baseOutputPath): void
    {
        $pathInfo = pathinfo($baseOutputPath);
        $pattern = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_*.{' . $pathInfo['extension'] . '}';

        $segmentFiles = glob($pattern, GLOB_BRACE);
        sort($segmentFiles, SORT_NATURAL);

        foreach ($segmentFiles as $index => $filePath) {
            if (file_exists($filePath) && filesize($filePath) > 0) {
                $segment = new SegmentInfo(
                    index: $index + 1,
                    startTime: filemtime($filePath),
                    duration: $this->splitStrategy->getMaxSegmentDuration(),
                    outputPath: $filePath
                );

                $segment->markAsCompleted();
                $segment->setFileSize(filesize($filePath) / 1024 / 1024);

                $this->segments->add($segment);
            }
        }
    }

    /**
     * 检查停止条件
     * 
     * @param int $startTime 开始时间
     * @return bool 是否应该停止
     */
    private function shouldCheckStopConditions(int $startTime): bool
    {
        $options = $this->pendingRecorder->getOptions();
        $elapsed = time() - $startTime;

        // 检查总录制时间限制
        if ($options->timeoutSeconds > 0 && $elapsed >= $options->timeoutSeconds) {
            return true;
        }

        // 检查直播状态（每30秒检查一次）
        static $lastStreamCheck = 0;
        if (time() - $lastStreamCheck >= 30) {
            if (!$this->isStreamStillLive()) {
                return true;
            }
            $lastStreamCheck = time();
        }

        return false;
    }

    /**
     * 优雅停止进程
     */
    private function gracefulStopProcess(): void
    {
        if (!is_resource($this->currentProcess)) {
            return;
        }

        // 发送 'q' 命令优雅停止 FFmpeg
        if (isset($this->currentPipes[0]) && is_resource($this->currentPipes[0])) {
            fwrite($this->currentPipes[0], 'q');
            fclose($this->currentPipes[0]);
        }

        // 等待进程结束
        $timeout = 10; // 10秒超时
        $start = time();

        while (time() - $start < $timeout) {
            $status = proc_get_status($this->currentProcess);
            if (!$status['running']) {
                break;
            }
            usleep(500000);
        }

        // 强制终止（如果仍在运行）
        $status = proc_get_status($this->currentProcess);
        if ($status['running']) {
            proc_terminate($this->currentProcess);
            echo "⚠️  强制终止 FFmpeg 进程\n";
        }

        proc_close($this->currentProcess);
        $this->currentProcess = null;
    }

    /**
     * 检查直播是否仍在进行
     */
    private function isStreamStillLive(): bool
    {
        try {
            return $this->pendingRecorder->getRoomInfo()->isLive();
        } catch (Throwable $e) {
            return true; // 网络错误时假设仍在直播
        }
    }

    /**
     * 验证配置
     */
    private function validateConfiguration(): void
    {
        $options = $this->pendingRecorder->getOptions();

        // 检查格式支持
        $supportedFormats = ['mp4', 'ts', 'mkv'];
        if (!in_array($options->format->value, $supportedFormats)) {
            throw RecordingException::invalidConfiguration(
                "原生分割器不支持 {$options->format->value} 格式，支持的格式：" . implode(', ', $supportedFormats)
            );
        }
    }

    /**
     * 确保输出目录存在
     */
    private function ensureOutputDirectoryExists(string $outputDir): void
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
    }

    /**
     * 记录完成摘要
     */
    private function logCompletionSummary(): void
    {
        $info = $this->getSegmentInfo();

        echo "\n🎬 原生 FFmpeg 分割录制完成！\n";
        echo "实现方式: {$info['implementation']}\n";
        echo "分割策略: {$info['strategy']}\n";
        echo "总分段数: {$info['total_segments']}\n";
        echo "成功分段: {$info['completed_segments']}\n";
        echo "总大小: " . number_format($info['total_size'], 2) . " MB\n\n";

        echo "🚀 原生分割的优势:\n";
        echo "  ✅ 真正的实时分割\n";
        echo "  ✅ 无额外内存开销\n";
        echo "  ✅ 无需后处理步骤\n";
        echo "  ✅ FFmpeg 原生优化\n";
        echo "  ✅ 网络重连和容错\n\n";

        if ($this->segments->count() > 0) {
            echo "生成的文件:\n";
            foreach ($this->segments->getCompleted() as $i => $segment) {
                echo "  " . ($i + 1) . ". " . basename($segment->outputPath);
                echo " (" . number_format($segment->fileSize, 2) . " MB)\n";
            }
        }
    }

    /**
     * 设置信号处理器
     */
    private function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'stop']);
            pcntl_signal(SIGTERM, [$this, 'stop']);
        }
    }

    /**
     * 清理资源
     */
    private function cleanup(): void
    {
        if (is_resource($this->currentProcess)) {
            proc_close($this->currentProcess);
        }

        foreach ($this->currentPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $this->currentPipes = [];
        $this->currentProcess = null;
    }
}
