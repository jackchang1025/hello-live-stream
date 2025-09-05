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
 * 实时分割录制器
 * 
 * 真正的实时分割：在录制过程中直接分割，而不是后处理
 * 
 * 特性：
 * - 实时分割：录制时直接输出分段文件
 * - 动态停止：支持主播下播时自动停止
 * - 多种策略：时间分割、大小分割、混合分割
 * - 内存友好：不需要存储完整视频
 * - 故障恢复：支持网络中断恢复
 */
final class RealtimeSplitter implements SplitterInterface
{
    private readonly SegmentCollection $segments;
    private bool $shouldStop = false;
    private int $currentSegmentIndex = 1;
    private ?resource $currentProcess = null;

    public function __construct(
        private readonly FFMpeg $ffmpeg,
        private readonly FFProbe $ffprobe,
        private readonly PendingRecorder $pendingRecorder,
        private readonly SplitStrategy $splitStrategy
    ) {
        $this->segments = new SegmentCollection();
    }

    /**
     * 执行实时分割录制
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

            echo "🎬 开始实时分割录制...\n";
            echo "策略: " . $this->splitStrategy->getDescription() . "\n\n";

            $this->executeRealtimeRecording($progressCallback);

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
        ];
    }

    /**
     * 停止录制
     */
    public function stop(): void
    {
        $this->shouldStop = true;

        if ($this->currentProcess && is_resource($this->currentProcess)) {
            proc_terminate($this->currentProcess);
        }

        echo "\n⏹️  录制停止信号已发送\n";
    }

    // ==================== 私有方法 ====================

    /**
     * 执行实时录制
     * 
     * @param callable|null $progressCallback 进度回调
     */
    private function executeRealtimeRecording(?callable $progressCallback = null): void
    {
        $streamUrl = $this->pendingRecorder->getStreamConfig()->getRecordUrl();
        $startTime = time();

        while (!$this->shouldStop) {
            // 检查直播状态
            if (!$this->isStreamStillLive()) {
                echo "📡 检测到直播已结束，停止录制\n";
                break;
            }

            // 创建当前分段
            $segment = $this->createCurrentSegment();
            $this->segments->add($segment);

            echo "📹 开始录制第 {$segment->index} 段: " . basename($segment->outputPath) . "\n";

            try {
                // 实时录制当前分段
                $this->recordSegmentRealtime($segment, $streamUrl, $progressCallback);

                // 检查分割条件
                if ($this->splitStrategy->shouldSplit($segment, $this->segments)) {
                    $this->currentSegmentIndex++;
                    echo "✂️  分割条件满足，开始下一段\n";
                }

                // 检查总体停止条件
                if ($this->shouldStopRecording($startTime)) {
                    echo "⏰ 达到录制时间限制，停止录制\n";
                    break;
                }
            } catch (Throwable $e) {
                $segment->markAsFailed($e->getMessage());
                echo "❌ 第 {$segment->index} 段录制失败: " . $e->getMessage() . "\n";

                if ($this->shouldStopOnError()) {
                    throw $e;
                }

                // 继续下一段
                $this->currentSegmentIndex++;
            }
        }
    }

    /**
     * 实时录制单个分段
     * 
     * @param SegmentInfo $segment 分段信息
     * @param string $streamUrl 流地址
     * @param callable|null $progressCallback 进度回调
     */
    private function recordSegmentRealtime(SegmentInfo $segment, string $streamUrl, ?callable $progressCallback = null): void
    {
        $segment->markAsRecording();

        // 确保输出目录存在
        $this->ensureOutputDirectoryExists($segment->outputPath);

        // 构建 FFmpeg 命令（实时录制）
        $command = $this->buildRealtimeCommand($streamUrl, $segment);

        // 启动录制进程
        $this->currentProcess = proc_open(
            $command,
            [
                0 => ['pipe', 'r'], // stdin
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ],
            $pipes,
            dirname($segment->outputPath)
        );

        if (!is_resource($this->currentProcess)) {
            throw new RecordingException('Failed to start recording process');
        }

        // 关闭 stdin
        fclose($pipes[0]);

        // 监控录制进程
        $this->monitorRecordingProcess($segment, $pipes, $progressCallback);

        $segment->markAsCompleted();
        $this->logSegmentCompletion($segment);
    }

    /**
     * 监控录制进程
     * 
     * @param SegmentInfo $segment 分段信息
     * @param array $pipes 进程管道
     * @param callable|null $progressCallback 进度回调
     */
    private function monitorRecordingProcess(SegmentInfo $segment, array $pipes, ?callable $progressCallback = null): void
    {
        $startTime = time();
        $maxDuration = $this->splitStrategy->getMaxSegmentDuration();

        while (proc_get_status($this->currentProcess)['running'] && !$this->shouldStop) {
            $elapsed = time() - $startTime;

            // 进度回调
            if ($progressCallback) {
                $progress = $maxDuration > 0 ? min(100, ($elapsed / $maxDuration) * 100) : 0;
                call_user_func($progressCallback, null, null, $progress, $segment);
            }

            // 检查分割条件（时间）
            if ($this->splitStrategy->shouldSplitByTime($elapsed)) {
                echo "\n⏰ 时间分割条件满足 ({$elapsed}秒)\n";
                break;
            }

            // 检查分割条件（文件大小）
            if ($this->splitStrategy->shouldSplitBySize($segment->outputPath)) {
                $fileSize = file_exists($segment->outputPath) ? filesize($segment->outputPath) / 1024 / 1024 : 0;
                echo "\n📦 大小分割条件满足 ({$fileSize}MB)\n";
                break;
            }

            // 检查直播状态
            if ($elapsed % 10 === 0 && !$this->isStreamStillLive()) {
                echo "\n📡 直播已结束\n";
                $this->shouldStop = true;
                break;
            }

            sleep(1);
        }

        // 优雅停止进程
        if (is_resource($this->currentProcess)) {
            proc_terminate($this->currentProcess);
            $exitCode = proc_close($this->currentProcess);

            // 读取错误输出
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            if ($exitCode !== 0 && !empty($stderr) && !$this->shouldStop) {
                throw new RecordingException("Recording failed: " . $stderr);
            }
        }
    }

