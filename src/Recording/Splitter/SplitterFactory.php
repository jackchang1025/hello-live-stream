<?php

declare(strict_types=1);

namespace LiveStream\Recording\Splitter;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use LiveStream\Recording\PendingRecorder;
use LiveStream\Recording\Splitter\Contracts\SplitterInterface;
use LiveStream\Recording\Splitter\Strategies\TimeSplitStrategy;
use LiveStream\Recording\Splitter\Strategies\SizeSplitStrategy;
use LiveStream\Recording\Splitter\Strategies\HybridSplitStrategy;
use LiveStream\Exceptions\RecordingException;

/**
 * 分割器工厂类
 * 
 * 根据配置创建合适的分割器实例
 * 支持多种分割策略和实现方式
 */
final class SplitterFactory
{
    public function __construct(
        private readonly FFMpeg $ffmpeg,
        private readonly FFProbe $ffprobe
    ) {}

    /**
     * 创建分割器实例
     * 
     * @param PendingRecorder $pendingRecorder 录制配置
     * @param bool $useRealtime 是否使用实时分割
     * @return SplitterInterface 分割器实例
     * @throws RecordingException 当配置无效时
     */
    public function create(PendingRecorder $pendingRecorder, bool $useRealtime = true): SplitterInterface
    {
        $options = $pendingRecorder->getOptions();

        // 验证格式支持
        if (!in_array($options->format->value, ['mp4', 'webm', 'mkv', 'flv', 'ts'])) {
            throw RecordingException::invalidConfiguration(
                '不支持的输出格式: ' . $options->format->value .
                    '，支持的格式: mp4, webm, mkv, flv, ts'
            );
        }

        if ($useRealtime) {
            // 优先使用原生 FFmpeg 分割器（最高效）
            $strategy = $this->createSplitStrategy($options);

            if ($this->supportsNativeSegmentation($options->format->value)) {
                return new NativeFFmpegSplitter($this->ffmpeg, $this->ffprobe, $pendingRecorder, $strategy);
            } else {
                // 回退到智能实时分割器
                return new SmartRealtimeSplitter($this->ffmpeg, $this->ffprobe, $pendingRecorder, $strategy);
            }
        } else {
            // 使用传统分割器（后处理）
            return new VideoSplitter($this->ffmpeg, $this->ffprobe, $pendingRecorder);
        }
    }

    /**
     * 创建分割策略
     * 
     * @param \LiveStream\Config\RecordingOptions $options 录制选项
     * @return \LiveStream\Recording\Splitter\Strategies\SplitStrategy 分割策略
     */
    private function createSplitStrategy(\LiveStream\Config\RecordingOptions $options): \LiveStream\Recording\Splitter\Strategies\SplitStrategy
    {
        $hasTimeLimit = $options->splitTime !== null;
        $hasSizeLimit = $options->maxFileSize !== null;

        if ($hasTimeLimit && $hasSizeLimit) {
            // 混合策略：时间和大小都考虑
            return new HybridSplitStrategy($options->splitTime, $options->maxFileSize);
        } elseif ($hasTimeLimit) {
            // 时间策略
            return new TimeSplitStrategy($options->splitTime);
        } elseif ($hasSizeLimit) {
            // 大小策略
            return new SizeSplitStrategy($options->maxFileSize);
        } else {
            // 默认时间策略：5分钟
            return new TimeSplitStrategy(300);
        }
    }

    /**
     * 检查格式是否支持原生分段
     * 
     * @param string $format 输出格式
     * @return bool 是否支持原生分段
     */
    private function supportsNativeSegmentation(string $format): bool
    {
        // 基于 DouyinLiveRecorder 的实践，这些格式支持原生分段
        $supportedFormats = ['mp4', 'ts', 'mkv'];
        return in_array($format, $supportedFormats, true);
    }

    /**
     * 判断是否使用视频分割器
     * 
     * @param \LiveStream\Config\RecordingOptions $options 录制选项
     * @return bool 是否使用视频分割器
     */
    private function shouldUseVideoSplitter(\LiveStream\Config\RecordingOptions $options): bool
    {
        // 如果没有分割需求，使用普通录制
        return $options->splitTime === null && $options->maxFileSize === null;
    }

    /**
     * 获取支持的分割器类型
     * 
     * @return array 支持的分割器类型列表
     */
    public function getSupportedSplitters(): array
    {
        return [
            'native' => [
                'name' => '原生 FFmpeg 分割器',
                'description' => '使用 FFmpeg 原生 -f segment 参数，最高效的分割方式',
                'formats' => ['mp4', 'ts', 'mkv'],
                'features' => ['实时分割', '无额外开销', '网络重连']
            ],
            'smart' => [
                'name' => '智能实时分割器',
                'description' => '基于 php-ffmpeg 的智能分割，兼容性最好',
                'formats' => ['mp4', 'webm', 'mkv', 'flv'],
                'features' => ['预设时长', '实时监控', '统一接口']
            ],
            'video' => [
                'name' => '视频后处理分割器',
                'description' => '录制完成后进行分割，精度最高',
                'formats' => ['mp4', 'webm', 'mkv', 'flv'],
                'features' => ['精确分割', '完整录制', '后处理']
            ]
        ];
    }
}
