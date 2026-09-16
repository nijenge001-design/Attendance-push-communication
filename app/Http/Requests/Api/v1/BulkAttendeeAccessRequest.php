<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\v1;

use Illuminate\Foundation\Http\FormRequest;

class BulkAttendeeAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('api.bulk_max_items', 500);

        return [
            'data.attributes.action' => 'required|in:grant,revoke',
            'data.attributes.pins' => "required|array|min:1|max:{$max}",
            'data.attributes.pins.*' => 'required|string|exists:attendees,pin',
            'data.attributes.deviceSerials' => 'required|array|min:1',
            'data.attributes.deviceSerials.*' => 'required|string|exists:devices,serial_number',
            'data.attributes.privilege' => 'nullable|integer',
            'data.attributes.groupId' => 'nullable|integer',
            'data.attributes.timezone' => 'nullable|string|max:64',
        ];
    }
}
