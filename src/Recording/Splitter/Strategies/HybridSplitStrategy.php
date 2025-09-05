<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter\Strategies;

use LiveStream\Recording\Splitter\ValueObjects\SegmentInfo;
use LiveStream\Recording\Splitter\Collections\SegmentCollection;

/**
 * 混合分割策略
 * 
 * 同时考虑时间和文件大小，满足任一条件即分割
 */
final class HybridSplitStrategy extends SplitStrategy
{
    public function __construct(
        private readonly int $splitTimeSeconds,
        private readonly int $maxFileSizeMB
    ) {}

    public function getDescription(): string
    {
        return "混合分割 (每 {$this->splitTimeSeconds} 秒或 {$this->maxFileSizeMB} MB)";
    }

    public function shouldSplit(SegmentInfo $currentSegment, SegmentCollection $allSegments): bool
    {
        $elapsedTime = time() - $currentSegment->startTime;

        return $this->shouldSplitByTime($elapsedTime) || $this->shouldSplitBySize($currentSegment->outputPath);
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
        if (!file_exists($filePath)) {
            return false;
        }

        $fileSizeMB = filesize($filePath) / 1024 / 1024;
        return $fileSizeMB >= $this->maxFileSizeMB;
    }
}
