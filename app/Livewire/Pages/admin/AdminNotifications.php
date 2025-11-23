<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Mary\Traits\Toast;

#[Title('Notifications')]
class AdminNotifications extends AdminComponent
{
    use WithPagination, Toast;

    public $filterType = '';
    public $filterRead = '';
    public $perPage = 15;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public function getNotificationsProperty()
    {
        $query = Auth::user()->userNotifications()
            ->when($this->filterType, fn($q) => $q->where('type', $this->filterType))
            ->when($this->filterRead === 'read', fn($q) => $q->read())
            ->when($this->filterRead === 'unread', fn($q) => $q->unread());

        return $query->paginate($this->perPage);
    }

    public function getUnreadCountProperty()
    {
        return Auth::user()->unreadNotifications()->count();
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
        Auth::user()->userNotifications()->unread()->update([
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
        return view('livewire.pages.Admin.admin-notifications');
    }
}
