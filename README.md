# WebmunkeezContextBundle

This bundle brings cookie-backed context storage to Symfony applications — persisting small pieces of state (e.g. an anonymous visitor's data) across requests without authentication.

## Installation

Use Composer to install this bundle:

```console
$ composer require webmunkeez/context-bundle
```

Add the bundle in your application kernel:

```php
// config/bundles.php

return [
    // ...
    Webmunkeez\ContextBundle\WebmunkeezContextBundle::class => ['all' => true],
    // ...
];
```

## Usage

### Writing a context

A context implements `\Webmunkeez\ContextBundle\Context\ContextInterface` — in practice you extend `\Webmunkeez\ContextBundle\Context\AbstractContext`, which derives the context's reference from its class name (`ProfileContext` → `profile`, used both as the request attribute key and the cookie name):

```php
final class ProfileContext extends AbstractContext
{
    /**
     * @var array<Profile>
     */
    private array $profiles = [];

    /**
     * @return array<Profile>
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * @param array<Profile> $profiles
     */
    public function setProfiles(array $profiles): self
    {
        $this->profiles = $profiles;

        return $this;
    }

    public function getHash(): string
    {
        return hash('xxh128', serialize($this->profiles));
    }
}
```

`getHash()` is used to detect whether a context changed since it was read — it must return the same value for two contexts that should be considered equal, and a different value otherwise.

`AbstractContext` also declares `getTtl()` (default `'1 year'`) and `getRefreshAfter()` (default `'1 day'`) — both relative date/time strings (anything accepted by `strtotime('+'.$value)`) controlling that context's own cookie/JWT lifetime and auto-refresh threshold, see [Cookie lifetime & auto-refresh](#cookie-lifetime--auto-refresh). Override either as a static method on your own context class if the defaults don't fit:

```php
final class ProfileContext extends AbstractContext
{
    // ...

    public static function getTtl(): string
    {
        return '30 days';
    }

    public static function getRefreshAfter(): string
    {
        return '2 hours';
    }
}
```

### Reading and writing a context

`\Webmunkeez\ContextBundle\Context\ContextProviderInterface` (autowireable, backed by `ContextProvider`) is the entry point:

```php
final class ProfileController
{
    public function __construct(
        private readonly ContextProviderInterface $contextProvider,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $context = $this->contextProvider->get(ProfileContext::class);

        // ... mutate $context ...

        $this->contextProvider->update($context);

        // ...
    }
}
```

- `get(string $contextClass): ContextInterface` denormalizes the context from the current request, or returns a fresh `new $contextClass()` if none was stored yet.
- `update(ContextInterface $context): void` compares the given context's `getHash()` against the currently stored one and, only if it changed, normalizes it and marks it for persistence.

### How it's wired

Two `kernel.event_listener`s do the actual cookie I/O so the rest of the request lifecycle only ever deals with request attributes:

- `ContextRequestListener` (`kernel.request`) reads every cookie named `{reference}_context` and decodes its JWT via `ContextTokenEncoderInterface`. On success, it copies the resulting `ContextToken`'s payload into the `context.{reference}` request attribute, along with the context class name (`context.{reference}.class`, copied straight from the token's own `ctx` claim), and flags `context.{reference}.refresh` as `true` when the token's `iat` (issuance time) is older than `$contextClass::getRefreshAfter()` — sliding the cookie/JWT's lifetime forward for active visitors without re-signing on every single request. A cookie that's malformed, expired, or signed with a different secret decodes to `null` and is silently ignored, exactly as if it had never been set — but one that's otherwise valid yet names a context class that no longer exists (renamed or removed) throws `ContextClassNotFoundException`, which the listener turns into `context.{reference}.delete = true` instead, so the orphaned cookie gets actively cleaned up rather than lingering, unrefreshable, until its `ttl` runs out.
- `ContextProvider::get()` lazily denormalizes that attribute (a plain array, via Symfony's `NormalizerInterface`/`DenormalizerInterface`) into the requested context class; `update()` normalizes the context back to an array, sets `context.{reference}.class` to the context's own class name, and flags `context.{reference}.refresh` as `true` — only when the hash actually changed (write-on-change).
- `ContextResponseListener` (`kernel.response`, main request only) looks for `context.{reference}.delete === true` and clears the `{reference}_context` cookie accordingly; separately, it looks for `context.{reference}.refresh === true` and, when found, encodes the array payload against `context.{reference}.class` via `ContextTokenEncoderInterface` and writes the result as the `{reference}_context` cookie (`HttpOnly`, `SameSite=Lax`, `Secure` when the request itself is HTTPS, `Expires` set from that class's `getTtl()`). Since this re-encodes the payload from scratch, the JWT and the cookie are always refreshed together — a new `iat`/`exp` on the token, a new `Expires` on the cookie — whichever of the two flags triggered it. The two flags are mutually exclusive per reference: `.delete` only ever comes from a token whose payload was never even read, so there's nothing left to refresh.

Because only the context's fully-qualified class name travels through the request attributes and the JWT (not the `ttl`/`refresh_after` values themselves), a stale-triggered silent refresh always re-reads `getTtl()`/`getRefreshAfter()` straight off the class — so a change to those methods takes effect on the very next request, not just on the next real `ContextProvider::update()`.

### Cookie lifetime & auto-refresh

Each context controls its own cookie/JWT lifetime through `getTtl()`/`getRefreshAfter()` (see [Writing a context](#writing-a-context)) — there is no bundle-wide `ttl` setting. What travels inside the JWT is the context's class name (the `ctx` claim, alongside `iat`/`exp`/`data`) — `getTtl()`/`getRefreshAfter()` are re-resolved from that class on every decode, which is how a stale-triggered silent refresh (see above) can re-issue a cookie without any context instance at hand.

### Cookie signing

`\Webmunkeez\ContextBundle\Token\ContextTokenEncoderInterface` is a JWT-backed codec for `ContextInterface`-bound payloads: `encode(array $payload, string $contextClass)` takes the target context's class name and resolves its `getTtl()`/`getRefreshAfter()` itself; `decode()` returns a `\Webmunkeez\ContextBundle\Token\ContextToken` (`getPayload()`, `getIssuedAt()`, `getContextClass()`) instead of a bare array. The cookie content is a JWT (backed by `\Webmunkeez\ContextBundle\Jwt\ContextJwtTokenEncoder` and [firebase/php-jwt](https://github.com/firebase/php-jwt)), signed with HS256 and carrying `iat`/`exp`/`ctx`/`data` claims. The context's normalized array is embedded directly as the `data` claim — not JSON-encoded twice — keeping the cookie as small as the data actually requires. This guarantees integrity — a tampered or forged cookie is rejected — but **not confidentiality**: the payload is base64url-encoded, not encrypted, so it's still readable by anyone with the cookie value. Don't store anything sensitive in a context.

`ContextJwtTokenEncoder::encode()` throws a `\DomainException` if `$contextClass::getRefreshAfter()` doesn't resolve to a shorter duration than `$contextClass::getTtl()`. `decode()` throws `\Webmunkeez\ContextBundle\Exception\ContextClassNotFoundException` — distinct from returning `null` — if the token is otherwise well-formed and correctly signed but its `ctx` claim doesn't name an existing class implementing `ContextInterface`; see [How it's wired](#how-its-wired) for how `ContextRequestListener` turns that into an active cookie deletion instead of merely ignoring the cookie.

### Configuration

```yaml
# config/packages/webmunkeez_context.yaml
webmunkeez_context:
    secret: '%env(CONTEXT_SECRET)%' # defaults to kernel.secret
```

`secret` is the JWT signing key. It must be at least 32 characters long (HS256 requires a 256-bit key) or `ContextJwtTokenEncoder::encode()` throws a `\DomainException`.
