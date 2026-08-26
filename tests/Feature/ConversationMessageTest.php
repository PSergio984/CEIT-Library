<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationMessageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_cascades_messages_when_conversation_deleted(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->count(2)->create(['conversation_id' => $conversation->id]);

        $this->assertDatabaseCount('ai_messages', 2);

        $conversation->delete();

        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    #[Test]
    public function it_cascades_conversations_and_messages_when_user_deleted(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        Message::factory()->count(2)->create(['conversation_id' => $conversation->id]);

        $user->delete();

        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    #[Test]
    public function it_orders_messages_by_id_asc(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->count(3)->create(['conversation_id' => $conversation->id]);

        $this->assertSame([1, 2, 3], $conversation->messages()->pluck('id')->all());
    }

    #[Test]
    public function it_touches_conversation_when_message_saved(): void
    {
        $conversation = Conversation::factory()->create();
        $originalUpdatedAt = $conversation->fresh()->updated_at;

        usleep(1_100_000);

        Message::factory()->create(['conversation_id' => $conversation->id]);

        $this->assertTrue($conversation->fresh()->updated_at->gt($originalUpdatedAt));
    }

    #[Test]
    public function it_truncates_auto_title_to_120_chars(): void
    {
        $title = Conversation::makeTitle(str_repeat('a', 150));

        $this->assertSame(120, mb_strlen($title));
    }

    #[Test]
    public function it_falls_back_to_new_conversation_title(): void
    {
        $this->assertSame('New conversation', Conversation::makeTitle('   '));
    }

    #[Test]
    public function it_casts_citations_to_array_and_defaults_null(): void
    {
        $conversation = Conversation::factory()->create();

        $plain = Message::factory()->create(['conversation_id' => $conversation->id, 'citations' => null]);
        $this->assertNull($plain->fresh()->citations);

        $payload = [
            ['n' => 1, 'id' => 'paper-77', 'corpus' => 'catalog', 'title' => 'Water Pump', 'url' => '/academic-papers?paper=77', 'catalog_code' => 'CEIT-CE-15-014'],
        ];
        $cited = Message::factory()->create(['conversation_id' => $conversation->id, 'citations' => $payload]);
        $this->assertSame($payload, $cited->fresh()->citations);
    }

    #[Test]
    public function it_creates_rows_via_factories(): void
    {
        $user = User::factory()->create();

        Conversation::factory()->for($user)->has(Message::factory()->count(2))->create();

        $this->assertDatabaseCount('ai_conversations', 1);
        $this->assertDatabaseCount('ai_messages', 2);
        $this->assertSame(1, Conversation::where('user_id', $user->id)->count());
    }
}