    /**
     * 构建实时录制命令
     * 
     * @param string $streamUrl 流地址
     * @param SegmentInfo $segment 分段信息
     * @return array 命令数组
     */
    private function buildRealtimeCommand(string $streamUrl, SegmentInfo $segment): array
    {
        $options = $this->pendingRecorder->getOptions();

        $command = [
            'ffmpeg',
            '-y', // 覆盖输出文件
            '-i',
            $streamUrl,
            '-c',
            'copy', // 复制流，不重新编码（最快）
            '-avoid_negative_ts',
            'make_zero', // 避免负时间戳
            '-fflags',
            '+genpts', // 生成时间戳
        ];

        // 添加格式特定参数
        $command = array_merge($command, $this->getFormatSpecificOptions($options->format));

        // 添加海外优化参数
        if ($this->pendingRecorder->getEnableOverseasOptimization()) {
            $command = array_merge($command, [
                '-timeout',
                '50000000',
                '-reconnect',
                '1',
                '-reconnect_streamed',
                '1',
                '-reconnect_delay_max',
                '60'
            ]);
        }

        $command[] = $segment->outputPath;

        return $command;
    }

    /**
     * 创建当前分段信息
     * 
     * @return SegmentInfo 分段信息
     */
    private function createCurrentSegment(): SegmentInfo
    {
        $baseOutputPath = $this->pendingRecorder->getOutputPath();
        $segmentPath = $this->generateSegmentPath($baseOutputPath, $this->currentSegmentIndex);

        return new SegmentInfo(
            index: $this->currentSegmentIndex,
            startTime: time(),
            duration: $this->splitStrategy->getMaxSegmentDuration(),
            outputPath: $segmentPath
        );
    }

    /**
     * 生成分段文件路径
     * 
     * @param string $baseOutputPath 基础输出路径
     * @param int $index 分段索引
     * @return string 分段文件路径
     */
    private function generateSegmentPath(string $baseOutputPath, int $index): string
    {
        $pathInfo = pathinfo($baseOutputPath);
        $baseName = $pathInfo['dirname'] . '/' . $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? 'mp4';

        return sprintf('%s_part%03d.%s', $baseName, $index, $extension);
    }

    /**
     * 检查直播是否仍在进行
     * 
     * @return bool 是否仍在直播
     */
    private function isStreamStillLive(): bool
    {
        try {
            return $this->pendingRecorder->getRoomInfo()->isLive();
        } catch (Throwable $e) {
            // 网络错误时假设仍在直播，避免误停止
            return true;
        }
    }

    /**
     * 检查是否应该停止录制
     * 
     * @param int $startTime 开始时间
     * @return bool 是否应该停止
     */
    private function shouldStopRecording(int $startTime): bool
    {
        $options = $this->pendingRecorder->getOptions();
        $elapsed = time() - $startTime;

        return $elapsed >= $options->timeoutSeconds;
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
        if ($this->currentProcess && is_resource($this->currentProcess)) {
            proc_close($this->currentProcess);
        }
    }

    /**
     * 其他辅助方法...
     */
    private function validateConfiguration(): void
    {
        // 验证逻辑
    }

    private function ensureOutputDirectoryExists(string $outputPath): void
    {
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
    }

    private function getFormatSpecificOptions(\LiveStream\Enum\OutputFormat $format): array
    {
        return match ($format->value) {
            'mp4' => ['-f', 'mp4'],
            'webm' => ['-f', 'webm'],
            'mkv' => ['-f', 'matroska'],
            'flv' => ['-f', 'flv'],
            default => ['-f', 'mp4'],
        };
    }

    private function logSegmentCompletion(SegmentInfo $segment): void
    {
        if (file_exists($segment->outputPath)) {
            $fileSize = filesize($segment->outputPath) / 1024 / 1024;
            $segment->setFileSize($fileSize);
            echo "✅ 第 {$segment->index} 段录制完成，文件大小: " . number_format($fileSize, 2) . " MB\n";
        }
    }

    private function logCompletionSummary(): void
    {
        $info = $this->getSegmentInfo();
        echo "\n🎬 实时分割录制完成！\n";
        echo "分割策略: {$info['strategy']}\n";
        echo "总分段数: {$info['total_segments']}\n";
        echo "成功分段: {$info['completed_segments']}\n";
        echo "总大小: " . number_format($info['total_size'], 2) . " MB\n";
    }

    private function shouldStopOnError(): bool
    {
        return $this->segments->countFailed() > 3; // 连续失败3次则停止
    }
}
