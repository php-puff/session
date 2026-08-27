<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

namespace Puff\Session\Store;

use Puff\Session\SessionStoreInterface;

final class MemorySessionStore implements SessionStoreInterface
{
    /** @var array<string, array{payload: string, expires: int}> */
    private array $sessions = [];

    public function read(string $id): ?string
    {
        $session = $this->sessions[$id] ?? null;
        if ($session === null || $session['expires'] < \time()) {
            unset($this->sessions[$id]);
            return null;
        }
        return $session['payload'];
    }

    public function write(string $id, string $payload, int $ttl): void
    {
        if ($ttl < 1) {
            throw new \InvalidArgumentException('Session TTL must be greater than zero.');
        }
        $this->sessions[$id] = ['payload' => $payload, 'expires' => \time() + $ttl];
    }

    public function delete(string $id): void
    {
        unset($this->sessions[$id]);
    }
}
