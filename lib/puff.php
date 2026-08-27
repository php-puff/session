<?php

declare(strict_types=1);
/*
 * PHP Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

use Puff\Di\Container;
use Puff\Session\Session;

if (!\function_exists('session')) {
    function session(?string $key = null, mixed $value = null): mixed
    {
        $session = Container::getInstance()?->get(Session::class);
        if (!$session instanceof Session) {
            throw new LogicException('Session is only available inside the session pipeline.');
        }
        if ($key === null) {
            return $session;
        }
        return \func_num_args() > 1 ? $session->set($key, $value) : $session->get($key);
    }
}
