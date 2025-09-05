<?php

declare(strict_types=1);

namespace LiveStream\Platforms\Douyin\Http\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Douyin App 接口 GET 请求
 */
class DouYinAppStreamRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private string $url) {}

    public function resolveEndpoint(): string
    {
        return $this->url;
    }
}
