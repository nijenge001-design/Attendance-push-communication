# Device & iClock Artisan Commands

Operational guide for ZKTeco PUSH devices, the pending-command queue, protocol helpers, and related maintenance.

```bash
php artisan list device
php artisan list command
php artisan list logs
```

Most commands live under `device:*`. Protocol helpers share `App\Console\Commands\Concerns\QueuesDeviceCommands` (`--serial`, `--status`, `--all`, `--force`).

---

## Quick start

```bash
# Inventory
php artisan device:list
php artisan device:list --pending
php artisan device:heartbeat

# Approve / block (fires DeviceApproved / DeviceBlocked)
php artisan device:approve SN001
php artisan device:approve --all --dry-run
php artisan device:approve --all --force
php artisan device:approve SN001 --block --reason="lost unit"

# Time + INFO
php artisan device:sync-time --serial=SN001
php artisan device:sys info --serial=SN001

# Watch the queue
php artisan device:commands-watch --status=approved

# One-off command
php artisan device:command SN001 --sync-time
php artisan device:command SN001 --info
```

---

## Targeting convention (protocol commands)

Applies to `device:attlog`, `device:user`, `device:face`, `device:fp`, `device:fv`, `device:bio`, `device:photo`, `device:file`, `device:ad`, `device:sys`, `device:sync-time`.

| Option | Meaning |
|--------|---------|
| `--serial=` | One SN, or comma/space-separated list (`SN1,SN2`) |
| `--status=` | When no `--serial`: filter by status (default `approved`) |
| `--all` | Every device (ignores `--status`) |
| `--force` | Skip confirmation on multi-device broadcasts |

Missing serials abort the command. Non-approved targets still queue, but a warning is printed (device may not poll until approved).

---

## Lifecycle & inventory

### `device:list`

```bash
php artisan device:list
php artisan device:list --pending
php artisan device:list --status=approved --online
php artisan device:list --offline --threshold=5
php artisan device:list --search=PSS --limit=20
```

| Option | Default | Description |
|--------|---------|-------------|
| `--status=` | — | `pending`, `approved`, `blocked` |
| `--pending` | off | Shortcut for `--status=pending` |
| `--online` / `--offline` | off | Filter by last-seen vs `--threshold` |
| `--search=` | — | Match serial, name, or IP |
| `--limit=` | `50` | Max rows |
| `--threshold=` | `5` | Minutes for online/offline |

Replaces the old `device:pending` / `device:list-pending` commands.

### `device:show`

```bash
php artisan device:show SN001
```

### `device:approve`

Approve or block one, many, or all pending devices.

`Device::approve()` / `Device::block()` fire `DeviceApproved` / `DeviceBlocked` (same path as the API).

```bash
php artisan device:approve SN001
php artisan device:approve SN001 SN002 --create
php artisan device:approve --all
php artisan device:approve --all --dry-run
php artisan device:approve SN001 --block --reason="lost unit" --force
```

| Argument / option | Description |
|-------------------|-------------|
| `{serials?*}` | One or more serials (omit with `--all`) |
| `--all` | All **pending** devices (approve only; refused with `--block`) |
| `--block` | Block instead of approve |
| `--reason=` | Reason when blocking |
| `--create` | Create missing serials as pending, then act |
| `--dry-run` | Print targets only |
| `--force` | Skip confirmation |

**Removed (use this command instead):**

| Old | New |
|-----|-----|
| `device:approve-all` | `device:approve --all` |
| `device:bulk-approve SN1 SN2` | `device:approve SN1 SN2` |
| `device:block SN --reason=` | `device:approve SN --block --reason=` |

### `device:delete`

```bash
php artisan device:delete SN001
php artisan device:delete SN001 --force   # hard delete
```

`--force` here means **hard delete**, not “skip confirm”. Confirm is skipped only with `-n` / `--no-interaction`.

---

## Heartbeats

### `device:heartbeat`

```bash
php artisan device:heartbeat
php artisan device:heartbeat --offline-only
php artisan device:heartbeat --watch --interval=5 --threshold=5
```

| Option | Default | Description |
|--------|---------|-------------|
| `--threshold=` | `5` | Minutes without last-seen → offline |
| `--offline-only` | off | Hide online devices |
| `--watch` | off | Refresh loop |
| `--interval=` | `5` | Seconds between refreshes |

---

## Queue a command

### `device:command`

Queue one (or a raw body) for a **single** serial.

```bash
php artisan device:command SN001 --info
php artisan device:command SN001 --reboot
php artisan device:command SN001 --sync-time
php artisan device:command SN001 --user=1001 --name="Jane"
php artisan device:command SN001 "CHECK"
```

