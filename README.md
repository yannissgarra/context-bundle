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

- `ContextRequestListener` (`kernel.request`) reads every cookie named `{reference}_context`, verifies and decodes its JWT via `TokenEncoderInterface`, and copies the resulting array payload into the `context.{reference}` request attribute. A cookie that fails to decode — malformed, expired, or signed with a different secret — is silently ignored, exactly as if it had never been set. It also flags `context.{reference}.refresh` as `true` when the token's `iat` (issuance time) is older than the configured `refresh_after` — sliding the cookie/JWT's lifetime forward for active visitors without re-signing on every single request.
- `ContextProvider::get()` lazily denormalizes that attribute (a plain array, via Symfony's `NormalizerInterface`/`DenormalizerInterface`) into the requested context class; `update()` normalizes the context back to an array and flags `context.{reference}.refresh` as `true` only when the hash actually changed (write-on-change).
- `ContextResponseListener` (`kernel.response`, main request only) looks for `context.{reference}.refresh === true` and, when found, encodes the array payload via `TokenEncoderInterface` and writes the result as the `{reference}_context` cookie (`HttpOnly`, `SameSite=Lax`, `Secure` when the request itself is HTTPS, expiring after the configured `ttl`). Since this re-encodes the payload from scratch, the JWT and the cookie are always refreshed together — a new `iat`/`exp` on the token, a new `Expires` on the cookie — whichever of the two flags triggered it.

### Cookie signing

`\Webmunkeez\ContextBundle\Token\TokenEncoderInterface` is a generic array-payload-to-string-token codec — it knows nothing about contexts. The cookie content is a JWT (backed by `\Webmunkeez\ContextBundle\Jwt\JwtTokenEncoder` and [firebase/php-jwt](https://github.com/firebase/php-jwt)), signed with HS256 and carrying an `exp` claim matching the cookie's lifetime. The context's normalized array is embedded directly as the `data` claim — not JSON-encoded twice — keeping the cookie as small as the data actually requires. This guarantees integrity — a tampered or forged cookie is rejected — but **not confidentiality**: the payload is base64url-encoded, not encrypted, so it's still readable by anyone with the cookie value. Don't store anything sensitive in a context.

### Configuration

```yaml
# config/packages/webmunkeez_context.yaml
webmunkeez_context:
    secret: '%env(CONTEXT_SECRET)%' # defaults to kernel.secret
    ttl: '1 year' # this is the default
    refresh_after: '1 day' # this is the default
```

- `secret` is the JWT signing key. It must be at least 32 characters long (HS256 requires a 256-bit key) or `JwtTokenEncoder::encode()` throws a `\DomainException`.
- `ttl` is a relative date/time string (anything accepted by `strtotime('+'.$ttl)`, e.g. `'30 days'`, `'2 weeks'`) used both for the JWT's `exp` claim and the cookie's `Expires` attribute, so they always stay in sync.
- `refresh_after` is the same kind of relative date/time string, and must resolve to a shorter duration than `ttl` (rejected otherwise). Past this age, `ContextRequestListener` re-issues the cookie/JWT on the next request even though nothing in the context itself changed — keeping active visitors permanently within `ttl` of expiry while a visitor who never comes back still expires normally.
