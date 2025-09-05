<?php

declare(strict_types=1);

namespace LiveStream\Infrastructure\Factories;

use LiveStream\Domain\Entities\Platform;
use LiveStream\Domain\ValueObjects\StreamUrl;
use LiveStream\Domain\Factories\PlatformFactoryInterface;
use LiveStream\Domain\Factories\UnsupportedPlatformException;

/**
 * 可配置的平台工厂实现
 * 
 * 支持运行时注册新的平台类型
 */
final class ConfigurablePlatformFactory implements PlatformFactoryInterface
{
    /**
     * 平台创建器映射
     * 格式：['pattern' => ['creator' => callable, 'description' => string]]
     */
    private array $creators = [];

    public function __construct()
    {
        $this->registerDefaultPlatforms();
    }

    public function createPlatform(StreamUrl $url): Platform
    {
        foreach ($this->creators as $pattern => $config) {
            if ($url->matchesDomain($pattern)) {
                $creator = $config['creator'];
                return $creator($url);
            }
        }

        throw new UnsupportedPlatformException(
            "No platform found for URL: {$url->getValue()}",
            0,
            null,
            ['url' => $url->getValue(), 'supported_patterns' => array_keys($this->creators)]
        );
    }

    public function register(string $pattern, callable $creator): void
    {
        $this->creators[$pattern] = [
            'creator' => $creator,
            'description' => $this->extractDescriptionFromPattern($pattern),
        ];
    }

    public function getSupportedPlatforms(): array
    {
        $platforms = [];
        foreach ($this->creators as $pattern => $config) {
            $platforms[$pattern] = $config['description'];
        }
        return $platforms;
    }

    public function supports(StreamUrl $url): bool
    {
        foreach ($this->creators as $pattern => $config) {
            if ($url->matchesDomain($pattern)) {
                return true;
            }
        }
        return false;
    }

    public function getPlatformName(StreamUrl $url): ?string
    {
        foreach ($this->creators as $pattern => $config) {
            if ($url->matchesDomain($pattern)) {
                return $config['description'];
            }
        }
        return null;
    }

    /**
     * 注册默认支持的平台
     */
    private function registerDefaultPlatforms(): void
    {
        // 抖音直播
        $this->register(
            '/(live\.douyin\.com|v\.douyin\.com|www\.douyin\.com)/',
            function (StreamUrl $url) {
                return new \LiveStream\Infrastructure\Platforms\DouyinPlatform($url);
            }
        );

        // 快手直播
        $this->register(
            '/(live\.kuaishou\.com|www\.kuaishou\.com)/',
            function (StreamUrl $url) {
                return new \LiveStream\Infrastructure\Platforms\KuaishouPlatform($url);
            }
        );

        // B站直播
        $this->register(
            '/(live\.bilibili\.com)/',
            function (StreamUrl $url) {
                return new \LiveStream\Infrastructure\Platforms\BilibiliPlatform($url);
            }
        );

        // 虎牙直播
        $this->register(
            '/(www\.huya\.com|m\.huya\.com)/',
            function (StreamUrl $url) {
                return new \LiveStream\Infrastructure\Platforms\HuyaPlatform($url);
            }
        );

        // 斗鱼直播
        $this->register(
            '/(www\.douyu\.com|m\.douyu\.com)/',
            function (StreamUrl $url) {
                return new \LiveStream\Infrastructure\Platforms\DouyuPlatform($url);
            }
        );
    }

    /**
     * 从正则模式中提取描述
     *
     * @param string $pattern
     * @return string
     */
    private function extractDescriptionFromPattern(string $pattern): string
    {
        // 简单的模式识别，实际项目中可以更复杂
        if (strpos($pattern, 'douyin') !== false) {
            return '抖音直播';
        }
        if (strpos($pattern, 'kuaishou') !== false) {
            return '快手直播';
        }
        if (strpos($pattern, 'bilibili') !== false) {
            return 'B站直播';
        }
        if (strpos($pattern, 'huya') !== false) {
            return '虎牙直播';
        }
        if (strpos($pattern, 'douyu') !== false) {
            return '斗鱼直播';
        }
        
        return 'Unknown Platform';
    }
}