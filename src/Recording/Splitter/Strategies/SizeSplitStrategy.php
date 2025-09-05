<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter\Strategies;

use LiveStream\Recording\Splitter\ValueObjects\SegmentInfo;
use LiveStream\Recording\Splitter\Collections\SegmentCollection;

/**
 * 基于文件大小的分割策略
 * 
 * 文件达到指定大小时分割
 */
final class SizeSplitStrategy extends SplitStrategy
{
    public function __construct(
        private readonly int $maxFileSizeMB
    ) {}

    public function getDescription(): string
    {
        return "大小分割 (每 {$this->maxFileSizeMB} MB)";
    }

    public function shouldSplit(SegmentInfo $currentSegment, SegmentCollection $allSegments): bool
    {
        return $this->shouldSplitBySize($currentSegment->outputPath);
    }

    public function getMaxSegmentDuration(): int
    {
        return 0; // 大小策略没有时间限制
    }

    public function shouldSplitByTime(int $elapsedSeconds): bool
    {
        return false; // 大小策略不考虑时间
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
