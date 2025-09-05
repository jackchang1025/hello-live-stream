<?php

declare(strict_types=1);

namespace LiveStream\Application\DTOs;

use LiveStream\Shared\Exceptions\ApplicationException;

/**
 * 录制请求DTO
 * 
 * 封装录制请求的所有参数
 */
final readonly class RecordingRequest
{
    public function __construct(
        public string $url,
        public string $outputPath,
        public string $quality = 'origin',
        public string $format = 'mp4',
        public bool $enableSplitting = false,
        public ?int $splitDuration = null,
        public ?int $splitSize = null,
        public array $options = []
    ) {
        $this->validate();
    }

    /**
     * 从数组创建请求对象
     *
     * @param array $data
     * @return self
     * @throws InvalidRecordingRequestException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'] ?? '',
            outputPath: $data['output_path'] ?? $data['outputPath'] ?? '',
            quality: $data['quality'] ?? 'origin',
            format: $data['format'] ?? 'mp4',
            enableSplitting: $data['enable_splitting'] ?? $data['enableSplitting'] ?? false,
            splitDuration: $data['split_duration'] ?? $data['splitDuration'] ?? null,
            splitSize: $data['split_size'] ?? $data['splitSize'] ?? null,
            options: $data['options'] ?? []
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
            'url' => $this->url,
            'output_path' => $this->outputPath,
            'quality' => $this->quality,
            'format' => $this->format,
            'enable_splitting' => $this->enableSplitting,
            'split_duration' => $this->splitDuration,
            'split_size' => $this->splitSize,
            'options' => $this->options,
        ];
    }

    /**
     * 验证请求参数
     *
     * @throws InvalidRecordingRequestException
     */
    private function validate(): void
    {
        if (empty($this->url)) {
            throw new InvalidRecordingRequestException('URL is required');
        }

        if (empty($this->outputPath)) {
            throw new InvalidRecordingRequestException('Output path is required');
        }

        if (!in_array($this->quality, ['origin', 'high', 'medium', 'low'], true)) {
            throw new InvalidRecordingRequestException("Invalid quality: {$this->quality}");
        }

        if (!in_array($this->format, ['mp4', 'flv', 'mkv', 'ts'], true)) {
            throw new InvalidRecordingRequestException("Invalid format: {$this->format}");
        }

        if ($this->enableSplitting) {
            if ($this->splitDuration === null && $this->splitSize === null) {
                throw new InvalidRecordingRequestException(
                    'Split duration or split size must be specified when splitting is enabled'
                );
            }

            if ($this->splitDuration !== null && $this->splitDuration <= 0) {
                throw new InvalidRecordingRequestException('Split duration must be positive');
            }

            if ($this->splitSize !== null && $this->splitSize <= 0) {
                throw new InvalidRecordingRequestException('Split size must be positive');
            }
        }
    }

    /**
     * 获取分割配置
     *
     * @return array|null
     */
    public function getSplittingConfig(): ?array
    {
        if (!$this->enableSplitting) {
            return null;
        }

        return [
            'enabled' => true,
            'duration' => $this->splitDuration,
            'size' => $this->splitSize,
        ];
    }

    /**
     * 检查是否启用了分割
     *
     * @return bool
     */
    public function isSplittingEnabled(): bool
    {
        return $this->enableSplitting;
    }

    /**
     * 获取扩展选项
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * 检查是否有指定选项
     *
     * @param string $key
     * @return bool
     */
    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }
}

/**
 * 无效录制请求异常
 */
final class InvalidRecordingRequestException extends ApplicationException
{
    public function getErrorCode(): string
    {
        return 'INVALID_RECORDING_REQUEST';
    }
}