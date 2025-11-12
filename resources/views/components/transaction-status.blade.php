@props(['status'])

@php
    // Map status to DaisyUI badge classes for consistent styling
    $badgeClass = match($status) {
        'completed' => 'badge-success',      // Green for completed/returned on-time
        'started' => 'badge-info',           // Blue for active/ongoing transactions
        'expired' => 'badge-error',          // Red for overdue/expired transactions
        'requested' => 'badge-warning',      // Yellow for pending requests
        'cancelled' => 'badge-neutral',      // Gray for cancelled transactions
        default => 'badge-ghost',            // Default ghost badge
    };
    
    // Display text - handle null/empty status with fallback
    if (empty(trim((string)$status))) {
        $displayText = 'Unknown';
    } else {
        $displayText = match($status) {
            'started' => 'Borrowed',
            'expired' => 'Overdue',
            default => ucfirst($status),
        };
    }
@endphp

<div class="badge {{ $badgeClass }} badge-md font-semibold">
    {{ $displayText }}
</div>


