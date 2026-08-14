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

    private function streamQuestion(string $question): void
    {
        if ($this->activeConversationId === null) {
            $conversation = Conversation::create([
                'user_id' => auth()->id(),
                'title' => Conversation::makeTitle($question),
            ]);
            $this->activeConversationId = $conversation->id;
            $this->refreshConversations();
        }

        Message::create([
            'conversation_id' => $this->activeConversationId,
            'role' => 'user',
            'content' => $question,
        ]);

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
