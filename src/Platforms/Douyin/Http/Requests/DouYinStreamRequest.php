<?php

declare(strict_types=1);

namespace LiveStream\Platforms\Douyin\Http\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * 通用 GET 请求（支持绝对 URL、可传入自定义请求头与代理）
 */
class DouYinStreamRequest extends Request
{
    protected Method $method = Method::GET;


    public function __construct(private string $url)
    {
    }

    public function resolveEndpoint(): string
    {
        // 直接返回绝对 URL
        return $this->url;
    }
}
