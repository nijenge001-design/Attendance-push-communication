# Domain Models & Services – Improvements

Improvements applied after reviewing models, services, migrations and the official **Attendance PUSH Communication Protocol** (2020 + 2024 docs).

## 1. Schema / Migration

**New migration:** `2026_09_07_000001_improve_attendance_and_indexes.php`

### attendance_logs
- Added protocol fields from 2024 spec:
  - `mask_flag` (0/1)
  - `temperature` (decimal)
  - `conv_temperature` (decimal)
- Unique constraint on `(device_serial, pin, timestamp)` → prevents duplicate punches
- Indexes: `pin`, `device_serial`, `timestamp`, composites for common queries

### bio_templates
- Unique constraint on `(device_serial, pin, type, no, index)`
- Lookup indexes

### photos / attendee_device / pending_commands
- Additional indexes for frequent filters (`stuck` commands, active attendees, etc.)

## 2. AttendanceLog model

- New fillable + casts for mask/temperature fields
- Status & type constants aligned with common firmware values
- Scopes: `withMask()`, `hasTemperature()`
- Helpers: `woreMask()`, `statusLabel()`
- Typed Builder return types

## 3. DeviceDataHandler

- **ATTLOG parser** updated for optional MaskFlag / Temperature / ConvTemperature
- Robust timestamp parsing (handles minor format variations)
- Temperature sanity range (20–50 °C)
- Card number placeholders (`0`, `65535`, `65536`) normalised to `null`
- Encoding normalisation for OPERLOG (GBK/legacy → UTF-8)
- All significant events also written via `DeviceLog`
- Debug dumps prefer `DeviceLog::dump()` when available
- ATTPHOTO always stores binary on disk (never base64 in DB)

## 4. PendingCommand model

- `markAsExecuted()` now accepts `array|string|null` (was incorrectly typed as string while cast is array)
- `scopeStuck` uses `whereColumn` instead of raw DB expression
- Explicit `enabled` cast
- Cleaner PHPDoc

## 5. CommandResponseHandler

- Consistent use of `DeviceLog`
- Safer card-number nulling
- try/catch around post-processing so a bad INFO/USERINFO body cannot break the response flow
- Logging of body length instead of full body for large USERINFO replies

## Recommended next steps

1. Run the new migration:
   ```bash
   php artisan migrate
   ```
2. If you already have production data, the unique indexes may fail if duplicates exist – clean them first:
   ```sql
   -- example: find duplicate punches
   SELECT device_serial, pin, timestamp, COUNT(*)
   FROM attendance_logs
   GROUP BY device_serial, pin, timestamp
   HAVING COUNT(*) > 1;
   ```
3. Consider a scheduled job that marks “stuck” commands for retry:
   ```php
   PendingCommand::stuck(10)->each->scheduleRetry(2);
   ```
4. Optional future work:
   - Store large `template_data` / photo content only on disk
   - Add `stamp` tracking per table (ATTLOGStamp, OPERLOGStamp…) for proper resume
   - Soft-delete policy review on high-volume tables

## Protocol reference (ATTLOG)

From 2024 protocol (p.37):

```
Pin HT Time HT Status HT Verify HT Workcode HT Reserved1 HT Reserved2
    HT MaskFlag HT Temperature HT ConvTemperature
```

ID-card variant also carries `IDNum` + `Type`.
