<?php

declare(strict_types=1);

namespace LiveStream\Enum;

/**
 * 录制输出格式枚举
 * 
 * 定义支持的录制输出格式
 */
enum OutputFormat: string
{
    case MP4 = 'mp4';
    case MKV = 'mkv';
    case FLV = 'flv';
    case TS = 'ts';
    case MP3 = 'mp3';
    case AAC = 'aac';

    /**
     * 判断是否为视频格式
     */
    public function isVideo(): bool
    {
        return match ($this) {
            self::MP4, self::MKV, self::FLV, self::TS => true,
            self::MP3, self::AAC => false,
        };
    }

    /**
     * 判断是否为音频格式
     */
    public function isAudio(): bool
    {
        return !$this->isVideo();
    }

    /**
     * 获取格式的 MIME 类型
     */
    public function getMimeType(): string
    {
        return match ($this) {
            self::MP4 => 'video/mp4',
            self::MKV => 'video/x-matroska',
            self::FLV => 'video/x-flv',
            self::TS => 'video/mp2t',
            self::MP3 => 'audio/mpeg',
            self::AAC => 'audio/aac',
        };
    }

    /**
     * 获取文件扩展名
     */
    public function getExtension(): string
    {
        return $this->value;
    }

    /**
     * 获取格式描述
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::MP4 => 'MP4 视频格式',
            self::MKV => 'Matroska 视频格式',
            self::FLV => 'Flash 视频格式',
            self::TS => 'MPEG-TS 传输流',
            self::MP3 => 'MP3 音频格式',
            self::AAC => 'AAC 音频格式',
        };
    }
}
