<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter\Contracts;

use LiveStream\Recording\PendingRecorder;
use LiveStream\Exceptions\RecordingException;

/**
 * 分割器接口
 * 
 * 定义分割录制的基本契约
 * 遵循接口隔离原则（ISP）
 */
interface SplitterInterface
{
    /**
     * 执行分割操作
     * 
     * @param callable|null $progressCallback 进度回调函数
     * @return PendingRecorder 录制结果
     * @throws RecordingException 当分割失败时
     */
    public function execute(?callable $progressCallback = null): PendingRecorder;

    /**
     * 获取分割信息
     * 
     * @return array 分割统计信息
     */
    public function getSegmentInfo(): array;
}
