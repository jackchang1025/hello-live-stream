<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter\ValueObjects;

/**
 * 分段信息值对象
 * 
 * 封装单个录制分段的所有信息
 * 遵循值对象模式，不可变且自包含
 */
final class SegmentInfo
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RECORDING = 'recording';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    private string $status = self::STATUS_PENDING;
    private ?string $errorMessage = null;

    public function __construct(
        public readonly int $index,
        public readonly int $startTime,
        public readonly int $duration,
        public readonly string $outputPath,
        public float $fileSize = 0.0
    ) {}

    /**
     * 标记为录制中
     */
    public function markAsRecording(): void
    {
        $this->status = self::STATUS_RECORDING;
    }

    /**
     * 标记为已完成
     */
    public function markAsCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->errorMessage = null;
    }

    /**
     * 标记为失败
     * 
     * @param string $errorMessage 错误信息
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->status = self::STATUS_FAILED;
        $this->errorMessage = $errorMessage;
    }

    /**
     * 设置文件大小
     * 
     * @param float $fileSize 文件大小（MB）
     */
    public function setFileSize(float $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    /**
     * 获取状态
     * 
     * @return string 当前状态
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * 获取错误信息
     * 
     * @return string|null 错误信息
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * 是否已完成
     * 
     * @return bool 是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 是否失败
     * 
     * @return bool 是否失败
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 是否正在录制
     * 
     * @return bool 是否正在录制
     */
    public function isRecording(): bool
    {
        return $this->status === self::STATUS_RECORDING;
    }

    /**
     * 获取结束时间
     * 
     * @return int 结束时间（秒）
     */
    public function getEndTime(): int
    {
        return $this->startTime + $this->duration;
    }

    /**
     * 获取时间范围描述
     * 
     * @return string 时间范围描述
     */
    public function getTimeRange(): string
    {
        $startFormatted = gmdate('H:i:s', $this->startTime);
        $endFormatted = gmdate('H:i:s', $this->getEndTime());

        return "{$startFormatted} - {$endFormatted}";
    }

    /**
     * 转换为数组
     * 
     * @return array 分段信息数组
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'start_time' => $this->startTime,
            'duration' => $this->duration,
            'end_time' => $this->getEndTime(),
            'time_range' => $this->getTimeRange(),
            'output_path' => $this->outputPath,
            'file_size' => $this->fileSize,
            'status' => $this->status,
            'error_message' => $this->errorMessage,
            'is_completed' => $this->isCompleted(),
            'is_failed' => $this->isFailed(),
            'is_recording' => $this->isRecording(),
        ];
    }
}
