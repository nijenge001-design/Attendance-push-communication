<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class DeviceResource extends JsonApiResource
{
    public function type(): string
    {
        return 'devices';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'serialNumber' => $this->serial_number,
            'deviceName' => $this->device_name,
            'status' => $this->status,
            'approvedAt' => $this->approved_at?->toIso8601String(),
            'rejectionReason' => $this->rejection_reason,
            'ip' => $this->ip,
            'macAddress' => $this->mac_address,
            'firmwareVersion' => $this->firmware_version,
            'pushVersion' => $this->push_version,
            'platform' => $this->platform,
            'language' => $this->language,
            'userCount' => $this->user_count,
            'fpCount' => $this->fp_count,
            'faceCount' => $this->face_count,
            'attlogCount' => $this->attlog_count,
//            'fingerFunOn' => $this->finger_fun_on,
//            'faceFunOn' => $this->face_fun_on,
//            'photoFunOn' => $this->photo_fun_on,
//            'lastSeenAt' => $this->last_seen_at?->toIso8601String(),
//            'lastHeartbeatAt' => $this->last_heartbeat_at?->toIso8601String(),
            'lastHeartbeatSource' => $this->last_heartbeat_source,
            'siteId' => $this->site_id,
//            'capabilities' => $this->capabilities,
//            'isOnline' => $this->isOnline(),
//            'createdAt' => $this->created_at?->toIso8601String(),
//            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'site' => SiteResource::class,
            'attendees' => AttendeeResource::class,
            'attendanceLogs' => AttendanceLogResource::class,
            'bioTemplates' => BioTemplateResource::class,
            'photos' => PhotoResource::class,
            'pendingCommands' => PendingCommandResource::class,
        ];
    }
}
