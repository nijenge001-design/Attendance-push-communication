<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

trait PaginatesJsonApi
{
    /**
     * Offset pagination by default (page[number] / page[size]).
     * Cursor pagination when page[cursor] is present — use this for
     * attendance logs and other append-only tables.
     */
    protected function paginateQuery(Builder $query, Request $request, int $defaultSize = 15): LengthAwarePaginator|CursorPaginator
    {
        $size = $this->pageSize($request, $defaultSize);

        if ($request->filled('page.cursor')) {
            return $query->cursorPaginate($size, ['*'], 'cursor', $request->input('page.cursor'))
                ->withQueryString();
        }

        return $query->paginate($size)->withQueryString();
    }

    protected function pageSize(Request $request, int $default): int
    {
        $requested = (int) $request->input('page.size', $default);
        $max = (int) config('api.page_size_max', 100);

        if ($requested < 1) {
            return $default;
        }

        return min($requested, $max);
    }
}
