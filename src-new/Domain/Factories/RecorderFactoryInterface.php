<?php

declare(strict_types=1);

namespace LiveStream\Domain\Factories;

use LiveStream\Domain\Entities\Platform;
use LiveStream\Domain\Entities\Recording;

/**
 * 录制器工厂接口
 * 
 * 定义录制器创建的抽象
 */
interface RecorderFactoryInterface
{
    /**
     * 创建录制器实例
     *
     * @param Platform $platform
     * @param Recording $recording
     * @return RecorderInterface
     * @throws UnsupportedRecorderException
     */
    public function create(Platform $platform, Recording $recording): RecorderInterface;

    /**
     * 注册录制器创建器
     *
     * @param string $type 录制器类型
     * @param callable $creator 创建器回调
     */
    public function register(string $type, callable $creator): void;

    /**
     * 获取支持的录制器类型
     *
     * @return string[]
     */
    public function getSupportedTypes(): array;

    /**
     * 检查是否支持指定类型
     *
     * @param string $type
     * @return bool
     */
    public function supports(string $type): bool;
}

/**
 * 录制器接口
 */
interface RecorderInterface
{
    /**
     * 启动录制
     *
     * @param Recording $recording
     * @param callable|null $progressCallback
     * @return RecordHandle
     */
    public function start(Recording $recording, ?callable $progressCallback = null): RecordHandle;

    /**
     * 停止录制
     *
     * @return void
     */
    public function stop(): void;

    /**
     * 获取录制状态
     *
     * @return array
     */
    public function getStatus(): array;
}

/**
 * 录制句柄
 */
interface RecordHandle
{
    /**
     * 获取录制ID
     *
     * @return string
     */
    public function getId(): string;

    /**
     * 检查是否正在录制
     *
     * @return bool
     */
    public function isRecording(): bool;

    /**
     * 停止录制
     *
     * @return void
     */
    public function stop(): void;

    /**
     * 获取录制统计
     *
     * @return array
     */
    public function getStatistics(): array;
}

/**
 * 不支持的录制器异常
 */
final class UnsupportedRecorderException extends \LiveStream\Shared\Exceptions\DomainException
{
    public function getErrorCode(): string
    {
        return 'UNSUPPORTED_RECORDER_TYPE';
    }
}