<?php

namespace App\Services;

use App\Models\Inventory;
use Illuminate\Support\Carbon;

class AvailabilityService
{
    /**
     * Hydrate copy availability for a set of paper ids with one grouped
     * query. Returns [id => ['available' => int, 'total' => int, 'checked_at' => Carbon]].
     * `available` counts inventory rows with status "Available" only;
     * `total` counts all inventory rows for the paper. Papers with zero
     * inventory rows are absent from the result.
     */
    public function forPapers(array $ids): array
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        $checkedAt = now();

        $rows = Inventory::whereIn('academic_paper_id', $ids)
            ->selectRaw("academic_paper_id, COUNT(*) AS total, SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available")
            ->groupBy('academic_paper_id')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [
            (int) $row->academic_paper_id => [
                'available' => (int) $row->available,
                'total' => (int) $row->total,
                'checked_at' => $checkedAt,
            ],
        ])->all();
    }
}
