<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AttendeeResource extends JsonApiResource
{
    public function type(): string
    {
        return 'attendees';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'pin' => $this->pin,
            'name' => $this->name,
            'privilege' => $this->privilege,
            'cardNumber' => $this->card_number,
            'viceCard' => $this->vice_card,
            'groupId' => $this->group_id,
            'timezone' => $this->timezone,
            'verificationMode' => $this->verification_mode,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'devices' => DeviceResource::class,
            'sites' => SiteResource::class,
            'attendanceLogs' => AttendanceLogResource::class,
            'bioTemplates' => BioTemplateResource::class,
            'photos' => PhotoResource::class,
        ];
    }
}
