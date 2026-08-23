---
name: support-dotenv
description: Load .env files (or .env-formatted strings) with type casting, variable substitution, cascade loading, raw-key cast exemptions, secret support. Use for DotEnv, addRawKeys, loadPublicFromString/loadPrivateFromString, or env config.
user-invocable: false
zone: post-active
persona: C
prerequisites: [rules-architecture, rules-patterns]
next: [support-secret]
---

# DOTENV_COMPONENT_SKILL
> jardissupport/dotenv v1.2 | NS: `JardisSupport\DotEnv` | Implements: `DotEnvInterface` | PHP 8.2+

**Constructor:**
```php
new DotEnv(
    ?LoadFilesFromPath $fileFinder = null,
    ?LoadValuesFromFiles $fileContentReader = null,
    ?LoadValuesFromString $stringContentReader = null,
    ?MatchesRawKey $matchesRawKey = null,
)
```
`CastTypeHandler` is created internally — not in constructor. `DotEnvInterface` itself is
unchanged since v1 (only `loadPublic`/`loadPrivate`) — `addRawKeys()` and the `*FromString()`
methods are class-API additions (v1.2), reached via `new DotEnv()`, not the interface.

## TWO-STAGE LOADING
1. Load `.env` + `.env.local`
2. Resolve `APP_ENV` (parsed result → `$_ENV` → `getenv()`)
3. Load `.env.{APP_ENV}` + `.env.{APP_ENV}.local`

## API
```php
$dotEnv = new DotEnv();
$dotEnv->loadPublic(string $path): void           // putenv + $_ENV + $_SERVER
$dotEnv->loadPrivate(string $path): array<string,mixed>  // no globals, returns cast values

$dotEnv->addHandler(object $handler, bool $prepend = false): void   // invokable, else InvalidArgumentException
$dotEnv->removeHandler(string $handlerClass): void

$dotEnv->addRawKeys(array $keysOrSuffixes): void                   // case-insensitive, accumulates + dedupes, no remove

$dotEnv->loadPublicFromString(string $content, ?string $baseDir = null): void          // like loadPublic(), no cascade
$dotEnv->loadPrivateFromString(string $content, ?string $baseDir = null): array        // like loadPrivate(), no cascade
```
`addHandler()` → `CastTypeHandler::setCastTypeInstance()`.  
`removeHandler()` → `CastTypeHandler::removeCastTypeClass()`.
`addRawKeys()` → `MatchesRawKey::addRawKeys()`.

## RAW KEYS — cast-chain exemption by key (v1.2)
Registers keys/suffixes that skip the whole cast chain and survive as the literal string — for
values where a cast would corrupt them (e.g. `DB_PASSWORD=false`/`=123456` must stay `'false'`/
`'123456'`, not become `bool`/`int`).
```php
$dotEnv->addRawKeys(['_PASSWORD', '_SECRET', 'DB_PORT']);  // suffix OR exact key, case-insensitive
```
- `MatchesRawKey::__invoke(string $key): bool` — `str_ends_with($upperKey, $rawKey)`; an exact key
  is just a suffix equal to the whole string. **No substring match** (`'PASS'` does NOT match
  `'BYPASS'` unless registered as `'BYPASS'` or a suffix of it).
- Checked at **both** cast-chain entry points inside the reading engine — a plain `KEY=value` line
  and the resolved key of a `KEY_FILE=...` secret-file reference (`DB_PASSWORD_FILE` → the rule is
  evaluated against `DB_PASSWORD`, the key after `_FILE`-stripping, not the literal `_FILE` key).
- Distinct from `jardissupport/secret`: secret decryption is **value-based** (a handler triggers on
  the value's shape, e.g. `secret(aes:...)`); raw-key exemption is **key-based** (skips casting
  regardless of value shape) — the handler chain is key-blind, so this could never be a handler.
- Injection caveat (pre-existing, undocumented elsewhere): a raw key registered on one `DotEnv`
  instance's internal reader does not retroactively apply if you construct a custom
  `LoadValuesFromFiles`/`LoadValuesFromString` yourself and bypass `addRawKeys()` — always call
  `addRawKeys()` on the `DotEnv` instance you actually load through.

## PUBLISH BEHAVIOR
| Mode | `putenv` | `$_ENV` / `$_SERVER` | Return |
|------|----------|----------------------|--------|
| `loadPublic()` | raw string | cast value | `void` |
| `loadPrivate()` | — | — | `array<string,mixed>` (cast) |
Both modes: VariableRegistry populated identically → `${VAR}` and `~` work in both.

## TYPE CAST CHAIN (strict order, early exit on non-string)
1. `CastStringToValue` — `${VAR}` via VariableRegistry (+ `getenv()` fallback) → `?string`
2. `CastUserHome` — `~` via VariableRegistry HOME (+ `getenv()` fallback) → `?string`
3. `CastStringToNumeric` — `is_numeric()` → `int|float|string|null`
4. `CastStringToBool` — `true/false/1/0` via `filter_var()` → `bool|string|null`
5. `CastStringToJson` — `{...}` / `[...]` via `json_decode`, recursive → `array|string|null`
6. `CastStringToArray` — `[key=>val,1,2]` custom syntax, recursive → `array|string|null`

