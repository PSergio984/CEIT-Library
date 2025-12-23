# Role Management System Guide

## Overview

The CEIT Library now has a comprehensive role-based access control system with two pages for managing user permissions:

1. **Manage Roles** (`/admin/manage-roles`) - Assign Super Admin and Librarian roles to users
2. **Librarian Batches** (`/admin/librarians`) - Assign students to temporary librarian duty batches

## Role System Architecture

### Three User Roles

#### 1. **Student** (Default)

-   Regular library users
-   Can browse academic papers, view rules, check transactions
-   Cannot access admin dashboard
-   Role ID: 1

#### 2. **Librarian**

-   Permanent role with read-only admin access
-   Can scan QR codes
-   Can view admin dashboard (read-only)
-   Can see: Dashboard, Borrow Logs, Rules & Regulations, Violation Logs
-   Cannot see: Academic Papers management, Students list, Attendance, Edit buttons
-   Role ID: 2

#### 3. **Super Admin**

-   Full system access
-   Can manage all content
-   Can assign roles to other users
-   Can create other Super Admins and Librarians
-   Role ID: 3

## Two Pages for Managing Access

### Page 1: Manage Roles (`/admin/manage-roles`)

**Access:** Super Admins only

**Purpose:** Assign permanent roles (Super Admin or Librarian) to users

**Features:**

-   View all users with their current roles
-   Search users by name or email
-   Filter users by role
-   Change any user's role (except your own)
-   Role statistics dashboard
-   Visual role badges (Red for Super Admin, Blue for Librarian, Green for Student)

**Workflow:**

1. Navigate to Admin → Manage Roles
2. Search/filter to find the user
3. Click "Change" button
4. Select new role from modal
5. Confirm assignment

**Important Notes:**

-   Super Admins cannot demote themselves
-   Role changes are permanent until changed again
-   Librarians assigned here have continuous access to read-only admin dashboard

### Page 2: Librarian Batches (`/admin/librarians`)

**Access:** Super Admins and Librarians

**Purpose:** Assign students to temporary librarian duty batches for scheduled shifts

**Features:**

-   Create batches with exactly 5 students
-   Assign dates to batches
-   Track batch status (active/inactive/expired)
-   View available and assigned batches
-   Search and filter batches
-   Prevent scheduling conflicts

**Workflow:**

1. Navigate to Admin → Librarian Batches
2. Click "Create New Batch"
3. Enter batch number
4. Select up to 5 students
5. Assign date for their shift
6. Add shift notes

**Important Notes:**

-   Only students (not librarians/admins) can be assigned to batches
-   Exactly 5 students per batch
-   One batch per date (no conflicts)
-   Batches are for scheduled duty shifts, not permanent role changes

## Key Differences Between the Pages

| Feature  | Manage Roles               | Librarian Batches          |
| -------- | -------------------------- | -------------------------- |
| Access   | Super Admin only           | Super Admin + Librarians   |
| Purpose  | Permanent role assignment  | Temporary shift scheduling |
| Target   | All users                  | Students only              |
| Duration | Permanent                  | Date-specific              |
| Effect   | Changes system permissions | Assigns duty shift         |

## Use Cases

### Scenario 1: Making Someone a Permanent Librarian

**Use:** Manage Roles page
**Steps:**

1. Go to `/admin/manage-roles`
2. Find the user
3. Click "Change"
4. Select "Librarian"
5. Confirm

**Result:** User now has permanent librarian role with read-only dashboard access

### Scenario 2: Assigning Students to a Duty Shift

**Use:** Librarian Batches page
**Steps:**

1. Go to `/admin/librarians`
2. Click "Create New Batch"
3. Select students for duty
4. Assign shift date
5. Save

**Result:** Students are scheduled for librarian duty on that date

### Scenario 3: Promoting Someone to Super Admin

**Use:** Manage Roles page
**Steps:**

1. Go to `/admin/manage-roles`
2. Find the user
3. Click "Change"
4. Select "Super Admin"
5. Confirm

**Result:** User now has full admin access and can manage roles

## User Model Methods

### Role Checking

```php
$user->isStudent()              // Is user a student?
$user->hasLibrarianRole()       // Does user have librarian role?
$user->isSuperAdmin()           // Is user a super admin?
$user->hasAdminAccess()         // Does user have any admin privileges?
$user->hasActiveLibrarianDuty() // Is user on active batch duty?
$user->isLibrarian()            // Has librarian role OR active duty?
```

### Practical Usage

```php
// Check if user can access admin dashboard
if ($user->hasAdminAccess()) {
    // Allow access
}

// Check if user can edit content
if ($user->isSuperAdmin()) {
    // Allow editing
}

// Check if user can scan QR codes
if ($user->isLibrarian()) {
    // Allow QR scanning
}
```

## Database Structure

### `roles` Table

```sql
id | name         | display_name | description
1  | student      | Student      | Regular library users
2  | librarian    | Librarian    | Can scan QR codes and view dashboard (read-only)
3  | super_admin  | Super Admin  | Full system access
```

### `users` Table (relevant fields)

```sql
id | first_name | last_name | email | role_id | account_status
```

### `librarians` Table (for batch assignments)

```sql
id | user_id | batch_no | date_start | status | expires_at | shift_notes | created_by
```

## Authorization Gates

### Super Admin Only

-   `manage-user-roles` - Access to Manage Roles page
-   `manage-system-settings` - System configuration

### Super Admin Accessible (Librarian Read-Only)

-   `access-admin-dashboard` - View admin dashboard
-   `view-borrow-logs` - View borrow transactions
-   `view-violation-logs` - View violations
-   `view-rules` - View rules and regulations

### Super Admin Full Access

-   `manage-academic-papers` - Edit academic papers
-   `view-attendance-logs` - View attendance
-   `manage-students` - Manage student accounts
-   `manage-rules` - Edit rules and regulations
-   `assign-librarian-role` - Manage batch assignments

## Migration History

The system was migrated from a boolean `is_admin` field to the role-based system:

```php
// Old system
User::where('is_admin', 1) // Admins
User::where('is_admin', 0) // Students

// New system
User::where('role_id', 3) // Super Admins
User::where('role_id', 2) // Librarians
User::where('role_id', 1) // Students
```

All existing `is_admin = 1` users were automatically converted to Super Admins during migration.

## FAQ

**Q: Can a librarian create other librarians?**
A: No, only Super Admins can assign roles. Librarians can only manage batch assignments.

**Q: Can I demote myself from Super Admin?**
A: No, the system prevents you from changing your own role to prevent lockouts.

**Q: What's the difference between a librarian role and a librarian batch?**
A: Librarian role is permanent and gives read-only admin access. Batch assignment is temporary and schedules duty shifts.

**Q: Can someone have both a librarian role and be in a batch?**
A: No, only students can be assigned to batches. Librarians have continuous access.

**Q: How do I give someone QR scanning access?**
A: Assign them the Librarian role via the Manage Roles page.

**Q: Can librarians edit anything?**
A: No, librarians have read-only access to the admin dashboard. All edit buttons are hidden.

## Navigation

-   **Manage Roles:** Admin sidebar → "Manage Roles" (shield icon)
-   **Librarian Batches:** Admin sidebar → "Librarian Batches" (library icon)

Both pages are clearly labeled and have descriptive headers explaining their purpose.
