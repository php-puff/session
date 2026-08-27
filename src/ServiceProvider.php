<?php

declare(strict_types=1);
/*
 * PHP Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

namespace Puff\Session;

use Puff\Di\Container;
use Puff\Di\ServiceProvider as BaseServiceProvider;
use Puff\Redis\ClientInterface;
use Puff\Session\Codec\MigratingSessionCodec;
use Puff\Session\Store\FilesSessionStore;
use Puff\Session\Store\MemorySessionStore;
use Puff\Session\Store\RedisSessionStore;

final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->alias(Session::class, 'session');
        $this->app->bind(Session::class, static function (): never {
            throw new \LogicException('Session is only available inside the session pipeline.');
        });
        $this->app->singleton(SessionCodecInterface::class, MigratingSessionCodec::class);
        $this->app->singleton(SessionStoreInterface::class, function (Container $container): SessionStoreInterface {
            $store = (string) $this->config($container, 'session.store', 'memory');

            return match ($store) {
                'files' => new FilesSessionStore((string) $this->config(
                    $container,
                    'session.path',
                    \getcwd() . '/runtime/session',
                )),
                'memory' => new MemorySessionStore(),
                'redis' => $this->redisStore($container),
                default => throw new \InvalidArgumentException("Unsupported session store [{$store}]."),
            };
        });
        $this->app->singleton(Pipeline::class, function (Container $container): Pipeline {
            return new Pipeline(
                $container,
                $container->make(SessionStoreInterface::class),
                $container->make(SessionCodecInterface::class),
                (string) $this->config($container, 'session.cookie', 'PFID'),
                (int) $this->config($container, 'session.ttl', 7200),
                (string) $this->config($container, 'session.cookie_path', '/'),
                (string) $this->config($container, 'session.cookie_domain', ''),
                (string) $this->config($container, 'session.same_site', 'Lax'),
                (bool) $this->config($container, 'session.http_only', true),
                $this->nullableBool($this->config($container, 'session.secure', null)),
            );
        });
    }

    private function redisStore(Container $container): RedisSessionStore
    {
        if (!\interface_exists(ClientInterface::class) || !$container->bound(ClientInterface::class)) {
            throw new \LogicException('The Redis session store requires puff/redis. Install and register that package first.');
        }
        $redis = $container->make(ClientInterface::class);
        if (!$redis instanceof ClientInterface) {
            throw new \LogicException('The Redis client service must implement ' . ClientInterface::class . '.');
        }
        return new RedisSessionStore(
            $redis,
            (string) $this->config($container, 'session.prefix', 'puff:session:'),
        );
    }

    private function config(Container $container, string $key, mixed $default): mixed
    {
        if (!$container->bound('config')) {
            return $default;
        }
        $config = $container->make('config');
        if (!\is_object($config) || !\is_callable([$config, 'get'])) {
            throw new \LogicException('The config service must provide a get() method.');
        }
        return $config->get($key, $default) ?? $default;
    }

    private function nullableBool(mixed $value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }
}
