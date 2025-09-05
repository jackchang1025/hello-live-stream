<?php

declare(strict_types=1);

namespace LiveStream\Exceptions;

use Exception;

/**
 * 音频提取异常
 */
class AudioExtractionException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
