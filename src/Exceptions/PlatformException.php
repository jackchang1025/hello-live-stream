<?php

declare(strict_types=1);

namespace LiveStream\Exceptions;

use Exception;

/**
 * 直播平台异常类
 * 
 * 用于处理直播平台相关的异常
 */
class PlatformException extends Exception
{
    private string $platform;
    private string $url;

    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null, string $platform = '', string $url = '')
    {
        parent::__construct($message, $code, $previous);
        $this->platform = $platform;
        $this->url = $url;
    }

    /**
     * 获取平台名称
     * 
     * @return string
     */
    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * 获取URL
     * 
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * 创建网络异常
     * 
     * @param string $message 异常消息
     * @param string $platform 平台名称
     * @param string $url URL
     * @param Exception|null $previous 前一个异常
     * @return static
     */
    public static function networkError(string $message, string $platform, string $url, ?Exception $previous = null): static
    {
        return new static("Network error for {$platform}: {$message}", 0, $previous, $platform, $url);
    }

    /**
     * 创建解析异常
     * 
     * @param string $message 异常消息
     * @param string $platform 平台名称
     * @param string $url URL
     * @param Exception|null $previous 前一个异常
     * @return static
     */
    public static function parseError(string $message, string $platform, string $url, ?Exception $previous = null): static
    {
        return new static("Parse error for {$platform}: {$message}", 0, $previous, $platform, $url);
    }

    /**
     * 创建未支持URL异常
     * 
     * @param string $url URL
     * @param string $platform 平台名称
     * @return static
     */
    public static function unsupportedUrl(string $url, string $platform): static
    {
        return new static("Unsupported URL for {$platform}: {$url}", 0, null, $platform, $url);
    }

    /**
     * 创建直播未开始异常
     * 
     * @param string $platform 平台名称
     * @param string $url URL
     * @return static
     */
    public static function notLive(string $platform, string $url): static
    {
        return new static("Live stream is not started for {$platform}", 0, null, $platform, $url);
    }
}
