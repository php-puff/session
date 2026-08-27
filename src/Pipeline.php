<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/session
 * https://github.com/php-puff/session/issues
 * Copyright (c) Puff
 */

namespace Puff\Session;

use Closure;
use Puff\Di\Container;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Pipeline
{
    public function __construct(
        private Container $container,
        private SessionStoreInterface $store,
        private SessionCodecInterface $codec,
        private string $cookie = 'PUFF-ID',
        private int $ttl = 7200,
        private string $path = '/',
        private string $domain = '',
        private string $sameSite = 'Lax',
        private bool $httpOnly = true,
        private ?bool $secure = null,
    ) {
        if (\preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $cookie) !== 1) {
            throw new \InvalidArgumentException('Invalid session cookie name.');
        }
        if ($ttl < 1) {
            throw new \InvalidArgumentException('Session TTL must be greater than zero.');
        }
        if (!\in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new \InvalidArgumentException('Session SameSite must be Lax, Strict, or None.');
        }
        if ($path === '' || \preg_match('/[\x00-\x1F\x7F;]/', $path) === 1) {
            throw new \InvalidArgumentException('Invalid session cookie path.');
        }
        if (\preg_match('/[\x00-\x20\x7F;]/', $domain) === 1) {
            throw new \InvalidArgumentException('Invalid session cookie domain.');
        }
    }

    public function handle(ServerRequestInterface $request, Closure $next): mixed
    {
        $id = $request->getCookieParams()[$this->cookie] ?? null;
        $fresh = !\is_string($id) || \preg_match('/^[a-f0-9]{64}$/', $id) !== 1;
        if ($fresh) {
            $id = \bin2hex(\random_bytes(32));
        }
        $session = Session::open($id, $this->store, $this->codec, $this->ttl);
        $this->container->scopedInstance(Session::class, $session);
        $this->container->scopedInstance('session', $session);
        $response = $next($request);
        $session->save();

        if (!$response instanceof ResponseInterface) {
            return $response;
        }

        if ($session->isDestroyed()) {
            return $response->withAddedHeader('Set-Cookie', $this->cookieHeader('', 0, $request));
        }
        if ($fresh || $session->idChanged()) {
            return $response->withAddedHeader(
                'Set-Cookie',
                $this->cookieHeader($session->id(), $this->ttl, $request),
            );
        }
        return $response;
    }

    private function cookieHeader(string $value, int $maxAge, ServerRequestInterface $request): string
    {
        $secure = $this->secure ?? $request->getUri()->getScheme() === 'https';
        $parts = [
            $this->cookie . '=' . $value,
            'Path=' . $this->path,
            'Max-Age=' . $maxAge,
            'SameSite=' . $this->sameSite,
        ];
        if ($this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($secure) {
            $parts[] = 'Secure';
        }
        return \implode('; ', $parts);
    }
}
