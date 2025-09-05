<?php

declare(strict_types=1);

/**
 * 新架构引导文件
 * 
 * 设置依赖注入容器和服务绑定
 */

use LiveStream\Shared\Utils\SimpleContainer;
use LiveStream\Application\Services\RecordingService;
use LiveStream\Domain\Repositories\RecordingRepositoryInterface;
use LiveStream\Domain\Repositories\PlatformRepositoryInterface;
use LiveStream\Domain\Factories\RecorderFactoryInterface;
use LiveStream\Domain\Factories\PlatformFactoryInterface;
use LiveStream\Infrastructure\Repositories\MemoryRecordingRepository;
use LiveStream\Infrastructure\Repositories\FilePlatformRepository;
use LiveStream\Infrastructure\Factories\StrategyBasedRecorderFactory;
use LiveStream\Infrastructure\Factories\ConfigurablePlatformFactory;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// 创建容器
$container = new SimpleContainer();

// 绑定日志器
$container->singleton(LoggerInterface::class, function () {
    $logger = new Logger('livestream');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    return $logger;
});

// 绑定仓储实现
$container->singleton(RecordingRepositoryInterface::class, MemoryRecordingRepository::class);
$container->singleton(PlatformRepositoryInterface::class, FilePlatformRepository::class);

// 绑定工厂实现
$container->singleton(RecorderFactoryInterface::class, StrategyBasedRecorderFactory::class);
$container->singleton(PlatformFactoryInterface::class, ConfigurablePlatformFactory::class);

// 绑定应用服务
$container->singleton(RecordingService::class, RecordingService::class);

// 返回配置好的容器
return $container;