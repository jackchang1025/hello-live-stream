<?php

declare(strict_types=1);

namespace LiveStream\Contracts;

/**
 * 直播平台基础接口
 *
 * 基于DouyinLiveRecorder项目main.py的实际录制流程设计
 * 确保接口与Python版本的业务逻辑保持一致
 */
interface PlatformInterface
{

    /**
     * 获取平台名称（对应main.py中的platform变量）
     *
     * @return string 平台名称，如：'抖音直播'、'快手直播'、'B站直播'等
     */
    public function getPlatformName(): string;

    /**
     * 检查URL是否属于该平台（对应main.py中的URL匹配逻辑）
     *
     * @param string $url 直播间URL
     * @return bool 是否支持该URL
     */
    public function supportsUrl(string $url): bool;

    /**
     * 获取房间信息（对应main.py中spider模块调用）
     *
     * 这是录制流程的第一步，获取直播间的基础信息
     * 对应Python版本中的：spider.get_douyin_stream_data()等方法
     *
     * @return RoomInfoInterface 房间信息对象
     * @throws \LiveStream\Exceptions\PlatformException
     */
    public function getRoomInfo(): RoomInfoInterface;

    /**
     * 获取平台特定的 Referer 请求头
     *
     * 不同平台的 HLS 流可能需要特定的 Referer 头才能正常访问
     * 对应 main.py 中的 record_headers 配置逻辑
     *
     * @return string|null 平台特定的 Referer 值，null 表示不需要特殊设置
     */
    public function getReferer(): string;
}
