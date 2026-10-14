<?php

namespace Tests\Traits;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;

trait TestHelper
{
    /**
     * Generate a unique session token for testing purposes.
     *
     * @param  string|null  $prefix  Optional prefix for the token
     */
    protected function generateSessionToken(?string $prefix = null): string
    {
        $basePrefix = $prefix ?: 'test-token';

        return $basePrefix.'-'.uniqid();
    }

    /**
     * Generate multiple unique session tokens.
     *
     * @param  int  $count  Number of tokens to generate
     * @param  string|null  $prefix  Optional prefix for the tokens
     */
    protected function generateSessionTokens(int $count, ?string $prefix = null): array
    {
        $tokens = [];
        for ($i = 1; $i <= $count; $i++) {
            $tokens[] = $this->generateSessionToken($prefix ? "{$prefix}-{$i}" : "test-token-{$i}");
        }

        return $tokens;
    }

    /**
     * Create a borrow transaction with all required FK rows.
     */
    protected function makeBorrowTransaction(array $overrides = []): BorrowTransaction
    {
        $paper = AcademicPaper::factory()->create();

        return BorrowTransaction::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => Inventory::factory()->create([
                'academic_paper_id' => $paper->id,
                'copy_number' => 1,
            ])->id,
        ], $overrides));
    }
}