**Key edge cases:** `ENABLED=1` → `int(1)` via Numeric (NOT bool). `DEBUG=true` → `bool(true)`. `ZERO=0` → `int(0)`.

**Instance creation by `CastTypeHandler`:**
- `CastStringToValue`, `CastUserHome` → `new $class($registry)`
- `CastStringToNumeric`, `CastStringToBool` → `new $class()`
- All others (incl. custom) → `new $class($castTypeHandler)`

## VARIABLE REGISTRY
```php
$registry = $castTypeHandler->getRegistry();
$registry->set('KEY', 'raw_value');   // raw string before casting
$registry->get('KEY');                // Registry first, getenv() fallback
$registry->reset();                   // clear all entries
```

## INCLUDE SYSTEM
```env
load(.env.database)           # required — throws EnvFileNotFoundException if missing
load("path/with spaces/.env") # quoted paths supported
load?(.env.local)             # optional — silent skip
```
- Relative paths resolved from directory of the including file
- Each include cascades: base → `.local` → `.{APP_ENV}` → `.{APP_ENV}.local`
- APP_ENV read from VariableRegistry (works in both modes)
- Circular reference detection → `CircularEnvIncludeException`

## FILE SECRET RESOLUTION (`_FILE` PATTERN)
```env
DB_PASSWORD_FILE=/run/secrets/db_password   # → DB_PASSWORD = trimmed file content
API_PORT_FILE=secrets/port                  # relative paths resolved from .env directory
```
- `KEY_FILE` suffix stripped → becomes `KEY`
- File content trimmed, passed through full cast chain
- Raw value registered in VariableRegistry (for `${VAR}` refs)
- Missing file → `EnvFileNotFoundException`; unreadable → `EnvFileNotReadableException`
- Combinable with `jardissupport/secret`: file contains `secret(aes:...)` → decrypted via cast chain

## STRING INPUT (v1.2) — `loadPublicFromString` / `loadPrivateFromString`
For content that never touches the filesystem (e.g. pulled from a secrets manager at runtime).
```php
$dotEnv = new DotEnv();
$config = $dotEnv->loadPrivateFromString("DB_HOST=localhost\nDB_PORT=5432\n");
$dotEnv->loadPublicFromString($content, baseDir: '/app/config');  // baseDir for relative KEY_FILE
```
- Same cast chain, same `${VAR}`/`~` substitution, same raw-key exemptions as the file path —
  parity is by design (`LoadValuesFromRows` is the shared line-parsing engine both
  `LoadValuesFromFiles` and `LoadValuesFromString` delegate to).
- **No `APP_ENV` cascade** — a string has no directory to resolve `.{APP_ENV}` siblings against.
- **`load(...)`/`load?(...)` directives are a hard error** (`IncludeNotSupportedException`) — no
  file-system context to resolve an include path against.
- **Relative `KEY_FILE=...` paths require `$baseDir`** — omit it and a relative path throws;
  absolute `KEY_FILE` paths work with `$baseDir` omitted.
- Line splitting: `preg_split('/\R/')` (CRLF/LF-agnostic), leading UTF-8 BOM stripped, only exactly
  empty lines filtered (parity with `FILE_SKIP_EMPTY_LINES`).

## OPTIONAL SECRET SUPPORT
```php
// composer require jardissupport/secret
$dotEnv = new DotEnv();
$dotEnv->addHandler(new SecretHandler(new FileKeyProvider('support/secret.key')), prepend: true);
$config = $dotEnv->loadPrivate('/path/to/app');
// In .env: DB_PASSWORD=secret(base64encryptedvalue)
```

## EXCEPTIONS
| Exception | Trigger | Extra |
|-----------|---------|-------|
| `CircularEnvIncludeException` | a.env → b.env → a.env | `getIncludeStack()` |
| `EnvFileNotFoundException` | required `load()` missing or `_FILE` path not found | `getFilePath()` |
| `EnvFileNotReadableException` | file not readable | `getFilePath()` |
| `IncludeNotSupportedException` | `load()`/`load?()` used inside `*FromString()` input | — |

NS: `JardisSupport\DotEnv\Exception` — all extend `DotEnvException`.

## RULES
- **NEVER:** DotEnv in domain; `loadPublic()` outside bootstrap; circular includes; `getenv()` directly for `.env` values
- **ALWAYS:** `loadPrivate()` for domain-specific config; `*Config` classes in `Infrastructure/Config/`; inject config as primitives into the domain; `load?()` for optional files; `prepend: true` for handlers that must run before variable substitution

## USAGE
```php
// Bootstrap
(new DotEnv())->loadPublic(__DIR__);

// Domain config
final class OrderConfig {
    private array $config;
    public function __construct() {
        $this->config = (new DotEnv())->loadPrivate(__DIR__ . '/../../config/orders');
    }
    public function maxOrderValue(): float { return $this->config['MAX_ORDER_VALUE']; }
}
```

## LAYER
- **Infrastructure/Bootstrap:** `loadPublic()`
- **Infrastructure/Config:** `loadPrivate()` → `*Config` classes
- **Domain:** NEVER imports DotEnv
