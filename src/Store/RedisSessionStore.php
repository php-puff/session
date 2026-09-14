<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Session\Store;

use Puff\Redis\ClientInterface;
use Puff\Session\SessionStoreInterface;
use RuntimeException;

final readonly class RedisSessionStore implements SessionStoreInterface
{
    public function __construct(
        private ClientInterface $redis,
        private string $prefix = 'puff:session:',
    ) {
    }

    public function read(string $id): ?string
    {
        return $this->redis->get($this->key($id));
    }

    public function write(string $id, string $payload, int $ttl): void
    {
        if ($ttl < 1) {
            throw new RuntimeException('Session TTL must be greater than zero.');
        }
        if (!$this->redis->set($this->key($id), $payload, $ttl)) {
            throw new RuntimeException('Unable to write session data to Redis.');
        }
    }

    public function delete(string $id): void
    {
        $this->redis->delete($this->key($id));
    }

    private function key(string $id): string
    {
        if (\preg_match('/^[a-f0-9]{64}$/D', $id) !== 1) {
            throw new RuntimeException('Invalid session identifier.');
        }
        return $this->prefix . $id;
    }
}
