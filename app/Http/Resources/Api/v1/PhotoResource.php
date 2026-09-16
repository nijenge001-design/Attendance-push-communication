<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class PhotoResource extends JsonApiResource
{
    public function type(): string
    {
        return 'photos';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'deviceSerial' => $this->device_serial,
            'pin' => $this->pin,
            'filename' => $this->filename,
            'type' => $this->type,
            'typeLabel' => $this->typeLabel(),
            'url' => $this->url,
            'hasContent' => $this->hasContent(),
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
