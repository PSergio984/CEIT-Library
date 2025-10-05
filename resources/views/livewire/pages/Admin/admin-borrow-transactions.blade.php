<div>
    <h1>Borrow Transactions</h1>

    <div class="overflow-x-auto">
        <table class="table table-zebra w-full">
            <thead>
                <tr>
                    <th>(lastname, firstname)</th>
                    <th>Email</th>
                    <th>Student No.</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->user->last_name }}, {{ $transaction->user->first_name }}</td>
                        <td>{{ $transaction->user->email }}</td>
                        <td>{{ $transaction->user->student_no }}</td>
                        <td>{{ $transaction->time_in ? $transaction->time_in->format('Y-m-d H:i:s') : 'N/A' }}</td>
                        <td>{{ $transaction->time_out ? $transaction->time_out->format('Y-m-d H:i:s') : 'Still Active' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $transactions->links() }}
    </div>
</div>
