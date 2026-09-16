<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\v1;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpsertAttendeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('api.bulk_max_items', 500);

        return [
            'data.attributes.mode' => 'sometimes|in:upsert,create-only',
            'data.attributes.items' => "required|array|min:1|max:{$max}",
            'data.attributes.items.*.pin' => 'required|string|max:32|distinct',
            'data.attributes.items.*.name' => 'nullable|string|max:255',
            'data.attributes.items.*.privilege' => 'sometimes|integer',
            'data.attributes.items.*.password' => 'nullable|string|max:64',
            'data.attributes.items.*.cardNumber' => 'nullable|string|max:64|distinct',
            'data.attributes.items.*.viceCard' => 'nullable|string|max:64',
            'data.attributes.items.*.groupId' => 'sometimes|integer',
            'data.attributes.items.*.timezone' => 'nullable|string|max:64',
            'data.attributes.items.*.verificationMode' => 'sometimes|integer',
            'data.attributes.items.*.siteIds' => 'nullable|array',
            'data.attributes.items.*.siteIds.*' => 'uuid|exists:sites,id',
            'data.attributes.items.*.deviceSerials' => 'nullable|array',
            'data.attributes.items.*.deviceSerials.*' => 'string|exists:devices,serial_number',
        ];
    }
}
