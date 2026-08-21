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

- `get(string $contextClass): ContextInterface` returns the context deserialized from the current request, or a fresh `new $contextClass()` if none was stored yet.
- `update(ContextInterface $context): void` compares the given context's `getHash()` against the currently stored one and, only if it changed, marks it for persistence.

### How it's wired

Two `kernel.event_listener`s do the actual cookie I/O so the rest of the request lifecycle only ever deals with request attributes:

- `ContextRequestListener` (`kernel.request`) reads every cookie named `{reference}_context`, and copies its raw JSON value into the `context.{reference}` request attribute.
- `ContextProvider::get()` lazily deserializes that attribute into the requested context class; `update()` re-serializes and flags `context.{reference}.refresh` as `true` only when the hash actually changed (write-on-change).
- `ContextResponseListener` (`kernel.response`, main request only) looks for `context.{reference}.refresh === true` and, when found, writes the corresponding `{reference}_context` cookie (`HttpOnly`, `SameSite=Lax`, expires in one year) from the request attribute.

There is no encryption or signing — the cookie payload is plain JSON, so don't store anything sensitive in a context.
