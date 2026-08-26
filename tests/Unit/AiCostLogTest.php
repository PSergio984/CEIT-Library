<?php

namespace Tests\Unit;

use App\Services\AiService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiCostLogTest extends TestCase
{
    private mixed $originalCostChannel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCostChannel = config('services.ai_sidecar.cost_channel');
    }

    protected function tearDown(): void
    {
        config(['services.ai_sidecar.cost_channel' => $this->originalCostChannel]);

        parent::tearDown();
    }

    #[Test]
    public function estimates_tokens_at_four_characters_per_token(): void
    {
        $this->assertSame(0, AiService::estimateTokens(''));
        $this->assertSame(1, AiService::estimateTokens('abcd'));
        $this->assertSame(2, AiService::estimateTokens('abcde'));
    }

    #[Test]
    public function marks_usage_estimated_when_the_sidecar_omits_the_usage_event(): void
    {
        config()->set('services.ai_sidecar.cost_channel', 'null');

        $record = (new AiService)->logChatCost(
            question: 'abcd',
            answer: 'abcdef',
            startedAt: microtime(true) - 1.5,
            conversationId: 7,
            usage: null,
        );

        $this->assertTrue($record['tokens_estimated']);
        $this->assertSame(1, $record['prompt_tokens']);
        $this->assertSame(2, $record['completion_tokens']);
        $this->assertSame(7, $record['conversation_id']);
        $this->assertSame('chat_completion', $record['event']);
        $this->assertGreaterThanOrEqual(1000, $record['duration_ms']);
    }

    #[Test]
    public function prefers_real_usage_when_the_sidecar_reports_it(): void
    {
        config()->set('services.ai_sidecar.cost_channel', 'null');

        $record = (new AiService)->logChatCost(
            question: 'abcd',
            answer: 'abcdef',
            startedAt: microtime(true),
            conversationId: 7,
            usage: ['prompt_tokens' => 11, 'completion_tokens' => 13],
        );

        $this->assertFalse($record['tokens_estimated']);
        $this->assertSame(11, $record['prompt_tokens']);
        $this->assertSame(13, $record['completion_tokens']);
    }
}
