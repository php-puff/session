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
use RuntimeException;

final readonly class FilesSessionStore implements SessionStoreInterface
{
    public function __construct(private string $directory)
    {
        if (!\is_dir($directory) && !\mkdir($directory, 0700, true) && !\is_dir($directory)) {
            throw new RuntimeException('Unable to create session directory: ' . $directory);
        }
    }

    public function read(string $id): ?string
    {
        $file = $this->file($id);
        if (!\is_file($file)) {
            return null;
        }
        if ((int) @\filemtime($file) < \time()) {
            @\unlink($file);
            return null;
        }
        $handle = @\fopen($file, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            if (!\flock($handle, LOCK_SH)) {
                return null;
            }
            $payload = \stream_get_contents($handle);
            \flock($handle, LOCK_UN);
            return $payload === false ? null : $payload;
        } finally {
            \fclose($handle);
        }
    }

    public function write(string $id, string $payload, int $ttl): void
    {
        if ($ttl < 1) {
            throw new RuntimeException('Session TTL must be greater than zero.');
        }
        $file = $this->file($id);
        $temporary = \tempnam($this->directory, '.session-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create temporary session file.');
        }
        try {
            if (\file_put_contents($temporary, $payload, LOCK_EX) === false || !\touch($temporary, \time() + $ttl)) {
                throw new RuntimeException('Unable to write session data.');
            }
            \chmod($temporary, 0600);
            if (!\rename($temporary, $file)) {
                throw new RuntimeException('Unable to commit session data.');
            }
        } finally {
            if (\is_file($temporary)) {
                @\unlink($temporary);
            }
        }
    }

    public function delete(string $id): void
    {
        $file = $this->file($id);
        if (\is_file($file)) {
            @\unlink($file);
        }
    }

    private function file(string $id): string
    {
        if (\preg_match('/^[a-f0-9]{64}$/', $id) !== 1) {
            throw new RuntimeException('Invalid session identifier.');
        }
        return $this->directory . DIRECTORY_SEPARATOR . $id . '.session';
    }
}
