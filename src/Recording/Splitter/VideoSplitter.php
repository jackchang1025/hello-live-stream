<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Media\Video;
use FFMpeg\Coordinate\TimeCode;
use LiveStream\Recording\PendingRecorder;
use LiveStream\Exceptions\RecordingException;
use LiveStream\Recording\Splitter\Contracts\SplitterInterface;
use LiveStream\Recording\Splitter\ValueObjects\SegmentInfo;
use LiveStream\Recording\Splitter\Collections\SegmentCollection;
use Throwable;

/**
 * 视频分割器
 * 
 * 负责将长视频按时间或文件大小分割成多个片段
 * 使用 php-ffmpeg 包进行优雅的视频处理
 * 
 * 特性：
 * - 基于时间的分割
 * - 基于文件大小的分割（未来实现）
 * - 进度监控和回调
 * - 优雅的错误处理
 * - 符合 SOLID 原则的设计
 */
final class VideoSplitter implements SplitterInterface
{
    private readonly Video $video;
    private readonly SegmentCollection $segments;

    public function __construct(
        private readonly FFMpeg $ffmpeg,
        private readonly FFProbe $ffprobe,
        private readonly PendingRecorder $pendingRecorder
    ) {
        $this->video = $this->openVideo();
        $this->segments = new SegmentCollection();
    }

    /**
     * 执行分割录制
     * 
     * @param callable|null $progressCallback 进度回调函数
     * @return PendingRecorder 录制结果
     * @throws RecordingException 当分割失败时
     */
    public function execute(?callable $progressCallback = null): PendingRecorder
    {
        try {
            $this->validateConfiguration();
            $this->prepareSegments();

            foreach ($this->segments as $segment) {
                $this->recordSegment($segment, $progressCallback);
            }

            $this->logCompletionSummary();

            return $this->pendingRecorder;
        } catch (RecordingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw RecordingException::fromException($e, $this->pendingRecorder->getRecordId());
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
        ];
    }

    // ==================== 私有方法 ====================

    /**
     * 打开视频流
     * 
     * @return Video 视频对象
     * @throws RecordingException 当无法打开视频时
     */
    private function openVideo(): Video
    {
        try {
            $streamUrl = $this->pendingRecorder->getStreamConfig()->getRecordUrl();
            return $this->ffmpeg->open($streamUrl);
        } catch (Throwable $e) {
            throw RecordingException::fromException($e, $this->pendingRecorder->getRecordId());
        }
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
     * 准备分段信息
     */
    private function prepareSegments(): void
    {
        $options = $this->pendingRecorder->getOptions();
        $baseOutputPath = $this->pendingRecorder->getOutputPath();

        $segmentDuration = $options->splitTime ?? 300; // 默认5分钟
        $totalDuration = $options->timeoutSeconds;
        $segmentCount = (int)ceil($totalDuration / $segmentDuration);

        for ($i = 1; $i <= $segmentCount; $i++) {
            $startTime = ($i - 1) * $segmentDuration;
            $actualDuration = min($segmentDuration, $totalDuration - $startTime);

            if ($actualDuration <= 0) {
                break;
            }

            $segment = new SegmentInfo(
                index: $i,
                startTime: $startTime,
                duration: $actualDuration,
                outputPath: $this->generateSegmentPath($baseOutputPath, $i)
            );

            $this->segments->add($segment);
        }
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
     * 录制单个分段
     * 
     * @param SegmentInfo $segment 分段信息
     * @param callable|null $progressCallback 进度回调
     * @throws RecordingException 当录制失败时
     */
    private function recordSegment(SegmentInfo $segment, ?callable $progressCallback = null): void
    {
        try {
            echo "📹 开始录制第 {$segment->index} 段: " . basename($segment->outputPath) . "\n";

            // 确保输出目录存在
            $this->ensureOutputDirectoryExists($segment->outputPath);

            // 创建格式策略
            $format = $this->createFormatStrategy();

            // 设置进度回调
            if ($progressCallback && method_exists($format, 'on')) {
                $format->on('progress', function ($video, $format, $percentage) use ($progressCallback, $segment) {
                    call_user_func($progressCallback, $video, $format, $percentage, $segment);
                });
            }

            // 使用 php-ffmpeg 的 clip 方法进行精确分割
            $clip = $this->video->clip(
                TimeCode::fromSeconds($segment->startTime),
                TimeCode::fromSeconds($segment->duration)
            );

            // 保存分段
            $clip->save($format, $segment->outputPath);

            // 更新分段状态
            $segment->markAsCompleted();
            $this->logSegmentCompletion($segment);
        } catch (Throwable $e) {
            $segment->markAsFailed($e->getMessage());
            echo "❌ 第 {$segment->index} 段录制失败: " . $e->getMessage() . "\n";

            // 根据配置决定是否继续或停止
            if (!$this->shouldContinueAfterFailure()) {
                throw RecordingException::fromException($e, $this->pendingRecorder->getRecordId());
            }
        }
    }

    /**
     * 创建格式策略
     * 
     * @return mixed 格式对象
     */
    private function createFormatStrategy(): mixed
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
            $fileSize = filesize($segment->outputPath) / 1024 / 1024; // MB
            $segment->setFileSize($fileSize);

            echo "✅ 第 {$segment->index} 段录制完成，文件大小: " . number_format($fileSize, 2) . " MB\n";

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

        echo "\n🎬 分割录制完成！\n";
        echo "总分段数: {$info['total_segments']}\n";
        echo "成功分段: {$info['completed_segments']}\n";
        echo "失败分段: {$info['failed_segments']}\n";
        echo "总时长: " . number_format($info['total_duration'], 1) . " 秒\n";
        echo "总大小: " . number_format($info['total_size'], 2) . " MB\n\n";

        echo "生成的文件:\n";
        foreach ($this->segments->getCompleted() as $i => $segment) {
            echo "  " . ($i + 1) . ". " . basename($segment->outputPath) . " (" . number_format($segment->fileSize, 2) . " MB)\n";
        }
    }

    /**
     * 判断失败后是否继续
     * 
     * @return bool 是否继续录制
     */
    private function shouldContinueAfterFailure(): bool
    {
        // 可以根据配置或策略决定
        // 目前简单处理：如果失败段数超过总数的50%则停止
        $failedCount = $this->segments->countFailed();
        $totalCount = $this->segments->count();

        return $failedCount < ($totalCount * 0.5);
    }
}
