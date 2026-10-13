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

    /**
     * The viewed paper is the single seed and is also excluded server-side
     * (ADR 0027); the reject below stays as defence in depth. A
     * malformed-but-200 body is an empty result, not a failure, and only
     * strict `paper-<digits>` ids map back — "paper-78-old" must not be
     * coerced onto paper 78.
     */
    public function for(AcademicPaper $paper, int $limit = 10): Collection
    {
        $this->unavailable = false;

        try {
            $response = (new AiService)->recommend(
                ["paper-{$paper->id}"],
                ["paper-{$paper->id}"],
                $limit
            );
            $results = $response['results'] ?? [];
        } catch (AiServiceUnavailableException|AiServiceAuthException) {
            $this->unavailable = true;

            return collect();
        }

        $ids = collect($results)
            ->map(fn ($result) => preg_match('/^paper-(\d+)$/', (string) ($result['id'] ?? ''), $match)
                ? (int) $match[1]
                : 0)
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
