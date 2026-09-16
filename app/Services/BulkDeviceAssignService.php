<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use App\Support\BulkResult;
use Illuminate\Support\Collection;
use Throwable;

class BulkDeviceAssignService
{
    /**
     * Assign many existing devices to one site.
     *
     * @param  list<string>  $serials
     */
    public function assignToSite(User $user, Site $site, array $serials): BulkResult
    {
        $items = array_map(
            fn (string $serial) => ['serialNumber' => $serial, 'siteId' => $site->id],
            array_values($serials),
        );

        return $this->assignItems($user, $items, $site);
    }

    /**
     * @param  list<array{serialNumber: string, siteId: string}>  $items
     */
    public function assignItems(User $user, array $items, ?Site $preloadedSite = null): BulkResult
    {
        $result = new BulkResult('devices-assign');

        $serials = array_values(array_unique(array_map(
            fn (array $item) => (string) $item['serialNumber'],
            $items,
        )));

        $devices = Device::query()
            ->where(function ($q) use ($serials) {
                $q->whereIn('serial_number', $serials)
                    ->orWhereIn('id', $serials);
            })
            ->get();

        $bySerial = $devices->keyBy(fn (Device $d) => strtoupper((string) $d->serial_number));
        $byId = $devices->keyBy('id');

        $siteIds = array_values(array_unique(array_map(
            fn (array $item) => (string) $item['siteId'],
            $items,
        )));

        /** @var Collection<string, Site> $sites */
        $sites = $preloadedSite
            ? collect([$preloadedSite->id => $preloadedSite])
            : Site::query()->whereIn('id', $siteIds)->get()->keyBy('id');

        foreach ($items as $index => $item) {
            $serial = (string) $item['serialNumber'];
            $siteId = (string) $item['siteId'];

            try {
                $site = $sites->get($siteId);
                if (! $site instanceof Site) {
                    $result->itemError($index, "Site [{$siteId}] was not found.", 'Not Found', ['siteId' => $siteId]);
                    continue;
                }

                if (! $site->is_active) {
                    $result->itemError($index, "Site [{$site->code}] is inactive.", 'Unprocessable Entity', ['siteId' => $siteId]);
                    continue;
                }

                if (! $user->isAdmin() && ! $user->hasAccessToSite($site->id)) {
                    $result->itemError($index, 'You do not have access to the target site.', 'Forbidden', ['siteId' => $siteId]);
                    continue;
                }

                $device = $bySerial->get(strtoupper($serial)) ?? $byId->get($serial);
                if (! $device instanceof Device) {
                    $result->itemError($index, "Device [{$serial}] was not found.", 'Not Found', ['serialNumber' => $serial]);
                    continue;
                }

                if ($device->site_id && ! $user->hasAccessToDevice($device)) {
                    $result->itemError($index, "You do not have access to device [{$device->serial_number}].", 'Forbidden', [
                        'serialNumber' => $device->serial_number,
                    ]);
                    continue;
                }

                if ($device->site_id === $site->id) {
                    $result->skipped++;
                    $result->ids[] = $device->id;
                    continue;
                }

                $device->site_id = $site->id;
                $device->save();

                $result->updated++;
                $result->ids[] = $device->id;
            } catch (Throwable $e) {
                $result->itemError($index, $e->getMessage(), 'Processing Error', ['serialNumber' => $serial]);
            }
        }

        return $result;
    }
}