| Flag | Protocol |
|------|----------|
| `--reboot` | `REBOOT` |
| `--unlock` | `AC_UNLOCK` |
| `--info` | `INFO` |
| `--clear-log` | `CLEAR LOG` |
| `--check` | `CHECK` |
| `--log` | `LOG` |
| `--sync-time` | DateTime |
| `--user=` / `--name=` | USERINFO helpers |
| `{body?}` | Raw command body |

Prefer `device:sys` / `device:user` / `device:broadcast` for structured work.

### `device:broadcast`

Same flags to **many** devices by status.

```bash
php artisan device:broadcast --sync-time --status=approved --force
php artisan device:broadcast --info --status=approved
php artisan device:broadcast --check --status=all --force
php artisan device:broadcast --reboot --status=approved
php artisan device:broadcast "CHECK" --status=approved --force
php artisan device:broadcast --all-users --status=approved --force
```

| Option | Default | Description |
|--------|---------|-------------|
| `{body?}` | — | Raw body |
| `--status=` | `approved` | `approved`, `pending`, `blocked`, `all` |
| `--reboot` `--unlock` `--info` `--clear-log` `--check` `--log` `--sync-time` | — | Built-in bodies |
| `--clear-data` | — | Destructive clear |
| `--all-users` | — | `DATA QUERY USERINFO` |
| `--force` | off | Skip confirmation |

### `device:sync-time`

Alias of `device:sys set-time` with the shared targeting trait.

```bash
php artisan device:sync-time --serial=SN001
php artisan device:sync-time --status=approved --force
php artisan device:sync-time --all --force
php artisan device:sync-time --datetime="2026-09-11 12:00:00" --serial=SN001
```

---

## Inspect / maintain the command queue

### `device:commands`

```bash
php artisan device:commands
php artisan device:commands SN001 SN002
php artisan device:commands --status=approved --limit=50
php artisan device:commands SN001 --clear
php artisan device:commands --all
```

| Option | Description |
|--------|-------------|
| `{serials?*}` | Omit = all devices (optionally `--status`) |
| `--clear` | Delete **pending** commands for those serials |
| `--all` | Include executed rows |
| `--status=` | Filter devices when listing all |
| `--limit=` | Max rows (`100`) |

### `device:commands-watch`

```bash
php artisan device:commands-watch
php artisan device:commands-watch --status=approved --ready
php artisan device:commands-watch SN001 --stuck --stuck-minutes=10
php artisan device:commands-watch --interval=5 --limit=50
```

### `command:toggle`

Enable or disable a stored pending command.

```bash
php artisan command:toggle C123 --disable
php artisan command:toggle C123 --enable
```

`{id}` is `command_id` or the row UUID/primary key. Exactly one of `--enable` / `--disable`.

### `device:retry-stuck`

```bash
php artisan device:retry-stuck --dry-run
php artisan device:retry-stuck --minutes=10 --delay=5
```

### `device:cleanup-commands`

```bash
php artisan device:cleanup-commands --days=30 --dry-run
php artisan device:cleanup-commands --days=30
```

Deletes executed, **non-recurring** commands older than `--days`.

### `device:process-scheduled`

```bash
php artisan device:process-scheduled
```

Resets due recurring rows so the next `getrequest` can send them again. Schedule this (e.g. every minute).

---

## Protocol category commands

### `device:sys`

```bash
php artisan device:sys info --serial=SN001
php artisan device:sys check --serial=SN001
php artisan device:sys reboot --serial=SN001 --force
php artisan device:sys unlock --serial=SN001
php artisan device:sys set-time --all
php artisan device:sys set-time --datetime="2026-09-11 12:00:00" --serial=SN001
php artisan device:sys set-option --option=Door1Delay=5 --serial=SN001
php artisan device:sys clear-data --serial=SN001 --force
php artisan device:sys shell --cmd="ls" --serial=SN001
```

| Action | Body |
|--------|------|
| `info` | `INFO` |
| `check` | `CHECK` |
| `reboot` | `REBOOT` |
| `unlock` | `AC_UNLOCK` |
| `set-time` | DateTime |
| `set-option` | Repeatable `--option=key=value` |
| `clear-data` | Clear data |
| `shell` | `--cmd=` |

### `device:user`

USERINFO on the terminal (not the same as API `SyncAttendeeToDevices`, which is the event/job path).

```bash
php artisan device:user update --serial=SN001 --pin=1001 --name="Jane" --privilege=0
php artisan device:user delete --serial=SN001 --pin=1001
php artisan device:user query --serial=SN001 --pin=1001
php artisan device:user query --serial=SN001
```

`--pin` required for update/delete; optional for query (all users).

### `device:attlog`

```bash
php artisan device:attlog query --serial=SN001 --start="2026-09-01 00:00:00" --end="2026-09-11 23:59:59"
php artisan device:attlog upload --serial=SN001
php artisan device:attlog clear --serial=SN001 --force
```

