<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\v1;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreAttendanceLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('api.bulk_max_items', 500);
        $deviceFromRoute = $this->route('device') !== null;

        return [
            'data.attributes.dispatchJobs' => 'sometimes|boolean',
            'data.attributes.broadcast' => 'sometimes|boolean',
            'data.attributes.items' => "required|array|min:1|max:{$max}",
            'data.attributes.items.*.pin' => 'required|string|max:32',
            'data.attributes.items.*.timestamp' => 'required|date',
            'data.attributes.items.*.status' => 'required|integer',
            'data.attributes.items.*.verifyMode' => 'required|integer',
            'data.attributes.items.*.deviceSerial' => $deviceFromRoute
                ? 'sometimes|string|exists:devices,serial_number'
                : 'required|string|exists:devices,serial_number',
            'data.attributes.items.*.workcode' => 'nullable|string|max:32',
            'data.attributes.items.*.type' => 'sometimes|integer',
            'data.attributes.items.*.maskFlag' => 'nullable|integer',
            'data.attributes.items.*.temperature' => 'nullable|numeric',
            'data.attributes.items.*.convTemperature' => 'nullable|numeric',
            'data.attributes.items.*.idNumber' => 'nullable|string|max:64',
        ];
    }
}
