# Jardis DotEnv

![Build Status](https://github.com/jardisSupport/dotenv/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/Coverage-93.98%25-brightgreen.svg)](https://github.com/jardisSupport/dotenv)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

Environment file loader with cascading overrides, variable interpolation, type casting, and include directives. Goes beyond simple .env parsing — supports public and private loading modes, nested variable references, and an extensible cast chain.

---

## Features

- **Public/Private Loading** — `loadPublic()` writes to `$_ENV`/`$_SERVER`; `loadPrivate()` returns an isolated array without touching global state
- **Cascading Overrides** — two-stage loading: base `.env` first, then `APP_ENV`-specific files (e.g. `.env.production`) override selectively
- **Variable Interpolation** — `${VAR}` references are resolved against already-loaded values in the same file
- **Type Casting Chain** — automatically converts strings to `bool`, numeric, JSON, and `array` via a chainable handler pipeline
- **Home Path Expansion** — `~/` is expanded to the OS home directory in both loading modes
- **Include Directives** — `load(.env.database)` and `load?(.env.optional)` split configuration across multiple files
- **Circular Include Detection** — prevents infinite include loops with a typed `CircularEnvIncludeException`
- **Docker `_FILE` Secret Resolution** — `DB_PASSWORD_FILE=/run/secrets/db_password` reads the file and exposes the content as `DB_PASSWORD`. Works with Docker Swarm, Kubernetes mounted secrets, and any file-based secret store. Combines seamlessly with [`jardissupport/secret`](https://github.com/jardisSupport/secret) — a `_FILE` that contains `secret(aes:...)` is decrypted automatically through the cast chain
- **Extensible via `addHandler()`** — prepend or append custom cast handlers; remove built-in ones via `removeHandler()`

---

## Installation

```bash
composer require jardissupport/dotenv
```

## Quick Start

```php
use JardisSupport\DotEnv\DotEnv;

$dotEnv = new DotEnv();

// Write into $_ENV / $_SERVER / putenv — suitable for application bootstrap
$dotEnv->loadPublic('/path/to/app');

// Return an isolated array — no global state, suitable for bounded contexts
$config = $dotEnv->loadPrivate('/path/to/domain');

echo $config['DB_HOST']; // 'localhost'
echo $config['DEBUG'];   // bool(true) — automatically cast
```

## Advanced Usage

```php
use JardisSupport\DotEnv\DotEnv;
use JardisSupport\DotEnv\Handler\CastStringToBool;
use JardisSupport\Secret\Handler\SecretHandler;
use JardisSupport\Secret\KeyProvider\FileKeyProvider;

// .env example:
//
//   APP_ENV=production
//   load(.env.database)           <- required include
//   load?(.env.local)             <- optional include, silently skipped if absent
//   DB_URL=mysql://${DB_HOST}/${DB_NAME}   <- variable interpolation
//   LOG_PATH=~/logs/app.log       <- home path expansion
//   PORTS=[80,443]                <- cast to array [80, 443]
//   DEBUG=true                    <- cast to bool(true)

$dotEnv = new DotEnv();

// Prepend a custom handler — runs before all built-in casters
$dotEnv->addHandler($myCustomHandler, prepend: true);

// Remove a built-in handler when its behaviour is not needed
$dotEnv->removeHandler(CastStringToBool::class);

// Integrate secret decryption (requires jardissupport/secret)
// Values like DB_PASSWORD=secret(...) are decrypted transparently
$dotEnv->addHandler(
    new SecretHandler(new FileKeyProvider('support/secret.key')),
    prepend: true,
);

// Two-stage cascade:
// Stage 1 → .env + .env.local
// Stage 2 → .env.production + .env.production.local  (driven by APP_ENV)
$config = $dotEnv->loadPrivate('/path/to/app');
```

### Docker Secret Files (`_FILE` Pattern)

Read secrets from mounted files — the industry-standard pattern for Docker Swarm and Kubernetes:

```env
# .env
APP_NAME=MyApp
DB_HOST=localhost
DB_PASSWORD_FILE=/run/secrets/db_password
REDIS_TOKEN_FILE=/run/secrets/redis_token
```

```php
$config = (new DotEnv())->loadPrivate('/path/to/app');

echo $config['DB_PASSWORD'];  // content of /run/secrets/db_password
echo $config['REDIS_TOKEN'];  // content of /run/secrets/redis_token
// DB_PASSWORD_FILE / REDIS_TOKEN_FILE are NOT in the result
```

The `_FILE` suffix is stripped, the file content is read and passed through the full cast chain — variable substitution, type casting, and even secret decryption all work:

```env
# _FILE + secret() combined: file contains encrypted value
# /run/secrets/db_password contains: secret(aes:base64encodedValue)
DB_PASSWORD_FILE=/run/secrets/db_password
```

```php
$dotEnv = new DotEnv();
$dotEnv->addHandler(
    new SecretHandler(new FileKeyProvider('support/secret.key')),
    prepend: true,
);
$config = $dotEnv->loadPrivate('/path/to/app');
// DB_PASSWORD → file read → secret() decrypted → plaintext
```

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/support/dotenv](https://docs.jardis.io/en/support/dotenv)**

## License

This package is licensed under the [MIT License](LICENSE.md).

---

**[Jardis](https://jardis.io)** · [Documentation](https://docs.jardis.io) · [Headgent](https://headgent.com)

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## KI-gestützte Entwicklung

Dieses Package liefert einen Skill für Claude Code, Cursor, Continue und Aider mit. Installation im Konsumentenprojekt:

```bash
composer require --dev jardis/dev-skills
```

Mehr Details: <https://docs.jardis.io/en/skills>
<!-- END jardis/dev-skills README block -->
