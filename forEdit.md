

## Recent changes (after device assign)

| File | Error | Correction | Update |
|---|---|---|---|
| [app/Http/Controllers/Api/v1/DeviceController.php](app/Http/Controllers/Api/v1/DeviceController.php) | `#[Authorize('assignAny', ['site', Device::class])]` hit **SitePolicy** (no `assignAny`) → 403, admin `before()` never ran | `[Device::class, 'site']` — class first so `DevicePolicy` is used | Updated |
| [app/Policies/Api/v1/DevicePolicy.php](app/Policies/Api/v1/DevicePolicy.php) | Unclear that admins skip every ability | Docblock: admins allowed **all** device abilities via `Policy::before()` / `isAdmin()` | Updated |
| [app/Jobs/DispatchMailJob.php](app/Jobs/DispatchMailJob.php) | Reset mail went to RabbitMQ `notifications`; no worker → never sent. Failures swallowed | `pushAuth()`: **send in the HTTP request** unless `MAIL_QUEUE_AUTH=true`; `$lastError` recorded | Updated |
| [app/Models/User.php](app/Models/User.php) | `sendPasswordResetNotification` / password-changed used `push()` | `pushAuth()` | Updated |
| [app/Http/Controllers/Api/v1/UserController.php](app/Http/Controllers/Api/v1/UserController.php) | Welcome used `push()` | `pushAuth()` | Updated |
| [app/Notifications/ResetPasswordNotification.php](app/Notifications/ResetPasswordNotification.php) (and Changed / Welcome) | Same queue path | `pushAuth()` | Updated |
| [config/api.php](config/api.php) | No switch for auth mail | `mail_queue_auth` ← `MAIL_QUEUE_AUTH` (default `false`) | Updated |
| [app/Http/Controllers/Api/v1/AuthController.php](app/Http/Controllers/Api/v1/AuthController.php) | Debug payload hid SMTP failure | `sent`, `mailError`, hints: `log` driver, throttle, Mailpit `:2525`, queue worker | Updated |

