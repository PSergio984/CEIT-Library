<?php

namespace Tests\Feature;

use App\Livewire\ChatWidget;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/fixtures/ai-sidecar/'.$name));
    }

    private function emptySearchResponse(): array
    {
        return [
            'query' => 'borrowing rules',
            'total' => 0,
            'took_ms' => 1,
            'results' => [],
        ];
    }

    #[Test]
    public function it_mounts_with_conversation_list_default(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'This is a fairly long conversation title that should be trimmed to forty characters',
        ]);

        config(['services.ai_sidecar.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->emptySearchResponse(), 200),
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($user);

        Livewire::test(ChatWidget::class)
            ->assertSet('view', 'list')
            ->assertSee(mb_strimwidth($conversation->title, 0, 40, '…'))
            ->call('toggle')
            ->assertSet('open', true)
            ->assertSet('view', 'list');
    }

    #[Test]
    public function it_lazily_creates_conversation_and_persists_turn(): void
    {
        $user = User::factory()->create();

        config(['services.ai_sidecar.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->emptySearchResponse(), 200),
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($user);

        Livewire::test(ChatWidget::class)
            ->set('draft', 'borrowing rules')
            ->call('send')
            ->assertSet('streaming', false);

        $this->assertDatabaseHas('ai_conversations', ['user_id' => $user->id, 'title' => 'borrowing rules']);
        $this->assertDatabaseHas('ai_messages', ['role' => 'user', 'content' => 'borrowing rules']);
        $this->assertDatabaseHas('ai_messages', ['role' => 'assistant', 'citations' => null]);
        $this->assertDatabaseCount('ai_messages', 2);
        $this->assertSame(1, Message::where('role', 'assistant')->count());
    }

    #[Test]
    public function it_reuses_active_conversation(): void
    {
        $user = User::factory()->create();

        config(['services.ai_sidecar.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->emptySearchResponse(), 200),
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($user);

        Livewire::test(ChatWidget::class)
            ->set('draft', 'first question')
            ->call('send')
            ->set('draft', 'second question')
            ->call('send')
            ->assertSet('streaming', false);

        $this->assertDatabaseCount('ai_conversations', 1);
        $this->assertDatabaseCount('ai_messages', 4);
    }

    #[Test]
    public function it_renders_streamed_content_into_bubble(): void
    {
        $user = User::factory()->create();

        config(['services.ai_sidecar.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->emptySearchResponse(), 200),
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($user);

        Livewire::test(ChatWidget::class)
            ->set('draft', 'borrowing rules')
            ->call('send')
            ->assertSet('messages.1.content', 'CEIT Library ');
    }

    #[Test]
    public function it_shows_failure_banner_and_retry_replaces_turn(): void
    {
        $user = User::factory()->create();

        config(['services.ai_sidecar.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->emptySearchResponse(), 200),
            'http://127.0.0.1:8310/chat/stream' => Http::sequence()
                ->push([], 500)
                ->push($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($user);

        Livewire::test(ChatWidget::class)
            ->set('draft', 'borrowing rules')
            ->call('send')
            ->assertSet('messages.1.failed', true)
            ->assertSet('messages.1.error.code', 'provider_error')
            ->call('retry')
            ->assertSet('messages.1.failed', false)
            ->assertSet('messages.1.content', 'CEIT Library ');

        // Exactly one user + ONE assistant message — the failed turn was replaced, not duplicated.
        $this->assertDatabaseCount('ai_messages', 2);
        $this->assertSame(1, Message::where('role', 'assistant')->count());
    }

    #[Test]
    public function it_blocks_cross_user_conversation_open(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $userA->id]);
        Message::factory()->count(2)->create(['conversation_id' => $conversation->id]);

        config(['services.ai_sidecar.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->emptySearchResponse(), 200),
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($userB);

        Livewire::test(ChatWidget::class)
            ->call('openConversation', $conversation->id)
            ->assertSet('activeConversationId', null)
            ->assertSet('messages', []);
    }

    #[Test]
    public function it_guards_against_send_during_streaming(): void
    {
        $user = User::factory()->create();

        config(['services.ai_sidecar.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->emptySearchResponse(), 200),
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($user);

        Livewire::test(ChatWidget::class)
            ->set('streaming', true)
            ->set('draft', 'hello')
            ->call('send');

        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertDatabaseCount('ai_conversations', 0);
    }

    #[Test]
    public function it_renders_widget_on_authenticated_page(): void
    {
        $user = User::factory()->create();

        config(['services.ai_sidecar.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->emptySearchResponse(), 200),
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk()->assertSee('CEIT Library Assistant');
    }

    #[Test]
    public function it_shows_list_entries_with_relative_time(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'Older conversation',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        config(['services.ai_sidecar.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->emptySearchResponse(), 200),
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->actingAs($user);

        Livewire::test(ChatWidget::class)
            ->assertSee(mb_strimwidth($conversation->title, 0, 40, '…'))
            ->assertSee('hour');
    }
}
