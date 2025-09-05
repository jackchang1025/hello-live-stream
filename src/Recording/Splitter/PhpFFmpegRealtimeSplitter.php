<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Media\Video;
use FFMpeg\Format\FormatInterface;
use LiveStream\Recording\PendingRecorder;
use LiveStream\Exceptions\RecordingException;
use LiveStream\Recording\Splitter\Contracts\SplitterInterface;
use LiveStream\Recording\Splitter\ValueObjects\SegmentInfo;
use LiveStream\Recording\Splitter\Collections\SegmentCollection;
use LiveStream\Recording\Splitter\Strategies\SplitStrategy;
use Throwable;

/**
 * 基于 php-ffmpeg 的实时分割录制器
 * 
 * 使用 php-ffmpeg 扩展包进行录制，通过监控文件大小和时间实现分割
 * 遵循 PHP 最佳实践和 Laravel 工匠精神
 * 
 * 特性：
 * - 使用 php-ffmpeg 扩展包统一录制接口
 * - 实时监控分割条件（时间、大小）
 * - 优雅的进程管理和错误处理
 * - 支持动态停止和故障恢复
 * - 完全符合现有架构设计
 */
final class PhpFFmpegRealtimeSplitter implements SplitterInterface
{
    private readonly SegmentCollection $segments;
    private bool $shouldStop = false;
    private int $currentSegmentIndex = 1;
    private ?Video $currentVideo = null;
    private ?string $currentOutputPath = null;
    private int $currentSegmentStartTime = 0;

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

