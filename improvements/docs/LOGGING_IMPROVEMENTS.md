# Logging Improvements Summary

This document describes all improvements applied to the request + device logging stack.

## Files changed / added

| Path | Change |
|------|--------|
| `app/Http/Middleware/LogRequestMiddleware.php` | Major rewrite – terminable, request_id, response logging, configurable sanitization |
| `app/Jobs/SaveRequestLogToFile.php` | Retries, backoff, failed(), UUID names, optional gzip, richer metadata |
| `app/Support/DeviceLog.php` | Configurable context limit, sensitive-key redaction, alert/emergency helpers, safer SN sanitization |
| `config/logging.php` | Expanded `request` + `device_logs` configuration |
| `config/filesystems.php` | `request_logs` disk hardened (private + report) |
| `app/Console/Commands/PurgeRequestLogs.php` | **New** – retention cleanup command |

---

## 1. LogRequestMiddleware

### New capabilities
- **Terminable middleware** – implements `terminate()` so response status + duration are recorded after the response is sent.
- **Request ID** – every logged request gets a UUID (`request_id`) for correlation across logs and the saved metadata file.
- **Response logging** – controlled by `logging.request.log_response` (default `true`).
- **Configurable sanitization** – sensitive keys & headers now come from config instead of being hard-coded.
- **Header length limiting** – very long header values are truncated.
- **String length limiting** – configurable via `max_string_length`.
- **Raw body captured early** – avoids issues if the request stream is later consumed.
- **Safer method matching** – case-insensitive.

### Registration note
Make sure the middleware is registered so Laravel treats it as terminable. In Laravel 11+ this is automatic when the class has a `terminate` method and is added to the middleware stack (global or route group).

Example (bootstrap/app.php or Kernel):

```php
$middleware->append(\App\Http\Middleware\LogRequestMiddleware::class);
// or appendToGroup('api', ...)
```

---

## 2. SaveRequestLogToFile Job

### Improvements
- `$tries = 3`, exponential-ish `$backoff`, `$timeout = 30`.
- `failed()` method logs a clear error with request_id / path.
- Filenames use `Str::uuid()` (collision-proof).
- Optional **gzip compression** of the raw body (`compress_raw` config / `LOG_REQUEST_COMPRESS` env).
- Stores both original and stored byte counts, compression flag, content-type, saved_at.
- Dedicated queue name via `logging.request.queue`.

---

## 3. DeviceLog

### Improvements
- `max_context_string` is now configurable.
- Sensitive keys inside context are redacted (configurable list).
- Added `alert()` and `emergency()` helpers + a generic `log($level, ...)`.
- Safer serial-number sanitization (length limit, collapse underscores).
- Filename sanitization length-capped.

---

## 4. Configuration (`config/logging.php`)

New / expanded keys under `request`:

| Key | Env | Default | Purpose |
|-----|-----|---------|---------|
| `queue` | `LOG_REQUEST_QUEUE` | `default` | Queue name for the save job |
| `max_string_length` | `LOG_REQUEST_MAX_STRING` | 2000 | Truncate long body strings |
| `max_header_length` | `LOG_REQUEST_MAX_HEADER` | 500 | Truncate long headers |
| `compress_raw` | `LOG_REQUEST_COMPRESS` | false | Gzip raw bodies |
| `log_response` | `LOG_REQUEST_RESPONSE` | true | Log status + duration |
| `retention_days` | `LOG_REQUEST_RETENTION_DAYS` | 14 | Used by purge command |
| `sensitive_keys` | — | (list) | Body/query redaction |
| `sensitive_headers` | — | (list) | Header redaction |

Under `device_logs`:

| Key | Env | Default |
|-----|-----|---------|
| `max_context_string` | `LOG_DEVICE_MAX_CONTEXT` | 2000 |
| `sensitive_keys` | — | (list) |

---

## 5. Purge command

```bash
# Dry-run
php artisan logs:purge-requests --dry-run

# Real run (asks for confirmation)
php artisan logs:purge-requests

# Force + custom retention
php artisan logs:purge-requests --days=7 --force
```

Recommended schedule (in `routes/console.php` or Kernel):

```php
Schedule::command('logs:purge-requests --force')->dailyAt('03:15');
```

---

## 6. Suggested .env additions

```env
# --- Request logging (improved) ---
LOG_REQUESTS=true
LOG_REQUESTS_TO_FILE=true
LOG_REQUEST_ONLY_PATHS=iclock/*
LOG_REQUEST_MAX_BODY=2097152
LOG_REQUEST_CHANNEL=stack
LOG_REQUEST_LEVEL=debug
LOG_REQUEST_QUEUE=default
LOG_REQUEST_COMPRESS=false
LOG_REQUEST_RESPONSE=true
LOG_REQUEST_RETENTION_DAYS=14
LOG_REQUEST_MAX_STRING=2000
LOG_REQUEST_MAX_HEADER=500

# --- Device logging ---
LOG_DEVICE=true
LOG_DEVICE_PER_FILE=false
LOG_DEVICE_DAYS=7
LOG_DEVICE_LEVEL=debug
LOG_DEVICE_MAX_CONTEXT=2000
DEVICE_DEBUG_SN=
DEVICE_DEBUG_DUMP=false
```

---

## 7. Migration checklist

1. Copy the new PHP files into your project (overwrite the old ones).
2. Merge the new keys into your existing `config/logging.php` and `config/filesystems.php` (or replace if you have no custom changes).
3. Add the suggested env vars (or keep existing ones – defaults are backward-compatible).
4. Register the middleware if it is not already global / on the relevant routes.
5. Ensure a queue worker is running (the save job is still queued).
6. Schedule `logs:purge-requests --force` daily.
7. (Optional) Run `php artisan logs:purge-requests --dry-run` once to verify.

---

## Behaviour notes / compatibility

- Existing log files and directory layout are unchanged.
- If `log_response` is false the middleware still captures the request in `handle()` and only skips the second log line.
- Compression is off by default so existing tooling that reads `.json` / `.txt` files keeps working.
- All logging paths remain exception-safe – failures never break the main request.
