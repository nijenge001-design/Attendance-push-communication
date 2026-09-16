<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AttendeeAccessGranted;
use App\Events\AttendeeAccessRevoked;
use App\Events\AttendeeCreated;
use App\Events\AttendeeUpdated;
use App\Models\Attendee;
use App\Models\User;
use App\Support\BulkResult;
use Illuminate\Database\QueryException;
use Throwable;

class BulkAttendeeService
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function upsert(User $user, array $items, string $mode = 'upsert'): BulkResult
    {
        $result = new BulkResult('attendees-'.$mode);

        $pins = array_column($items, 'pin');
        $existing = Attendee::query()
            ->whereIn('pin', $pins)
            ->get()
            ->keyBy('pin');

        foreach ($items as $index => $item) {
            $pin = (string) $item['pin'];

            try {
                $attendee = $existing->get($pin);

                if ($attendee) {
                    if ($mode === 'create-only') {
                        $result->skipped++;
                        continue;
                    }

                    $attendee->update($this->columnsFromItem($item, includePin: false));
                    $this->attachSitesAndDevices($user, $attendee, $item);
                    $attendee = $attendee->fresh();
                    event(new AttendeeUpdated($attendee));
                    $result->updated++;
                    $result->ids[] = $attendee->id;
                    continue;
                }

                $attendee = Attendee::create($this->columnsFromItem($item, includePin: true));
                $deviceSerials = $this->attachSitesAndDevices($user, $attendee, $item);
                event(new AttendeeCreated($attendee, $deviceSerials));
                $existing->put($pin, $attendee);
                $result->created++;
                $result->ids[] = $attendee->id;
            } catch (QueryException $e) {
                $result->itemError($index, $this->uniqueMessage($e), 'Conflict', ['pin' => $pin]);
            } catch (Throwable $e) {
                $result->itemError($index, $e->getMessage(), 'Processing Error', ['pin' => $pin]);
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $pins
     * @param  list<string>  $deviceSerials
     */
    public function syncAccess(
        User $user,
        array $pins,
        array $deviceSerials,
        string $action,
        array $overrides = [],
    ): BulkResult {
        $result = new BulkResult('attendees-access-'.$action);

        $attendees = Attendee::query()
            ->whereIn('pin', $pins)
            ->accessibleBy($user)
            ->get()
            ->keyBy('pin');

        $allowedSerials = [];
        foreach ($deviceSerials as $serial) {
            $device = \App\Models\Device::query()->where('serial_number', $serial)->first();
            if ($device && $user->hasAccessToDevice($device)) {
                $allowedSerials[] = $serial;
            }
        }

        foreach ($pins as $index => $pin) {
            $attendee = $attendees->get($pin);
            if (! $attendee) {
                $result->itemError($index, 'Attendee not found or not accessible.', 'Not Found', ['pin' => $pin]);
                continue;
            }

            try {
                foreach ($allowedSerials as $serial) {
                    if ($action === 'grant') {
                        $attendee->grantAccessToDevice($serial, $overrides);
                        event(new AttendeeAccessGranted($attendee, $serial));
                    } else {
                        $attendee->revokeAccessFromDevice($serial);
                        event(new AttendeeAccessRevoked($attendee, $serial));
                    }
                }
                $result->updated++;
                $result->ids[] = $attendee->id;
            } catch (Throwable $e) {
                $result->itemError($index, $e->getMessage(), 'Processing Error', ['pin' => $pin]);
            }
        }

        $denied = array_diff($deviceSerials, $allowedSerials);
        foreach ($denied as $serial) {
            $result->errors[] = [
                'status' => '403',
                'title' => 'Forbidden',
                'detail' => 'No access to device.',
                'meta' => ['deviceSerial' => $serial],
            ];
            $result->failed++;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function attachSitesAndDevices(User $user, Attendee $attendee, array $item): array
    {
        foreach ($item['siteIds'] ?? [] as $siteId) {
            if ($user->hasAccessToSite($siteId)) {
                $attendee->sites()->syncWithoutDetaching([
                    $siteId => ['is_active' => true, 'granted_at' => now()],
                ]);
            }
        }

        $serials = array_values(array_filter($item['deviceSerials'] ?? []));
        foreach ($serials as $serial) {
            $attendee->grantAccessToDevice($serial);
        }

        return $serials;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function columnsFromItem(array $item, bool $includePin): array
    {
        $map = [
            'name' => 'name',
            'privilege' => 'privilege',
            'password' => 'password',
            'cardNumber' => 'card_number',
            'viceCard' => 'vice_card',
            'groupId' => 'group_id',
            'timezone' => 'timezone',
            'verificationMode' => 'verification_mode',
        ];

        $columns = [];
        if ($includePin) {
            $columns['pin'] = $item['pin'];
        }

        foreach ($map as $camel => $column) {
            if (array_key_exists($camel, $item)) {
                $columns[$column] = $item[$camel];
            }
        }

        return $columns;
    }

    private function uniqueMessage(QueryException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'card_number')) {
            return 'Card number already exists.';
        }
        if (str_contains($message, 'attendees_pin')) {
            return 'PIN already exists.';
        }

        return 'Database constraint failed.';
    }
}