            echo "🎬 开始基于 php-ffmpeg 的实时分割录制...\n";
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
            'implementation' => 'php-ffmpeg',
        ];
    }

    /**
     * 停止录制
     */
    public function stop(): void
    {
        $this->shouldStop = true;
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
                // 使用 php-ffmpeg 录制当前分段
                $this->recordSegmentWithPhpFFmpeg($segment, $progressCallback);

                $this->currentSegmentIndex++;

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
     * 使用 php-ffmpeg 录制单个分段
     * 
     * @param SegmentInfo $segment 分段信息
     * @param callable|null $progressCallback 进度回调
     */
    private function recordSegmentWithPhpFFmpeg(SegmentInfo $segment, ?callable $progressCallback = null): void
    {
        $segment->markAsRecording();

        // 确保输出目录存在
        $this->ensureOutputDirectoryExists($segment->outputPath);

        // 使用 php-ffmpeg 打开流
        $streamUrl = $this->pendingRecorder->getStreamConfig()->getRecordUrl();
        $this->currentVideo = $this->ffmpeg->open($streamUrl);
        $this->currentOutputPath = $segment->outputPath;
        $this->currentSegmentStartTime = time();

        // 创建格式策略
        $format = $this->createFormatStrategy();

        // 设置进度回调和分割监控
        $this->setupProgressCallbackWithSplitMonitoring($format, $segment, $progressCallback);

        // 开始录制（这里会阻塞直到录制完成或被中断）
        $this->currentVideo->save($format, $segment->outputPath);

        // 标记完成
        $segment->markAsCompleted();
        $this->logSegmentCompletion($segment);
    }

    /**
     * 设置进度回调和分割监控
     * 
     * @param FormatInterface $format 格式对象
     * @param SegmentInfo $segment 分段信息
     * @param callable|null $progressCallback 用户进度回调
     */
    private function setupProgressCallbackWithSplitMonitoring(FormatInterface $format, SegmentInfo $segment, ?callable $progressCallback = null): void
    {
        if (method_exists($format, 'on')) {
            $format->on('progress', function ($video, $format, $percentage) use ($segment, $progressCallback) {
                // 调用用户的进度回调
                if ($progressCallback) {
                    call_user_func($progressCallback, $video, $format, $percentage, $segment);
                }

                // 检查分割条件
                $this->checkSplitConditions($segment);
            });
        }
    }

    /**
     * 检查分割条件
     * 
     * @param SegmentInfo $segment 当前分段
     */
    private function checkSplitConditions(SegmentInfo $segment): void
    {
        $elapsedTime = time() - $this->currentSegmentStartTime;

        // 检查时间条件
        if ($this->splitStrategy->shouldSplitByTime($elapsedTime)) {
            echo "\n⏰ 时间分割条件满足 ({$elapsedTime}秒)\n";
            $this->stopCurrentRecording();
            return;
        }

        // 检查文件大小条件
        if ($this->splitStrategy->shouldSplitBySize($this->currentOutputPath ?? '')) {
            $fileSize = $this->getCurrentFileSize();
            echo "\n📦 大小分割条件满足 ({$fileSize}MB)\n";
            $this->stopCurrentRecording();
            return;
        }

        // 定期检查直播状态（每10秒检查一次）
        static $lastStreamCheck = 0;
        if (time() - $lastStreamCheck >= 10) {
            if (!$this->isStreamStillLive()) {
                echo "\n📡 直播已结束\n";
                $this->shouldStop = true;
                $this->stopCurrentRecording();
            }
            $lastStreamCheck = time();
        }
    }

    /**
     * 停止当前录制
     */
    private function stopCurrentRecording(): void
    {
        // php-ffmpeg 没有直接的停止方法，我们通过设置标志位
        // 在下次进度回调时会检查这个标志位
        // 这里我们使用一个技巧：抛出一个特殊的异常来中断录制
        throw new RecordingException('Split condition met - stopping current segment');
    }

    /**
     * 获取当前文件大小（MB）
     * 
     * @return float 文件大小
     */
    private function getCurrentFileSize(): float
    {
        if (!$this->currentOutputPath || !file_exists($this->currentOutputPath)) {
            return 0.0;
        }

        return filesize($this->currentOutputPath) / 1024 / 1024;
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
     * 创建格式策略
     * 
     * @return FormatInterface 格式对象
     */
    private function createFormatStrategy(): FormatInterface
    {
        $options = $this->pendingRecorder->getOptions();

        return match ($options->format->value) {
            'mp4' => new \FFMpeg\Format\Video\X264('aac', 'libx264'),
            'webm' => new \FFMpeg\Format\Video\WebM(),
            'mp3' => new \FFMpeg\Format\Audio\Mp3(),
            'aac' => new \FFMpeg\Format\Audio\Aac(),
            default => new \FFMpeg\Format\Video\X264('aac', 'libx264'),
        };
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

        return $options->timeoutSeconds > 0 && $elapsed >= $options->timeoutSeconds;
    }

    /**
     * 验证配置
     * 
     * @throws RecordingException 当配置无效时
     */
    private function validateConfiguration(): void
    {
        $options = $this->pendingRecorder->getOptions();

        if ($options->splitTime === null && $options->maxFileSize === null) {
            throw RecordingException::invalidConfiguration('分割录制需要设置 splitTime 或 maxFileSize');
        }

        if ($options->splitTime !== null && $options->splitTime <= 0) {
            throw RecordingException::invalidConfiguration('splitTime 必须大于 0');
        }

        if ($options->maxFileSize !== null && $options->maxFileSize <= 0) {
            throw RecordingException::invalidConfiguration('maxFileSize 必须大于 0');
        }
    }

    /**
     * 确保输出目录存在
     * 
     * @param string $outputPath 输出路径
     */
    private function ensureOutputDirectoryExists(string $outputPath): void
    {
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
    }

    /**
     * 记录分段完成信息
     * 
     * @param SegmentInfo $segment 分段信息
     */
    private function logSegmentCompletion(SegmentInfo $segment): void
    {
        if (file_exists($segment->outputPath)) {
            $fileSize = filesize($segment->outputPath) / 1024 / 1024;
            $segment->setFileSize($fileSize);

            $duration = time() - $segment->startTime;
            echo "✅ 第 {$segment->index} 段录制完成";
            echo " - 大小: " . number_format($fileSize, 2) . " MB";
            echo " - 时长: {$duration} 秒\n";

            // 检查文件是否过小
            if ($fileSize < 0.1) {
                echo "⚠️  文件过小，可能录制出错\n";
            }
        }
    }

    /**
     * 记录完成摘要
     */
    private function logCompletionSummary(): void
    {
        $info = $this->getSegmentInfo();

        echo "\n🎬 php-ffmpeg 实时分割录制完成！\n";
        echo "实现方式: {$info['implementation']}\n";
        echo "分割策略: {$info['strategy']}\n";
        echo "总分段数: {$info['total_segments']}\n";
        echo "成功分段: {$info['completed_segments']}\n";
        echo "失败分段: {$info['failed_segments']}\n";
        echo "总大小: " . number_format($info['total_size'], 2) . " MB\n\n";

        echo "生成的文件:\n";
        foreach ($this->segments->getCompleted() as $i => $segment) {
            echo "  " . ($i + 1) . ". " . basename($segment->outputPath);
            echo " (" . number_format($segment->fileSize, 2) . " MB)\n";
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
        $this->currentVideo = null;
        $this->currentOutputPath = null;
    }

    /**
     * 判断失败后是否停止
     * 
     * @return bool 是否停止录制
     */
    private function shouldStopOnError(): bool
    {
        return $this->segments->countFailed() > 3; // 连续失败3次则停止
    }
}
