<?php

declare(strict_types=1);

/**
 * Pest PHP 测试配置文件
 */

use Pest\TestSuite;

// 设置测试套件
uses()->in('Unit')->group('unit');
uses()->in('Integration')->group('integration');
uses()->in('Feature')->group('feature');

// 全局测试函数
function createMockContainer(): \LiveStream\Shared\Contracts\ContainerInterface
{
    return new \LiveStream\Shared\Utils\SimpleContainer();
}

function createTestRecordingRequest(array $overrides = []): \LiveStream\Application\DTOs\RecordingRequest
{
    return new \LiveStream\Application\DTOs\RecordingRequest(
        url: $overrides['url'] ?? 'https://live.douyin.com/123456',
        outputPath: $overrides['outputPath'] ?? './test-output.mp4',
        quality: $overrides['quality'] ?? 'origin',
        format: $overrides['format'] ?? 'mp4',
        enableSplitting: $overrides['enableSplitting'] ?? false,
        splitDuration: $overrides['splitDuration'] ?? null,
        splitSize: $overrides['splitSize'] ?? null,
        options: $overrides['options'] ?? []
    );
}

function createTestRecording(): \LiveStream\Domain\Entities\Recording
{
    return \LiveStream\Domain\Entities\Recording::create(
        id: \LiveStream\Domain\ValueObjects\RecordingId::generate(),
        url: \LiveStream\Domain\ValueObjects\StreamUrl::fromString('https://live.douyin.com/123456'),
        outputPath: './test-output.mp4',
        quality: 'origin'
    );
}

// 期望辅助函数
expect()->extend('toBeValidUrl', function () {
    return $this->toMatch('/^https?:\/\/.+/');
});

expect()->extend('toBeRecordingId', function () {
    return $this->toMatch('/^rec_\d{14}_[a-f0-9]{8}$/');
});

expect()->extend('toBeDuration', function () {
    return $this->toBeInstanceOf(\LiveStream\Domain\ValueObjects\Duration::class);
});

expect()->extend('toBeStreamUrl', function () {
    return $this->toBeInstanceOf(\LiveStream\Domain\ValueObjects\StreamUrl::class);
});

expect()->extend('toBeRecording', function () {
    return $this->toBeInstanceOf(\LiveStream\Domain\Entities\Recording::class);
});