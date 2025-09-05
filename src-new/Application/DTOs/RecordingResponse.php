<?php

declare(strict_types=1);

namespace LiveStream\Application\DTOs;

use LiveStream\Domain\ValueObjects\RecordingId;
use LiveStream\Domain\ValueObjects\RecordingStatus;
use LiveStream\Domain\Factories\RecordHandle;

/**
 * 录制响应DTO
 * 
 * 封装录制操作的响应结果
 */
final readonly class RecordingResponse
{
    public function __construct(
        public RecordingId $id,
        public RecordingStatus $status,
        public ?RecordHandle $handle = null,
        public ?string $message = null,
        public array $metadata = []
    ) {}

    /**
     * 创建成功响应
     *
     * @param RecordingId $id
     * @param RecordHandle $handle
     * @param string|null $message
     * @return self
     */
    public static function success(
        RecordingId $id,
        RecordHandle $handle,
        ?string $message = null
    ): self {
        return new self(
            id: $id,
            status: RecordingStatus::RECORDING,
            handle: $handle,
            message: $message ?? 'Recording started successfully'
        );
    }

    /**
     * 创建失败响应
     *
     * @param RecordingId $id
     * @param string $message
     * @param array $metadata
     * @return self
     */
    public static function failure(
        RecordingId $id,
        string $message,
        array $metadata = []
    ): self {
        return new self(
            id: $id,
            status: RecordingStatus::FAILED,
            handle: null,
            message: $message,
            metadata: $metadata
        );
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'status' => $this->status->value,
            'status_display' => $this->status->getDisplayName(),
            'message' => $this->message,
            'handle_id' => $this->handle?->getId(),
            'is_recording' => $this->handle?->isRecording() ?? false,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * 检查是否成功
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->status !== RecordingStatus::FAILED && $this->handle !== null;
    }

    /**
     * 获取录制句柄ID
     *
     * @return string|null
     */
    public function getHandleId(): ?string
    {
        return $this->handle?->getId();
    }

    /**
     * 检查是否正在录制
     *
     * @return bool
     */
    public function isRecording(): bool
    {
        return $this->handle?->isRecording() ?? false;
    }

    /**
     * 获取元数据
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}