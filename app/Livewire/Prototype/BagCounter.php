<?php

namespace App\Livewire\Prototype;

use App\Livewire\Pages\Admin\AdminComponent;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Mary\Traits\Toast;

// PROTOTYPE — throwaway. Question: what should the bag counter look like?
// Three variants on /admin/prototype/bag-counter?variant=A|B|C. See issue #69.
// All state is in-memory; nothing here persists or calls the real QR pipeline.
#[Title('Bag Counter — PROTOTYPE')]
class BagCounter extends AdminComponent
{
    use Toast;

    #[Url(as: 'variant')]
    public string $variant = 'A';

    public array $users = [
        'juan' => 'Juan Dela Cruz',
        'maria' => 'Maria Santos',
        'pedro' => 'Pedro Reyes',
        'nena' => 'Nena Villanueva',
    ];

    public ?string $scannedUser = null;

    public array $slots = [
        1 => 'free', 2 => 'occupied', 3 => 'occupied', 4 => 'free',
        5 => 'free', 6 => 'free', 7 => 'occupied', 8 => 'free',
        9 => 'unclaimed', 10 => 'free', 11 => 'free', 12 => 'out_of_service',
    ];

    public array $deposits = [
        ['id' => 1, 'slot' => 2, 'user_key' => 'pedro', 'depositor' => 'Pedro Reyes', 'item_count' => 1, 'description' => 'Grey backpack', 'code' => '734120', 'photo' => false, 'deposited_at' => '8:15 AM', 'status' => 'open', 'unclaimed' => false, 'method' => null, 'claimant' => null, 'events' => []],
        ['id' => 2, 'slot' => 3, 'user_key' => 'juan', 'depositor' => 'Juan Dela Cruz', 'item_count' => 2, 'description' => 'Backpack + project box', 'code' => '482913', 'photo' => true, 'deposited_at' => '8:42 AM', 'status' => 'open', 'unclaimed' => false, 'method' => null, 'claimant' => null, 'events' => []],
        ['id' => 3, 'slot' => 7, 'user_key' => 'pedro', 'depositor' => 'Pedro Reyes', 'item_count' => 1, 'description' => 'Tuba case', 'code' => '915276', 'photo' => false, 'deposited_at' => '9:03 AM', 'status' => 'open', 'unclaimed' => false, 'method' => null, 'claimant' => null, 'events' => []],
        ['id' => 4, 'slot' => 9, 'user_key' => 'nena', 'depositor' => 'Nena Villanueva', 'item_count' => 1, 'description' => 'Red tote bag', 'code' => '207551', 'photo' => false, 'deposited_at' => 'Yesterday 4:57 PM', 'status' => 'open', 'unclaimed' => true, 'method' => null, 'claimant' => null, 'events' => []],
    ];

    public bool $depositModal = false;

    public bool $claimModal = false;

    public bool $rescueModal = false;

    public bool $detailModal = false;

    public ?int $rescueDepositId = null;

    public ?int $detailDepositId = null;

    public string $rescueNote = '';

    public bool $rescueIdVerified = false;

    public ?int $formSlot = null;

    public int $formItemCount = 1;

    public string $formDescription = '';

    public bool $formPhoto = false;

    public ?string $issuedCode = null;

    public string $claimCodeInput = '';

    public array $codeEntitledIds = [];

    public int $nextId = 5;

    public function mount()
    {
        $this->authorizeAccess();
    }

    public function simulateScan(string $key)
    {
        $this->scannedUser = $key;
        $this->success($this->users[$key].' scanned', 'QR Scan');
    }

    public function openDepositModal()
    {
        if (! $this->scannedUser) {
            $this->error('Scan a student QR first', 'No scan');

            return;
        }
        $this->issuedCode = null;
        $this->formSlot = collect($this->slots)->search(fn ($s) => $s === 'free') ?: null;
        $this->formItemCount = 1;
        $this->formDescription = '';
        $this->formPhoto = false;
        $this->depositModal = true;
    }

