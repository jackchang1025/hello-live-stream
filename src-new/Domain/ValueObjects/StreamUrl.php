<?php

declare(strict_types=1);

namespace LiveStream\Domain\ValueObjects;

use LiveStream\Shared\Exceptions\DomainException;

/**
 * 流地址值对象
 * 
 * 封装URL验证和操作逻辑
 */
final readonly class StreamUrl
{
    private function __construct(
        private string $value
    ) {
        $this->validate();
    }

    /**
     * 从字符串创建流地址
     *
     * @param string $url
     * @return self
     * @throws InvalidStreamUrlException
     */
    public static function fromString(string $url): self
    {
        return new self($url);
    }

    /**
     * 获取URL值
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * 获取域名
     *
     * @return string
     */
    public function getDomain(): string
    {
        return parse_url($this->value, PHP_URL_HOST) ?? '';
    }

    /**
     * 获取协议
     *
     * @return string
     */
    public function getScheme(): string
    {
        return parse_url($this->value, PHP_URL_SCHEME) ?? '';
    }

    /**
     * 获取路径
     *
     * @return string
     */
    public function getPath(): string
    {
        return parse_url($this->value, PHP_URL_PATH) ?? '';
    }

    /**
     * 检查是否为HTTPS
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return $this->getScheme() === 'https';
    }

    /**
     * 检查是否匹配域名模式
     *
     * @param string $pattern 正则表达式模式
     * @return bool
     */
    public function matchesDomain(string $pattern): bool
    {
        return (bool) preg_match($pattern, $this->getDomain());
    }

    /**
     * 验证URL格式
     *
     * @throws InvalidStreamUrlException
     */
    private function validate(): void
    {
        if (empty($this->value)) {
            throw new InvalidStreamUrlException('Stream URL cannot be empty');
        }

        if (!filter_var($this->value, FILTER_VALIDATE_URL)) {
            throw new InvalidStreamUrlException("Invalid URL format: {$this->value}");
        }

        $scheme = $this->getScheme();
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidStreamUrlException("Unsupported URL scheme: {$scheme}");
        }

        if (empty($this->getDomain())) {
            throw new InvalidStreamUrlException("URL must have a valid domain: {$this->value}");
        }
    }

    /**
     * 字符串表示
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * 相等性比较
     *
     * @param self $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

/**
 * 无效流地址异常
 */
final class InvalidStreamUrlException extends DomainException
{
    public function getErrorCode(): string
    {
        return 'INVALID_STREAM_URL';
    }
}