<?php

declare(strict_types=1);

namespace LiveStream\Platforms\Douyin\RoomInfo;

use LiveStream\Contracts\RoomInfoInterface;
use LiveStream\Config\StreamConfig;
use LiveStream\Enum\Quality;
use LiveStream\Exceptions\PlatformException;
use Saloon\Http\Response;

class DouyinRoomInfo implements RoomInfoInterface
{
    public function __construct(private array $data) {}

    public function isLive(): bool
    {
        return (int)($this->data['status'] ?? 4) === 2;
    }

    public function getStreamConfig(string $quality = '原画', ?string $proxy = null): StreamConfig
    {
        $stream = $this->data['stream_url'] ?? [];

        // 按照 Python stream.py 中 get_douyin_stream_url 的逻辑
        // 获取流地址映射并转换为数组
        $hlsMap = $stream['hls_pull_url_map'] ?? [];
        $flvMap = $stream['flv_pull_url'] ?? [];

        // 转换为有序数组，按质量排序：ORIGIN, FULL_HD1, HD1, SD1, SD2
        $qualityOrder = ['ORIGIN', 'FULL_HD1', 'HD1', 'SD1', 'SD2'];
        $hlsUrls = [];
        $flvUrls = [];

        foreach ($qualityOrder as $q) {
            if (isset($hlsMap[$q])) $hlsUrls[] = $hlsMap[$q];
            if (isset($flvMap[$q])) $flvUrls[] = $flvMap[$q];
        }

        // 填充到至少5个元素（与 Python 逻辑一致）
        while (count($hlsUrls) < 5) $hlsUrls[] = end($hlsUrls) ?: '';
        while (count($flvUrls) < 5) $flvUrls[] = end($flvUrls) ?: '';

        $index = $this->getQualityIndex($quality);
        $m3u8Url = $hlsUrls[$index] ?? ($hlsUrls[0] ?? '');
        $flvUrl = $flvUrls[$index] ?? ($flvUrls[0] ?? '');

        return new StreamConfig(
            url: $m3u8Url ?: $flvUrl,
            recordUrl: $m3u8Url ?: $flvUrl,
            m3u8Url: $m3u8Url ?: null,
            flvUrl: $flvUrl ?: null,
            headers: [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer' => 'https://live.douyin.com/',
            ]
        );
    }



    public function getQualityMapping(): array
    {
        return [
            '原画' => 'ORIGIN',
            '超清' => 'UHD',
            '高清' => 'HD',
            '标清' => 'SD',
            '流畅' => 'LD',
        ];
    }

    public function supportsQuality(): bool
    {
        return true;
    }

    public function getAnchorName(): string
    {
        return (string)($this->data['anchor_name'] ?? '');
    }

    public function getTitle(): string
    {
        return (string)($this->data['title'] ?? '');
    }

    public function getRoomId(): string
    {
        return (string)($this->data['id_str'] ?? $this->data['id'] ?? '');
    }

    public function getQuality(): Quality
    {
        return Quality::ORIGINAL;
    }

    private function getQualityIndex(string $quality): int
    {
        $map = [
            '原画' => 0,
            '超清' => 1,
            '高清' => 2,
            '标清' => 3,
            '流畅' => 4,
        ];
        return $map[$quality] ?? 0;
    }
}
