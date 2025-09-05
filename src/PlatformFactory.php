<?php

declare(strict_types=1);

namespace LiveStream;

use LiveStream\Contracts\PlatformInterface;
use LiveStream\Exceptions\PlatformException;
use LiveStream\Platforms\Douyin\Http\Connector\DouyinConnector;
use LiveStream\Platforms\Douyin\DouyinPlatform;

class PlatformFactory
{
    public static function createPlatform(string $url): PlatformInterface
    {
        if (preg_match('/(live\\.douyin\\.com|v\\.douyin\\.com|www\\.douyin\\.com)/', $url)) {
            return new DouyinPlatform(new DouyinConnector(), $url);
        }

        throw new PlatformException('Unsupported URL', 0, null, 'Unknown', $url);
    }
}
