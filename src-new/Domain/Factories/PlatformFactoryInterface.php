<?php

declare(strict_types=1);

namespace LiveStream\Domain\Factories;

use LiveStream\Domain\Entities\Platform;
use LiveStream\Domain\ValueObjects\StreamUrl;

/**
 * 平台工厂接口
 * 
 * 定义平台创建的抽象
 */
interface PlatformFactoryInterface
{
    /**
     * 根据URL创建平台实例
     *
     * @param StreamUrl $url
     * @return Platform
     * @throws UnsupportedPlatformException
     */
    public function createPlatform(StreamUrl $url): Platform;

    /**
     * 注册平台创建器
     *
     * @param string $pattern URL匹配模式（正则表达式）
     * @param callable $creator 创建器回调
     */
    public function register(string $pattern, callable $creator): void;

    /**
     * 获取支持的平台列表
     *
     * @return array 格式：['pattern' => 'description']
     */
    public function getSupportedPlatforms(): array;

    /**
     * 检查是否支持指定URL
     *
     * @param StreamUrl $url
     * @return bool
     */
    public function supports(StreamUrl $url): bool;

    /**
     * 获取URL对应的平台名称
     *
     * @param StreamUrl $url
     * @return string|null
     */
    public function getPlatformName(StreamUrl $url): ?string;
}

/**
 * 不支持的平台异常
 */
final class UnsupportedPlatformException extends \LiveStream\Shared\Exceptions\DomainException
{
    public function getErrorCode(): string
    {
        return 'UNSUPPORTED_PLATFORM';
    }
}