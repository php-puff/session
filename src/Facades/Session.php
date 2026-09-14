<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Session\Facades;

use Puff\Di\Facade;

/**
 * @method static string id()
 * @method static string regenerate()
 * @method static mixed get(string $key, mixed $default = null)
 * @method static \Puff\Session\Session set(string $key, mixed $value)
 * @method static \Puff\Session\Session remove(string $key)
 * @method static array<string, mixed> all()
 * @method static void save()
 * @method static void destroy()
 * @method static int count()
 */
final class Session extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Puff\Session\Session::class;
    }
}
