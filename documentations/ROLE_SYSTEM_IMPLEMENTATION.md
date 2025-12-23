# Role-Based System Implementation

## Overview

Replaced the `is_admin` boolean column with a proper role-based system supporting:

-   **Student** (role_id: 1)
-   **Admin** (role_id: 2)
-   **Super Admin** (role_id: 3)

## Database Changes

### New Tables

-   `roles` table with columns:
    -   `id`
    -   `name` (student, admin, super_admin)
    -   `display_name` (Student, Admin, Super Admin)
    -   `description`
    -   `timestamps`

### Modified Tables

-   `users` table:
    -   ✅ Added: `role_id` (foreign key to roles table)
    -   ❌ Removed: `is_admin` (boolean)

## Files Created/Modified

### Models

-   ✅ `app/Models/Role.php` - New model with helper methods
-   ✅ `app/Models/User.php` - Updated with role relationship and new methods

### Migrations

-   ✅ `database/migrations/2025_11_09_113114_create_roles_table.php`
-   ✅ `database/migrations/2025_11_09_113129_add_role_id_to_users_table.php`

### Providers

-   ✅ `app/Providers/AppServiceProvider.php` - Updated all gates to use new role system

### Middleware

-   ✅ `app/Http/Middleware/AdminOnly.php` - Uses `hasAdminAccess()`
-   ✅ `app/Http/Middleware/LibrarianOrAdmin.php` - Uses `hasAdminAccess()`

### Factories

-   ✅ `database/factories/UserFactory.php` - Defaults to student role

## New User Model Methods

```php
// Role relationship
$user->role; // Returns Role model

// Check specific roles
$user->isStudent();      // Returns boolean
$user->isAdmin();        // Returns boolean
$user->isSuperAdmin();   // Returns boolean

// Check admin access (admin OR super_admin)
$user->hasAdminAccess(); // Returns boolean

// Generic role checker
$user->hasRole('admin'); // Returns boolean
```

## New Role Model Methods

```php
$role->isStudent();      // Returns boolean
$role->isAdmin();        // Returns boolean
$role->isSuperAdmin();   // Returns boolean
$role->hasAdminAccess(); // Returns boolean (admin or super_admin)
```

## Gate Permissions

### Super Admin Only

-   `manage-user-roles` - Manage user role assignments
-   `manage-system-settings` - System configuration

### Admin & Super Admin

-   `Admin-access` - General admin check
-   `assign-librarian-role` - Assign librarian duties
-   `manage-academic-papers` - CRUD academic papers
-   `view-attendance-logs` - View attendance
-   `manage-students` - Manage student accounts
-   `manage-rules` - Edit rules and regulations

### Librarian, Admin & Super Admin

-   `access-admin-dashboard` - Access admin area
-   `view-borrow-logs` - View borrow transactions
-   `view-violation-logs` - View violations
-   `view-rules` - View rules and regulations

## Migration Instructions

### Fresh Installation

```bash
php artisan migrate:fresh --seed
```

### Existing Database

```bash
# This will migrate existing data
# Users with is_admin=1 become 'admin'
# Users with is_admin=0 become 'student'
php artisan migrate
```

## Creating Users

### Student (Default)

```php
User::factory()->create([
    'email' => 'student@plv.edu.ph',
    'role_id' => 1, // or Role::where('name', 'student')->first()->id
]);
```

### Admin

```php
User::factory()->create([
    'email' => 'admin@plv.edu.ph',
    'role_id' => 2, // or Role::where('name', 'admin')->first()->id
]);
```

### Super Admin

```php
User::factory()->create([
    'email' => 'superadmin@plv.edu.ph',
    'role_id' => 3, // or Role::where('name', 'super_admin')->first()->id
]);
```

## Seeder Update Needed

Update `DatabaseSeeder.php` to create:

1. At least one Super Admin user
2. Admin users (existing admins will be migrated)
3. Student users (existing students will be migrated)

Example:

```php
// Create Super Admin
User::factory()->create([
    'first_name' => 'Super',
    'last_name' => 'Admin',
    'email' => 'superadmin@plv.edu.ph',
    'password' => Hash::make('password'),
    'role_id' => Role::where('name', 'super_admin')->first()->id,
]);
```

## Testing Checklist

After migration:

-   [ ] Super Admin can access all pages
-   [ ] Admin can access all pages except super admin features
-   [ ] Librarians can access limited admin pages
-   [ ] Students can only access student pages
-   [ ] Role permissions work correctly in gates
-   [ ] Navigation menu shows/hides correctly based on role

## Breaking Changes

⚠️ **Code that needs updating:**

1. Any direct `$user->is_admin` checks should be replaced with:

    - `$user->hasAdminAccess()` for admin/super_admin check
    - `$user->isAdmin()` for admin-only check
    - `$user->isSuperAdmin()` for super_admin-only check

2. Search codebase for `is_admin` and update accordingly

## Future Enhancements

-   [ ] Add role management UI for Super Admin
-   [ ] Add permission-based access control (optional)
-   [ ] Add role assignment history/audit log
-   [ ] Add more granular permissions if needed
