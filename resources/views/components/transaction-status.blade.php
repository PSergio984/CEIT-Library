@props(['status'])

@php
    // Map status to DaisyUI badge classes for consistent styling
    $badgeClass = match($status) {
        'completed' => 'badge-success',      // Green for completed/returned on-time
        'started' => 'badge-info',           // Blue for active/ongoing transactions
        'overdue' => 'badge-error',          // Red for overdue transactions
        default => 'badge-ghost',            // Default ghost badge
    };
    
    // Display text - handle null/empty status with fallback
    if (empty(trim((string)$status))) {
        \Log::warning('Transaction status is null or empty in transaction-status component.', ['status' => $status]);
        $displayText = 'Unknown';
    } else {
        $displayText = match($status) {
            'started' => 'Borrowed',
            'overdue' => 'Overdue',
            default => ucfirst($status),
        };
    }
@endphp

<div class="badge {{ $badgeClass }} badge-md font-semibold">
    {{ $displayText }}
</div>


