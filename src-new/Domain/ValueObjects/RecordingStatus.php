<?php

declare(strict_types=1);

namespace LiveStream\Domain\ValueObjects;

/**
 * 录制状态枚举
 */
enum RecordingStatus: string
{
    case PENDING = 'pending';
    case RECORDING = 'recording';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * 获取状态显示名称
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::PENDING => '等待中',
            self::RECORDING => '录制中',
            self::PAUSED => '已暂停',
            self::COMPLETED => '已完成',
            self::FAILED => '录制失败',
            self::CANCELLED => '已取消',
        };
    }

    /**
     * 检查是否可以启动录制
     *
     * @return bool
     */
    public function canStart(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * 检查是否可以暂停录制
     *
     * @return bool
     */
    public function canPause(): bool
    {
        return $this === self::RECORDING;
    }

    /**
     * 检查是否可以恢复录制
     *
     * @return bool
     */
    public function canResume(): bool
    {
        return $this === self::PAUSED;
    }

    /**
     * 检查是否可以停止录制
     *
     * @return bool
     */
    public function canStop(): bool
    {
        return in_array($this, [self::RECORDING, self::PAUSED], true);
    }

    /**
     * 检查是否为进行中状态
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return in_array($this, [self::RECORDING, self::PAUSED], true);
    }

    /**
     * 检查是否为结束状态
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    /**
     * 检查是否为成功状态
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }
}