<?php

declare(strict_types=1);

namespace LiveStream\Domain\Repositories;

use LiveStream\Domain\Entities\Recording;
use LiveStream\Domain\ValueObjects\RecordingId;
use LiveStream\Domain\ValueObjects\RecordingStatus;

/**
 * 录制仓储接口
 * 
 * 定义录制数据访问的抽象
 */
interface RecordingRepositoryInterface
{
    /**
     * 保存录制记录
     *
     * @param Recording $recording
     */
    public function save(Recording $recording): void;

    /**
     * 根据ID查找录制记录
     *
     * @param RecordingId $id
     * @return Recording|null
     */
    public function findById(RecordingId $id): ?Recording;

    /**
     * 根据状态查找录制记录
     *
     * @param RecordingStatus $status
     * @return Recording[]
     */
    public function findByStatus(RecordingStatus $status): array;

    /**
     * 获取活跃的录制记录
     *
     * @return Recording[]
     */
    public function findActive(): array;

    /**
     * 删除录制记录
     *
     * @param RecordingId $id
     */
    public function delete(RecordingId $id): void;

    /**
     * 获取所有录制记录
     *
     * @param int $limit
     * @param int $offset
     * @return Recording[]
     */
    public function findAll(int $limit = 100, int $offset = 0): array;

    /**
     * 统计录制记录数量
     *
     * @param RecordingStatus|null $status
     * @return int
     */
    public function count(?RecordingStatus $status = null): int;
}