**Mail:** reset / welcome / changed send **now**, not via RabbitMQ. `.env` `MAIL_PASSWORD=app-password` is a placeholder — use a 16-character [Google App Password](https://myaccount.google.com/apppasswords), then `config:clear` + `octane:reload`.

**Bulk assign:** retry `POST /api/v1/sites/{site}/devices/bulk-assign` after overlay + `octane:reload`.

# End
---
## Recent changes (after DispatchMailJob / attendee create)

| File | Error | Correction | Update |
|---|---|---|---|
| [app/Services/DeviceDataHandler.php](app/Services/DeviceDataHandler.php) | OPERLOG went through `DispatchMailJob`; USER lines never became attendees | Process OPERLOG **in-request** (`processOperLogContent`). Attendee create no longer depends on RabbitMQ | Updated |
| [app/Jobs/DispatchMailJob.php](app/Jobs/DispatchMailJob.php) | `dispatch_sync` throw rolled back `Attendee::create` | Never rethrow; log and continue. Mail/jobs only | Updated |
| [app/Listeners/SyncAttendeeOnCreated.php](app/Listeners/SyncAttendeeOnCreated.php) | Failed device push aborted API create | `try/catch` around `DispatchMailJob::push` | Updated |
| [app/Http/Controllers/Api/v1/DeviceController.php](app/Http/Controllers/Api/v1/DeviceController.php) | No way to put an existing device on an existing site | `assign`, `assignSite`, `bulkAssignToSite`, `bulkAssign`; nested PATCH also sets `site_id` | Updated |
| [app/Policies/Api/v1/DevicePolicy.php](app/Policies/Api/v1/DevicePolicy.php) | No assign abilities | `assign` (one device); `assignAny` (bulk). Unassigned `site_id` null can be claimed | Updated |
| [app/Services/BulkDeviceAssignService.php](app/Services/BulkDeviceAssignService.php) | — | Bulk assign: missing → failed, same site → skipped, moved → updated | Updated |
| [app/Http/Requests/Api/v1/BulkDeviceAssignRequest.php](app/Http/Requests/Api/v1/BulkDeviceAssignRequest.php) | — | `serialNumbers` **or** `items[{serialNumber,siteId}]`, cap `api.bulk_max_items` | Updated |
| [app/Http/Resources/Api/v1/DeviceResource.php](app/Http/Resources/Api/v1/DeviceResource.php) | No `siteId` on the device | `attributes.siteId` | Updated |
| [routes/api.php](routes/api.php) | Missing assign routes (`bulk-assign` would have been eaten by `{device}`) | Nested + body assign; `…/devices/bulk-assign` **before** `{device}` | Updated |
| [tests/Feature/Api/v1/DeviceAssignApiTest.php](tests/Feature/Api/v1/DeviceAssignApiTest.php) | — | Single assign, move, inactive site, bulk 207, mixed items | Updated |
| [postman/Attendance-API.postman_collection.json](postman/Attendance-API.postman_collection.json) | Collection stopped at approve/block | Assign URL/body, bulk one-site, bulk mixed, list attendees on device, sync-users | Updated |
| [postman/Attendance-API.local.postman_environment.json](postman/Attendance-API.local.postman_environment.json) | — | `{{siteId2}}`, `{{deviceSerial2}}` | Updated |

**Assign existing device → existing site**

```
POST /api/v1/sites/{site}/devices/{serial}/assign
POST /api/v1/devices/{serial}/assign-site
POST /api/v1/sites/{site}/devices/bulk-assign
POST /api/v1/devices/bulk-assign
```

200 / 207 / 422 same as other bulk endpoints. Re-import the Postman zip.

# End
---

## Recent changes (this stretch)

| File | Error | Correction | Update |
|---|---|---|---|
| [resources/views/emails/layout.blade.php](resources/views/emails/layout.blade.php) | `{{ $app }}` was Laravel’s Application object → `htmlspecialchars()` TypeError | Renamed to `$appName` | Updated |
| [app/Jobs/DispatchMailJob.php](app/Jobs/DispatchMailJob.php) | `forgetInstance('queue')` → `No connector for [rabbitmq]`; called **protected** `reconnect()`; mail jobs landed on `default` / `dispatch_sync` | Close + drop cached connection only; re-register connector; `onConnection('rabbitmq')->onQueue(...)`; `push($job, $queue)`; `$lastVia` = `rabbitmq` \| `sync`; OPERLOG uses same publisher | Updated |
| [app/Listeners/DisconnectRabbitMq.php](app/Listeners/DisconnectRabbitMq.php) | Closed the socket and left a dead `RabbitMQQueue` on the Octane worker | `DispatchMailJob::release()` — close then forget cache, keep manager | Updated |
| [app/Jobs/SendResetPasswordEmail.php](app/Jobs/SendResetPasswordEmail.php) (and Changed / Welcome) | `#[Queue]` ignored when constructed with `new` | Constructor `onQueue('notifications')->onConnection('rabbitmq')` | Updated |
| [app/Http/Controllers/Api/v1/AuthController.php](app/Http/Controllers/Api/v1/AuthController.php) | `queued: true` meant `passwords.sent`, not on RabbitMQ | `queued` only if `$lastVia === 'rabbitmq'`; `via`, throttle hint | Updated |
| [app/Services/CommandBuilder.php](app/Services/CommandBuilder.php) | `queryUserInfo($pin)` always required PIN; `queryUser()` did not exist | PIN optional (omit = all users); `queryUser()` alias | Updated |
| [app/Console/Commands/User/UserCommand.php](app/Console/Commands/User/UserCommand.php) | `device:user query` called missing `queryUser()` | `CommandBuilder::queryUserInfo($pin ?: null)` | Updated |
| [app/Services/CommandResponseHandler.php](app/Services/CommandResponseHandler.php) | `str_contains(..., 'INFO')` also matched `USERINFO` — query body written as device INFO; `PIN` only, skipped `Pin` | Skip INFO when command is USERINFO; accept `PIN` \| `Pin` | Updated |
| [app/Services/DeviceDataHandler.php](app/Services/DeviceDataHandler.php) | `ProcessOperLogJob::dispatch()` died on Octane AMQP; cdata still returned OK; USERINFO lines without `USER` prefix dropped | `DispatchMailJob::push(...)` (sync fallback); parse `USER` or `PIN=` | Updated |
| [app/Jobs/ProcessOperLogJob.php](app/Jobs/ProcessOperLogJob.php) | Attribute queue not applied | `onQueue('operlog')->onConnection('rabbitmq')` | Updated |
| [app/Http/Controllers/Api/v1/DeviceController.php](app/Http/Controllers/Api/v1/DeviceController.php) | No way to list or pull users on a terminal | `GET .../devices/{device}/attendees`; `POST .../devices/{device}/sync-users` (`DATA QUERY USERINFO`) | Updated |
| [app/Policies/Api/v1/DevicePolicy.php](app/Policies/Api/v1/DevicePolicy.php) | No pull-users ability | `syncUsers` — manage-devices + site access | Updated |
| [routes/api.php](routes/api.php) | Missing those routes | `devices.attendees.index`, `devices.attendees.sync` | Updated |
| [tests/Feature/Api/v1/PasswordResetApiTest.php](tests/Feature/Api/v1/PasswordResetApiTest.php) | Asserted `Bus::dispatch` | `Queue::fake()` / `assertPushed` | Updated |

**Pull attendees from a device**

```
POST /api/v1/devices/{serial}/sync-users
GET  /api/v1/devices/{serial}/attendees
```

Device must be approved. Worker must include `operlog`. Replay leftovers in `storage/app/operlog_jobs/` if OPERLOG was saved but never processed.
# End ***********************************
---
## Recent changes

| File | Error | Correction | Update |
|---|---|---|---|
| [app/Jobs/DispatchMailJob.php](app/Jobs/DispatchMailJob.php) | `Job::dispatch()` + Octane published on a dead AMQP channel (`Channel connection is closed`) | Immediate `Bus::dispatch`; reconnect; `dispatch_sync` fallback | Added |
| [app/Listeners/DisconnectRabbitMq.php](app/Listeners/DisconnectRabbitMq.php) | FrankenPHP reused a RabbitMQ socket after heartbeat timeout | Close AMQP after every Octane request/task/tick | Added |
| [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) | No Octane AMQP reset | Listen `RequestTerminated` / `TaskTerminated` / `TickTerminated` | Updated |
| [app/Jobs/SendResetPasswordEmail.php](app/Jobs/SendResetPasswordEmail.php) | SMTP in the HTTP request; `afterCommit()` published in a destructor | `#[Queue('notifications')]`; no `afterCommit`; sends `ResetPasswordMail` | Added |
| [app/Jobs/SendPasswordChangedEmail.php](app/Jobs/SendPasswordChangedEmail.php) | Same | Queued confirmation mail | Added |
| [app/Jobs/SendWelcomeUserEmail.php](app/Jobs/SendWelcomeUserEmail.php) | Same | Queued welcome mail | Added |
| [app/Mail/ResetPasswordMail.php](app/Mail/ResetPasswordMail.php) | Default Laravel markdown | Branded HTML + text; `::for($user, $token)` | Added |
| [app/Mail/PasswordChangedMail.php](app/Mail/PasswordChangedMail.php) | No confirmation mail | Branded HTML + text; `::for($user)` | Added |
| [app/Mail/WelcomeUserMail.php](app/Mail/WelcomeUserMail.php) | No welcome mail | Branded HTML + text; `::for($user)` | Added |
| [app/Mail/Concerns/BuildsMailData.php](app/Mail/Concerns/BuildsMailData.php) | `MailBrand` helper (`Class not found` if not copied) | First name + frontend URL on the Mailable | Added |
| [app/Support/MailBrand.php](app/Support/MailBrand.php) | Extra class workers still autoloaded | Removed | Deleted |
| [app/Models/User.php](app/Models/User.php) | `notify()` + `notifyNow()` double-dispatched; blocked Octane | `DispatchMailJob::push(...)` only | Updated |
| [app/Notifications/ResetPasswordNotification.php](app/Notifications/ResetPasswordNotification.php) | `via()` dispatched a job *and* User notified | Same `DispatchMailJob::push`; `via` returns `[]` | Updated |
| [app/Notifications/PasswordChangedNotification.php](app/Notifications/PasswordChangedNotification.php) | Sync notify | Delegates to queued job | Updated |
| [app/Notifications/WelcomeUserNotification.php](app/Notifications/WelcomeUserNotification.php) | Sync notify | Delegates to queued job | Updated |
| [app/Http/Controllers/Api/v1/AuthController.php](app/Http/Controllers/Api/v1/AuthController.php) | 202 hid `MAIL_MAILER=log` / failed broker | Debug: `mailer`, `brokerStatus`, `queued`, `queue` | Updated |
| [app/Http/Controllers/Api/v1/UserController.php](app/Http/Controllers/Api/v1/UserController.php) | Welcome sent in-request | `DispatchMailJob::push(new SendWelcomeUserEmail)` | Updated |
| [app/Http/Controllers/Api/v1/MailPreviewController.php](app/Http/Controllers/Api/v1/MailPreviewController.php) | Couldn’t see HTML without SMTP | `GET /api/v1/mail/preview/{reset-password\|password-changed\|welcome}` | Added |
| [resources/views/emails/layout.blade.php](resources/views/emails/layout.blade.php) | Gray Laravel markdown | Teal card layout, dark mode, mobile | Added |
| [resources/views/emails/auth/*](resources/views/emails/auth) | No branded templates | Reset / changed / welcome + plaintext | Added |
| [config/api.php](config/api.php) | No mail/queue knobs | `frontend_url`, `mail_brand_color`, `mail_queue=notifications` | Updated |
| [config/queue.php](config/queue.php) | Missing rabbitmq heartbeat / named queues | `heartbeat` 30, `notifications` queue | Updated |
| [routes/api.php](routes/api.php) | Unnamed auth routes; 404 until Octane reload | `api.v1.password.*` aliases + mail preview | Updated |
| [tests/Feature/Api/v1/PasswordResetApiTest.php](tests/Feature/Api/v1/PasswordResetApiTest.php) | Asserted notifications | `Bus::assertDispatched(SendResetPasswordEmail)` | Updated |

**Still required on the box:** `php artisan octane:reload`, RabbitMQ up, and `queue:work rabbitmq --queue=...,notifications,...`. `MAIL_MAILER=log` still does not hit Gmail.
# End

---
## Recent changes

| File | Error | Correction | Update |
|---|---|---|---|
| [routes/api.php](routes/api.php) | No password routes; unnamed routes; Octane kept the old table after edits | Named every route (`api.v1.*`). Auth aliases: `forgot-password` / `password/email`, `reset-password` / `password/reset`, `change-password` / `PUT password`. `users/{user}/reset-password`. Comment: run `octane:reload` | Updated |
| [app/Http/Controllers/Api/v1/AuthController.php](app/Http/Controllers/Api/v1/AuthController.php) | Login/logout only | `forgotPassword` (always 202), `resetPassword` (token), `changePassword` (current token kept) | Updated |
| [app/Http/Controllers/Api/v1/UserController.php](app/Http/Controllers/Api/v1/UserController.php) | Admin could only PATCH a password | `POST users/{user}/reset-password` — set password or `sendEmail` | Updated |
| [app/Models/User.php](app/Models/User.php) | No `HasUuids`; default reset mail pointed at a web route | `HasUuids`; `sendPasswordResetNotification`; `resetToPassword()` (tokens + `password_changed_at`) | Updated |
| [app/Notifications/ResetPasswordNotification.php](app/Notifications/ResetPasswordNotification.php) | Reset URL used a web route | SPA link `{FRONTEND_URL}/reset-password?token&email` | Added |
| [app/Policies/Api/v1/UserPolicy.php](app/Policies/Api/v1/UserPolicy.php) | No reset ability; admin could hit self | `resetPassword`; self blocked (use change-password) | Updated |
| [config/api.php](config/api.php) | No frontend reset URL | `frontend_url`, `password_reset_path` | Updated |
| [database/migrations/2026_09_06_084606_create_personal_access_tokens_table.php](database/migrations/2026_09_06_084606_create_personal_access_tokens_table.php) | `$table->morphs()` → BIGINT `tokenable_id` | `uuidMorphs('tokenable')` for fresh installs | Updated |
| [database/migrations/2026_09_13_034916_change_personal_access_tokens_tokenable_id_to_uuid.php](database/migrations/2026_09_13_034916_change_personal_access_tokens_tokenable_id_to_uuid.php) | Login: `1265 Data truncated for column 'tokenable_id'` (UUID into BIGINT) | `dropMorphs` + `uuidMorphs` on existing table | Added |
| [postman/Attendance-API.postman_collection.json](postman/Attendance-API.postman_collection.json) | No importable collection | 64 requests, 13 folders, Bearer saved on Login | Added |
| [postman/Attendance-API.local.postman_environment.json](postman/Attendance-API.local.postman_environment.json) | No env | `baseUrl=http://127.0.0.1:6060`, seeded admin | Added |
| [postman/attendees-import-sample.csv](postman/attendees-import-sample.csv) | Nothing to attach on Excel import | Sample HR sheet | Added |
| [postman/attendance-logs-import-sample.csv](postman/attendance-logs-import-sample.csv) | Same for punches | Sample punch sheet | Added |
| [tests/Feature/Api/v1/PasswordResetApiTest.php](tests/Feature/Api/v1/PasswordResetApiTest.php) | No reset coverage | Forgot / reset / change / admin / self-deny | Added |

**Still 404 on `forgot-password`:** FrankenPHP Octane does not reload `routes/api.php` until `php artisan octane:reload` (or a full Octane restart). `route:list --path=password` must show `api.v1.password.email` before Postman will work.

# End

---
## Recent changes

| File | Error | Correction | Update |
|---|---|---|---|
| [routes/api.php](routes/api.php) | No bulk, import, or password-reset routes; `{id}` would swallow `/bulk` | Static `/bulk`, `/import`, `/import-template`, `/forgot-password`, `/reset-password`, `/change-password`, `/users/{user}/reset-password` registered first | Updated |
| [config/api.php](config/api.php) | No caps for list size, bulk, Excel, or frontend reset URL | `page_size_max`, `bulk_max_items` (500), `import_max_rows` (2000), `FRONTEND_URL` | Added |
| [config/queue.php](config/queue.php) | Default queue only | Named RabbitMQ queues (`attendance`, `attendees`, `operlog`, …) | Updated |
| [app/Http/Controllers/Concerns/PaginatesJsonApi.php](app/Http/Controllers/Concerns/PaginatesJsonApi.php) | Offset pagination only; `page.size` unbounded | Clamp `page[size]`; cursor pagination via `page[cursor]` | Added |
| [app/Support/BulkResult.php](app/Support/BulkResult.php) | No partial-success contract | DTO: created/updated/skipped/failed + `200/207/422` + merge | Added |
| [app/Http/Resources/Api/v1/BulkResultResource.php](app/Http/Resources/Api/v1/BulkResultResource.php) | Bulk replies were not JSON:API | `type: bulk-results` + top-level `errors[]` | Added |
| [app/Services/BulkAttendeeService.php](app/Services/BulkAttendeeService.php) | One attendee per HTTP call | Upsert by PIN; bulk grant/revoke; unique PIN/card → item errors | Added |
| [app/Services/BulkAttendanceLogService.php](app/Services/BulkAttendanceLogService.php) | One punch per call; dupes explode unique index | Chunk insert; skip `(device, pin, timestamp)` | Added |
| [app/Services/BulkImportErrorService.php](app/Services/BulkImportErrorService.php) | Resolve/ignore one row at a time | Bulk resolve/ignore by ids | Added |
| [app/Jobs/ProcessBulkAttendanceChunk.php](app/Jobs/ProcessBulkAttendanceChunk.php) | 500 punches would flood Reverb | Queue `attendance`; broadcast opt-in | Added |
| [app/Http/Requests/Api/v1/BulkUpsertAttendeesRequest.php](app/Http/Requests/Api/v1/BulkUpsertAttendeesRequest.php) | No bulk validation | Distinct PIN/card; max 500 items | Added |
| [app/Http/Requests/Api/v1/BulkAttendeeAccessRequest.php](app/Http/Requests/Api/v1/BulkAttendeeAccessRequest.php) | Access only per attendee | `grant\|revoke` + pins × deviceSerials | Added |
| [app/Http/Requests/Api/v1/BulkStoreAttendanceLogsRequest.php](app/Http/Requests/Api/v1/BulkStoreAttendanceLogsRequest.php) | No bulk punch schema | Nested device vs mixed-serial rules | Added |
| [app/Http/Requests/Api/v1/BulkImportErrorsRequest.php](app/Http/Requests/Api/v1/BulkImportErrorsRequest.php) | No bulk import-error schema | `action` + ids + adminNote | Added |
| [app/Http/Requests/Api/v1/ImportSpreadsheetRequest.php](app/Http/Requests/Api/v1/ImportSpreadsheetRequest.php) | No spreadsheet upload rules | `.xlsx/.csv`, 5 MB, dryRun flags | Added |
| [app/Http/Controllers/Api/v1/AttendeeController.php](app/Http/Controllers/Api/v1/AttendeeController.php) | Single CRUD only | `bulkStore`, `bulkAccess`, `import`, `importTemplate` | Updated |
| [app/Http/Controllers/Api/v1/AttendanceLogController.php](app/Http/Controllers/Api/v1/AttendanceLogController.php) | Manual store skipped `AttendancePunched`; no bulk/import | Event on single store; bulk + Excel import; cursor paging | Updated |
| [app/Http/Controllers/Api/v1/AttendeeImportErrorController.php](app/Http/Controllers/Api/v1/AttendeeImportErrorController.php) | One resolve/ignore | `bulkUpdate` | Updated |
| [app/Policies/Api/v1/AttendeePolicy.php](app/Policies/Api/v1/AttendeePolicy.php) | No bulk access ability | `manageAccessAny` | Updated |
| [app/Policies/Api/v1/AttendanceLogPolicy.php](app/Policies/Api/v1/AttendanceLogPolicy.php) | Create required a route device | `createBulk` (per-device check in service) | Updated |
| [app/Policies/Api/v1/AttendeeImportErrorPolicy.php](app/Policies/Api/v1/AttendeeImportErrorPolicy.php) | No bulk resolve | `resolveAny` | Updated |
| [app/Services/Imports/SpreadsheetReader.php](app/Services/Imports/SpreadsheetReader.php) | Excel needed a Composer package | ZipArchive + SimpleXML `.xlsx` / CSV | Added |
| [app/Services/Imports/SpreadsheetWriter.php](app/Services/Imports/SpreadsheetWriter.php) | No import template | Writes sample `.xlsx` / `.csv` | Added |
| [app/Services/Imports/ColumnMapper.php](app/Services/Imports/ColumnMapper.php) | Rigid headers | Aliases (`employee_id` → pin, Excel date serials) | Added |
| [app/Services/Imports/SpreadsheetImportService.php](app/Services/Imports/SpreadsheetImportService.php) | Sheets could not hit bulk services | Parse → map → chunk into existing bulk upsert/insert; dry-run | Added |
| [app/Http/Controllers/Api/v1/AuthController.php](app/Http/Controllers/Api/v1/AuthController.php) | Login/logout only | `forgotPassword`, `resetPassword`, `changePassword` | Updated |
| [app/Http/Controllers/Api/v1/UserController.php](app/Http/Controllers/Api/v1/UserController.php) | Admin could only PATCH password on update | `POST users/{user}/reset-password` (set password or email link) | Updated |
| [app/Models/User.php](app/Models/User.php) | Default Laravel reset mail (web route) | `sendPasswordResetNotification` + `resetToPassword` (tokens + `password_changed_at`) | Updated |
| [app/Notifications/ResetPasswordNotification.php](app/Notifications/ResetPasswordNotification.php) | Reset URL pointed at a web route | SPA link `{FRONTEND_URL}/reset-password?token&email` | Added |
| [app/Policies/Api/v1/UserPolicy.php](app/Policies/Api/v1/UserPolicy.php) | No dedicated reset ability; admin could hit self | `resetPassword`; self blocked (use change-password) | Updated |
| [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) | Octane/queue workers not listed | Worker map for named queues | Updated |
| [tests/Feature/Api/v1/BulkApiTest.php](tests/Feature/Api/v1/BulkApiTest.php) | No bulk coverage | Upsert, skip dupes, 500-cap, policies | Added |
| [tests/Feature/Api/v1/SpreadsheetImportApiTest.php](tests/Feature/Api/v1/SpreadsheetImportApiTest.php) | No Excel coverage | CSV/XLSX, dry-run, template, punches | Added |
| [tests/Feature/Api/v1/PasswordResetApiTest.php](tests/Feature/Api/v1/PasswordResetApiTest.php) | No reset coverage | Forgot/reset/change/admin/self-deny | Added |
# End
---
### Recent Changes Summary — Console Commands & Device Events

| File | What was changed | Issue | Improvement |
|------|------------------|-------|-------------|
| `app/Console/Commands/ApproveDevice.php` | Unified approve/block for one, many, or all pending (`--all`, `--block`, `--create`, `--dry-run`, `--force`) | Four overlapping commands (`approve`, `approve-all`, `bulk-approve`, `block`) | One command covers all approve/block flows |
| `app/Console/Commands/ListDevices.php` | Added `--pending`, `--offline`, `--search`, richer columns | Separate `list-pending` command; limited filters | Single list command with common filters |
| `app/Console/Commands/ApproveAllDevices.php` | **Removed** | Redundant | Use `device:approve --all` |
| `app/Console/Commands/BulkApproveDevices.php` | **Removed** | Redundant | Use `device:approve SN1 SN2 …` |
| `app/Console/Commands/BlockDevice.php` | **Removed** | Redundant | Use `device:approve {serial} --block` |
| `app/Console/Commands/ListPendingDevices.php` | **Removed** | Redundant | Use `device:list --pending` |
| `app/Console/Commands/ShowDevice.php` | Added `declare(strict_types=1)` | Inconsistent style | Matches other commands |
| `app/Console/Commands/DeleteDevice.php` | Added `declare(strict_types=1)` | Same | Same |
| `app/Console/Commands/SyncDeviceTime.php` | Uses `QueuesDeviceCommands` + `--serial` / `--status` / `--all` | Only targeted by status; duplicated `device:sys set-time` targeting | Same targeting model as protocol commands |
| `app/Console/Commands/Files/FileCommand.php` | Renamed `{--action=}` → `{--put-action=}` | Option name collided with `{action}` argument | `put\|get\|delete` and PutFile `Action` no longer clash |
| `Biometrics/FaceCommand.php` | Removed local `abortWithError()` | Duplicated trait helper | Uses `QueuesDeviceCommands::abortWithError()` |
| `Biometrics/FingerprintCommand.php` | Same | Same | Same |
| `Biometrics/FingerVeinCommand.php` | Same | Same | Same |
| `Biometrics/PhotoCommand.php` | Same | Same | Same |
| `Biometrics/UnifiedBioCommand.php` | Same | Same | Same |
| `Publicity/PublicityCommand.php` | Same | Same | Same |
| `app/Models/Device.php` | `approve()` / `block()` fire `DeviceApproved` / `DeviceBlocked` | Events only fired from the API controller | Artisan and API share one event path |
| `app/Http/Controllers/Api/v1/DeviceController.php` | Removed duplicate `event(...)`; status updates use `approve()` / `block()` | Events would fire twice after the model change | Single emission path |

### Command cheatsheet (after merges)

| Action | Command |
|--------|---------|
| Approve one/many | `php artisan device:approve SN1 SN2` |
| Approve all pending | `php artisan device:approve --all` |
| Block | `php artisan device:approve SN1 --block --reason="…"` |
| List pending / online / offline | `php artisan device:list --pending` / `--online` / `--offline` |
| Sync time | `php artisan device:sync-time --serial=SN1` or `device:sys set-time` |
| File put | `php artisan device:file put --path=… --url=… --put-action=SyncData` |

# End

---
### Recent Changes Summary — Console Commands & Device Events

| File | What was changed | Issue | Improvement |
|------|------------------|-------|-------------|
| `app/Console/Commands/ApproveDevice.php` | Unified approve/block for one, many, or all pending (`--all`, `--block`, `--create`, `--dry-run`, `--force`) | Four overlapping commands (`approve`, `approve-all`, `bulk-approve`, `block`) | One command covers all approve/block flows |
| `app/Console/Commands/ListDevices.php` | Added `--pending`, `--offline`, `--search`, richer table columns | Separate `list-pending` command; limited filters | Single list command with common filters |
| `app/Console/Commands/ApproveAllDevices.php` | **Removed** | Redundant with unified approve | Use `device:approve --all` |
| `app/Console/Commands/BulkApproveDevices.php` | **Removed** | Redundant | Use `device:approve SN1 SN2 …` |
| `app/Console/Commands/BlockDevice.php` | **Removed** | Redundant | Use `device:approve {serial} --block` |
| `app/Console/Commands/ListPendingDevices.php` | **Removed** | Redundant | Use `device:list --pending` |
| `app/Console/Commands/ShowDevice.php` | Added `declare(strict_types=1)` | Inconsistent style | Aligns with other commands |
| `app/Console/Commands/DeleteDevice.php` | Added `declare(strict_types=1)` | Same | Same |
| `app/Models/Device.php` | `approve()` / `block()` now fire `DeviceApproved` / `DeviceBlocked` | Events only fired from the API controller | Artisan and API share the same domain events |
| `app/Http/Controllers/Api/v1/DeviceController.php` | Removed duplicate `event(...)`; status updates use `approve()` / `block()` | Double-firing events after model change | Single event emission path |

### New command cheatsheet

| Action | Command |
|--------|---------|
| Approve one/many | `php artisan device:approve SN1 SN2` |
| Approve all pending | `php artisan device:approve --all` |
| Block | `php artisan device:approve SN1 --block --reason="…"` |
| List pending | `php artisan device:list --pending` |
| List online/offline | `php artisan device:list --online` / `--offline` |

# End

---
### Recent Changes Summary — Jobs & Events

| File | What was changed | Issue | Improvement |
|------|------------------|-------|-------------|
| `app/Providers/EventServiceProvider.php` | Replaced job classes and `boot()` closures with explicit listener classes in `$listen` | Jobs were registered as listeners but need models in the constructor (broken wiring); attendee logic lived in anonymous closures | Clear, testable event map; correct dispatch path |
| `app/Listeners/DispatchProcessAttendanceLog.php` *(new)* | Thin listener → `ProcessAttendanceLog::dispatch($event->log)` | `ProcessAttendanceLog` could not be built from the event by the container | Reliable attendance processing after `AttendancePunched` |
| `app/Listeners/DispatchDeviceApprovedSideEffects.php` *(new)* | Thin listener → `DeviceApprovedSideEffects::dispatch` | Same constructor/listener mismatch | Side effects run after approve |
| `app/Listeners/DispatchNotifyDeviceOffline.php` *(new)* | Thin listener → `NotifyDeviceOffline::dispatch` | Same mismatch | Offline notifications queued correctly |
| `app/Listeners/DispatchDeviceInfoUpdated.php` *(new)* | Thin listener → `DeviceInfoUpdated` job | Event/job same name; unsafe direct `$listen` entry | Safe bridge; job still aliased correctly |
| `app/Listeners/DispatchProcessAttendeeImportError.php` *(new)* | Thin listener → `ProcessAttendeeImportError::dispatch` | Same mismatch | Import errors processed via queue |
| `app/Listeners/SyncAttendeeOnCreated.php` *(new)* | Replaces closure; skips empty serial lists | Closure-only registration | Explicit, skip no-op dispatches |
| `app/Listeners/SyncAttendeeOnUpdated.php` *(new)* | Replaces closure | Same | Explicit update → device sync |
| `app/Listeners/SyncAttendeeOnDeleted.php` *(new)* | Replaces closure; `delete: true` | Same | Explicit delete → device DELETE commands |
| `app/Listeners/SyncAttendeeOnAccessGranted.php` *(new)* | Replaces closure | Same | Single-device grant sync |
| `app/Listeners/SyncAttendeeOnAccessRevoked.php` *(new)* | Replaces closure; `delete: true` | Same | Single-device revoke |
| `app/Events/AttendeeAccessGranted.php` | `ShouldBroadcast` + payload | No realtime UI update on grant | Broadcasts to `attendees` + `device.{serial}` |
| `app/Events/AttendeeAccessRevoked.php` | `ShouldBroadcast` + payload | No realtime UI update on revoke | Same channels + clear event name |
| `app/Events/AttendeeDeleted.php` | `ShouldBroadcast` + pin/serials payload | Delete was silent to frontends | Clients can refresh lists in realtime |
| `app/Events/AttendeeCreated.php` | Added `declare(strict_types=1)` | Inconsistent typing | Aligns with other events |
| `app/Jobs/DeviceApprovedSideEffects.php` | After-commit, timeout, backoff, tags, logging, `failed()` | Minimal job; weak failure handling | Safer post-approve INFO + attendee re-sync |
| `app/Jobs/SyncAttendeeToDevices.php` | `ShouldBeUnique`, `ShouldQueueAfterCommit`, tags, better logs | Duplicate sync jobs; weak observability | Deduped syncs; clearer success/skip/fail logs |

### Wiring pattern (after change)

| Before | After |
|--------|--------|
| `Event → Job` (broken for model ctors) | `Event → Listener → Job::dispatch(...)` |
| Attendee side effects in `boot()` closures | All in `$listen` + named listeners |
---
# End
---
### Policy Changes Summary

| File | What was changed | Issue | Improvement |
|------|------------------|-------|-------------|
| `app/Policies/Api/v1/Policy.php` | Added class + method docblocks and detailed inline comments explaining `before()`, `authorizeDevice()`, `authorizeSite()`, and `resolveDevice()` | Base policy had almost no documentation; logic for 404 vs 403 and device resolution was opaque | Clear single source of truth for multi-tenancy rules and admin bypass |
| `app/Policies/Api/v1/DevicePolicy.php` | Tightened `view()` with permission check; added full comments | `view()` only checked site access — a user with zero device permissions could still open a device by ID | Permission gate + site gate; admin-only delete documented |
| `app/Policies/Api/v1/PendingCommandPolicy.php` | `viewAny` & `view` now require `canManageCommands()` or `canManageDevices()`; full comments | `viewAny` returned `Response::allow()` unconditionally (too open) | Only users who can manage commands/devices can list or view them |
| `app/Policies/Api/v1/AttendeePolicy.php` | Added comprehensive comments (including `sharesSiteWith()`) | Logic for dual site/device sharing was undocumented | Self-documenting multi-tenancy model for attendees |
| `app/Policies/Api/v1/SitePolicy.php` | Added class + method comments | No explanation of why `viewAny` is open or why delete is admin-only | Clear documentation of sites as the multi-tenant boundary |
| `app/Policies/Api/v1/AttendanceLogPolicy.php` | Replaced hard-coded `isOperator()` with permission checks; added comments | Used role check instead of the permission system | Consistent permission-based authorization |
| `app/Policies/Api/v1/BioTemplatePolicy.php` | Added permission checks on `viewAny` / `view` / `delete`; removed `isOperator()`; added comments | `viewAny` was completely open; delete used hard-coded role | Proper permission gates for biometric data |
| `app/Policies/Api/v1/PhotoPolicy.php` | Same as BioTemplate — permission checks + removed `isOperator()` + comments | `viewAny` was completely open; delete used hard-coded role | Proper permission gates for photos |
| `app/Policies/Api/v1/AttendeeImportErrorPolicy.php` | Added class + method comments | No documentation of resolve/ignore flow | Clear rules for import-error handling |
| `app/Policies/Api/v1/UserPolicy.php` | Added detailed comments for self-protection in `before()` and admin-role guards | Self-lockout prevention and admin-role rules were hard to understand | Explicit documentation of self-protection and elevation rules |
| `routes/api.php` *(new)* | Full reconstructed route list + `throttle:5,1` on login | Routes file was missing from the original ZIP; no rate limiting on login | Complete route map + protection against brute-force login |

### Summary of security improvements

| Issue fixed | Before | After |
|-------------|--------|-------|
| Open `viewAny` policies | PendingCommand, BioTemplate, Photo allowed anyone | Require relevant permissions |
| Hard-coded role checks | `isOperator()` used in several places | Pure permission helpers (`canManage*`, `canRead*`) |
| Missing permission on `Device::view` | Only site check | Permission + site check |
| No login rate limiting | None | `throttle:5,1` |
| Undocumented multi-tenancy logic | Opaque | Inline + docblock comments everywhere |

---

### Recent Changes Summary

| File | What was changed | Issue | Improvement |
|------|------------------|-------|-------------|
| `database/migrations/2026_09_09_034859_create_user_site_table.php` | Table name changed from `site_user` → **`user_site`** | Migration created `site_user`, but `User`, `Site`, and `SiteUser` all expected `user_site` → SQL error on `GET /me` | Pivot table name matches models; `user->sites()` works |
| `app/Models/Device.php` | Added `getRouteKeyName()` returning **`serial_number`** | Route binding looked up devices by UUID; calling `/devices/PSS7234900035` failed with “No query results for model” | URLs use real-world serial numbers: `/devices/{serial}` |
| `bootstrap/app.php` *(new / updated)* | Custom `withExceptions()` renderers for API | Missing models returned full stack traces and exception dumps | Clean JSON:API errors (404, 401, 403, 422, etc.) with short `errors[]` body — no traces to the client |

### Response behavior (after exception fix)

| Situation | Before | After |
|-----------|--------|--------|
| Device serial not found | Full `NotFoundHttpException` + stack trace | `404` + `{ "errors": [{ "status": "404", "title": "Not Found", "detail": "Device [SERIAL] not found." }] }` |
| Validation error | Default Laravel validation JSON | JSON:API-style `errors` with `source.pointer` |
| Unauthenticated | Often HTML or raw exception | `401` + clear message |
| Policy forbidden | Mixed | `403` + policy message |

### Quick reference — what to run on your machine

```bash
# 1. Fix pivot table (if needed)
php artisan migrate

# 2. Device model change is code-only — no migrate
# 3. Merge bootstrap/app.php exception block into your app
# 4. APP_DEBUG=false in production
```
# End

---

### Review summary

| Area | Issue | Improvement applied |
|------|--------|---------------------|
| **Event → Job wiring** | Jobs like `ProcessAttendanceLog` were listed directly in `$listen` but their constructors expect models. Laravel cannot build those jobs from the event. | Thin **Listeners** that call `Job::dispatch(...)` |
| **EventServiceProvider** | Attendee side-effects lived in anonymous `Event::listen` closures in `boot()` | All mappings moved to explicit `$listen` + dedicated listener classes |
| **Access events** | `AttendeeAccessGranted` / `Revoked` did not broadcast | Now `ShouldBroadcast` with useful payload |
| **AttendeeDeleted** | No broadcast | Broadcasts pin + device serials |
| **DeviceApprovedSideEffects** | Minimal; no `failed()`, no timeout, no after-commit | `ShouldQueueAfterCommit`, timeout, tags, logging, `failed()` |
| **SyncAttendeeToDevices** | Could enqueue duplicate work | `ShouldBeUnique` + `ShouldQueueAfterCommit`, clearer logging |

### New listeners (`app/Listeners/`)

| Listener | Event | Dispatches |
|----------|--------|------------|
| `DispatchProcessAttendanceLog` | `AttendancePunched` | `ProcessAttendanceLog` |
| `DispatchDeviceApprovedSideEffects` | `DeviceApproved` | `DeviceApprovedSideEffects` |
| `DispatchNotifyDeviceOffline` | `DeviceOffline` | `NotifyDeviceOffline` |
| `DispatchDeviceInfoUpdated` | `DeviceInfoUpdated` | `DeviceInfoUpdated` job |
| `DispatchProcessAttendeeImportError` | `AttendeeImportErrorCreated` | `ProcessAttendeeImportError` |
| `SyncAttendeeOnCreated` | `AttendeeCreated` | `SyncAttendeeToDevices` |
| `SyncAttendeeOnUpdated` | `AttendeeUpdated` | `SyncAttendeeToDevices` |
| `SyncAttendeeOnDeleted` | `AttendeeDeleted` | `SyncAttendeeToDevices` (delete) |
| `SyncAttendeeOnAccessGranted` | `AttendeeAccessGranted` | `SyncAttendeeToDevices` |
| `SyncAttendeeOnAccessRevoked` | `AttendeeAccessRevoked` | `SyncAttendeeToDevices` (delete) |

### Flow (after change)

```
Event fired
  → Listener (sync, tiny)
    → Job::dispatch(...) on the right queue
      → Worker runs side effects (device commands, notifications, …)
```

### Still optional / TODO in jobs

- `ProcessAttendanceLog` — still mostly a stub (`TODO: late/early, daily summary`)
- `NotifyDeviceOffline` — still has `TODO` for real notification channels

Copy the new `app/Listeners/` files and updated `EventServiceProvider`, jobs, and events into your project, then:

```bash
php artisan event:clear
php artisan queue:restart
# or octane:reload
```


# End
---
| File | Error | Correction |Update|
|---|---|---|---|
| `2026_09_09_034859_create_user_site_table.php` | Created/dropped table `site_user`; filename did not match models | Table is `user_site`; file renamed to `2026_09_09_034859_create_user_site_table.php` ||
| `0001_01_01_000000_create_users_table.php` | `sessions.user_id` was `foreignUuid` but `users.id` is bigint | `foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete()` ||
| `UserPolicy.php` | Missing — `UserController` / `UserPermissionController` authorized against `User` with no policy | Added `App\Policies\API\V1\UserPolicy` (self vs `manage-users`; deny self delete/sites/permissions) ||
| `AuthServiceProvider.php` | V1 policies not auto-discovered (`App\Policies\API\V1`) | New provider maps all 9 models → V1 policies ||
| `AttendanceLogController.php` | Namespace `Api\v1`; no resource import; `index()` untyped | Namespace `Api\V1`; import `AttendanceLogResource` + `JsonApiResourceCollection`; typed `index()` ||
| `AttendeeController.php` | Imported `App\Http\Resources\AttendeeResource`; missing collection import | `App\Http\Resources\Api\V1\AttendeeResource` + `JsonApiResourceCollection` ||
| `AttendeeImportErrorController.php` | Wrong resource namespace; missing collection import | `Api\V1\AttendeeImportErrorResource` + `JsonApiResourceCollection` ||
| `AuthController.php` | Imported `App\Http\Resources\UserResource` | `App\Http\Resources\Api\V1\UserResource` ||
| `BioTemplateController.php` | Wrong resource namespace; missing collection import | `Api\V1\BioTemplateResource` + `JsonApiResourceCollection` ||
| `DeviceController.php` | Wrong resource namespace; missing collection import | `Api\V1\DeviceResource` + `JsonApiResourceCollection` ||
| `PendingCommandController.php` | Wrong resource namespace; missing collection import | `Api\V1\PendingCommandResource` + `JsonApiResourceCollection` ||
| `PhotoController.php` | Wrong resource namespace; missing collection import | `Api\V1\PhotoResource` + `JsonApiResourceCollection` ||
| `SiteController.php` | Wrong resource namespace; missing collection import | `Api\V1\SiteResource` + `JsonApiResourceCollection` ||
| `UserController.php` | Wrong resource namespace; missing collection import | `Api\V1\UserResource` + `JsonApiResourceCollection` ||
| `UserPermissionController.php` | Wrong resource namespace; missing collection import | `Api\V1\UserPermissionResource` + `JsonApiResourceCollection` ||
| `*Resource.php` (10 files) | Namespace `App\Http\Resources\Api\v1` | `App\Http\Resources\Api\V1` ||
| `AttendanceLogPolicy.php` | Unused `Response` import; `create()` used `isOperator()` (managers included) | Dropped unused import; `create()` → `canManageDevices()` ||
| `AttendeeImportErrorPolicy.php` | Unused `Response` import | Removed ||
| `AttendeePolicy.php` | Unused `Response` import | Removed ||
| `BioTemplatePolicy.php` | Unused `Response`; `viewAny()` always true; `delete()` used `isOperator()` | `viewAny()` → `canReadEmployees()`; `delete()` → `canManageDevices()` ||
| `DevicePolicy.php` | Unused `Response`; `viewAny()` always true | `viewAny()` → `canManageDevices() \|\| canReadAttendance()` ||
| `PendingCommandPolicy.php` | Unused `Response`; `viewAny()` always true | `viewAny()` → `canManageCommands()` ||
| `PhotoPolicy.php` | Unused `Response`; `viewAny()` always true; `delete()` used `isOperator()` | `viewAny()` → `canReadEmployees()`; `delete()` → `canManageDevices()` ||
| `SitePolicy.php` | `viewAny()` always true | `viewAny()` → `canManageSites() \|\| canReadEmployees() \|\| canReadAttendance()` ||
| `AttendeeCreated.php` | `Attendee` type-hinted with no `use`; `broadcastOn()` but not `ShouldBroadcast` | Imported `Attendee`; `implements ShouldBroadcastNow` ||
| `AttendeeUpdated.php` | Broadcast methods without `ShouldBroadcast` | `implements ShouldBroadcastNow`; unused channel imports removed ||
| `AttendeeDeleted.php` | Unused broadcast imports (event is not broadcast) | Kept dispatch/serialize only ||
| `DeviceApproved.php` | `broadcastOn()` without `ShouldBroadcast` | `implements ShouldBroadcastNow` ||
| `DeviceBlocked.php` | `broadcastOn()` without `ShouldBroadcast` | `implements ShouldBroadcastNow` ||
| `CommandQueued.php` | `broadcastOn()` without `ShouldBroadcast` | `implements ShouldBroadcastNow` ||
| `DeviceOffline.php` | Public `Channel('devices')` leaked serial/IP; event name `offline` | Private channels (+ site); `device.offline`; payload aligned with other device events ||
