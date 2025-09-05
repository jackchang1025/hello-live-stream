<?php

declare(strict_types=1);

namespace LiveStream\Domain\Repositories;

use LiveStream\Domain\Entities\Platform;
use LiveStream\Domain\ValueObjects\StreamUrl;

/**
 * 平台仓储接口
 * 
 * 定义平台数据访问的抽象
 */
interface PlatformRepositoryInterface
{
    /**
     * 根据URL查找支持的平台
     *
     * @param StreamUrl $url
     * @return Platform|null
     */
    public function findByUrl(StreamUrl $url): ?Platform;

    /**
     * 根据名称查找平台
     *
     * @param string $name
     * @return Platform|null
     */
    public function findByName(string $name): ?Platform;

    /**
     * 获取所有支持的平台
     *
     * @return Platform[]
     */
    public function findAll(): array;

    /**
     * 保存平台配置
     *
     * @param Platform $platform
     */
    public function save(Platform $platform): void;

    /**
     * 检查URL是否被支持
     *
     * @param StreamUrl $url
     * @return bool
     */
    public function supports(StreamUrl $url): bool;
}