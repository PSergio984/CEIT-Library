# Librarian Batch System - Updated Rules

## Overview

The librarian batch system has been updated with new strict requirements and automatic role management.

## New Rules

### 1. **Exactly 5 Students Required**

-   ❌ **No longer allowed:** 1-5 students per batch
-   ✅ **Now required:** Exactly 5 students per batch
-   Cannot create or save a batch with more or fewer than 5 students
-   UI shows visual feedback:
    -   **Green**: When exactly 5 students selected ✅
    -   **Yellow**: When less than 5 students selected ⚠️
    -   **Blue**: Initial state (no students selected) ℹ️

### 2. **Date Assignment is Optional**

-   Can create a batch **without** assigning a date
-   Date can be added later when you're ready to activate the batch
-   When no date is assigned:
    -   Batch status: `inactive`
    -   Students remain in student role

### 3. **Automatic Role Assignment on Date**

-   **When date = TODAY**: Students immediately become librarians

    -   Their role changes from `student` → `librarian`
    -   They gain read-only admin dashboard access
    -   They can scan QR codes
    -   Batch status: `active`

-   **When date = FUTURE**: Students remain students until that date

    -   Batch status: `inactive`
    -   Automatic promotion happens at midnight on that date

-   **When date = PAST**: Students return to student role
    -   Batch status: `expired`
    -   Role changes from `librarian` → `student`

### 4. **Automatic Role Removal After Shift**

-   After the assigned date passes, students **automatically** lose librarian role
-   They return to regular student status
-   This happens automatically via scheduled command

## Workflow Example

### Scenario 1: Create Batch for Today

1. Click "Create New Batch"
2. Enter batch number (e.g., `2025-11-09-A`)
3. Select exactly 5 students
4. **Don't assign date yet** (leave empty)
5. Click "Create Batch" ✅
6. Batch created as `inactive`

7. Click "Edit" on the batch
8. Set date to TODAY (2025-11-09)
9. Click "Save & Assign Date"
10. ✨ **All 5 students instantly become librarians!**

### Scenario 2: Schedule Future Batch

1. Create batch with 5 students
2. Assign date to November 15, 2025
3. Save
4. Students remain as students
5. On November 15 at midnight → automatic promotion to librarian
6. On November 16 at midnight → automatic demotion back to student

### Scenario 3: Update Existing Batch

1. Find batch in "All Batches" table
2. Click edit button
3. Change students (must keep exactly 5)
4. Change/remove/add date as needed
5. Save

## Visual Feedback in UI

### Create Modal

```
Selected Students: (3/5 selected)
⚠️ You must select exactly 5 students (2 more needed)
```

```
Selected Students: (5/5 selected)
✅ Required 5 students selected
```

### Edit Modal - Date Field

```
Serving Date: (Optional - Set when students should become librarians)
[Date Input]
ℹ️ Leave empty to create batch without assignment. Set date later to activate.
```

```
[Date Input: 2025-11-09]
🎯 Today's Date! These students will immediately become librarians when you save.
```

```
[Date Input: 2025-11-15]
✅ This date is available. Students will become librarians on this date.
```

## Automatic Role Management

### Command

```bash
php artisan librarian:update-roles
```

**What it does:**

1. Checks all batches with dates
2. **For today's batches:** Promotes students to librarian role
3. **For past batches:** Demotes librarians back to student role
4. **For future batches:** Marks as inactive (waits for the date)

### Schedule

The command runs automatically:

-   **Every hour** (to catch changes)
-   **Daily at midnight** (for daily transitions)

You can also run it manually anytime.

## Database Structure

### Batch Status Flow

```
inactive → active → expired
   ↓         ↓         ↓
  No date   Today    Past
```

### User Role Flow

```
student → librarian → student
  ↓           ↓           ↓
Before     On Date    After Date
```

## Important Notes

⚠️ **Exactly 5 Students**: This is now enforced. You cannot bypass this requirement.

⚠️ **Automatic Changes**: Role changes happen automatically. Monitor your batches!

⚠️ **One Batch Per Date**: Still enforced - no date conflicts allowed.

✅ **Flexible Scheduling**: Create batches now, assign dates later.

✅ **No Manual Role Changes**: The system handles all role assignments automatically based on dates.

## Technical Details

### Files Modified

1. `app/Livewire/Pages/Admin/AdminAssignLibrarians.php`

    - Changed validation: `size:5` (exactly 5)
    - Made date nullable
    - Added automatic role assignment on save

2. `app/Console/Commands/UpdateLibrarianRoles.php`

    - New command for automatic role management
    - Promotes/demotes based on dates
    - Updates batch statuses

3. `routes/console.php`

    - Scheduled hourly and daily runs

4. `resources/views/livewire/pages/Admin/admin-assign-librarians.blade.php`
    - Updated UI feedback
    - Changed button states
    - Added date explanations

### Role Assignment Logic

```php
// In AdminAssignLibrarians::saveBatchAssignment()
if ($this->editingDateStart && $this->editingDateStart === date('Y-m-d')) {
    $librarianRoleId = Role::where('name', 'librarian')->value('id');
    User::whereIn('id', $this->editingSelectedStudents)
        ->update(['role_id' => $librarianRoleId]);
}
```

### Automatic Role Updates

```php
// UpdateLibrarianRoles command
- Today's batches: student → librarian
- Past batches: librarian → student
- Future batches: stay student
```

## Testing

To test the system:

1. **Create a batch without date:**

    ```
    Create batch → Select 5 students → Save
    Result: Batch created, students remain students
    ```

2. **Assign today's date:**

    ```
    Edit batch → Set date to today → Save
    Result: Students immediately become librarians
    ```

3. **Run command manually:**

    ```bash
    php artisan librarian:update-roles
    ```

    Result: See how many promoted/demoted

4. **Check user roles:**
    ```
    Visit /admin/manage-roles
    Filter by Librarian role
    See active librarians
    ```

## FAQ

**Q: What happens if I try to select only 3 students?**
A: The "Create Batch" button will be disabled. You must select exactly 5.

**Q: Can I change the students after creating a batch?**
A: Yes, but you must always maintain exactly 5 students.

**Q: What if I create a batch without a date?**
A: It sits as "inactive" until you assign a date. Students remain students.

**Q: Can I change the date after assignment?**
A: Yes, edit the batch and change the date. Role changes apply accordingly.

**Q: What happens at midnight?**
A: The scheduled command runs and updates all roles based on batch dates.

**Q: Can a student be in multiple batches?**
A: No, each student can only be in one batch at a time.

**Q: Do I need to manually change roles back after the shift?**
A: No, the system does this automatically based on the date.

**Q: Can I skip the automatic system?**
A: No, role management is fully automated. Use the "Manage Roles" page for permanent role assignments.
