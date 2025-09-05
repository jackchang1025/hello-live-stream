<?php

declare(strict_types=1);

namespace LiveStream\Recording\Contracts;

use LiveStream\Recording\PendingRecorder;
use LiveStream\Recording\ValueObjects\RecordHandle;

/**
 * 录制器契约
 *
 * 定义启动/停止录制的最小接口，保持与具体实现（原生 FFmpeg、php-ffmpeg）解耦。
 */
interface RecorderInterface
{
    /**
     * 启动录制（异步）。
     *
     * 约定：
     * - 返回的 RecordHandle 可用于后续停止或查询状态。
     * - 可选 $progress 回调将以增量方式传递 ffmpeg 输出。
     *
     * @param PendingRecorder $pendingRecorder 录制配置（包含流地址、输出路径等）
     * @param callable|null $progress 回调签名：function(string $type, string $buffer): void
     *                                $type 取值 'stdout'|'stderr'
     */
    public function start(PendingRecorder $pendingRecorder, ?callable $progress = null);

}
