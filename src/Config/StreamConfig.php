<?php

declare(strict_types=1);

namespace LiveStream\Config;

/**
 * 流配置类
 * 
 * 封装流相关的配置信息
 */
final readonly class StreamConfig
{
    public function __construct(
        public string $url,
        public ?string $recordUrl = null,
        public ?string $m3u8Url = null,
        public ?string $flvUrl = null,
        public array $headers = [],
        public ?string $userAgent = null,
        public ?string $referer = null,
    ) {}

    /**
     * 从数组创建流配置
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'] ?? '',
            recordUrl: $data['record_url'] ?? null,
            m3u8Url: $data['m3u8_url'] ?? null,
            flvUrl: $data['flv_url'] ?? null,
            headers: $data['headers'] ?? [],
            userAgent: $data['user_agent'] ?? null,
            referer: $data['referer'] ?? null,
        );
    }

    /**
     * 获取有效的录制 URL
     */
    public function getRecordUrl(): string
    {
        return $this->recordUrl ?? $this->m3u8Url ?? $this->flvUrl ?? $this->url;
    }

    /**
     * 检查是否有有效的流 URL
     */
    public function hasValidUrl(): bool
    {
        return !empty($this->getRecordUrl());
    }

    /**
     * 获取所有请求头
     */
    public function getAllHeaders(): array
    {
        $headers = $this->headers;

        if ($this->userAgent !== null) {
            $headers['User-Agent'] = $this->userAgent;
        }

        if ($this->referer !== null) {
            $headers['Referer'] = $this->referer;
        }

        return $headers;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'record_url' => $this->recordUrl,
            'm3u8_url' => $this->m3u8Url,
            'flv_url' => $this->flvUrl,
            'headers' => $this->headers,
            'user_agent' => $this->userAgent,
            'referer' => $this->referer,
        ];
    }
}
