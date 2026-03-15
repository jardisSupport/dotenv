# Jardis DotEnv

![Build Status](https://github.com/jardisSupport/dotenv/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm NC](https://img.shields.io/badge/License-PolyForm%20NC-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-blue.svg)](https://www.php-fig.org/psr/psr-12/)
[![Coverage](https://img.shields.io/badge/coverage->90%25-brightgreen)](https://github.com/jardisSupport/dotenv)

> Part of the **[Jardis Ecosystem](https://jardis.io)** — A modular DDD framework for PHP

A powerful environment configuration reader for PHP applications. Unlike traditional dotenv libraries, Jardis DotEnv supports **protected contexts** for domain-driven design — load different configurations for different bounded contexts without global state pollution.

---

## Features

- **Protected Contexts** — Load configurations into isolated arrays instead of global `$_ENV`
- **Automatic Type Casting** — Converts strings to `bool`, `int`, `float`, `array`, or JSON automatically
- **Variable Substitution** — Use `${VAR}` syntax for dynamic value resolution (works in both public and private mode)
- **JSON Support** — Parses JSON objects `{"key":"value"}` and JSON arrays `["a","b"]` with recursive type casting
- **Modular Includes** — Split configs with `load()` and `load?()` directives
- **Include Cascade** — Included files automatically resolve `.local` and `.{APP_ENV}` variants
- **Two-Stage APP_ENV Loading** — `APP_ENV` defined in `.env` is correctly used for environment-specific file discovery
- **Circular Reference Detection** — Prevents infinite loops in include chains
- **Custom Exception Hierarchy** — Typed exceptions for missing files, unreadable files, parse errors, and circular includes
- **Home Directory Expansion** — Automatically expands `~` to user home path (consistent in both public and private mode)
- **Handler API** — Add or remove cast handlers via `addHandler()` / `removeHandler()`
- **Optional Secret Support** — Integrates with `jardissupport/secret` for encrypted values via `secret(...)` syntax

---

## Installation

```bash
composer require jardissupport/dotenv
```

## Quick Start

```php
use JardisSupport\DotEnv\DotEnv;

$dotEnv = new DotEnv();

// Load into global scope ($_ENV, $_SERVER, putenv)
$dotEnv->loadPublic('/path/to/app');

// Load into protected scope (returns array, no global pollution)
$domainConfig = $dotEnv->loadPrivate('/path/to/domain');
```

## Type Casting

```env
DEBUG=true                            # bool(true)
MAX_CONN=100                          # int(100)
TIMEOUT=2.5                           # float(2.5)
PORTS=[80,443]                        # array [80, 443]
DB=[host=>localhost,port=>3306]       # array ['host' => 'localhost', 'port' => 3306]
CONFIG={"host":"localhost","port":3306} # array via JSON
LOG_PATH=~/logs/app.log               # Home expansion
DB_URL=mysql://${DB_HOST}/${DB_NAME}  # Variable substitution
```

## Handler API

Add or remove cast handlers to customize the processing chain:

```php
use JardisSupport\DotEnv\DotEnv;
use JardisSupport\DotEnv\Handler\CastStringToBool;

$dotEnv = new DotEnv();

// Add a custom handler (prepend: runs before built-in handlers)
$dotEnv->addHandler($myHandler, prepend: true);

// Remove a built-in handler
$dotEnv->removeHandler(CastStringToBool::class);
```

### Secret Support (optional)

```bash
composer require jardissupport/secret
```

```php
use JardisSupport\DotEnv\DotEnv;
use JardisSupport\Secret\Handler\SecretHandler;
use JardisSupport\Secret\KeyProvider\FileKeyProvider;

$dotEnv = new DotEnv();
$dotEnv->addHandler(new SecretHandler(new FileKeyProvider('support/secret.key')), prepend: true);
$config = $dotEnv->loadPrivate('/path/to/app');
```

```env
DB_PASSWORD=secret(base64encryptedvalue)
API_KEY=secret(sodium:base64encryptedvalue)
```

## Documentation

Full documentation, examples and API reference:

**→ [jardis.io/docs/support/dotenv](https://jardis.io/docs/support/dotenv)**

## Jardis Ecosystem

This package is part of the Jardis Ecosystem — a collection of modular, high-quality PHP packages designed for Domain-Driven Design.

| Category | Packages |
|----------|----------|
| **Core** | Foundation |
| **Support** | DotEnv, Data, DbQuery, ClassVersion, Factory, Repository, Validation, Workflow, Secret |
| **Adapter** | Cache, Logger, Messaging, DbConnection |
| **Tools** | Builder, DbSchema |

**→ [Explore all packages](https://jardis.io/docs)**

## License

This package is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE).

For commercial use, see [COMMERCIAL.md](COMMERCIAL.md).

---

**[Jardis Ecosystem](https://jardis.io)** by [Headgent Development](https://headgent.com)
