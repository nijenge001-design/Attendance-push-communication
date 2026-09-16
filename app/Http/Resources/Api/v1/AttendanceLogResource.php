<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AttendanceLogResource extends JsonApiResource
{
    public function type(): string
    {
        return 'attendance-logs';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'pin' => $this->pin,
            'deviceSerial' => $this->device_serial,
            'timestamp' => $this->timestamp?->toIso8601String(),
            'status' => $this->status,
            'statusLabel' => $this->statusLabel(),
            'verifyMode' => $this->verify_mode,
            'workcode' => $this->workcode,
            'reserved1' => $this->reserved1,
            'reserved2' => $this->reserved2,
            'idNumber' => $this->id_number,
            'type' => $this->type,
            'maskFlag' => $this->mask_flag,
            'woreMask' => $this->woreMask(),
            'temperature' => $this->temperature,
            'convTemperature' => $this->conv_temperature,
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
