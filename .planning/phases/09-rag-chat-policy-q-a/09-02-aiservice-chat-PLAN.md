---
phase: 09-rag-chat-policy-q-a
plan: 02
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Services/AiService.php
  - app/Exceptions/AiServiceProviderException.php
  - tests/Feature/AiServiceChatTest.php
  - tests/fixtures/ai-sidecar/chat-stream.txt
autonomous: true
requirements: [CHAT-01]
must_haves:
  truths:
    - "AiService::chatStream() POSTs /chat/stream with {query, mode, corpus, top_k}, uses retries 0, and returns the streamed Response after throwUnlessOk (D-10/Δ7/Δ10)"
    - "SSE parsing reads `data: ` chunks via fgets on Response::resource() (NOT Http::toStream() — does not exist in Laravel 13); `event: error` throws AiServiceProviderException with the JSON message; `data: [DONE]` terminates (Δ11/Δ9)"
    - "Existing search/rebuildIndex/health behavior is unchanged — the full AiServiceTest suite stays green after the shared request() helper refactor (D-09)"
  artifacts:
    - path: tests/fixtures/ai-sidecar/chat-stream.txt
      provides: "Canonical SSE body fixture for Http::fake string-body streams"
      contains: "[DONE]"
  key_links:
    - from: app/Services/AiService.php
      to: app/Exceptions/AiServiceProviderException.php
      via: "chatStreamEvents decodes the `event: error` payload and throws the typed exception"
      pattern: "AiServiceProviderException"
---

<objective>
Extend the Laravel sidecar gateway `AiService` for chat: refactor the inline request builder in `send()` into a shared private `request()` helper (Δ6/D-09), add `chatStream()` returning the streamed `Response` (Δ7/D-10) with `retries: 0` (Δ10), add the typed `AiServiceProviderException` (Δ9/D-12), and add an SSE line parser `chatStreamEvents()` over `Response::resource()` (Δ8/Δ11). Ship `AiServiceChatTest` with the full fake stack — `Http::fake` string-body SSE, `Http::preventStrayRequests()`, fixture-based payload assertions — plus the `chat-stream.txt` fixture.

Purpose: CHAT-01's streaming path starts here — the widget (09-04) consumes `chatStream()` + `chatStreamEvents()`. The refactor is behavior-identical so the 11 existing AiServiceTest cases are the regression net.

Output: `AiService` with chat methods, the new exception, green `AiServiceChatTest` + unchanged `AiServiceTest` suite.

Commit discipline: each task is one focused commit.
</objective>

