<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

namespace Puff\Session;

interface SessionStoreInterface
{
    public function read(string $id): ?string;
    public function write(string $id, string $payload, int $ttl): void;
    public function delete(string $id): void;
}
