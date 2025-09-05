<?php

declare(strict_types=1);

namespace LiveStream\Utils;

use Closure;


/**
 * @internal
 */
final class Helpers
{
    /**
     * Return the default value of the given value.
     */
    public static function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}
