<?php

declare(strict_types=1);

namespace LiveStream\Platforms\Douyin;

use LiveStream\Contracts\PlatformInterface;
use LiveStream\Contracts\RoomInfoInterface;
use LiveStream\Platforms\Douyin\Http\Connector\DouyinConnector;


class DouyinPlatform implements PlatformInterface
{
    private ?RoomInfoInterface $room = null;

    public function __construct(private DouyinConnector $connector, private string $url) {}

    public function getPlatformName(): string
    {
        return 'douyin';
    }

    public function supportsUrl(string $url): bool
    {
        return (bool)preg_match('/(live\\.douyin\\.com|v\\.douyin\\.com|www\\.douyin\\.com)/', $url);
    }

    // 基于 Resource 提供 RoomInfo
    public function getRoomInfo(): RoomInfoInterface
    {
        if ($this->room === null) {
            $this->room = $this->connector->resource()->getDouYinRoomInfo($this->url);
        }
        return $this->room;
    }

    /**
     * 获取抖音平台特定的 Referer 请求头
     * 
     * 抖音的 HLS 流需要正确的 Referer 头才能正常访问
     */
    public function getReferer(): string
    {
        return 'https://live.douyin.com/';
    }
}
