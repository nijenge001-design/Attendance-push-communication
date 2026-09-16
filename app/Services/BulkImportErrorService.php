<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendeeImportError;
use App\Models\User;
use App\Support\BulkResult;

class BulkImportErrorService
{
    /**
     * @param  list<string>  $ids
     */
    public function apply(User $user, array $ids, string $action, ?string $note): BulkResult
    {
        $result = new BulkResult('import-errors-'.$action);

        $errors = AttendeeImportError::query()
            ->whereIn('id', $ids)
            ->accessibleBy($user)
            ->get()
            ->keyBy('id');

        foreach ($ids as $index => $id) {
            $error = $errors->get($id);
            if (! $error) {
                $result->itemError($index, 'Import error not found or not accessible.', 'Not Found', ['id' => $id]);
                continue;
            }

            if ($error->status !== AttendeeImportError::STATUS_PENDING) {
                $result->skipped++;
                continue;
            }

            if ($action === 'resolve') {
                $error->markResolved($note);
            } else {
                $error->markIgnored($note);
            }

            $result->updated++;
            $result->ids[] = $error->id;
        }

        return $result;
    }
}
