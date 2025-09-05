<?php

declare(strict_types=1);

namespace LiveStream\Infrastructure\Factories;

use LiveStream\Domain\Entities\Platform;
use LiveStream\Domain\Entities\Recording;
use LiveStream\Domain\Factories\RecorderFactoryInterface;
use LiveStream\Domain\Factories\RecorderInterface;
use LiveStream\Domain\Factories\UnsupportedRecorderException;

/**
 * 基于策略的录制器工厂实现
 * 
 * 根据平台类型和配置选择最适合的录制器
 */
final class StrategyBasedRecorderFactory implements RecorderFactoryInterface
{
    /**
     * 录制器创建器映射
     * 格式：['type' => callable]
     */
    private array $creators = [];

    /**
     * 默认录制器类型
     */
    private string $defaultRecorderType = 'ffmpeg';

    public function __construct()
    {
        $this->registerDefaultRecorders();
    }

    public function create(Platform $platform, Recording $recording): RecorderInterface
    {
        $recorderType = $this->selectRecorderType($platform, $recording);
        
        if (!isset($this->creators[$recorderType])) {
            throw new UnsupportedRecorderException(
                "Recorder type '{$recorderType}' is not supported",
                0,
                null,
                [
                    'requested_type' => $recorderType,
                    'platform' => $platform->getName(),
                    'supported_types' => array_keys($this->creators)
                ]
            );
        }

        $creator = $this->creators[$recorderType];
        return $creator($platform, $recording);
    }

    public function register(string $type, callable $creator): void
    {
        $this->creators[$type] = $creator;
    }

    public function getSupportedTypes(): array
    {
        return array_keys($this->creators);
    }

    public function supports(string $type): bool
    {
        return isset($this->creators[$type]);
    }

    /**
     * 设置默认录制器类型
     *
     * @param string $type
     */
    public function setDefaultRecorderType(string $type): void
    {
        $this->defaultRecorderType = $type;
    }

    /**
     * 选择录制器类型
     *
     * @param Platform $platform
     * @param Recording $recording
     * @return string
     */
    private function selectRecorderType(Platform $platform, Recording $recording): string
    {
        $options = $recording->getOptions();
        
        // 如果明确指定了录制器类型
        if (isset($options['recorder_type'])) {
            return $options['recorder_type'];
        }

        // 根据平台特性选择录制器
        $platformName = $platform->getName();
        
        // 某些平台可能更适合特定的录制器
        switch ($platformName) {
            case 'douyin':
                // 抖音直播可能更适合原生FFmpeg
                return $options['prefer_native'] ?? false ? 'native_ffmpeg' : 'php_ffmpeg';
                
            case 'bilibili':
                // B站直播可能需要特殊处理
                return 'php_ffmpeg';
                
            default:
                return $this->defaultRecorderType;
        }
    }

    /**
     * 注册默认录制器
     */
    private function registerDefaultRecorders(): void
    {
        // 原生FFmpeg录制器
        $this->register('native_ffmpeg', function (Platform $platform, Recording $recording) {
            return new \LiveStream\Infrastructure\Recording\NativeFFmpegRecorder($platform, $recording);
        });

        // PHP-FFmpeg录制器
        $this->register('php_ffmpeg', function (Platform $platform, Recording $recording) {
            return new \LiveStream\Infrastructure\Recording\PhpFFmpegRecorder($platform, $recording);
        });

        // 简化版FFmpeg录制器（用于测试）
        $this->register('ffmpeg', function (Platform $platform, Recording $recording) {
            return new \LiveStream\Infrastructure\Recording\SimpleFFmpegRecorder($platform, $recording);
        });

        // 模拟录制器（用于测试）
        $this->register('mock', function (Platform $platform, Recording $recording) {
            return new \LiveStream\Infrastructure\Recording\MockRecorder($platform, $recording);
        });
    }
}