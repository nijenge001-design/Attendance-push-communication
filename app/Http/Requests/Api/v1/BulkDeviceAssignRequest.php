<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\v1;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeviceAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('api.bulk_max_items', 500);
        $nestedSite = $this->route('site') !== null;

        $rules = [
            'data.attributes.serialNumbers' => ($nestedSite ? 'required' : 'required_without:data.attributes.items')."|array|min:1|max:{$max}",
            'data.attributes.serialNumbers.*' => 'required|string|max:64',
        ];

        if (! $nestedSite) {
            $rules['data.attributes.siteId'] = 'required_without:data.attributes.items|uuid|exists:sites,id';
            $rules['data.attributes.items'] = "required_without:data.attributes.serialNumbers|array|min:1|max:{$max}";
            $rules['data.attributes.items.*.serialNumber'] = 'required_with:data.attributes.items|string|max:64';
            $rules['data.attributes.items.*.siteId'] = 'required_with:data.attributes.items|uuid|exists:sites,id';
        }

        return $rules;
    }
}
