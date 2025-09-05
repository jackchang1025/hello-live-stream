<?php

declare(strict_types=1);

namespace LiveStream\Config;

use LiveStream\Enum\Quality;
use LiveStream\Enum\OutputFormat;
use LiveStream\Exceptions\RecordingException;


class RecordingOptions extends Config
{
    public function setQuality(Quality $quality): static
    {
        $this->set('quality', $quality);
        return $this;
    }

    public function getQuality(mixed $default = Quality::ORIGINAL): Quality
    {
        return $this->get('quality', $default);
    }


    public function setFormat(OutputFormat $format): static
    {
        $this->set('format', $format);
        return $this;
    }

    public function setSavePath(string $path): static
    {
        $this->set('savePath', $path);
        return $this;
    }

    public function getSavePath(mixed $default = __DIR__ . '/downloads'): string
    {
        return $this->get('savePath', $default);
    }

    public function getFormat(mixed $default = OutputFormat::MP4): OutputFormat
    {
        return $this->get('format', $default);
    }

    public function setFfmpegOptions(string $key, mixed $value = null): static
    {
        $this->set("ffmpegOptions.{$key}", $value);
        return $this;
    }

    public function getFfmpegOptions(?string $key = null, mixed $default = []): array
    {
        if ($key === null) {
            return $this->get("ffmpegOptions", $default);
        }

        return $this->get("ffmpegOptions.{$key}", $default);
    }

    /**
     * 存储配置访问器（用于对象存储上传）
     */
    public function getStorage(?string $key = null, mixed $default = []): array|string|int|bool|null
    {
        if ($key === null) {
            return $this->get('storage', $default);
        }
        return $this->get("storage.{$key}", $default);
    }

    /**
     * 语音转文字配置访问器
     */
    public function getTranscription(?string $key = null, mixed $default = []): array|string|int|bool|null
    {
        if ($key === null) {
            return $this->get('transcription', $default);
        }
        return $this->get("transcription.{$key}", $default);
    }
}
