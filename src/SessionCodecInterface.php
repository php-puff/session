<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

namespace Puff\Session;

interface SessionCodecInterface
{
    /** @param array<string, mixed> $data */
    public function encode(array $data): string;

    /** @return array<string, mixed> */
    public function decode(string $payload): array;
}
