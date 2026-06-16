<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Transactions Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0 0 5px 0;
            font-size: 20px;
        }
        .filters {
            margin-bottom: 15px;
            font-size: 11px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .status-started { color: #d97706; }
        .status-completed { color: #059669; }
        .status-overdue { color: #dc2626; }
        .footer {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CEIT Library - Borrow Transactions Report</h1>
        <p>Total Records: {{ $transactions->count() }}</p>
    </div>

    <div class="filters">
        <strong>Filters Applied:</strong>
        @if(array_filter($filters))
            {{ implode(', ', array_map(function ($k, $v) { return ucfirst($k) . ': ' . ($v ?: 'Any'); }, array_keys($filters), $filters)) }}
        @else
            None (All Records)
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Title</th>
                <th>Type</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->id }}</td>
                    <td>{{ $transaction->user?->first_name }} {{ $transaction->user?->last_name }}</td>
                    <td>{{ $transaction->inventory?->academicPaper?->title ?? 'N/A' }}</td>
                    <td>{{ $transaction->inventory?->academicPaper?->paper_type ?? 'N/A' }}</td>
                    <td>{{ $transaction->time_in ? $transaction->time_in->format('Y-m-d H:i') : 'N/A' }}</td>
                    <td>{{ $transaction->time_out ? $transaction->time_out->format('Y-m-d H:i') : 'N/A' }}</td>
                    <td class="status-{{ $transaction->status }}">
                        {{ ucfirst($transaction->status) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">No transactions found matching criteria.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated at: {{ $generatedAt }}
    </div>
</body>
</html>