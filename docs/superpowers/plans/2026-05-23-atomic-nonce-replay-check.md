# Atomic Nonce Replay Check Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace non-atomic Cache::has/put with atomic Cache::add for nonce replay prevention in AdminBorrowTransactions.

**Architecture:** Use Laravel's `Cache::add($key, $value, $seconds)` which returns `true` only if the key did not exist, providing an atomic "set-if-not-exists" operation.

**Tech Stack:** Laravel (Cache Facade), Livewire.

---

### Task 1: Replace non-atomic check with atomic Cache::add

**Files:**
- Modify: `app/Livewire/Pages/Admin/AdminBorrowTransactions.php:317-326`

- [ ] **Step 1: Replace Cache::has/put with Cache::add**

```php
            // 3. Nonce Replay Prevention (v7)
            if (isset($data['nonce'])) {
                $nonceKey = 'qr_nonce:'.$data['nonce'];
                if (! \Illuminate\Support\Facades\Cache::add($nonceKey, true, 150)) {
                    \Log::warning('Borrow QR code rejected: Replay attack detected (nonce reuse)');
                    $this->error('This QR code has already been used.');
                    $this->isProcessingQr = false;
                    return ['found' => false];
                }
            }
```

- [ ] **Step 2: Run unit tests to verify behavior**

I should check if there are tests for this component first.

### Task 2: Verification

**Files:**
- Test: `tests/Feature/Livewire/Admin/AdminBorrowTransactionsTest.php` (if it exists)

- [ ] **Step 1: Check for existing tests**

Run: `ls tests/Feature/Livewire/Admin/AdminBorrowTransactionsTest.php`

- [ ] **Step 2: Create or update test to verify nonce replay prevention**

If a test exists, add a case for nonce reuse. If not, create a minimal test.

```php
    public function test_it_prevents_nonce_replay()
    {
        $nonce = 'test-nonce';
        $data = ['nonce' => $nonce, 'p' => ['inventory_id' => 1, 'paper_id' => 1]];
        
        Livewire::test(AdminBorrowTransactions::class)
            ->call('processQr', $data)
            ->assertSet('isProcessingQr', false);
            
        Livewire::test(AdminBorrowTransactions::class)
            ->call('processQr', $data)
            ->assertSet('error', 'This QR code has already been used.')
            ->assertSet('found', false);
    }
```

- [ ] **Step 3: Run the test**

Run: `php artisan test --filter=test_it_prevents_nonce_replay`
Expected: PASS
