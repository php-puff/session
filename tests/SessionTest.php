<?php

declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

namespace Puff\Session\Tests;

use Fiber;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Puff\Config\Config;
use Puff\Di\Container;
use Puff\Redis\ClientInterface;
use Puff\Session\Codec\JsonSessionCodec;
use Puff\Session\Codec\MigratingSessionCodec;
use Puff\Session\Codec\PhpSessionCodec;
use Puff\Session\Pipeline;
use Puff\Session\ServiceProvider;
use Puff\Session\Session;
use Puff\Session\SessionStoreInterface;
use Puff\Session\Store\MemorySessionStore;
use Puff\Session\Store\RedisSessionStore;
use RuntimeException;
use UnexpectedValueException;

final class SessionTest extends TestCase
{
    public function testWritesJsonAndReadsLegacyPhpPayload(): void
    {
        $store = new MemorySessionStore();
        $codec = new MigratingSessionCodec();
        $id = \str_repeat('a', 64);
        $store->write($id, (new PhpSessionCodec())->encode(['user' => 7]), 60);

        $session = Session::open($id, $store, $codec, 60);
        self::assertSame(7, $session->get('user'));
        $session->set('active', true)->save();
        self::assertStringStartsWith(JsonSessionCodec::PREFIX, (string) $store->read($id));
    }

    public function testLegacyCodecRejectsObjects(): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new PhpSessionCodec())->decode('payload|' . \serialize(new \stdClass()));
    }

    public function testSessionsRemainIsolatedAcrossFibers(): void
    {
        $store = new MemorySessionStore();
        $codec = new MigratingSessionCodec();
        $results = [];
        $fibers = [];
        foreach (['a', 'b'] as $name) {
            $fibers[] = new Fiber(function () use ($name, $store, $codec, &$results): void {
                $session = Session::open(\str_repeat($name, 64), $store, $codec);
                $session->set('name', $name)->save();
                Fiber::suspend();
                $results[$name] = Session::open(\str_repeat($name, 64), $store, $codec)->get('name');
            });
        }
        foreach ($fibers as $fiber) {
            $fiber->start();
        }
        foreach ($fibers as $fiber) {
            $fiber->resume();
        }
        self::assertSame(['a' => 'a', 'b' => 'b'], $results);
    }

    public function testPipelineCreatesSecureSessionId(): void
    {
        $container = new Container();
        $store = new MemorySessionStore();
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getCookieParams')->willReturn([]);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn('https');
        $request->method('getUri')->willReturn($uri);
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())
            ->method('withAddedHeader')
            ->with(
                'Set-Cookie',
                self::callback(static fn (string $cookie): bool => \preg_match(
                    '/^PUFF-ID=[a-f0-9]{64}; Path=\/; Max-Age=60; SameSite=Lax; HttpOnly; Secure$/',
                    $cookie,
                ) === 1),
            )
            ->willReturnSelf();
        $id = null;

        $result = (new Pipeline($container, $store, new JsonSessionCodec(), 'PUFF-ID', 60))->handle(
            $request,
            static function () use ($container, $response, &$id): ResponseInterface {
                $id = $container->make(Session::class)->id();
                return $response;
            },
        );

        self::assertSame($response, $result);
        self::assertIsString($id);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $id);
    }

    public function testRegenerateReplacesSessionId(): void
    {
        $session = Session::open(
            \str_repeat('a', 64),
            new MemorySessionStore(),
            new JsonSessionCodec(),
        );

        $id = $session->regenerate();

        self::assertNotSame(\str_repeat('a', 64), $id);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $id);
        self::assertTrue($session->idChanged());
    }

    public function testPipelineRefreshesCookieAfterRegeneration(): void
    {
        $container = new Container();
        $request = $this->requestWithCookie(\str_repeat('a', 64));
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())
            ->method('withAddedHeader')
            ->with('Set-Cookie', self::stringStartsWith('PUFF-ID='))
            ->willReturnSelf();

        (new Pipeline($container, new MemorySessionStore(), new JsonSessionCodec(), 'PUFF-ID'))->handle(
            $request,
            static function () use ($container, $response): ResponseInterface {
                $container->make(Session::class)->regenerate();
                return $response;
            },
        );
    }

    public function testPipelineExpiresCookieAfterDestroy(): void
    {
        $container = new Container();
        $request = $this->requestWithCookie(\str_repeat('a', 64));
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())
            ->method('withAddedHeader')
            ->with('Set-Cookie', self::stringContains('Max-Age=0'))
            ->willReturnSelf();

        (new Pipeline($container, new MemorySessionStore(), new JsonSessionCodec(), 'PUFF-ID'))->handle(
            $request,
            static function () use ($container, $response): ResponseInterface {
                $container->make(Session::class)->destroy();
                return $response;
            },
        );
    }

    public function testRedisStoreUsesPrefixAndTtl(): void
    {
        $redis = new FakeRedisClient();
        $store = new RedisSessionStore($redis, 'sessions:');
        $id = \str_repeat('b', 64);

        $store->write($id, 'payload', 120);

        self::assertSame('payload', $store->read($id));
        self::assertSame(120, $redis->ttls['sessions:' . $id]);

        $store->delete($id);
        self::assertNull($store->read($id));
    }

    public function testRedisStoreRejectsInvalidSessionId(): void
    {
        $this->expectException(RuntimeException::class);
        (new RedisSessionStore(new FakeRedisClient()))->read('../session');
    }

    public function testServiceProviderSelectsRedisStore(): void
    {
        $container = new Container();
        $container->instance('config', new Config(['session' => [
            'store' => 'redis',
            'prefix' => 'test:',
        ]]));
        $container->instance(ClientInterface::class, new FakeRedisClient());

        (new ServiceProvider($container))->register();

        self::assertInstanceOf(RedisSessionStore::class, $container->get(SessionStoreInterface::class));
    }

    private function requestWithCookie(string $id): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getCookieParams')->willReturn(['PUFF-ID' => $id]);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn('http');
        $request->method('getUri')->willReturn($uri);
        return $request;
    }
}

final class FakeRedisClient implements ClientInterface
{
    /** @var array<string, string> */
    public array $values = [];

    /** @var array<string, int> */
    public array $ttls = [];

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        $this->values[$key] = $value;
        $this->ttls[$key] = $ttl;
        return true;
    }

    public function delete(string ...$keys): int
    {
        $deleted = 0;
        foreach ($keys as $key) {
            if (isset($this->values[$key])) {
                unset($this->values[$key], $this->ttls[$key]);
                ++$deleted;
            }
        }
        return $deleted;
    }
}
