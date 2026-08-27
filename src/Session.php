<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

namespace Puff\Session;

/** @implements \IteratorAggregate<string, mixed> */
final class Session implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private string $id,
        private readonly SessionStoreInterface $store,
        private readonly SessionCodecInterface $codec,
        private array $data = [],
        private readonly int $ttl = 7200,
        private bool $dirty = false,
        private bool $idChanged = false,
        private bool $destroyed = false,
    ) {
        if ($ttl < 1) {
            throw new \InvalidArgumentException('Session TTL must be greater than zero.');
        }
    }

    public static function open(string $id, SessionStoreInterface $store, SessionCodecInterface $codec, int $ttl = 7200): self
    {
        $payload = $store->read($id);
        return new self($id, $store, $codec, $payload === null ? [] : $codec->decode($payload), $ttl);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function regenerate(): string
    {
        $this->store->delete($this->id);
        $this->id = \bin2hex(\random_bytes(32));
        $this->dirty = true;
        $this->idChanged = true;
        $this->destroyed = false;
        return $this->id;
    }

    public function idChanged(): bool
    {
        return $this->idChanged;
    }

    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        $this->dirty = true;
        $this->destroyed = false;
        return $this;
    }

    public function remove(string $key): self
    {
        unset($this->data[$key]);
        $this->dirty = true;
        return $this;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    public function save(): void
    {
        if ($this->dirty) {
            $this->store->write($this->id, $this->codec->encode($this->data), $this->ttl);
            $this->dirty = false;
        }
    }

    public function destroy(): void
    {
        $this->store->delete($this->id);
        $this->data = [];
        $this->dirty = false;
        $this->destroyed = true;
    }

    public function count(): int
    {
        return \count($this->data);
    }

    /** @return \ArrayIterator<string, mixed> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
