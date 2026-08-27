# Puff Session

Fiber-safe, PSR-7 session management for Puff applications. Session state is scoped to the current request and never uses PHP's process-global `$_SESSION` state.

## Installation

```bash
composer require puff/session
```

The process-local memory store is enabled by default. To store sessions in Redis, install the optional Redis client:

```bash
composer require puff/redis
```

## Configuration

```php
return [
    'store' => 'memory', // memory, files, or redis
    'cookie' => 'PFID',
    'ttl' => 7200,
    'path' => dirname(__DIR__) . '/runtime/session',
    'prefix' => 'puff:session:',
    'cookie_path' => '/',
    'cookie_domain' => '',
    'same_site' => 'Lax',
    'http_only' => true,
    'secure' => null, // Auto-detect HTTPS; set explicitly when required.
];
```

- `files` persists sessions with locked, atomic file writes.
- `memory` is process-local and is intended for tests or ephemeral workloads.
- `redis` uses `puff/redis`, applies the configured key prefix, and sets the Redis TTL on every write.

Package discovery registers the selected store automatically. Choosing `redis` without installing and registering `puff/redis` produces an explicit configuration error.

## Usage

Inside the session pipeline, inject `Puff\Session\Session` or use the helper:

```php
session('user_id', 42);
$userId = session('user_id');
$session = session();
```

Injection and the helper are the preferred APIs. A Fiber-scoped facade is also available for concise application code:

```php
use Puff\Session\Facades\Session;

Session::set('user_id', 42);
$userId = Session::get('user_id');
Session::regenerate();
```

Sessions use versioned JSON for new data and can migrate legacy PHP-session payloads without allowing object deserialization. The pipeline refreshes the cookie after ID regeneration, expires it after destruction, sends `HttpOnly` and `SameSite=Lax` by default, and enables `Secure` automatically for HTTPS requests.

The Redis store provides persistence and expiry, but does not implement distributed request locking. Applications that modify the same session concurrently should coordinate those writes at the application level.
