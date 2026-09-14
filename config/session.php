<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/puff
 * https://github.com/php-puff/puff/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

return [
    'store' => 'memory',
    'cookie' => 'PFID',
    'ttl' => 7200,
    'path' => \dirname(__DIR__) . '/runtime/session',
    'prefix' => 'puff:session:',
    'cookie_path' => '/',
    'cookie_domain' => '',
    'same_site' => 'Lax',
    'http_only' => true,
    'secure' => null,
];
