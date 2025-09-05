<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter\Strategies;

use LiveStream\Recording\Splitter\ValueObjects\SegmentInfo;
use LiveStream\Recording\Splitter\Collections\SegmentCollection;

/**
 * 分割策略抽象类
 * 
 * 定义分割录制的策略模式基类
 */
abstract class SplitStrategy
{
    /**
     * 获取策略描述
     * 
     * @return string 策略描述
     */
    abstract public function getDescription(): string;

    /**
     * 判断是否应该分割
     * 
     * @param SegmentInfo $currentSegment 当前分段
     * @param SegmentCollection $allSegments 所有分段
     * @return bool 是否应该分割
     */
    abstract public function shouldSplit(SegmentInfo $currentSegment, SegmentCollection $allSegments): bool;

    /**
     * 获取最大分段时长
     * 
     * @return int 最大分段时长（秒），0表示无限制
     */
    abstract public function getMaxSegmentDuration(): int;

    /**
     * 基于时间判断是否分割
     * 
     * @param int $elapsedSeconds 已录制秒数
     * @return bool 是否应该分割
     */
    abstract public function shouldSplitByTime(int $elapsedSeconds): bool;

    /**
     * 基于文件大小判断是否分割
     * 
     * @param string $filePath 文件路径
     * @return bool 是否应该分割
     */
    abstract public function shouldSplitBySize(string $filePath): bool;
}
