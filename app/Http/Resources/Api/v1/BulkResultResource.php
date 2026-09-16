<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\v1;

use App\Support\BulkResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BulkResult
 */
class BulkResultResource extends JsonResource
{
    public static $wrap = 'data';

    public function toArray(Request $request): array
    {
        /** @var BulkResult $result */
        $result = $this->resource;

        return [
            'type' => 'bulk-results',
            'id' => $result->operation,
            'attributes' => [
                'accepted' => $result->accepted(),
                'created' => $result->created,
                'updated' => $result->updated,
                'skipped' => $result->skipped,
                'failed' => $result->failed,
                'async' => $result->async,
                'ids' => $result->ids,
            ],
        ];
    }

    public function with(Request $request): array
    {
        /** @var BulkResult $result */
        $result = $this->resource;

        return $result->errors === []
            ? []
            : ['errors' => $result->errors];
    }

    public function withResponse(Request $request, $response): void
    {
        $response->header('Content-Type', 'application/vnd.api+json');
    }
}
