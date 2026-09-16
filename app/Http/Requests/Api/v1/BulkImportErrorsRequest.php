<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\v1;

use Illuminate\Foundation\Http\FormRequest;

class BulkImportErrorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('api.bulk_max_items', 500);

        return [
            'data.attributes.action' => 'required|in:resolve,ignore',
            'data.attributes.ids' => "required|array|min:1|max:{$max}",
            'data.attributes.ids.*' => 'required|uuid|exists:attendee_import_errors,id',
            'data.attributes.adminNote' => 'nullable|string|max:2000',
        ];
    }
}
