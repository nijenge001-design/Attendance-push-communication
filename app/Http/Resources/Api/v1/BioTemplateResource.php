<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class BioTemplateResource extends JsonApiResource
{
    public function type(): string
    {
        return 'bio-templates';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'deviceSerial' => $this->device_serial,
            'pin' => $this->pin,
            'type' => $this->type,
            'typeLabel' => $this->typeLabel(),
            'no' => $this->no,
            'index' => $this->index,
            'valid' => $this->valid,
            'isValid' => $this->isValid(),
            'duress' => $this->duress,
            'isDuress' => $this->isDuress(),
            'majorVer' => $this->major_ver,
            'minorVer' => $this->minor_ver,
            'format' => $this->format,
            'hasTemplateData' => ! empty($this->template_data),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'device' => DeviceResource::class,
            'attendee' => AttendeeResource::class,
        ];
    }
}
