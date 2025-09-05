<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter\Strategies;

use LiveStream\Recording\Splitter\ValueObjects\SegmentInfo;
use LiveStream\Recording\Splitter\Collections\SegmentCollection;

/**
 * 基于时间的分割策略
 * 
 * 每隔指定时间分割一次
 */
final class TimeSplitStrategy extends SplitStrategy
{
    public function __construct(
        private readonly int $splitTimeSeconds
    ) {}

    public function getDescription(): string
    {
        return "时间分割 (每 {$this->splitTimeSeconds} 秒)";
    }

    public function shouldSplit(SegmentInfo $currentSegment, SegmentCollection $allSegments): bool
    {
        return $this->shouldSplitByTime(time() - $currentSegment->startTime);
    }

    public function getMaxSegmentDuration(): int
    {
        return $this->splitTimeSeconds;
    }

    public function shouldSplitByTime(int $elapsedSeconds): bool
    {
        return $elapsedSeconds >= $this->splitTimeSeconds;
    }

    public function shouldSplitBySize(string $filePath): bool
    {
        return false; // 时间策略不考虑文件大小
    }
}
