<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

namespace Puff\Session\Codec;

use JsonException;
use Puff\Session\SessionCodecInterface;
use UnexpectedValueException;

final class JsonSessionCodec implements SessionCodecInterface
{
    public const PREFIX = 'puffj1:';

    public function encode(array $data): string
    {
        try {
            return self::PREFIX . \json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('Session data cannot be encoded as JSON.', 0, $exception);
        }
    }

    public function decode(string $payload): array
    {
        if (!\str_starts_with($payload, self::PREFIX)) {
            throw new UnexpectedValueException('Unsupported JSON session payload version.');
        }
        try {
            $data = \json_decode(\substr($payload, \strlen(self::PREFIX)), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('Invalid JSON session payload.', 0, $exception);
        }
        if (!\is_array($data)) {
            throw new UnexpectedValueException('Session payload must decode to an array.');
        }
        return $data;
    }
}
