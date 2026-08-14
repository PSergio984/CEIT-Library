<?php

namespace App\Livewire;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceProviderException;
use App\Exceptions\AiServiceUnavailableException;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiService;
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

        try {
            $svc = new AiService;
            $response = $svc->chatStream($question, 'citations', null, 5);

            foreach ($svc->chatStreamEvents($response) as $chunk) {
                $accumulated .= $chunk;
                $this->stream($chunk, false, 'ans');
            }

            $idx = array_key_last($this->messages);
            $this->messages[$idx]['content'] = $accumulated;

            Message::create([
                'conversation_id' => $this->activeConversationId,
                'role' => 'assistant',
                'content' => $accumulated,
                'citations' => null,
            ]);

            $this->refreshConversations();
        } catch (AiServiceProviderException|AiServiceUnavailableException|AiServiceAuthException $e) {
            $idx = array_key_last($this->messages);
            $this->messages[$idx]['failed'] = true;
            $this->messages[$idx]['error'] = [
                'code' => 'provider_error',
                'message' => $e->getMessage(),
            ];
        } finally {
            $this->streaming = false;
        }
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
