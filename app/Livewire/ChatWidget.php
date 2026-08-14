<?php

namespace App\Livewire;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceProviderException;
use App\Exceptions\AiServiceUnavailableException;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiService;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Component;

class ChatWidget extends Component
{
    public bool $open = false;

    /** @var 'list'|'chat' */
    public string $view = 'list';

    public ?int $activeConversationId = null;

    /** @var array<int, Conversation> */
    public array $conversations = [];

    /** @var array<int, array{role: string, content: string, citations?: array|null, failed?: bool, error?: array|null}> */
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
        $conversation = Conversation::where('user_id', auth()->id())->whereKey($id)->first();

        if (! $conversation) {
            return;
        }

        $this->messages = $conversation->messages->map(fn (Message $message) => [
            'role' => $message->role,
            'content' => $message->content,
            'citations' => $message->citations,
            'failed' => false,
            'error' => null,
        ])->all();

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

        $this->messages[] = ['role' => 'user', 'content' => $question];
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
        if ($this->activeConversationId !== null) {
            $owned = Conversation::where('user_id', auth()->id())
                ->whereKey($this->activeConversationId)
                ->exists();

            if (! $owned) {
                $this->activeConversationId = null;
                $this->messages = [];
            }
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
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '',
            'citations' => null,
            'failed' => false,
            'error' => null,
        ];

        $accumulated = '';
        $citations = $this->companionCitations($question);

        try {
            $svc = new AiService;
            $response = $svc->chatStream($question, 'citations', null, 5);

            // Typing dots stream first — the persistent stream slot (W-3)
            // needs the indicator inside the live stream, not in a
            // conditionally-rendered element.
            $this->stream(
                '<span class="inline-flex gap-1 py-1"><span class="w-1.5 h-1.5 rounded-full bg-base-content/40 animate-bounce"></span><span class="w-1.5 h-1.5 rounded-full bg-base-content/40 animate-bounce [animation-delay:150ms]"></span><span class="w-1.5 h-1.5 rounded-full bg-base-content/40 animate-bounce [animation-delay:300ms]"></span></span>',
                false,
                'ans'
            );

            foreach ($svc->chatStreamEvents($response) as $chunk) {
                $accumulated .= $chunk;
                $this->stream($chunk, false, 'ans');
            }

            $idx = array_key_last($this->messages);
            $this->messages[$idx]['content'] = $accumulated;
            $this->messages[$idx]['citations'] = $citations;

            Message::create([
                'conversation_id' => $this->activeConversationId,
                'role' => 'assistant',
                'content' => $accumulated,
                'citations' => $citations,
            ]);

            $this->refreshConversations();
        } catch (AiServiceProviderException|AiServiceUnavailableException|AiServiceAuthException|ConnectionException $e) {
            $idx = array_key_last($this->messages);
            $this->messages[$idx]['failed'] = true;
            $this->messages[$idx]['error'] = [
                'code' => match (true) {
                    $e instanceof AiServiceAuthException => 'auth_failed',
                    $e instanceof AiServiceUnavailableException => 'unavailable',
                    default => 'provider_error',
                },
                'message' => $e->getMessage(),
            ];
        } finally {
            $this->streaming = false;
        }
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
        } catch (AiServiceUnavailableException|AiServiceAuthException) {
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

    public function render()
    {
        return view('livewire.chat-widget');
    }
}
