<?php

declare(strict_types=1);

namespace LiveStream\Shared\Exceptions;

use Exception;

/**
 * 应用层异常基类
 * 
 * 用于应用层的协调和流程异常
 */
abstract class ApplicationException extends Exception
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