# WebmunkeezContextBundle

This bundle will bring cookie-backed context storage to Symfony applications — persisting small pieces of state (e.g. an anonymous visitor's data) across requests without authentication.

Scaffold only for now: the bundle boots (`WebmunkeezContextBundle` + a no-op `WebmunkeezContextExtension`) but has no context storage logic yet.

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

_Coming soon._
