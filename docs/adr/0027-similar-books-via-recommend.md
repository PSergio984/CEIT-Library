# Similar books via the sidecar /recommend endpoint

_Supersedes the mechanism part of ADR 0011; its fail-closed and list-shape decisions still stand. (CEIT-Library #59, ceit-ai-sidecar #10.)_

Similar books now calls the sidecar's `POST /recommend` (ceit-ai-sidecar ADR 0015). The viewed paper is the single seed — `seed_ids: ["paper-N"]` — and is also sent in `exclude_ids`, so the seed can never occupy a result slot. The sidecar ranks the catalog corpus by cosine to the seed's stored embedding and orders by `(-score, id)`: the recommendation set is deterministic across renders with no query string involved. Unknown seed ids resolve to `seeds_used: 0` and an empty list, not an error.

**The mechanism is still a Laravel service: `SimilarPapersService::for(AcademicPaper $paper, int $limit = 10)`.** It calls `AiService::recommend()` verbatim, maps `paper-N` ids back to `AcademicPaper` models in sidecar rank order, and returns the same collection shape — cards still hydrate through the ADR 0010 path. The client-side self-exclusion drop stays as defence in depth, but it is now belt-and-suspenders: exclusion is sent to the sidecar, so the result pool no longer wastes a rank slot on the seed.

Unchanged from ADR 0011: the `unavailable` flag — sidecar down or auth failure (`AiServiceUnavailableException`/`AiServiceAuthException`) → empty result + flag → "Recommendations unavailable right now" — and fail-closed with no SQL or LLM fallback (the list is never an LLM answer). Empty retrieval or no resolvable seed → empty collection → "No similar books found".

_Considered (rejected):_ keeping title-as-query through `/search` — it re-derives similarity from a bibliographic text query, spends a rank slot on the seed, and can't express "same as this paper" directly; `/recommend` ranks on the stored embedding and removes the seed server-side.