    public function submitDeposit()
    {
        if (! $this->formSlot) {
            $this->error('Pick a slot', 'No slot');

            return;
        }
        $this->issuedCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->deposits[] = [
            'id' => $this->nextId++,
            'slot' => $this->formSlot,
            'user_key' => $this->scannedUser,
            'depositor' => $this->users[$this->scannedUser],
            'item_count' => $this->formItemCount,
            'description' => $this->formDescription ?: 'Bag',
            'code' => $this->issuedCode,
            'photo' => $this->formPhoto,
            'deposited_at' => now()->format('g:i A'),
            'status' => 'open',
            'unclaimed' => false,
            'method' => null,
            'claimant' => null,
            'events' => [],
        ];
        $this->slots[$this->formSlot] = 'occupied';
    }

    public function openClaimModal()
    {
        if (! $this->scannedUser) {
            $this->error('Scan a student QR first', 'No scan');

            return;
        }
        $this->claimModal = true;
    }

    public function entitledDeposits(): array
    {
        return array_values(array_filter(
            $this->deposits,
            fn ($d) => $d['status'] === 'open'
                && ($d['user_key'] === $this->scannedUser || in_array($d['id'], $this->codeEntitledIds))
        ));
    }

    public function lookupCode()
    {
        $code = trim($this->claimCodeInput);
        $hit = collect($this->deposits)->firstWhere('code', $code);
        if ($hit && $hit['status'] === 'open') {
            if (! in_array($hit['id'], $this->codeEntitledIds)) {
                $this->codeEntitledIds[] = $hit['id'];
            }
            $this->claimModal = true;
            $this->success("Deposit in slot {$hit['slot']} unlocked by code", 'Code accepted');
        } else {
            $this->error('No open deposit with that code', 'Code rejected');
        }
        $this->claimCodeInput = '';
    }

    public function claim(int $id, string $method = 'owner_scan')
    {
        foreach ($this->deposits as $i => $d) {
            if ($d['id'] !== $id) {
                continue;
            }
            $this->deposits[$i]['status'] = 'claimed';
            $this->deposits[$i]['claimant'] = $this->scannedUser ? $this->users[$this->scannedUser] : 'Unverified claimant';
            $this->deposits[$i]['method'] = $method;
            $this->slots[$d['slot']] = 'free';
            $msg = "Slot {$d['slot']} freed — {$d['description']} released ({$method})";
            if ($d['user_key'] !== $this->scannedUser) {
                $msg .= '. Depositor notified';
            }
            $this->success($msg, 'Claimed');
        }
        $this->claimModal = false;
        $this->rescueModal = false;
        $this->detailModal = false;
    }

    public function tempAccess(int $id)
    {
        foreach ($this->deposits as $i => $d) {
            if ($d['id'] === $id) {
                $who = $this->scannedUser ? $this->users[$this->scannedUser] : 'someone';
                $this->deposits[$i]['events'][] = now()->format('g:i A')." — temp access by {$who}";
                $this->success("Temp access logged for slot {$d['slot']}. Depositor notified", 'Temp access');
            }
        }
    }

    public function openDetail(int $id)
    {
        $this->detailDepositId = $id;
        $this->detailModal = true;
    }

    public function openRescue(int $id)
    {
        $this->rescueDepositId = $id;
        $this->rescueNote = '';
        $this->rescueIdVerified = false;
        $this->rescueModal = true;
    }

    public function submitRescue()
    {
        if (! $this->rescueIdVerified || trim($this->rescueNote) === '') {
            $this->error('ID check + a written note are required', 'Rescue blocked');

            return;
        }
        $this->claim($this->rescueDepositId, 'rescue_lookup');
    }

    public function freeSlots(): array
    {
        return array_keys(array_filter($this->slots, fn ($s) => $s === 'free'));
    }

    public function openDeposits(): array
    {
        return array_values(array_filter($this->deposits, fn ($d) => $d['status'] === 'open'));
    }

    public function depositById(?int $id): ?array
    {
        return collect($this->deposits)->firstWhere('id', $id);
    }

    public function render()
    {
        return view('livewire.prototype.bag-counter');
    }
}
