<?php

declare(strict_types=1);

namespace LiveStream\Domain\ValueObjects;

use LiveStream\Shared\Exceptions\DomainException;

/**
 * 时长值对象
 * 
 * 封装时长计算和格式化逻辑
 */
final readonly class Duration
{
    private function __construct(
        private int $seconds
    ) {
        $this->validate();
    }

    /**
     * 从秒数创建时长
     *
     * @param int $seconds
     * @return self
     * @throws InvalidDurationException
     */
    public static function fromSeconds(int $seconds): self
    {
        return new self($seconds);
    }

    /**
     * 从分钟创建时长
     *
     * @param int $minutes
     * @return self
     */
    public static function fromMinutes(int $minutes): self
    {
        return new self($minutes * 60);
    }

    /**
     * 从小时创建时长
     *
     * @param int $hours
     * @return self
     */
    public static function fromHours(int $hours): self
    {
        return new self($hours * 3600);
    }

    /**
     * 从字符串格式创建时长 (HH:MM:SS)
     *
     * @param string $timeString
     * @return self
     * @throws InvalidDurationException
     */
    public static function fromString(string $timeString): self
    {
        if (!preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $timeString, $matches)) {
            throw new InvalidDurationException("Invalid time format: {$timeString}. Expected HH:MM:SS");
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = (int) $matches[3];

        if ($minutes >= 60 || $seconds >= 60) {
            throw new InvalidDurationException("Invalid time values in: {$timeString}");
        }

        return new self($hours * 3600 + $minutes * 60 + $seconds);
    }

    /**
     * 零时长
     *
     * @return self
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * 获取总秒数
     *
     * @return int
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * 获取总分钟数
     *
     * @return int
     */
    public function getMinutes(): int
    {
        return intval($this->seconds / 60);
    }

    /**
     * 获取总小时数
     *
     * @return int
     */
    public function getHours(): int
    {
        return intval($this->seconds / 3600);
    }

    /**
     * 格式化为 HH:MM:SS
     *
     * @return string
     */
    public function format(): string
    {
        $hours = intval($this->seconds / 3600);
        $minutes = intval(($this->seconds % 3600) / 60);
        $seconds = $this->seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * 格式化为人类可读格式
     *
     * @return string
     */
    public function toHuman(): string
    {
        if ($this->seconds < 60) {
            return "{$this->seconds}秒";
        }

        if ($this->seconds < 3600) {
            $minutes = intval($this->seconds / 60);
            $remainingSeconds = $this->seconds % 60;
            return $remainingSeconds > 0 ? "{$minutes}分{$remainingSeconds}秒" : "{$minutes}分";
        }

        $hours = intval($this->seconds / 3600);
        $remainingMinutes = intval(($this->seconds % 3600) / 60);
        $remainingSeconds = $this->seconds % 60;

        $result = "{$hours}小时";
        if ($remainingMinutes > 0) {
            $result .= "{$remainingMinutes}分";
        }
        if ($remainingSeconds > 0) {
            $result .= "{$remainingSeconds}秒";
        }

        return $result;
    }

    /**
     * 添加时长
     *
     * @param self $other
     * @return self
     */
    public function add(self $other): self
    {
        return new self($this->seconds + $other->seconds);
    }

    /**
     * 减去时长
     *
     * @param self $other
     * @return self
     */
    public function subtract(self $other): self
    {
        return new self(max(0, $this->seconds - $other->seconds));
    }

    /**
     * 比较大小
     *
     * @param self $other
     * @return bool
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->seconds > $other->seconds;
    }

    /**
     * 比较大小
     *
     * @param self $other
     * @return bool
     */
    public function isLessThan(self $other): bool
    {
        return $this->seconds < $other->seconds;
    }

    /**
     * 相等性比较
     *
     * @param self $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->seconds === $other->seconds;
    }

    /**
     * 是否为零
     *
     * @return bool
     */
    public function isZero(): bool
    {
        return $this->seconds === 0;
    }

    /**
     * 验证时长
     *
     * @throws InvalidDurationException
     */
    private function validate(): void
    {
        if ($this->seconds < 0) {
            throw new InvalidDurationException('Duration cannot be negative');
        }
    }

    /**
     * 字符串表示
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->format();
    }
}

/**
 * 无效时长异常
 */
final class InvalidDurationException extends DomainException
{
    public function getErrorCode(): string
    {
        return 'INVALID_DURATION';
    }
}