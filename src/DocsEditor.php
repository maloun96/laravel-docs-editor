<?php

declare(strict_types=1);

namespace Maloun96\DocsEditor;

use Closure;

final class DocsEditor
{
    /** @var Closure|null */
    private static ?Closure $authCallback = null;

    public static function auth(Closure $callback): void
    {
        static::$authCallback = $callback;
    }

    public static function authCallback(): ?Closure
    {
        return static::$authCallback;
    }
}
