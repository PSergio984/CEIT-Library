<?php

namespace App\Livewire;

use App\Models\Conversation;
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
