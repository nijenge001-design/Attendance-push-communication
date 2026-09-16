<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AttendeeImportErrorResource extends JsonApiResource
{
    public function type(): string
    {
        return 'attendee-import-errors';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'deviceSerial' => $this->device_serial,
            'pin' => $this->pin,
            'name' => $this->name,
            'cardNumber' => $this->card_number,
            'viceCard' => $this->vice_card,
            'privilege' => $this->privilege,
            'groupId' => $this->group_id,
            'timezone' => $this->timezone,
            'verificationMode' => $this->verification_mode,
            'errorCode' => $this->error_code,
            'errorMessage' => $this->error_message,
            'rawData' => $this->raw_data,
            'status' => $this->status,
            'adminNote' => $this->admin_note,
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
