<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

namespace Puff\Session\Codec;

use Puff\Session\SessionCodecInterface;
use UnexpectedValueException;

final class PhpSessionCodec implements SessionCodecInterface
{
    public function encode(array $data): string
    {
        $payload = '';
        foreach ($data as $key => $value) {
            if (\str_contains($key, '|') || $this->containsObject($value)) {
                throw new UnexpectedValueException('PHP session values cannot contain objects or invalid keys.');
            }
            $payload .= $key . '|' . \serialize($value);
        }
        return $payload;
    }

    public function decode(string $payload): array
    {
        $data = [];
        $offset = 0;
        while ($offset < \strlen($payload)) {
            $separator = \strpos($payload, '|', $offset);
            if ($separator === false) {
                throw new UnexpectedValueException('Invalid PHP session payload.');
            }
            $key = \substr($payload, $offset, $separator - $offset);
            $offset = $separator + 1;
            [$value, $consumed] = $this->decodeValue(\substr($payload, $offset));
            $data[$key] = $value;
            $offset += $consumed;
        }
        return $data;
    }

    /** @return array{mixed, int} */
    private function decodeValue(string $payload): array
    {
        $value = @\unserialize($payload, ['allowed_classes' => false]);
        if ($value === false && !\str_starts_with($payload, 'b:0;')) {
            throw new UnexpectedValueException('Invalid serialized session value.');
        }
        if ($this->containsObject($value)) {
            throw new UnexpectedValueException('Objects are not allowed in session data.');
        }
        $encoded = \serialize($value);
        if (!\str_starts_with($payload, $encoded)) {
            throw new UnexpectedValueException('Invalid serialized session boundary.');
        }
        return [$value, \strlen($encoded)];
    }

    private function containsObject(mixed $value): bool
    {
        if (\is_object($value)) {
            return true;
        }
        if (!\is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if ($this->containsObject($item)) {
                return true;
            }
        }
        return false;
    }
}
