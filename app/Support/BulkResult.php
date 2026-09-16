<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Outcome of a bulk write. Controllers map this to JSON:API + HTTP status.
 *
 * 200 — everything succeeded
 * 207 — mixed success / failure (JSON:API errors[] populated)
 * 202 — accepted for async processing
 * 422 — nothing succeeded
 */
final class BulkResult
{
    /**
     * @param  list<array{status: string, title: string, detail: string, source?: array<string, string>, meta?: array<string, mixed>}>  $errors
     * @param  list<string>  $ids
     */
    public function __construct(
        public string $operation,
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public bool $async = false,
        public array $errors = [],
        public array $ids = [],
    ) {}

    public function accepted(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }

    public function httpStatus(): int
    {
        if ($this->async) {
            return 202;
        }

        if ($this->failed > 0 && $this->accepted() === 0) {
            return 422;
        }

        if ($this->failed > 0) {
            return 207;
        }

        return 200;
    }

    public function itemError(int $index, string $detail, string $title = 'Validation Error', array $meta = []): void
    {
        $this->failed++;
        $error = [
            'status' => '422',
            'title' => $title,
            'detail' => $detail,
            'source' => ['pointer' => "/data/attributes/items/{$index}"],
        ];
        if ($meta !== []) {
            $error['meta'] = $meta;
        }
        $this->errors[] = $error;
    }

    public function merge(self $other, int $indexOffset = 0): void
    {
        $this->created += $other->created;
        $this->updated += $other->updated;
        $this->skipped += $other->skipped;
        $this->failed += $other->failed;
        $this->ids = array_values(array_unique([...$this->ids, ...$other->ids]));

        foreach ($other->errors as $error) {
            if ($indexOffset > 0 && isset($error['source']['pointer'])) {
                if (preg_match('#/data/attributes/items/(\d+)$#', $error['source']['pointer'], $m)) {
                    $error['source']['pointer'] = '/data/attributes/items/'.((int) $m[1] + $indexOffset);
                }
            }
            $this->errors[] = $error;
        }
    }
}
