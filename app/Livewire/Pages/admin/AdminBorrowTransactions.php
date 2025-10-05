<?php

namespace App\Livewire\Pages\Admin;

use App\Models\BorrowTransaction;
use Livewire\Component;

class AdminBorrowTransactions extends Component
{
    public function render()
    {
        $transactions = BorrowTransaction::with(['user' => function($query) {
            $query->select('id', 'first_name', 'last_name', 'email', 'student_no');
        }])
        ->orderBy('time_in', 'desc')
        ->paginate(20);

        return view('livewire.pages.admin.admin-borrow-transactions', [
            'transactions' => $transactions
        ]);
    }
}
