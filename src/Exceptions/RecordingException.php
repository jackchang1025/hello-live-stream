<?php

declare(strict_types=1);

namespace LiveStream\Exceptions;

use Exception;
use Throwable;

/**
 * 录制异常类
 * 
 * 提供录制过程中可能出现的各种异常类型
 * 遵循领域驱动设计原则，提供丰富的上下文信息
 */
final class RecordingException extends Exception
{
    // 错误代码常量
    public const ERROR_FFMPEG_NOT_FOUND = 1001;
    public const ERROR_PROCESS_START_FAILED = 1002;
    public const ERROR_INVALID_STREAM_URL = 1003;
    public const ERROR_DISK_SPACE_INSUFFICIENT = 1004;
    public const ERROR_STREAM_NOT_LIVE = 1005;
    public const ERROR_INVALID_CONFIGURATION = 1006;
    public const ERROR_PERMISSION_DENIED = 1007;
    public const ERROR_NETWORK_TIMEOUT = 1008;
    public const ERROR_UNSUPPORTED_FORMAT = 1009;
    public const ERROR_COMMAND_BUILD_FAILED = 1010;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        private readonly ?string $recordId = null,
        private readonly ?string $url = null,
        private readonly array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取录制ID
     */
    public function getRecordId(): ?string
    {
        return $this->recordId;
    }

    /**
     * 获取相关URL
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * 获取异常上下文信息
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 获取完整的异常信息数组
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'record_id' => $this->recordId,
            'url' => $this->url,
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }

    // ==================== 静态工厂方法 ====================

    /**
     * FFmpeg 未找到异常
     */
    public static function ffmpegNotFound(?string $path = null): self
    {
        $message = $path
            ? "FFmpeg not found at path: {$path}"
            : 'FFmpeg not found in system PATH';

        return new self(
            message: $message,
            code: self::ERROR_FFMPEG_NOT_FOUND,
            context: ['searched_path' => $path]
        );
    }

    /**
     * 进程启动失败异常
     */
    public static function processStartFailed(string $recordId, string $url, ?Throwable $previous = null): self
    {
        return new self(
            message: 'Failed to start recording process',
            code: self::ERROR_PROCESS_START_FAILED,
            previous: $previous,
            recordId: $recordId,
            url: $url
        );
    }

    /**
     * 无效流地址异常
     */
    public static function invalidStreamUrl(string $url, ?string $reason = null): self
    {
        $message = $reason
            ? "Invalid or unreachable stream URL: {$reason}"
            : 'Invalid or unreachable stream URL';

        return new self(
            message: $message,
            code: self::ERROR_INVALID_STREAM_URL,
            url: $url,
            context: ['reason' => $reason]
        );
    }

    /**
     * 磁盘空间不足异常
     */
    public static function diskSpaceInsufficient(string $path, int $requiredBytes = 0, int $availableBytes = 0): self
    {
        return new self(
            message: "Insufficient disk space at path: {$path}",
            code: self::ERROR_DISK_SPACE_INSUFFICIENT,
            context: [
                'path' => $path,
                'required_bytes' => $requiredBytes,
                'available_bytes' => $availableBytes,
            ]
        );
    }

    /**
     * 直播未开始异常
     */
    public static function streamNotLive(?string $anchorName = null,?string $url = null): self
    {
        $context = ['anchor_name' => $anchorName];

        return new self(
            message: "{$anchorName} 直播间未开播",
            code: self::ERROR_STREAM_NOT_LIVE,
            url: $url,
            context: $context
        );
    }

    /**
     * 配置无效异常
     */
    public static function invalidConfiguration(array $errors, array $config = []): self
    {
        return new self(
            message: 'Invalid configuration: ' . implode(', ', $errors),
            code: self::ERROR_INVALID_CONFIGURATION,
            context: [
                'validation_errors' => $errors,
                'config' => $config,
            ]
        );
    }

    /**
     * 权限拒绝异常
     */
    public static function permissionDenied(string $path, string $operation = 'write'): self
    {
        return new self(
            message: "Permission denied for {$operation} operation on path: {$path}",
            code: self::ERROR_PERMISSION_DENIED,
            context: [
                'path' => $path,
                'operation' => $operation,
            ]
        );
    }

    /**
     * 网络超时异常
     */
    public static function networkTimeout(string $url, int $timeoutSeconds): self
    {
        return new self(
            message: "Network timeout after {$timeoutSeconds} seconds",
            code: self::ERROR_NETWORK_TIMEOUT,
            url: $url,
            context: ['timeout_seconds' => $timeoutSeconds]
        );
    }

    /**
     * 不支持的格式异常
     */
    public static function unsupportedFormat(string $format, array $supportedFormats = []): self
    {
        return new self(
            message: "Unsupported format: {$format}",
            code: self::ERROR_UNSUPPORTED_FORMAT,
            context: [
                'requested_format' => $format,
                'supported_formats' => $supportedFormats,
            ]
        );
    }

    /**
     * 命令构建失败异常
     */
    public static function commandBuildFailed(string $reason, array $config = []): self
    {
        return new self(
            message: "Failed to build FFmpeg command: {$reason}",
            code: self::ERROR_COMMAND_BUILD_FAILED,
            context: [
                'reason' => $reason,
                'config' => $config,
            ]
        );
    }

    /**
     * 从通用异常创建录制异常
     */
    public static function fromException(Throwable $exception, ?string $recordId = null, ?string $url = null): self
    {
        return new self(
            message: $exception->getMessage(),
            code: $exception->getCode(),
            previous: $exception,
            recordId: $recordId,
            url: $url,
            context: [
                'original_exception' => get_class($exception),
                'original_file' => $exception->getFile(),
                'original_line' => $exception->getLine(),
            ]
        );
    }

    /**
     * 不支持的分割器类型异常
     */
    public static function unsupportedSplitterType(string $format, array $supportedFormats): self
    {
        $supportedList = implode(', ', $supportedFormats);

        return new self(
            message: "Unsupported splitter type '{$format}'. Supported formats: {$supportedList}",
            code: self::ERROR_UNSUPPORTED_FORMAT,
            context: [
                'format' => $format,
                'supported_formats' => $supportedFormats
            ]
        );
    }
}
