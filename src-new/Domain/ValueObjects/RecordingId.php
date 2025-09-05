<?php

declare(strict_types=1);

namespace LiveStream\Domain\ValueObjects;

use LiveStream\Shared\Exceptions\DomainException;

/**
 * 录制ID值对象
 * 
 * 封装ID生成和验证逻辑
 */
final readonly class RecordingId
{
    private function __construct(
        private string $value
    ) {
        $this->validate();
    }

    /**
     * 生成新的录制ID
     *
     * @return self
     */
    public static function generate(): self
    {
        $timestamp = date('YmdHis');
        $random = bin2hex(random_bytes(4));
        return new self("rec_{$timestamp}_{$random}");
    }

    /**
     * 从字符串创建录制ID
     *
     * @param string $id
     * @return self
     * @throws InvalidRecordingIdException
     */
    public static function fromString(string $id): self
    {
        return new self($id);
    }

    /**
     * 获取ID值
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * 获取时间戳部分
     *
     * @return string|null
     */
    public function getTimestamp(): ?string
    {
        if (preg_match('/^rec_(\d{14})_[a-f0-9]{8}$/', $this->value, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * 获取随机部分
     *
     * @return string|null
     */
    public function getRandomPart(): ?string
    {
        if (preg_match('/^rec_\d{14}_([a-f0-9]{8})$/', $this->value, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * 检查是否为生成的ID格式
     *
     * @return bool
     */
    public function isGenerated(): bool
    {
        return (bool) preg_match('/^rec_\d{14}_[a-f0-9]{8}$/', $this->value);
    }

    /**
     * 验证ID格式
     *
     * @throws InvalidRecordingIdException
     */
    private function validate(): void
    {
        if (empty($this->value)) {
            throw new InvalidRecordingIdException('Recording ID cannot be empty');
        }

        if (strlen($this->value) > 100) {
            throw new InvalidRecordingIdException('Recording ID too long (max 100 characters)');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $this->value)) {
            throw new InvalidRecordingIdException('Recording ID contains invalid characters');
        }
    }

    /**
     * 相等性比较
     *
     * @param self $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * 字符串表示
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}

/**
 * 无效录制ID异常
 */
final class InvalidRecordingIdException extends DomainException
{
    public function getErrorCode(): string
    {
        return 'INVALID_RECORDING_ID';
    }
}