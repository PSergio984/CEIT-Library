# Notification System Documentation

## Overview
The notification system provides real-time alerts to students, librarians, and administrators about important events in the CEIT Library system.

## Features

### Student Notifications
Students receive notifications for:
- **Librarian Assignment** (`librarian_assigned`) - When they are added to a librarian batch
- **Librarian Activation** (`librarian_activated`) - When their librarian batch becomes active
- **Paper Borrowed** (`paper_borrowed`) - When they successfully borrow an academic paper

### Admin/Librarian Notifications
Admins and librarians receive notifications for:
- **System Events** (`system`) - System-wide announcements and updates
- **User Activity** (`user_activity`) - Important user actions and events
- **Violations** (`violation`) - Violation records and infractions

## Routes

### Student Route
- **URL**: `/notifications`
- **Component**: `App\Livewire\Pages\Student\StudentNotifications`
- **Access**: Students only (authenticated users)

### Admin Route
- **URL**: `/admin/notifications`
- **Component**: `App\Livewire\Pages\Admin\AdminNotifications`
- **Access**: Admins and librarians (via `librarian.or.admin` middleware)

## Database Schema

### Notifications Table
```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    is_read BOOLEAN DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Notification Model

### Relationships
- `belongsTo(User::class)` - Each notification belongs to a user

### Scopes
- `unread()` - Get only unread notifications
- `read()` - Get only read notifications
- `forUser($userId)` - Get notifications for a specific user
- `ofType($type)` - Get notifications of a specific type

### Methods
- `markAsRead()` - Mark a notification as read (sets `is_read = true` and `read_at = now()`)

## User Model Integration

### New Relationships
```php
// Get all notifications for a user
public function userNotifications()
{
    return $this->hasMany(Notification::class);
}

// Get only unread notifications
public function unreadNotifications()
{
    return $this->userNotifications()->unread();
}
```

## Notification Triggers

### AdminAssignLibrarians Component
**Location**: `app/Livewire/Pages/Admin/AdminAssignLibrarians.php`

1. **Batch Creation** (Line ~303)
   - When students are added to a librarian batch
   - Type: `librarian_assigned`
   - Title: "You've been assigned as a Librarian!"
   - Message: Details about the batch and semester

2. **Batch Activation** (Line ~482)
   - When a librarian batch is activated
   - Type: `librarian_activated`
   - Title: "Your Librarian Role is Now Active!"
   - Message: Details about duty start date and semester

### AdminBorrowTransactions Component
**Location**: `app/Livewire/Pages/Admin/AdminBorrowTransactions.php`

3. **Paper Borrowed**
   - When a student borrows an academic paper
   - Type: `paper_borrowed`
   - Title: "Academic Paper Borrowed Successfully"
   - Message: Paper title, due date, and copy number
   - Data: `{ "paper_title": "...", "due_date": "...", "copy_number": 1 }`

## UI Features

### Notification List
- **Pagination**: 10 notifications per page
- **Filtering**: 
  - By type (all types, specific types)
  - By read status (all, unread only, read only)
- **Sorting**: Most recent first (created_at DESC)

### Notification Cards
- Type-specific icons:
  - Student: shield-check (librarian), document-text (papers)
  - Admin: cog (system), user-group (activity), exclamation-triangle (violations)
- Color-coded by type
- Timestamp display (human-readable, e.g., "2 hours ago")
- Unread indicator (bold text, blue dot)

### Actions
- **Mark as Read**: Mark individual notification as read
- **Mark All as Read**: Mark all unread notifications as read
- **Delete**: Remove a notification permanently

### Statistics
- Unread count displayed in stats card at the top

## How to Add New Notification Types

1. **Create the notification**:
```php
use App\Models\Notification;

