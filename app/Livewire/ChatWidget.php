<?php

namespace App\Livewire;

use App\Exceptions\AiServiceException;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiService;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ChatWidget extends Component
{
    public bool $open = false;

    /** @var 'list'|'chat' */
    public string $view = 'list';

    public ?int $activeConversationId = null;

    /** @var array<int, Conversation> */
    public array $conversations = [];

    /** @var array<int, array{role: string, content: string, citations?: array|null, failed?: bool, error?: array|null, rating?: string|null, result_ids?: array<int, mixed>|null}> */
    public array $messages = [];

    public string $draft = '';

    public bool $streaming = false;

    public function mount(): void
    {
        if (auth()->check()) {
            $this->refreshConversations();
        }
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->refreshConversations();
            $this->view = 'list';
        }
    }

    public function openConversation(int $id): void
    {
        $conversation = $this->ownedConversation($id);

        if (! $conversation) {
            return;
        }

        $this->messages = $conversation->messages
            ->map(fn (Message $message) => $message->role === 'user'
                ? $this->userBubble($message->content)
                : $this->assistantBubble($message->content, $message->citations))
            ->all();

        $this->activeConversationId = $conversation->id;
        $this->view = 'chat';
    }

    public function newConversation(): void
    {
        $this->view = 'chat';
        $this->activeConversationId = null;
        $this->messages = [];
        $this->draft = '';
    }

    public function send(): void
    {
        $question = trim($this->draft);
        if ($question === '' || $this->streaming) {
            return;
        }

        $this->messages[] = $this->userBubble($question);
        $this->draft = '';

        $this->streamQuestion($question);
    }

    public function retry(): void
    {
        if ($this->streaming) {
            return;
        }

        $lastUser = null;
        $lastUserIdx = null;
        foreach (array_reverse($this->messages, true) as $idx => $message) {
            if ($message['role'] === 'user') {
                $lastUser = $message['content'];
                $lastUserIdx = $idx;
                break;
            }
        }

        if ($lastUser === null) {
            return;
        }

        // Drop a trailing failed assistant bubble so the turn is replaced,
        // not duplicated.
        $last = array_key_last($this->messages);
        if ($last !== null && $last > $lastUserIdx && ! empty($this->messages[$last]['failed'])) {
            unset($this->messages[$last]);
        }

        // Re-index so the next bubble lands at a predictable key.
        $this->messages = array_values($this->messages);

        // The user row from the failed turn is already persisted — retry
        // only re-streams the assistant answer (D-29, no duplicates).
        $this->streamQuestion($lastUser, persistUser: false);
    }

    private function streamQuestion(string $question, bool $persistUser = true): void
    {
        // W-4: `activeConversationId` is client-hydratable — re-verify
        // ownership before persisting, or fall back to a fresh conversation.
        if ($this->activeConversationId !== null && $this->ownedConversation($this->activeConversationId) === null) {
            $this->activeConversationId = null;
            // Keep the current turn's user bubble; drop stale bubbles from
            // the display so they cannot be mistaken for this conversation.
            $this->messages = [$this->userBubble($question)];
        }

        if ($this->activeConversationId === null) {
            $conversation = Conversation::create([
                'user_id' => auth()->id(),
                'title' => Conversation::makeTitle($question),
            ]);
            $this->activeConversationId = $conversation->id;
            $this->refreshConversations();
        }

        if ($persistUser) {
            Message::create([
                'conversation_id' => $this->activeConversationId,
                'role' => 'user',
                'content' => $question,
            ]);
        }

        $this->streaming = true;

        $startedAt = microtime(true);
        $accumulated = '';
        $usage = null;
        $citations = $this->companionCitations($question);

        try {
            $svc = new AiService;
            $response = $svc->chatStream($question, 'citations', null, 5);

            // Typing dots stream first — the persistent activity slot (W-3)
            // needs the indicator inside the live stream, not in a
            // conditionally-rendered element; it masks the first-call
            // decision latency on the agentic path (A-4).
            $this->stream(
                '<span class="inline-flex gap-1 py-1"><span class="w-1.5 h-1.5 rounded-full bg-base-content/40 animate-bounce"></span><span class="w-1.5 h-1.5 rounded-full bg-base-content/40 animate-bounce [animation-delay:150ms]"></span><span class="w-1.5 h-1.5 rounded-full bg-base-content/40 animate-bounce [animation-delay:300ms]"></span></span>',
                false,
                'activity'
            );

            foreach ($svc->chatStreamFrames($response) as $frame) {
                if ($frame['type'] === 'activity') {
                    // One compact spinner+copy line per loop step (UI-SPEC
                    // Agentic Loop Activity Lines) — frame payload is the
                    // sidecar's static copy table, never raw tool JSON;
                    // e() escapes at the render boundary regardless (WR-3).
                    $this->stream(
                        '<div class="flex items-center gap-1.5 py-0.5"><span class="loading loading-spinner loading-xs text-primary"></span><span>'.e($frame['payload']['text'] ?? '').'</span></div>',
                        false,
                        'activity'
                    );

                    continue;
                }

                if ($frame['type'] === 'citations') {
                    // ADR 0006 shape-checked frame payload wins over the
                    // companion fallback; malformed/absent frames keep the
                    // companionCitations() result for both render and
                    // persistence (T-11-19).
                    if ($this->validCitationsPayload($frame['payload'])) {
                        $citations = $frame['payload'];
                    }

                    continue;
                }

                if ($frame['type'] === 'usage') {
                    $usage = is_array($frame['payload']) ? $frame['payload'] : null;

                    continue;
                }

                $accumulated .= $frame['payload'];
                $this->stream($frame['payload'], false, 'ans');
            }

            // The assistant row lands only once the answer is complete —
            // during streaming the persistent slot is the bubble, so no
            // empty row ever coexists with it.
            $this->messages[] = $this->assistantBubble(
                $accumulated,
                $citations,
                resultIds: collect($citations)->pluck('id')->values()->all(),
            );

            Message::create([
                'conversation_id' => $this->activeConversationId,
                'role' => 'assistant',
                'content' => $accumulated,
                'citations' => $citations,
            ]);

            $svc->logChatCost($question, $accumulated, $startedAt, $this->activeConversationId, $usage);

            $this->refreshConversations();
        } catch (AiServiceException $e) {
            $this->messages[] = $this->assistantBubble(
                $accumulated,
                null,
                failed: true,
                error: ['code' => $e->errorCode(), 'message' => $e->getMessage()],
            );
        } finally {
            $this->streaming = false;
        }
    }

    /**
     * Auth-scoped conversation fetch (D-15): only the current user's own
     * conversations are ever resolved, on both the read and write paths.
     */
    private function ownedConversation(int $id): ?Conversation
    {
        return Conversation::where('user_id', auth()->id())->whereKey($id)->first();
    }

    /**
     * Display shape for a user message. Every bubble carries the same keys
     * so the Livewire state stays uniform.
     */
    private function userBubble(string $content): array
    {
        return ['role' => 'user', 'content' => $content, 'citations' => null, 'failed' => false, 'error' => null, 'rating' => null, 'result_ids' => []];
    }

    /**
     * Display shape for an assistant message. `result_ids` carries the
     * retrieved document ids the answer was grounded on (for /feedback).
     */
    private function assistantBubble(string $content, ?array $citations = null, bool $failed = false, ?array $error = null, array $resultIds = []): array
    {
        return ['role' => 'assistant', 'content' => $content, 'citations' => $citations, 'failed' => $failed, 'error' => $error, 'rating' => null, 'result_ids' => $resultIds];
    }

    /**
     * Thumbs up/down on an assistant answer, forwarded to the sidecar's
     * /feedback endpoint (query + answer + retrieved doc ids). Best-effort:
     * a sidecar failure never breaks the chat — the bubble still reflects
     * the user's rating, and the miss is logged by AiService.
     */
    public function rate(int $index, string $rating): void
    {
        if ($rating !== 'up' && $rating !== 'down') {
            return;
        }

        if ($this->streaming || ! isset($this->messages[$index]) || $this->messages[$index]['role'] !== 'assistant') {
            return;
        }

        $message = $this->messages[$index];

        if (! empty($message['failed'])) {
            return;
        }

        $question = null;
        for ($i = $index - 1; $i >= 0; $i--) {
            if (($this->messages[$i]['role'] ?? null) === 'user') {
                $question = $this->messages[$i]['content'];
                break;
            }
        }

        if ($question === null) {
            return;
        }

        $resultIds = $message['result_ids'] ?? [];
        if ($resultIds === [] && ! empty($message['citations'])) {
            $resultIds = collect($message['citations'])->pluck('id')->values()->all();
        }

        (new AiService)->feedback($question, $rating, $message['content'], $resultIds);

        $this->messages[$index]['rating'] = $rating;
    }

    /**
     * ADR 0006 shape gate for the agentic citations frame payload: a list of
     * entries each carrying the six contract keys. `array_key_exists` (not
     * `isset`) so nullable keys (url, catalog_code) still validate. Malformed
     * payloads are rejected so the caller keeps the companionCitations()
     * fallback for render + persistence (T-11-19).
     */
    private function validCitationsPayload(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        foreach ($payload as $entry) {
            if (! is_array($entry)) {
                return false;
            }

            foreach (AiService::CITATION_KEYS as $key) {
                if (! array_key_exists($key, $entry)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Companion /search bound to the chat call's retrieval parameters —
     * same query, corpus null (both), and top_k 5 — so the citation rows
     * mirror the exact numbered set the model worked from (D-20). The
     * response envelope is dereferenced to its `results` list; empty or
     * failed retrieval yields null (no chips/sources) and never
     * short-circuits the chat call — the sidecar is the single refusal
     * authority (D-23).
     *
     * @return array<int, array{n: int, id: string, corpus: string, title: string, url: ?string, catalog_code: ?string}>|null
     */
    private function companionCitations(string $question): ?array
    {
        try {
            $results = (new AiService)->search($question, [], null, 5)['results'];
        } catch (AiServiceException) {
            return null;
        }

        if ($results === []) {
            return null;
        }

        $payload = [];

        foreach ($results as $i => $result) {
            $payload[] = [
                'n' => $i + 1,
                'id' => $result['id'],
                'corpus' => $result['corpus'],
                'title' => $result['title'],
                'url' => $result['metadata']['url'] ?? null,
                'catalog_code' => $result['metadata']['catalog_code'] ?? null,
            ];
        }

        return $payload;
    }

    private function refreshConversations(): void
    {
        $this->conversations = Conversation::where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->get()
            ->all();
    }

    /**
     * Live copy availability for every catalog citation across all messages —
     * one grouped forPapers() call per render, keyed by the persisted
     * catalog_code so the chips partial can look up the suffix directly.
     * Render-time enrichment only: nothing here is written back into
     * $this->messages or the persisted ai_messages.citations payload.
     * Papers with zero inventory rows are absent from forPapers()'s result,
     * so they fall back to a 0/0 entry to keep the red cue on the chip.
     *
     * @return array<string, array{available: int, total: int, checked_at: Carbon}>
     */
    #[Computed]
    public function availabilityMap(): array
    {
        $catalogCitations = collect($this->messages)
            ->pluck('citations')
            ->flatten(1)
            ->filter(fn ($citation) => is_array($citation) && ($citation['corpus'] ?? null) === 'catalog')
            ->values();

        $ids = $catalogCitations
            ->map(fn ($citation) => (int) str_replace('paper-', '', $citation['id']))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        $hydrated = (new AvailabilityService)->forPapers($ids);

        return $catalogCitations
            ->filter(fn ($citation) => ! empty($citation['catalog_code']))
            ->mapWithKeys(function ($citation) use ($hydrated) {
                $id = (int) str_replace('paper-', '', $citation['id']);

                if (! isset($hydrated[$id])) {
                    return [$citation['catalog_code'] => [
                        'available' => 0,
                        'total' => 0,
                        'checked_at' => now(),
                    ]];
                }

                return [$citation['catalog_code'] => $hydrated[$id]];
            })
            ->all();
    }

    public function render()
    {
        return view('livewire.chat-widget');
    }
}
