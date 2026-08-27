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

final readonly class MigratingSessionCodec implements SessionCodecInterface
{
    public function __construct(
        private JsonSessionCodec $json = new JsonSessionCodec(),
        private PhpSessionCodec $legacy = new PhpSessionCodec(),
    ) {
    }

    public function encode(array $data): string
    {
        return $this->json->encode($data);
    }

    public function decode(string $payload): array
    {
        return \str_starts_with($payload, JsonSessionCodec::PREFIX)
            ? $this->json->decode($payload)
            : $this->legacy->decode($payload);
    }
}
