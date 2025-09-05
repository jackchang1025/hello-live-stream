<?php

declare(strict_types=1);

namespace LiveStream\Domain\Entities;

use DateTime;
use LiveStream\Domain\ValueObjects\RecordingId;
use LiveStream\Domain\ValueObjects\StreamUrl;
use LiveStream\Domain\ValueObjects\Duration;
use LiveStream\Domain\ValueObjects\RecordingStatus;
use LiveStream\Shared\Exceptions\DomainException;

/**
 * 录制实体
 * 
 * 录制任务的业务逻辑封装
 */
final class Recording
{
    private RecordingStatus $status;
    private ?DateTime $startedAt = null;
    private ?DateTime $completedAt = null;
    private ?DateTime $pausedAt = null;
    private Duration $recordedDuration;
    private array $segments = [];
    private array $errors = [];

    private function __construct(
        private readonly RecordingId $id,
        private readonly StreamUrl $url,
        private readonly string $outputPath,
        private readonly string $quality,
        private readonly array $options = []
    ) {
        $this->status = RecordingStatus::PENDING;
        $this->recordedDuration = Duration::zero();
    }

    /**
     * 创建新的录制任务
     *
     * @param RecordingId $id
     * @param StreamUrl $url
     * @param string $outputPath
     * @param string $quality
     * @param array $options
     * @return self
     */
    public static function create(
        RecordingId $id,
        StreamUrl $url,
        string $outputPath,
        string $quality = 'origin',
        array $options = []
    ): self {
        return new self($id, $url, $outputPath, $quality, $options);
    }

    /**
     * 启动录制
     *
     * @throws InvalidRecordingStateException
     */
    public function start(): void
    {
        if (!$this->status->canStart()) {
            throw new InvalidRecordingStateException(
                "Cannot start recording in status: {$this->status->value}",
                0,
                null,
                ['current_status' => $this->status->value, 'recording_id' => $this->id->getValue()]
            );
        }

        $this->status = RecordingStatus::RECORDING;
        $this->startedAt = new DateTime();
    }

    /**
     * 暂停录制
     *
     * @throws InvalidRecordingStateException
     */
    public function pause(): void
    {
        if (!$this->status->canPause()) {
            throw new InvalidRecordingStateException(
                "Cannot pause recording in status: {$this->status->value}",
                0,
                null,
                ['current_status' => $this->status->value, 'recording_id' => $this->id->getValue()]
            );
        }

        $this->status = RecordingStatus::PAUSED;
        $this->pausedAt = new DateTime();
    }

    /**
     * 恢复录制
     *
     * @throws InvalidRecordingStateException
     */
    public function resume(): void
    {
        if (!$this->status->canResume()) {
            throw new InvalidRecordingStateException(
                "Cannot resume recording in status: {$this->status->value}",
                0,
                null,
                ['current_status' => $this->status->value, 'recording_id' => $this->id->getValue()]
            );
        }

        $this->status = RecordingStatus::RECORDING;
        $this->pausedAt = null;
    }

    /**
     * 完成录制
     *
     * @param Duration $finalDuration
     * @throws InvalidRecordingStateException
     */
    public function complete(Duration $finalDuration): void
    {
        if (!$this->status->canStop()) {
            throw new InvalidRecordingStateException(
                "Cannot complete recording in status: {$this->status->value}",
                0,
                null,
                ['current_status' => $this->status->value, 'recording_id' => $this->id->getValue()]
            );
        }

        $this->status = RecordingStatus::COMPLETED;
        $this->completedAt = new DateTime();
        $this->recordedDuration = $finalDuration;
    }

    /**
     * 标记录制失败
     *
     * @param string $errorMessage
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->status = RecordingStatus::FAILED;
        $this->completedAt = new DateTime();
        $this->errors[] = [
            'message' => $errorMessage,
            'timestamp' => new DateTime(),
        ];
    }

    /**
     * 取消录制
     */
    public function cancel(): void
    {
        if ($this->status->isFinished()) {
            return; // 已结束的录制无法取消
        }

        $this->status = RecordingStatus::CANCELLED;
        $this->completedAt = new DateTime();
    }

    /**
     * 添加分段
     *
     * @param Segment $segment
     */
    public function addSegment(Segment $segment): void
    {
        $this->segments[] = $segment;
    }

    /**
     * 更新录制时长
     *
     * @param Duration $duration
     */
    public function updateDuration(Duration $duration): void
    {
        if ($this->status === RecordingStatus::RECORDING) {
            $this->recordedDuration = $duration;
        }
    }

    /**
     * 添加错误信息
     *
     * @param string $errorMessage
     */
    public function addError(string $errorMessage): void
    {
        $this->errors[] = [
            'message' => $errorMessage,
            'timestamp' => new DateTime(),
        ];
    }

    // Getters
    public function getId(): RecordingId
    {
        return $this->id;
    }

    public function getUrl(): StreamUrl
    {
        return $this->url;
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    public function getQuality(): string
    {
        return $this->quality;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getStatus(): RecordingStatus
    {
        return $this->status;
    }

    public function getStartedAt(): ?DateTime
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTime
    {
        return $this->completedAt;
    }

    public function getPausedAt(): ?DateTime
    {
        return $this->pausedAt;
    }

    public function getRecordedDuration(): Duration
    {
        return $this->recordedDuration;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取录制统计信息
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $totalSegments = count($this->segments);
        $totalErrors = count($this->errors);
        
        $actualDuration = null;
        if ($this->startedAt && $this->completedAt) {
            $actualDuration = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
        }

        return [
            'id' => $this->id->getValue(),
            'status' => $this->status->value,
            'status_display' => $this->status->getDisplayName(),
            'url' => $this->url->getValue(),
            'output_path' => $this->outputPath,
            'quality' => $this->quality,
            'started_at' => $this->startedAt?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
            'recorded_duration' => $this->recordedDuration->format(),
            'recorded_duration_human' => $this->recordedDuration->toHuman(),
            'actual_duration_seconds' => $actualDuration,
            'total_segments' => $totalSegments,
            'total_errors' => $totalErrors,
            'is_active' => $this->status->isActive(),
            'is_finished' => $this->status->isFinished(),
            'is_successful' => $this->status->isSuccessful(),
        ];
    }
}

/**
 * 无效录制状态异常
 */
final class InvalidRecordingStateException extends DomainException
{
    public function getErrorCode(): string
    {
        return 'INVALID_RECORDING_STATE';
    }
}