| Action | Meaning |
|--------|---------|
| `query` | Query range (`--start` / `--end`) |
| `upload` | Force `LOG` / check-and-transmit |
| `clear` | `CLEAR LOG` |

### `device:fp` (fingerprint)

```bash
php artisan device:fp enroll --serial=SN001 --pin=1001 --fid=0
php artisan device:fp query --serial=SN001 --pin=1001
php artisan device:fp update --serial=SN001 --pin=1001 --fid=0 --template=...
php artisan device:fp delete --serial=SN001 --pin=1001 --fid=0
```

### `device:face`

```bash
php artisan device:face query --serial=SN001 --pin=1001
php artisan device:face update --serial=SN001 --pin=1001 --fid=0 --template=...
php artisan device:face delete --serial=SN001 --pin=1001
```

### `device:fv` (finger vein)

```bash
php artisan device:fv update --serial=SN001 --pin=1001 --fid=0 --template=...
php artisan device:fv delete --serial=SN001 --pin=1001
```

### `device:bio` (unified BIODATA)

Do **not** replace `fp` / `face` / `fv` with this. Different protocol tables.

```bash
php artisan device:bio query --serial=SN001 --pin=1001 --type=1
php artisan device:bio update --serial=SN001 --pin=1001 --type=1 --index=0 --template=...
php artisan device:bio delete --serial=SN001 --pin=1001 --type=1 --index=0
```

### `device:photo`

```bash
php artisan device:photo update-user --serial=SN001 --pin=1001 --content=... --filename=1001.jpg
php artisan device:photo delete-user --serial=SN001 --pin=1001
php artisan device:photo update-bio --serial=SN001 --pin=1001 --content=...
php artisan device:photo delete-bio --serial=SN001 --pin=1001
```

### `device:file`

```bash
php artisan device:file put --serial=SN001 --path=/tmp/x.dat --url=https://example.com/x.dat --put-action=SyncData
php artisan device:file get --serial=SN001 --path=/tmp/x.dat
php artisan device:file delete --serial=SN001 --path=/tmp/x.dat
```

`--put-action` is the PutFile `Action` parameter (not the `put|get|delete` argument).

### `device:ad` (publicity pictures)

```bash
php artisan device:ad query --serial=SN001
php artisan device:ad update --serial=SN001 --filename=ad.jpg --content=...
php artisan device:ad delete --serial=SN001 --filename=ad.jpg
```

---

## Logs maintenance

### `logs:purge-requests`

```bash
php artisan logs:purge-requests --dry-run
php artisan logs:purge-requests --days=14 --force
```

---

## Events fired from Artisan

| Action | Event | Downstream |
|--------|--------|------------|
| `device:approve` (approve) | `DeviceApproved` | Broadcast + `DeviceApprovedSideEffects` (INFO + attendee re-sync) |
| `device:approve --block` | `DeviceBlocked` | Broadcast |

Same events as `POST /api/v1/devices/{device}/approve` and `/block`.

---

## Suggested schedule

```php
// routes/console.php or bootstrap scheduler
Schedule::command('device:process-scheduled')->everyMinute();
Schedule::command('device:retry-stuck --minutes=10')->everyFiveMinutes();
Schedule::command('device:cleanup-commands --days=30')->daily();
Schedule::command('logs:purge-requests')->daily();
```

---

## Command index

| Signature | Class |
|-----------|--------|
| `device:approve` | `ApproveDevice` |
| `device:list` | `ListDevices` |
| `device:show` | `ShowDevice` |
| `device:delete` | `DeleteDevice` |
| `device:heartbeat` | `MonitorDeviceHeartbeats` |
| `device:command` | `QueueDeviceCommand` |
| `device:broadcast` | `BroadcastDeviceCommand` |
| `device:sync-time` | `SyncDeviceTime` |
| `device:commands` | `ManageDeviceCommands` |
| `device:commands-watch` | `WatchDeviceCommands` |
| `device:retry-stuck` | `RetryStuckCommands` |
| `device:cleanup-commands` | `CleanupDeviceCommands` |
| `device:process-scheduled` | `ProcessScheduledCommands` |
| `command:toggle` | `ToggleCommand` |
| `device:sys` | `System/SystemCommand` |
| `device:user` | `User/UserCommand` |
| `device:attlog` | `Attendance/AttendanceLogCommand` |
| `device:fp` | `Biometrics/FingerprintCommand` |
| `device:face` | `Biometrics/FaceCommand` |
| `device:fv` | `Biometrics/FingerVeinCommand` |
| `device:bio` | `Biometrics/UnifiedBioCommand` |
| `device:photo` | `Biometrics/PhotoCommand` |
| `device:file` | `Files/FileCommand` |
| `device:ad` | `Publicity/PublicityCommand` |
| `logs:purge-requests` | `PurgeRequestLogs` |

Shared targeting: `Commands/Concerns/QueuesDeviceCommands.php`.
