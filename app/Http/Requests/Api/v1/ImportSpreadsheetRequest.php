<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\v1;

use Illuminate\Foundation\Http\FormRequest;

class ImportSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('api.import_max_kb', 5120);

        return [
            'file' => [
                'required',
                'file',
                "max:{$maxKb}",
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $ext = strtolower((string) $value->getClientOriginalExtension());
                    if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
                        $fail('The file must be an .xlsx or .csv spreadsheet.');
                    }
                },
            ],
            'mode' => 'sometimes|in:upsert,create-only',
            'dryRun' => 'sometimes|boolean',
            'dispatchJobs' => 'sometimes|boolean',
            'broadcast' => 'sometimes|boolean',
            'deviceSerial' => 'nullable|string|exists:devices,serial_number',
            'siteIds' => 'nullable',
            'deviceSerials' => 'nullable',
            'format' => 'sometimes|in:xlsx,csv',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['dryRun', 'dispatchJobs', 'broadcast'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([
                    $flag => filter_var($this->input($flag), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }
}
