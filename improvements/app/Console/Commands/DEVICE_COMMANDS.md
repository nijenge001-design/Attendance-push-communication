# Device & iClock Artisan Commands

Operational guide for managing ZKTeco PUSH devices, the command queue, and related maintenance tools.

Register commands under `app/Console/Commands/`. Most live under the `device:*` namespace.

```bash
php artisan list device
php artisan list command
php artisan list logs
```

---

## Quick start

```bash
# 1. See what is talking to the server
php artisan device:list
php artisan device:pending
php artisan device:heartbeat

# 2. Approve devices (fires DeviceObserver → CHECK + DateTime + INFO)
php artisan device:approve-all --dry-run
php artisan device:approve-all --force
# or one SN:
php artisan device:approve CPV2232560484

# 3. Watch the command queue
php artisan device:commands-watch --status=approved

# 4. Queue a one-off command
php artisan device:command CPV2232560484 --sync-time
php artisan device:command CPV2232560484 --info
```

---

## Device management

### `device:list`

List devices with status, online flag, protocol versions, IP, and last seen.

```bash
php artisan device:list
php artisan device:list --status=approved
php artisan device:list --status=pending --limit=100
php artisan device:list --online --threshold=5
```

| Option | Default | Description |
|--------|---------|-------------|
| `--status=` | — | `pending`, `approved`, or `blocked` |
| `--limit=` | `50` | Max rows |
| `--online` | off | Only devices seen within threshold |
| `--threshold=` | `5` | Minutes for online check |

---

### `device:pending`

List devices waiting for approval.

```bash
php artisan device:pending
```

---

### `device:show {serial}`

Show full detail for one device (capabilities, negotiated protocol, online, pending cmds).

```bash
php artisan device:show CPV2232560484
php artisan device:show CKOU233560203
```

---

### `device:approve {serial}`

Approve one device (preferred path — runs model `approve()` so **DeviceObserver** queues onboarding commands). Use `--block` to block instead.

```bash
php artisan device:approve CPV2232560484
php artisan device:approve CPV2232560484 --block --reason="Lab unit"
# Creates the row as pending first if SN never checked in:
php artisan device:approve NEWDEVICE001
```

| Option | Description |
|--------|-------------|
| `--block` | Block instead of approve |
| `--reason=` | Optional block reason |

---

### `device:approve-all`

Approve every pending device.

```bash
php artisan device:approve-all --dry-run
php artisan device:approve-all
php artisan device:approve-all --force
php artisan device:approve-all --no-interaction
```

| Option | Description |
|--------|-------------|
| `--dry-run` | List only; do not update |
| `--force` | Skip confirmation |

---

### `device:bulk-approve {serials*}`

Approve or block several serial numbers.

```bash
php artisan device:bulk-approve CPV2232560484 CKOU233560203 PSS7234900035
php artisan device:bulk-approve CPV2223660066 --block --reason="Retired"
php artisan device:bulk-approve MISSING001 --create
```

| Option | Description |
|--------|-------------|
| `--block` | Block instead of approve |
| `--reason=` | Reason when blocking |
| `--create` | Create missing SNs as pending, then act |

---

### `device:block {serial}`

Block a device.

```bash
php artisan device:block CPV2232560484
php artisan device:block CPV2232560484 --reason="Stolen / decommissioned"
```

---

### `device:delete {serial}`

Soft-delete (default) or hard-delete a device.

```bash
php artisan device:delete CPV2232560484
php artisan device:delete CPV2232560484 --force
php artisan device:delete CPV2232560484 --force --no-interaction
```

| Option | Description |
|--------|-------------|
| `--force` | Permanent delete (`forceDelete`) |

---

### `device:heartbeat`

Monitor online/offline status from `last_seen_at`.

```bash
php artisan device:heartbeat
php artisan device:heartbeat --threshold=3
php artisan device:heartbeat --offline-only
php artisan device:heartbeat --watch --interval=5
```

| Option | Default | Description |
|--------|---------|-------------|
| `--threshold=` | `5` | Minutes without heartbeat → offline |
| `--offline-only` | off | Hide online devices |
| `--watch` | off | Continuous refresh |
| `--interval=` | `5` | Seconds between refreshes in watch mode |

