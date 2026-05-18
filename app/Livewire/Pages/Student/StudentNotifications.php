<?php

namespace App\Livewire\Pages\Student;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Notifications')]
#[Layout('components.layouts.app')]
class StudentNotifications extends Component
{
    use Toast, WithPagination;

    public $filterType = '';

    public $filterRead = '';

    public $perPage = 15;

    public function getNotificationsProperty()
    {
        $query = Notification::where('user_id', Auth::id())
            ->when($this->filterType, fn ($q) => $q->where('type', $this->filterType))
            ->when($this->filterRead === 'read', fn ($q) => $q->read())
            ->when($this->filterRead === 'unread', fn ($q) => $q->unread())
            ->orderBy('created_at', 'desc');

        return $query->paginate($this->perPage);
    }

    public function getUnreadCountProperty()
    {
        return Notification::where('user_id', Auth::id())->unread()->count();
    }

    public function markAsRead($notificationId)
    {
        $notification = Notification::find($notificationId);

        if ($notification && $notification->user_id === Auth::id()) {
            $notification->markAsRead();
            $this->success('Notification marked as read');
        }
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        $this->success('All notifications marked as read');
    }

    public function deleteNotification($notificationId)
    {
        $notification = Notification::find($notificationId);

        if ($notification && $notification->user_id === Auth::id()) {
            $notification->delete();
            $this->success('Notification deleted');
        }
    }

    /**
     * Navigate to the appropriate page based on the notification type.
     * Marks the notification as read before redirecting.
     */
    public function navigateToNotification(int $notificationId): void
    {
        $notification = Notification::find($notificationId);

        if (! $notification || $notification->user_id !== Auth::id()) {
            $this->error('Notification not found');

            return;
        }

        // Mark as read before navigating
        if (! $notification->is_read) {
            $notification->markAsRead();
        }

        // Determine the route based on notification type
        $route = match ($notification->type) {
            // Paper-related notifications → Transactions page
            'paper_borrowed', 'paper_returned', 'paper_returned_late', 'paper_overdue', 'overdue_transaction' => route('transactions'),

            // Credit score notifications → Credit Score History page
            'credit_score_increase' => route('CreditScoreHistory'),

            // Attendance notifications → Student Dashboard
            'attendance_checkout', 'attendance_checkin' => route('student.dashboard'),

            // Default: stay on notifications page
            default => null,
        };

        if ($route) {
            $this->redirect($route, navigate: true);
        }
    }

    public function clearFilters()
    {
        $this->filterType = '';
        $this->filterRead = '';
        $this->resetPage();
    }

    public function updatingFilterType()
    {
        $this->resetPage();
    }

    public function updatingFilterRead()
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.pages.student.student-notifications');
    }
}