Notification::create([
    'user_id' => $userId,
    'type' => 'your_notification_type',
    'title' => 'Notification Title',
    'message' => 'Notification message with details',
    'data' => json_encode(['key' => 'value']) // Optional additional data
]);
```

2. **Add to filter options**:
   - For students: Update `StudentNotifications::$typeFilter` options
   - For admins: Update `AdminNotifications::$typeFilter` options

3. **Add icon mapping**:
   - Update the blade view's notification card section to include icon for your type

## Testing the Notification System

### Test Librarian Assignment
1. Go to `/admin/librarians`
2. Create a new batch with students
3. Check students' notification pages - they should see "librarian_assigned" notification

### Test Batch Activation
1. Go to `/admin/librarians`
2. Activate an existing batch
3. Check students in that batch - they should see "librarian_activated" notification

### Test Paper Borrowing
1. Go to `/admin/logs` (borrow transactions)
2. Create a borrow transaction for a student
3. Check that student's notification page - they should see "paper_borrowed" notification

## Next Steps (Optional Enhancements)

### Navigation Menu Integration
Add notification links to the navigation menu with unread count badges:

```blade
<!-- Student Navigation -->
<li>
    <a href="{{ route('notifications') }}" class="{{ request()->routeIs('notifications') ? 'active' : '' }}">
        <x-icon name="o-bell" class="w-5 h-5" />
        Notifications
        @if(auth()->user()->unreadNotifications()->count() > 0)
            <span class="badge badge-primary badge-sm">
                {{ auth()->user()->unreadNotifications()->count() }}
            </span>
        @endif
    </a>
</li>

<!-- Admin Navigation -->
<li>
    <a href="{{ route('admin.notifications') }}" class="{{ request()->routeIs('admin.notifications') ? 'active' : '' }}">
        <x-icon name="o-bell" class="w-5 h-5" />
        Notifications
        @if(auth()->user()->unreadNotifications()->count() > 0)
            <span class="badge badge-primary badge-sm">
                {{ auth()->user()->unreadNotifications()->count() }}
            </span>
        @endif
    </a>
</li>
```

### Real-time Updates
Use Livewire polling to refresh notification count:

```php
// In component
public function getUnreadCountProperty()
{
    return auth()->user()->unreadNotifications()->count();
}

// In view
<div wire:poll.30s>
    @if($this->unreadCount > 0)
        <span class="badge">{{ $this->unreadCount }}</span>
    @endif
</div>
```

### Additional Notification Types
Consider adding notifications for:
- Paper return reminders (3 days before due date)
- Overdue paper warnings
- Violation records
- Credit score changes
- Account suspensions/reactivations
- System maintenance announcements

### Email Notifications
Integrate with Laravel's mail system to send email notifications for critical events:

```php
use App\Mail\NotificationEmail;
use Illuminate\Support\Facades\Mail;

// After creating notification
Mail::to($user->email)->send(new NotificationEmail($notification));
```

### Browser Notifications
Add browser push notifications using the Web Notifications API:

```javascript
// Request permission
Notification.requestPermission().then(permission => {
    if (permission === "granted") {
        new Notification("New Library Notification", {
            body: "You have a new notification!",
            icon: "/images/logo.png"
        });
    }
});
```

## Troubleshooting

### Notifications Not Appearing
1. Check if migration ran successfully: `php artisan migrate:status`
2. Verify User model has the notification relationships
3. Check if notification was created: `Notification::latest()->first()`
4. Ensure user is authenticated and accessing correct route

### Filter Not Working
1. Clear Livewire cache: `php artisan livewire:clear`
2. Check browser console for JavaScript errors
3. Verify filter values match notification types in database

### Permission Errors
1. Ensure proper middleware is applied to routes
2. Check user roles and permissions
3. Verify librarian.or.admin middleware is working

## Migration Status
✅ Notifications table created successfully (2025_11_23_212931)

## File Locations
- Migration: `database/migrations/2025_11_23_212931_create_notifications_table.php`
- Model: `app/Models/Notification.php`
- Student Component: `app/Livewire/Pages/Student/StudentNotifications.php`
- Student View: `resources/views/livewire/pages/Student/student-notifications.blade.php`
- Admin Component: `app/Livewire/Pages/Admin/AdminNotifications.php`
- Admin View: `resources/views/livewire/pages/Admin/admin-notifications.blade.php`
- Routes: `routes/web.php`
