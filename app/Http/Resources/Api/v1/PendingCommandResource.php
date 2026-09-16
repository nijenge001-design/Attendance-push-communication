<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class PendingCommandResource extends JsonApiResource
{
    public function type(): string
    {
        return 'pending-commands';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'deviceSerial' => $this->device_serial,
            'commandId' => $this->command_id,
            'commandText' => $this->command_text,
            'executed' => $this->executed,
            'result' => $this->result,
            'sentAt' => $this->sent_at?->toIso8601String(),
            'executedAt' => $this->executed_at?->toIso8601String(),
            'scheduledAt' => $this->scheduled_at?->toIso8601String(),
            'recurrence' => $this->recurrence,
            'nextRunAt' => $this->next_run_at?->toIso8601String(),
            'isRecurring' => $this->is_recurring,
            'enabled' => $this->enabled,
            'enabledAt' => $this->enabled_at?->toIso8601String(),
            'returnCode' => $this->return_code,
            'retryCount' => $this->retry_count,
            'maxRetries' => $this->max_retries,
            'lastRetryAt' => $this->last_retry_at?->toIso8601String(),
            'availableAt' => $this->available_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'device' => DeviceResource::class,
        ];
    }
}