---

## Command queue

### `device:command {serial}`

Queue a command for **one** device.

```bash
php artisan device:command CPV2232560484 --sync-time
php artisan device:command CPV2232560484 --info
php artisan device:command CPV2232560484 --check
php artisan device:command CPV2232560484 --log
php artisan device:command CPV2232560484 --reboot
php artisan device:command CPV2232560484 --unlock
php artisan device:command CPV2232560484 --clear-log
php artisan device:command CPV2232560484 --user=1001 --name="Jane Doe"
php artisan device:command CPV2232560484 "DATA QUERY ATTLOG StartTime=2026-09-01 00:00:00	EndTime=2026-09-07 23:59:59"
```

| Option / arg | Description |
|--------------|-------------|
| `{body?}` | Raw command body |
| `--reboot` | `REBOOT` |
| `--unlock` | Door unlock |
| `--info` | `INFO` |
| `--clear-log` | Clear attendance log on device |
| `--check` | `CHECK` (re-read config / re-upload) |
| `--log` | Immediate data upload |
| `--sync-time` | Set device clock from server |
| `--user=` | PIN for `DATA UPDATE USERINFO` |
| `--name=` | Name with `--user` |

---

### `device:broadcast`

Queue the same command(s) to many devices by status.

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
| `{body?}` | — | Raw command body |
| `--status=` | `approved` | `approved`, `pending`, `blocked`, or `all` |
| `--reboot` / `--unlock` / `--info` / … | — | Same flags as `device:command` |
| `--clear-data` | — | Clear all data on device (destructive) |
| `--all-users` | — | `DATA QUERY USERINFO` |
| `--sync-time` | — | DateTime sync |
| `--force` | off | Skip confirmation |

---

### `device:sync-time`

Shortcut to push server time to devices.

```bash
php artisan device:sync-time --status=approved --force
php artisan device:sync-time --status=all --force
php artisan device:sync-time --datetime="2026-09-07 12:00:00" --status=approved --force
```

| Option | Default | Description |
|--------|---------|-------------|
| `--status=` | `approved` | Target status or `all` |
| `--datetime=` | now | `Y-m-d H:i:s` |
| `--force` | off | Skip confirmation |

---

### `device:commands`

List or clear pending commands for one, many, or all devices.

```bash
# List pending for everyone
php artisan device:commands

# One or more SNs
php artisan device:commands CPV2232560484 CKOU233560203

# Only approved devices, include executed history
php artisan device:commands --status=approved --all --limit=200

# Clear pending for specific devices
php artisan device:commands CPV2232560484 --clear
php artisan device:commands --status=approved --clear --no-interaction
```

| Option | Default | Description |
|--------|---------|-------------|
| `{serials?*}` | all (optional status filter) | Limit to these SNs |
| `--clear` | off | Delete pending commands |
| `--all` | off | Include executed |
| `--status=` | — | When no serials given |
| `--limit=` | `100` | Max rows |

---

### `device:commands-watch`

**Live** monitor of the command queue (refreshing table).

```bash
# Everything pending
php artisan device:commands-watch

# Approved devices only
php artisan device:commands-watch --status=approved

# Specific serials
php artisan device:commands-watch CPV2232560484 CKOU233560203

# Only ready to send
php artisan device:commands-watch --ready --interval=3

# Stuck (sent, no ACK)
php artisan device:commands-watch --stuck --stuck-minutes=10

# Include executed rows
php artisan device:commands-watch --all --limit=80
```

| Option | Default | Description |
|--------|---------|-------------|
| `{serials?*}` | all matching status | Limit to SNs |
| `--status=` | — | Device status filter |
| `--all` | off | Include executed |
| `--ready` | off | Ready-to-send only |
| `--stuck` | off | Stuck only |
| `--stuck-minutes=` | `10` | Stuck threshold |
| `--interval=` | `5` | Refresh seconds |
| `--limit=` | `50` | Rows per refresh |

Press **Ctrl+C** to stop.

---

### `device:cleanup-commands`

Delete old **executed**, non-recurring commands.

