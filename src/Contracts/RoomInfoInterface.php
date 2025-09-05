<?php

declare(strict_types=1);

namespace LiveStream\Contracts;

use LiveStream\Enum\Quality;
use LiveStream\Config\StreamConfig;

interface RoomInfoInterface
{
    /**
     * 检查直播状态（对应main.py中的is_live判断）
     *
     * @param array $roomInfo 房间信息
     * @return bool 是否正在直播
     */
    public function isLive(): bool;

    /**
     * 获取流配置（对应main.py中stream模块调用）
     *
     * 这是录制流程的第二步，处理流地址获取
     * 对应Python版本中的：stream.get_douyin_stream_url()等方法
     *
     * @param string $quality 画质代码（原画|超清|高清|标清|流畅）
     * @param string|null $proxy 代理地址
     * @return StreamConfig 流配置对象，包含所有流相关信息
     * @throws \LiveStream\Exceptions\PlatformException 当直播不存在或获取流地址失败时
     */
    public function getStreamConfig(string $quality = '原画', ?string $proxy = null): StreamConfig;

    /**
     * 获取画质映射（对应main.py中的get_quality_code函数）
     *
     * @return array 画质映射表，如：['原画' => 'OD', '超清' => 'UHD']
     */
    public function getQualityMapping(): array;

    /**
     * 是否支持指定画质（对应虎牙等平台的画质限制）
     *
     * @param string $quality 画质代码
     * @return bool 是否支持
     */
    public function supportsQuality(): bool;

    /**
     * 获取主播名称（对应main.py中的anchor_name提取）
     *
     * @param array $roomInfo 房间信息
     * @return string 主播名称
     */
    public function getAnchorName(): string;

    /**
     * 获取直播标题（对应main.py中的title提取）
     *
     * @param array $roomInfo 房间信息
     * @return string 直播标题
     */
    public function getTitle(): string;

    /**
     * 获取房间ID（对应main.py中的room_id提取）
     *
     * @param array $roomInfo 房间信息
     * @return string 房间ID
     */
    public function getRoomId(): string;

    /**
     * 获取画质索引
     */
    public function getQuality(): Quality;
}
