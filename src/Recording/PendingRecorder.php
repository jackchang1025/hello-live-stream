<?php

declare(strict_types=1);

namespace LiveStream\Recording;

use LiveStream\Contracts\PlatformInterface;
use LiveStream\Config\RecordingOptions;
use LiveStream\Config\StreamConfig;
use LiveStream\Exceptions\RecordingException;
use LiveStream\Recording\RecordrConnector;
use LiveStream\Traits\HasRecordId;
use LiveStream\Traits\HasPathBuilder;
class PendingRecorder
{
    use HasRecordId;
    use HasPathBuilder;

    private ?StreamConfig $streamConfig = null;

    
    protected ?string $savePath = null;

    /**
     * 构造函数
     * 
     * @param PlatformInterface $platform 平台接口
     * @param RecordingOptions $options 录制选项
     * @param string $recordId 录制ID
     * @param bool $enableOverseasOptimization 是否启用海外优化
     * @throws RecordingException 当配置构建失败时
     */
    public function __construct(
        private readonly RecordrConnector $recordrConnector,
        private readonly PlatformInterface $platform,
        private readonly bool $enableOverseasOptimization = false,
    ) {
        
    }

    /**
     * 获取 RecordrConnector 实例
     * 
     * @return RecordrConnector  RecordrConnector 实例
     */
    public function recordrConnector(): RecordrConnector
    {
        return $this->recordrConnector;
    }

    /**
     * 获取房间信息
     * 
     * @return \LiveStream\Contracts\RoomInfoInterface 房间信息
     */
    public function getRoomInfo(): \LiveStream\Contracts\RoomInfoInterface
    {
        return $this->platform->getRoomInfo();
    }

    /**
     * 获取平台接口
     * 
     * @return PlatformInterface 平台接口
     */
    public function getPlatform(): PlatformInterface
    {
        return $this->platform;
    }

    /**
     * 是否启用海外优化
     * 
     * @return bool 是否启用海外优化
     */
    public function isOverseasOptimized(): bool
    {
        return $this->enableOverseasOptimization;
    }

    /**
     * 构建流配置
     * 
     * @return StreamConfig 流配置对象
     * @throws RecordingException 当无法获取流配置时
     */
    public function streamConfig(): StreamConfig
    {
        return $this->streamConfig ??= $this->platform->getRoomInfo()->getStreamConfig(
            $this->recordrConnector()->config()->getQuality()->getDisplayName()
        );
    }

    /**
     * 构建输出路径
     * 
     * @return string 输出路径
     * @throws RecordingException 当路径构建失败时
     */
    public function savePath(): string
    {
        return $this->savePath ??= $this->buildFilePath(
            $this->getRoomInfo()->getAnchorName()
        );
    }

            /**
     * 构建文件路径
     * 
     * @param string $filename 文件名称
     * @return string 文件路径
     */
    protected function buildFilePath(string $filename): string
    {
        $savePath = $this->recordrConnector()->config()->getSavePath();

        $filename = $this->sanitizeFilename($filename);

        $format = $this->recordrConnector()->config()->getFormat()->value;

        $timestamp = date('Y-m-d_H-i-s');

        $filename = "{$filename}_{$timestamp}.{$format}";

        $savePath = rtrim($savePath, '/') . '/' . $filename;

        $this->ensureOutputDirectoryExists($savePath);

        return $savePath;
    }
    
}