```bash
php artisan device:cleanup-commands --dry-run
php artisan device:cleanup-commands --days=30
php artisan device:cleanup-commands --days=7
```

| Option | Default | Description |
|--------|---------|-------------|
| `--days=` | `30` | Age threshold |
| `--dry-run` | off | Count only |

---

### `device:retry-stuck`

Reschedule commands that were sent but never acknowledged.

```bash
php artisan device:retry-stuck --dry-run
php artisan device:retry-stuck --minutes=10 --delay=5
php artisan device:retry-stuck --minutes=15 --delay=2
```

| Option | Default | Description |
|--------|---------|-------------|
| `--minutes=` | `10` | Sent this long ago without ACK → stuck |
| `--delay=` | `5` | Minutes before next send attempt |
| `--dry-run` | off | List only |

---

### `device:process-scheduled`

Prepare **due recurring** commands so the next `getrequest` can deliver them again.

```bash
php artisan device:process-scheduled
```

Schedule in `routes/console.php` or the scheduler:

```php
Schedule::command('device:process-scheduled')->everyMinute();
Schedule::command('device:retry-stuck')->everyFiveMinutes();
Schedule::command('device:cleanup-commands --days=30')->daily();
```

---

### `command:toggle {id}`

Enable or disable a single queued command by CmdID or primary key.

```bash
php artisan command:toggle 1001 --disable
php artisan command:toggle 1001 --enable
php artisan command:toggle 9f3c2e1a-... --disable
```

---

## Logging maintenance

### `logs:purge-requests` (PurgeRequestLogs)

Purge old files from the request-log disk.

```bash
php artisan logs:purge-requests --days=14 --dry-run
php artisan logs:purge-requests --days=7 --force
```

*(Exact signature depends on your PurgeRequestLogs implementation.)*

---

## Typical workflows

### Onboard a new site of devices

```bash
php artisan device:pending
php artisan device:approve-all --dry-run
php artisan device:approve-all --force
php artisan device:commands-watch --status=approved --ready
php artisan device:heartbeat --watch
```

### One device misbehaving

```bash
php artisan device:show CPV2232560484
php artisan device:commands CPV2232560484 --all
php artisan device:command CPV2232560484 --check
php artisan device:command CPV2232560484 --sync-time
php artisan device:commands-watch CPV2232560484 --interval=3
```

### Force re-upload of attendance

```bash
php artisan device:broadcast --log --status=approved --force
# or CHECK so devices re-apply stamps / config
php artisan device:broadcast --check --status=approved --force
```

### Block and remove a device

```bash
php artisan device:block OLDDEVICE001 --reason="Replaced"
php artisan device:commands OLDDEVICE001 --clear --no-interaction
php artisan device:delete OLDDEVICE001
```

---

## Notes

1. **Always prefer** `approve()` / `block()` model methods so observers run (onboarding commands on approve).
2. Unapproved devices may still **POST** data; depending on `CdataController`, posts can be ACK’d without storing until the device is approved.
3. Commands are delivered on **`GET /iclock/getrequest`**; replies arrive on **`POST /iclock/devicecmd`**.
4. Protocol negotiation (`pushver` / `PushProtVer`) is handled in **`CdataController`** handshake, not in these artisan commands.
5. Use **`--no-interaction`** or **`--force`** in scripts/CI to skip prompts.

---

## Command index

| Command | Purpose |
|---------|---------|
| `device:list` | List devices |
| `device:pending` | List pending |
| `device:show` | Device detail |
| `device:approve` | Approve/block one |
| `device:approve-all` | Approve all pending |
| `device:bulk-approve` | Approve/block many |
| `device:block` | Block one |
| `device:delete` | Delete one |
| `device:heartbeat` | Online monitor |
| `device:command` | Queue to one device |
| `device:broadcast` | Queue to many |
| `device:sync-time` | Time sync broadcast |
| `device:commands` | List/clear queue |
| `device:commands-watch` | Live queue watch |
| `device:cleanup-commands` | Purge old executed |
| `device:retry-stuck` | Retry un-ACKed |
| `device:process-scheduled` | Due recurring prep |
| `command:toggle` | Enable/disable one cmd |
| `logs:purge-requests` | Purge request log files |
```
