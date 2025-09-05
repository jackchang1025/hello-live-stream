<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter\Collections;

use LiveStream\Recording\Splitter\ValueObjects\SegmentInfo;
use Iterator;
use Countable;
use ArrayAccess;

/**
 * 分段集合
 * 
 * 管理多个录制分段的集合
 * 实现 Iterator、Countable、ArrayAccess 接口，提供便捷的操作方法
 */
final class SegmentCollection implements Iterator, Countable, ArrayAccess
{
    /** @var SegmentInfo[] */
    private array $segments = [];
    private int $position = 0;

    /**
     * 添加分段
     * 
     * @param SegmentInfo $segment 分段信息
     */
    public function add(SegmentInfo $segment): void
    {
        $this->segments[] = $segment;
    }

    /**
     * 获取所有分段
     * 
     * @return SegmentInfo[] 所有分段
     */
    public function all(): array
    {
        return $this->segments;
    }

    /**
     * 获取已完成的分段
     * 
     * @return SegmentInfo[] 已完成的分段
     */
    public function getCompleted(): array
    {
        return array_filter($this->segments, fn(SegmentInfo $segment) => $segment->isCompleted());
    }

    /**
     * 获取失败的分段
     * 
     * @return SegmentInfo[] 失败的分段
     */
    public function getFailed(): array
    {
        return array_filter($this->segments, fn(SegmentInfo $segment) => $segment->isFailed());
    }

    /**
     * 获取正在录制的分段
     * 
     * @return SegmentInfo[] 正在录制的分段
     */
    public function getRecording(): array
    {
        return array_filter($this->segments, fn(SegmentInfo $segment) => $segment->isRecording());
    }

    /**
     * 统计已完成的分段数
     * 
     * @return int 已完成的分段数
     */
    public function countCompleted(): int
    {
        return count($this->getCompleted());
    }

    /**
     * 统计失败的分段数
     * 
     * @return int 失败的分段数
     */
    public function countFailed(): int
    {
        return count($this->getFailed());
    }

    /**
     * 统计正在录制的分段数
     * 
     * @return int 正在录制的分段数
     */
    public function countRecording(): int
    {
        return count($this->getRecording());
    }

    /**
     * 获取总时长
     * 
     * @return int 总时长（秒）
     */
    public function getTotalDuration(): int
    {
        return array_sum(array_map(fn(SegmentInfo $segment) => $segment->duration, $this->segments));
    }

    /**
     * 获取已完成分段的总大小
     * 
     * @return float 总大小（MB）
     */
    public function getTotalSize(): float
    {
        return array_sum(array_map(fn(SegmentInfo $segment) => $segment->fileSize, $this->getCompleted()));
    }

    /**
     * 根据索引获取分段
     * 
     * @param int $index 分段索引
     * @return SegmentInfo|null 分段信息
     */
    public function getByIndex(int $index): ?SegmentInfo
    {
        foreach ($this->segments as $segment) {
            if ($segment->index === $index) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * 检查是否为空
     * 
     * @return bool 是否为空
     */
    public function isEmpty(): bool
    {
        return empty($this->segments);
    }

    /**
     * 清空集合
     */
    public function clear(): void
    {
        $this->segments = [];
        $this->position = 0;
    }

    /**
     * 转换为数组
     * 
     * @return array 分段信息数组
     */
    public function toArray(): array
    {
        return array_map(fn(SegmentInfo $segment) => $segment->toArray(), $this->segments);
    }

    // ==================== Iterator 接口实现 ====================

    public function current(): SegmentInfo
    {
        return $this->segments[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->segments[$this->position]);
    }

    // ==================== Countable 接口实现 ====================

    public function count(): int
    {
        return count($this->segments);
    }

    // ==================== ArrayAccess 接口实现 ====================

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->segments[$offset]);
    }

    public function offsetGet(mixed $offset): SegmentInfo
    {
        return $this->segments[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$value instanceof SegmentInfo) {
            throw new \InvalidArgumentException('Value must be an instance of SegmentInfo');
        }

        if ($offset === null) {
            $this->segments[] = $value;
        } else {
            $this->segments[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->segments[$offset]);
    }
}
