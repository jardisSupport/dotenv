# Jardis DotEnv

![Build Status](https://github.com/jardisSupport/dotenv/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/Coverage-93.98%25-brightgreen.svg)](https://github.com/jardisSupport/dotenv)

> Part of **[Jardis](https://jardis.io)** — the Domain-Driven Design platform for PHP. You model your domain; Jardis generates the production-ready hexagonal code (DTOs, Command/Query handlers, repositories, persistence). This package is part of the open-source foundation that generated code runs on.

A .env loader for PHP with cascading overrides, variable interpolation, type casting, and include directives. Goes beyond simple .env parsing — supports public and private loading modes, nested variable references, and an extensible cast chain.

---

## Features

- **Public/Private Loading** — `loadPublic()` writes to `$_ENV`/`$_SERVER`; `loadPrivate()` returns an isolated array without touching global state
- **Process Environment Wins** — a key already set in the environment (container, CI pipeline, shell export) beats the value from the `.env` file, the industry-standard precedence; the library's own published values keep overriding each other via the `JARDIS_DOTENV_VARS` marker
- **Source Visibility** — `sources()` answers "where did this value come from?" per key: `env`, `file:<realpath>` or `string`, so a value silently won by the process environment is never mistaken for the `.env` on screen
- **Cascading Overrides** — two-stage loading: base `.env` first, then `APP_ENV`-specific files (e.g. `.env.production`) override selectively
- **Variable Interpolation** — `${VAR}` references are resolved against already-loaded values in the same file
- **Type Casting Chain** — automatically converts strings to `bool`, numeric, JSON, and `array` via a chainable handler pipeline
- **Home Path Expansion** — `~/` is expanded to the OS home directory in both loading modes
- **Include Directives** — `load(.env.database)` and `load?(.env.optional)` split configuration across multiple files
- **Circular Include Detection** — prevents infinite include loops with a typed `CircularEnvIncludeException`
- **Docker `_FILE` Secret Resolution** — `DB_PASSWORD_FILE=/run/secrets/db_password` reads the file and exposes the content as `DB_PASSWORD`. Works with Docker Swarm, Kubernetes mounted secrets, and any file-based secret store. Combines with [`jardissupport/secret`](https://github.com/jardisSupport/secret) — a `_FILE` that contains `secret(aes:...)` is decrypted automatically through the cast chain. **Absolute paths only** (since 2026-08-30) — a relative `..._FILE` value (e.g. `COMPOSE_FILE=support/docker-compose.yml`) is left as a plain string, because Docker/Kubernetes secret mounts are always absolute and `_FILE` is sometimes just a name suffix, not a secret command
- **Extensible via `addHandler()`** — prepend or append custom cast handlers; remove built-in ones via `removeHandler()`
- **Raw-Key Cast Exemption** — `addRawKeys()` marks keys/suffixes (e.g. `_PASSWORD`) whose values skip the built-in casts, so a credential like `DB_PASSWORD=false` survives as the string `'false'` instead of `bool(false)`. Handlers registered via `addHandler()` (e.g. secret decryption) still run for these keys
- **String Input** — `loadPublicFromString()`/`loadPrivateFromString()` parse `.env`-formatted content that never touched disk (e.g. a secrets manager payload), reusing the same cast chain, variable substitution and `_FILE` resolution as file loading

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

### Precedence — the Process Environment Wins

A key that is **already set in the process environment** beats the value parsed from the `.env`
file or string — the same precedence `symfony/dotenv` and `vlucas/phpdotenv` apply. A container,
a CI pipeline or a plain `export` therefore configures the application without editing a file,
and the committed `.env` stays the default, not the override:

```bash
DB_HOST=prod-db php bin/console   # .env says DB_HOST=localhost — prod-db wins
```

```php
// .env: DB_HOST=localhost
$config = (new DotEnv())->loadPrivate('/path/to/app');
echo $config['DB_HOST']; // 'prod-db'
```

The rule holds for `loadPublic()`, `loadPrivate()`, `loadPublicFromString()`,
`loadPrivateFromString()` and inside `load()` include cascades — it lives in the one engine all of
them share. There is no opt-out.

The winning value is not a shortcut past the rest of the pipeline: it runs through the same cast
chain, the same raw-key exemption and the same `${VAR}` registry as a file value would, so
`DEBUG=true` in the environment still arrives as `bool(true)` and a `${DB_HOST}` reference resolves
against the winner.

**Empty means unset.** `getenv()` must return a non-empty string for the environment to win —
`DB_HOST=` counts as not set and the file value applies, consistent with how this library treats
empty values elsewhere.

**`_FILE` secrets.** If the **resolved** key is set in the environment, it wins and the secret file
is never read — a missing `DB_PASSWORD_FILE` target raises no exception when `DB_PASSWORD` already
comes from the environment:

```env
DB_PASSWORD_FILE=/run/secrets/db_password   # not read when DB_PASSWORD is in the environment
```

Only an **absolute** `..._FILE` value is ever read as a secret file (since 2026-08-30) — Docker and
Kubernetes secret mounts are always absolute paths. A relative value is a plain string with no file
lookup, no key rename and no exception, because a project `.env` shared with Compose can carry stack
keys that end in `_FILE` as a name, not a secret-mount command:

```env
COMPOSE_FILE=support/docker-compose.yml   # plain string — relative, so left alone
NGINX_INDEX_FILE=index.php                # plain string — relative, so left alone
```

**The `JARDIS_DOTENV_VARS` marker.** `loadPublic()`/`loadPublicFromString()` write their values
into the process environment via `putenv()`. Without a way to tell those apart from a genuine
ambient value, the very first published key would beat every later one and the
`.env` → `.env.local` → `.env.{APP_ENV}` cascade would collapse. Each published key is therefore
recorded in the environment variable `JARDIS_DOTENV_VARS` (comma-separated, duplicate-free), and a
key listed there never counts as ambient. The marker is process-wide and instance-independent by
design: a second `loadPublic()` run in the same process overrides the first, exactly as before.

### Sources — Where Did a Value Come From?

Because the process environment wins, the value an application runs on can come from somewhere
other than the `.env` the developer is looking at. `sources()` names that origin per key:

```php
$dotEnv = new DotEnv();
$config = $dotEnv->loadPrivate('/path/to/app');

$dotEnv->sources();
// [
//   'DB_HOST'     => 'env',                            // won by the process environment
//   'DB_PORT'     => 'file:/path/to/app/.env',         // parsed from this file
//   'MAIL_DSN'    => 'file:/path/to/app/.env.local',   // overridden later in the cascade
//   'API_KEY'     => 'string',                         // from loadPrivateFromString()
// ]
```

The vocabulary is closed — exactly three forms:

| Origin | Meaning |
|--------|---------|
| `env` | the process environment won (`ReadAmbientValue`) |
| `file:<realpath>` | parsed from that file |
| `string` | parsed from `loadPublicFromString()`/`loadPrivateFromString()` input |

- **Cascade:** the last assignment wins, so a key set in `.env` and again in `.env.local` reports
  `file:<…/.env.local>`.
- **Includes:** a key reports the file its line stands in — `load(.env.database)` makes the keys of
  that file report `file:<…/.env.database>`, not the including `.env`.
- **`_FILE` secrets:** the origin is the **line**, i.e. the `.env` (or `string`) that carries
  `DB_PASSWORD_FILE=…`, not the secret file it points at; the key reported is the stripped one
  (`DB_PASSWORD`). If the environment wins, it is `env` and the secret file is never read.
- **Accumulates:** the map is per instance and never reset between load calls, so several loads on
  one `DotEnv` give the full picture.
- **Values are never included** — only origins. `sources()` is safe to log or print next to a
  configuration dump.

`sources()` is a class-API addition; `DotEnvInterface` is unchanged.

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

**Absolute paths only.** Since 2026-08-30, resolution triggers only when the value starts with `/`
— the industry pattern above is always absolute anyway. A relative `..._FILE` value is left as an
ordinary string, key unchanged, no file read, no exception — this matters because a project `.env`
shared with Docker Compose can itself carry unrelated `_FILE`-suffixed stack keys:

```env
COMPOSE_FILE=support/docker-compose.yml   # a Compose key, not a secret — stays a plain string
```

```php
$config = (new DotEnv())->loadPrivate('/path/to/app');

echo $config['COMPOSE_FILE'];  // 'support/docker-compose.yml' — no lookup, no rename
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

### Raw Keys — Credentials That Must Never Be Cast

`false`, `0` or `123456` as a password are the cast chain's blind spot: it turns them into
`bool`/`int` before the caller ever sees the intended string. `addRawKeys()` registers
keys/suffixes (case-insensitive, exact or suffix match, no substring match) whose values skip
the built-in casts and stay strings. Raw means cast-free, not handler-free: handlers registered
via `addHandler()` (e.g. the `SecretHandler` decrypting `secret(...)` values) still run for raw
keys, in their chain order — only their string result is used:

```php
$dotEnv = new DotEnv();
$dotEnv->addRawKeys(['_PASSWORD', '_TOKEN']); // suffixes ...
$dotEnv->addRawKeys(['API_KEY']);             // ... or an exact key name

// .env: DB_PASSWORD=false
$config = $dotEnv->loadPrivate('/path/to/app');
var_dump($config['DB_PASSWORD']); // string(5) "false" — not bool(false)
```

The check applies at both cast sites: a plain `KEY=value` line and a `KEY_FILE=...` secret file —
for the latter the **resolved** key is checked (`DB_PASSWORD_FILE` → rule matches `DB_PASSWORD`).
Registrations accumulate and de-duplicate; there is no `removeRawKeys()`.
`removeHandler()` also detaches a value handler from the raw path.

### String Input — Loading From a Secrets Manager

`loadPublicFromString()`/`loadPrivateFromString()` accept `.env`-formatted content directly,
useful when the content comes from somewhere other than a file — AWS Secrets Manager, for example:

```php
$secretsManagerPayload = "DB_HOST=prod-db\nDB_PASSWORD=s3cret!\n";

$config = (new DotEnv())->loadPrivateFromString($secretsManagerPayload);
```

Behaves like file loading (same cast chain, `${VAR}` substitution, `_FILE` resolution, raw-key
exemption) with two differences dictated by having no file on disk:

- There is **no `APP_ENV` cascade** — a string is a single source, not a directory of variants.
- A `load()`/`load?()` directive throws `IncludeNotSupportedException` — a string has no
  file-system context to resolve an include against.

The optional `baseDir` second argument is kept for backward compatibility but has no effect since
2026-08-30: `KEY_FILE` resolution only ever considers absolute paths, so there is no relative path
left to resolve against it.

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/support/dotenv](https://docs.jardis.io/en/support/dotenv)**

## License

This package is licensed under the [MIT License](LICENSE.md).

---

**[Jardis](https://jardis.io)** · [Documentation](https://docs.jardis.io) · [Headgent](https://headgent.com)

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## AI-Assisted Development

This package ships with a skill for Claude Code, Cursor, Continue, and Aider. Install it in your consuming project:

```bash
composer require --dev jardis/dev-skills
```

More details: <https://docs.jardis.io/en/skills>
<!-- END jardis/dev-skills README block -->
