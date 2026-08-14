<?php

namespace App\Services;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceUnavailableException;
use App\Models\AcademicPaper;
use Illuminate\Support\Collection;

class SimilarPapersService
{
    /**
     * Fail-closed flag: true only when the sidecar call itself failed
     * (unavailable or auth), so callers can render an error state instead
     * of a misleading empty list.
     */
    public bool $unavailable = false;

    public function for(AcademicPaper $paper, int $limit = 10): Collection
    {
        $this->unavailable = false;

        try {
            $results = (new AiService)->search($paper->title, [], 'catalog', $limit)['results'];
        } catch (AiServiceUnavailableException|AiServiceAuthException) {
            $this->unavailable = true;

            return collect();
        }

        $ids = collect($results)
            ->map(fn ($result) => (int) str_replace('paper-', '', $result['id']))
            ->filter()
            ->reject(fn ($id) => $id === $paper->id)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $papers = AcademicPaper::with(['authors:id,name'])
            ->findMany($ids->all());

        $byId = $papers->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $byId->get($id))
            ->filter()
            ->values();
    }
}