<execution_context>
@$HOME/.codex/get-shit-done/workflows/execute-plan.md
@$HOME/.codex/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-rag-chat-policy-q-a/09-RESEARCH.md
@.planning/phases/09-rag-chat-policy-q-a/09-CONTEXT.md
@.planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md
@.planning/phases/09-rag-chat-policy-q-a/09-VALIDATION.md
@docs/adr/0004-sidecar-chat-endpoint-contract.md
@app/Services/AiService.php
@tests/Feature/AiServiceTest.php
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan adds an outbound streamed-POST path to a loopback service.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 Token or query content leaks into logs | HIGH | All new code reuses `logFailure()` (endpoint + reason keys only — never token/query/body); no new log statements; `AiServiceProviderException` carries only the sidecar-supplied safe message. Enforced by Mockery `Log::shouldReceive` assertions in the existing style. |
| T-02 Provider/internal error details surface to the UI | HIGH | The only message that can cross the boundary is the JSON `message` field of the sidecar error event (safe generic text by sidecar contract, Δ1 of 09-01); no `repr`, no exception class, no stack trace. |
| T-03 Retry re-issues the POST and duplicates LLM generation | MED | `chatStream()` passes `retries: 0`; test asserts exactly one request is issued against a 500 fake. |
| T-04 Streamed error event appended to answer content | HIGH | `chatStreamEvents()` treats `event: error` as a terminal throw — the error data line never yields as content; test asserts the exception and zero content yield after the error line. |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: Δ6 — extract the shared request() helper (behavior-identical refactor)</name>
  <files>app/Services/AiService.php</files>
  <read_first>app/Services/AiService.php, tests/Feature/AiServiceTest.php, .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.4)</read_first>
  <action>
  - In `app/Services/AiService.php`, extract the request-builder chain inside `send()` into a new private method: `private function request(string $method, string $path, array $body, int $timeout, int $retries, bool $stream = false): \Illuminate\Http\Client\PendingRequest`.
  - The helper builds and returns the pending request: `Http::withHeaders(['X-Sidecar-Token' => config('services.ai_sidecar.token')])->baseUrl(config('services.ai_sidecar.base_url'))->connectTimeout(3)->timeout($timeout)->retry($retries, 250, throw: false)`, plus `->withOptions(['stream' => true])` when `$stream` is true.
  - `send()` dispatches through the helper (`POST` → `post($path, $body)`, else `get($path)`) and keeps its existing `try/catch (ConnectionException)` → `logFailure` + `AiServiceUnavailableException`, `throwUnlessOk()`, and `$response->json() ?? []` behavior byte-for-byte.
  - Do NOT change the signatures or behavior of `search()`, `rebuildIndex()`, `health()`, `throwUnlessOk()`, or `logFailure()`.
  </action>
  <verify>php artisan test --filter=AiServiceTest</verify>
  <acceptance_criteria>
  - `app/Services/AiService.php` contains `private function request(` with the `bool $stream = false` parameter
  - `search()`, `rebuildIndex()`, `health()` public signatures are unchanged
  - `php artisan test --filter=AiServiceTest` exits 0 (all 11 existing tests, including the retry-sequence and Mockery log-context tests)
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 2: Δ9 — AiServiceProviderException typed exception</name>
  <files>app/Exceptions/AiServiceProviderException.php</files>
  <read_first>app/Exceptions/AiServiceAuthException.php (verbatim clone template)</read_first>
  <action>
  - Create `app/Exceptions/AiServiceProviderException.php`: namespace `App\Exceptions`, class `AiServiceProviderException extends RuntimeException` — a verbatim copy of `AiServiceAuthException` (bare subclass, no added members) with the new class name.
  </action>
  <verify>php -l app/Exceptions/AiServiceProviderException.php</verify>
  <acceptance_criteria>
  - File exists with namespace `App\Exceptions` and `class AiServiceProviderException extends RuntimeException`
  - `php -l app/Exceptions/AiServiceProviderException.php` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: Δ7 + Δ10 — chatStream() streamed POST with retries 0</name>
  <files>app/Services/AiService.php</files>
  <read_first>app/Services/AiService.php (post-refactor state from Task 1), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.4 CHANGE bullets)</read_first>
  <action>
  - Add `public function chatStream(string $query, ?string $mode = 'citations', ?string $corpus = null, int $topK = 5): \Illuminate\Http\Client\Response`.
  - Build the body as `['query' => $query, 'mode' => $mode, 'top_k' => $topK]`; add `'corpus' => $corpus` to the body ONLY when `$corpus` is not null.
  - Dispatch via `$this->request('POST', '/chat/stream', $body, timeout: 120, retries: 0, stream: true)` inside a `try` that catches `ConnectionException` → `logFailure('/chat/stream', 'connection')` + throw `AiServiceUnavailableException` (same as `send()`).
  - Call `$this->throwUnlessOk($response, '/chat/stream')` FIRST (before touching the body), then return `$response`.
  - Add a `use` import for `Illuminate\Http\Client\Response` (it may already be imported for `throwUnlessOk`'s signature).
  </action>
  <verify>php -l app/Services/AiService.php</verify>
  <acceptance_criteria>
  - `chatStream` signature matches `chatStream(string $query, ?string $mode = 'citations', ?string $corpus = null, int $topK = 5): \Illuminate\Http\Client\Response`
  - The POST body omits the `corpus` key when null (asserted via `Http::assertSent` in Task 5 tests)
  - A 500 fake response on `/chat/stream` results in `AiServiceUnavailableException` AND exactly one request issued (no retry) — asserted in Task 5 tests
  - `php -l app/Services/AiService.php` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 4: Δ11 — SSE parser chatStreamEvents() over Response::resource()</name>
  <files>app/Services/AiService.php</files>
  <read_first>app/Services/AiService.php, .planning/phases/09-rag-chat-policy-q-a/09-RESEARCH.md (section 2.6 — resource() not toStream()), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.4 CHANGE bullets)</read_first>
  <action>
  - Add `public function chatStreamEvents(\Illuminate\Http\Client\Response $response): \Generator` — a generator that parses SSE lines via `fgets($response->resource())` in a `while (! feof($response->resource()))` loop.
  - Line semantics: lines starting with `data: ` → strip the prefix; if the payload is `[DONE]`, stop the generator; otherwise `yield` the payload. A line exactly `event: error` → read the next `data:` line, `json_decode` it, and `throw new AiServiceProviderException($decoded['message'] ?? 'The AI provider is temporarily unavailable.')`. Any other line is ignored (blank separators, comment lines).
  - The error path must NOT yield the error data as content; after the throw nothing further is yielded (trailing `[DONE]` after an error is never observed by the caller).
  </action>
  <verify>php -l app/Services/AiService.php</verify>
  <acceptance_criteria>
  - `app/Services/AiService.php` contains `public function chatStreamEvents(` returning `\Generator`, and reads via `fgets($response->resource())` (no `toStream` anywhere in the file)
  - The parser yields only `data:` payloads, terminates on `[DONE]`, and throws `AiServiceProviderException` on `event: error` (proven by Task 5 tests)
  - `php -l app/Services/AiService.php` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 5: AiServiceChatTest + chat-stream.txt SSE fixture</name>
  <files>tests/Feature/AiServiceChatTest.php, tests/fixtures/ai-sidecar/chat-stream.txt</files>
  <read_first>tests/Feature/AiServiceTest.php (fixture() helper, Http::fake/preventStrayRequests/assertSent patterns, Log::shouldReceive), tests/fixtures/ai-sidecar/search.json, .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.7 ANALOG B)</read_first>
  <action>
  - Create `tests/fixtures/ai-sidecar/chat-stream.txt` containing the SSE body: `data: CEIT \n\ndata: Library \n\ndata: [DONE]\n\n` (verbatim, no trailing extra events).
  - Create `tests/Feature/AiServiceChatTest.php` (PHPUnit-attribute style, extends `Tests\TestCase`) with the `fixture()` helper copied from `AiServiceTest`. Every test sets `config(['services.ai_sidecar.token' => 'test-token'])` and calls `Http::preventStrayRequests()`; no test contains a real key.
  - Tests:
    1. `it_posts_chat_stream_with_locked_payload` — `Http::fake(['http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream'])])`; call `(new AiService)->chatStream('what are the borrowing rules?', 'citations', 'policy', 3)`; `Http::assertSent` asserting URL contains `/chat/stream`, header `X-Sidecar-Token` equals `test-token`, `$request['query']`, `$request['mode'] === 'citations'`, `$request['corpus'] === 'policy'`, `$request['top_k'] === 3`.
    2. `it_omits_corpus_when_null` — call `chatStream('x')`; `Http::assertSent` asserting `array_key_exists('corpus', $request->data())` is false.
    3. `it_reads_sse_chunks_in_order_and_stops_at_done` — fake the fixture; `$response = (new AiService)->chatStream('x'); $chunks = iterator_to_array((new AiService)->chatStreamEvents($response));` assert chunks equal `['CEIT ', 'Library ']` (no `[DONE]` in chunks).
    4. `it_throws_provider_exception_on_error_event` — fake body `"event: error\ndata: {\"code\": \"provider_error\", \"message\": \"The AI provider is temporarily unavailable. Please try again.\"}\n\ndata: [DONE]\n\n"`; `expectException(AiServiceProviderException::class)` and assert the exception message equals the safe generic text.
    5. `it_throws_auth_exception_on_401` and `it_throws_unavailable_on_connection_failure` — mirror the AiServiceTest 401/ConnectionException cases against `/chat/stream`.
    6. `it_does_not_retry_chat_stream` — fake `/chat/stream` with `Http::response([], 500)`; expect `AiServiceUnavailableException`; assert `Http::assertSentCount(1)`.
  </action>
  <verify>php artisan test --filter=AiServiceChatTest</verify>
  <acceptance_criteria>
  - `tests/fixtures/ai-sidecar/chat-stream.txt` exists and its body contains exactly the three data events ending in `data: [DONE]\n\n`
  - All six tests exist with the names above; `php artisan test --filter=AiServiceChatTest` exits 0 with 6 passing
  - The test file contains `preventStrayRequests` and `config(['services.ai_sidecar.token' => 'test-token'])`; it contains no real API key value
  - `php artisan test --filter=AiServiceTest` still exits 0 (regression net)
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `php artisan test --filter=AiServiceChatTest` — 6 passing
- [ ] `php artisan test --filter=AiServiceTest` — all 11 existing tests still green (D-09 refactor regression net)
- [ ] `php artisan test` — full Laravel suite stays green (523 passed / 3 skipped baseline)
- [ ] `grep -rn "toStream" app/Services/AiService.php` — no matches
</verification>

<success_criteria>
- All 5 tasks complete
- Laravel has a streamed chat client with typed provider failures and a testable SSE parser; no other class will do sidecar chat HTTP directly
</success_criteria>

<output>
After completion, create `.planning/phases/09-rag-chat-policy-q-a/09-02-SUMMARY.md`
</output>
