<?php

declare(strict_types=1);

namespace LiveStream\Shared\Exceptions;

use Exception;

/**
 * 基础设施层异常基类
 * 
 * 用于基础设施层的外部依赖和技术异常
 */
abstract class InfrastructureException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        protected readonly array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取异常上下文信息
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 获取异常的唯一标识符
     *
     * @return string
     */
    abstract public function getErrorCode(): string;
}