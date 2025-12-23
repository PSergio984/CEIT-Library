# CEIT Library System - Test Cases

**Application:** CEIT Library System

---

## Role-Based Access Control & Admin Features

### TC001: Manage Roles - Super Admin Access ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that only Super Admin can access Manage Roles page and promote users to Admin role

**Steps:**

1. Login with super admin credentials → Redirected to dashboard
2. Click "Manage Roles" from sidebar → Page opens showing all users
3. Verify 4 role cards visible (Students, Librarians, Admins, Super Admins) with correct counts
4. Click "Change" on student user → Role assignment modal opens
5. Verify all 4 roles visible with "Super Admin Only" badge on Admin and Super Admin roles
6. Select "Admin" role and click "Assign Role" → Success toast appears
7. Verify user now has purple "Admin" badge in table

---

### TC002: Manage Roles - Admin Cannot Access ❌

**Type:** Negative | **Category:** Security  
**Description:** Validate that Admin users cannot access Manage Roles page

**Steps:**

1. Login with admin credentials → Successfully logged in
2. Verify "Manage Roles" menu item NOT visible in sidebar
3. Manually navigate to `/admin/manage-roles` → 403 Forbidden error displayed

---

### TC003: Manage Roles - Librarian Cannot Access ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that Librarian users cannot access Manage Roles page

**Steps:**

1. Login with Librarian credentials → Successfully logged in
2. Verify "Manage Roles" menu item NOT visible in sidebar
3. Manually navigate to `/admin/manage-roles` → 403 Forbidden error displayed

---

### TC004: Manage Roles - Student Cannot Access ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that Student users cannot access Manage Roles page

**Steps:**

1. Login with student credentials → Successfully logged in
2. Verify no admin menu items visible → Student dashboard displayed
3. Manually navigate to `/admin/manage-roles` → 403 Forbidden error displayed

---

## Assign Librarians Page

### TC005: Assign Librarian - Super Admin Access ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that Super Admin can access and manage librarian batches

**Steps:**

1. Click "Librarian Batches" from sidebar → Assign Librarians page opens
2. Verify page shows: available students, assigned batches, all batches sections
3. Click "Create New Batch" → Create batch modal opens
4. Select exactly 5 students → Counter shows 5/5 selected
5. Click "Create Batch" → Batch created, success toast appears
6. Verify new batch appears in "All Batches" table with status "Pending" or "Inactive"
7. Click "Edit" on existing batch → Edit modal opens with current details
8. Change serving date and click "Save & Assign Date" → Batch updated successfully

---

### TC006: Assign Librarian - Admin Access ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that Admin users can access and manage librarian batches

**Steps:**

1. Login with Admin credentials → Successfully logged in
2. Click "Librarian Batches" from sidebar → Page opens successfully
3. Verify Admin can create new batches → Create batch modal works
4. Verify Admin can edit existing batches → Edit batch modal works

---

### TC007: Assign Librarian - Librarian Cannot Access ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that Librarian users cannot access Assign Librarians page

**Steps:**

1. Librarian logged in
2. Verify "Librarian Batches" menu NOT visible
3. Manually navigate to `/admin/librarians` → 403 Forbidden error displayed

---

## Borrow Logs Page

### TC008: Borrow Logs - Super Admin Full Access ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that Super Admin can view and edit borrow transactions

**Steps:**

1. Click "Borrow Logs" from sidebar → Page opens with transaction list
2. Verify table shows all borrow transactions with status badges
3. Click "Edit" button on transaction → Edit modal opens
4. Change transaction status or details → Changes can be made
5. Click "Save" in edit modal → Transaction updated successfully
6. Use search, filters, and date range filters → Filters work correctly

---

## Profile Page

### TC009: Profile Page - View Profile ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that authenticated user can view their profile details

**Steps:**

1. Open user menu → click "Profile" → Profile page opens
2. Verify Full Name and Email sections → Name and Email displayed read-only
3. Verify Update Password section presence → Password update form visible
4. Scroll to Attendance QR (if present) → Attendance QR component visible for students

---

### TC025: Profile - Update Password Success ✅

**Type:** Positive | **Category:** System  
**Description:** Validate successful password update functionality

**Steps:**

1. Navigate to Profile page → Profile displays user information
2. Scroll to "Update Password" section → Password update form visible
3. Enter current password correctly → Field accepts input
4. Enter new password (meets requirements) → Field accepts valid password
5. Confirm new password (matching) → Confirmation field accepts input
6. Click "Update Password" button → Password updated, success toast appears

---

## Admin Dashboard

### TC010: Admin Dashboard - Student Access Control ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that Student cannot access Admin Dashboard

**Steps:**

1. Student logged in
2. Attempt Admin → Dashboard or open direct URL → Access denied (403) or redirected

---

### TC011: Admin Dashboard - Metrics Render (Authorized) ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that Super Admin can view dashboard metrics

**Steps:**

1. Navigate to Admin → Dashboard → Dashboard opens
2. Verify tiles (Total Users, Academic Papers, Available Copies, Active Sessions) → Metrics visible and populated
3. Verify Today's Attendance, Active Borrows, Active Librarians → Secondary stats visible
4. Verify charts (Papers by Department, Academic Papers by Category) → Charts render successfully

---

## Academic Papers

### TC012: Academic Papers - Create Button Visibility (Super Admin) ✅

**Type:** Positive | **Category:** System  
**Description:** Validate the create button is visible for Super Admin

**Steps:**

1. Open Admin → Academic Papers → Page loads
2. Check for "Create Academic Paper" button → Button visible and enabled
3. Click "Create Academic Paper" → Create form opens
4. Fill the form → Validation passes
5. Click "Save" → Success toast; paper listed with available copy

---

### TC013: Academic Papers - Admin CRUD Allowed ✅

**Type:** Positive | **Category:** System  
**Description:** Validate Admin can perform CRUD

**Steps:**

1. Open Admin → Academic Papers → Page loads
2. Verify Create/Edit/Delete controls → Controls are available

---

### TC014: Academic Papers - Librarian Read-only ✅

**Type:** Positive | **Category:** System  
**Description:** Validate Librarian sees read-only list

**Steps:**

1. Open Admin → Academic Papers → Page loads
2. Check for Create/Edit/Delete → Controls are not visible

---

### TC015: Academic Papers - Student Denied ❌

**Type:** Negative | **Category:** System  
**Description:** Validate Student cannot access Academic Papers admin page

**Steps:**

1. Student logged in
2. Attempt Admin → Academic Papers or direct URL → Denied or redirected

---

### TC016: Academic Papers - Filters & Pagination ✅

**Type:** Positive | **Category:** System  
**Description:** Validate filters, search, and pagination

**Steps:**

1. Apply Department/Type/Year filters and search → List updates per filters
2. Change per-page size; navigate pages → Counts update; pagination works

---

## Attendance Logs

### TC017: Attendance Logs - Student Access Control ❌

**Type:** Negative | **Category:** System  
**Description:** Validate Student cannot access Attendance Logs

**Steps:**

1. Student logged in
2. Attempt Admin → Attendance Logs or open direct URL → Denied or redirected

---

### TC018: Attendance Logs - Open Scanner (Authorized) ✅

**Type:** Positive | **Category:** System  
**Description:** Validate authorized role can open QR scanner

**Steps:**

1. Go to Admin → Attendance Logs → Page loads with stats cards
2. Click "Scan QR Code" → Scanner appears; camera prompt shown

---

### TC019: Attendance Logs - Time In/Out via QR ✅

**Type:** Positive | **Category:** System  
**Description:** Validate attendance toggles between Time In and Time Out

**Steps:**

1. From scanner, scan Student Attendance QR → First scan → Time In
2. Scan same student QR again (same day) → Second scan → Time Out
3. Verify row/badge updates → Status updates (Active → Completed)

---

## Violation Management

### TC020: Violation Management - Student Access Control ❌

**Type:** Negative | **Category:** System  
**Description:** Validate Student cannot access Violation Management

**Steps:**

1. Student logged in
2. Attempt to open Violation Management page → Denied or redirected

---

### TC021: Violations - Create Violation Type ✅

**Type:** Positive | **Category:** System  
**Description:** Validate creation of a violation type

**Steps:**

1. In Violations tab, click "Add Violation" → Create drawer/modal opens
2. Enter Name, Description, Penalty Score → Validation passes
3. Click "Record" → New type appears in list; searchable

---

### TC022: Violation Transactions - Record Violation ✅

**Type:** Positive | **Category:** System  
**Description:** Validate recording a violation transaction for a student

**Steps:**

1. Open Violation Transactions tab → Transactions tab visible
2. Click "Record" and select student, type, severity, remarks → Form validation passes
3. Click "Record" → Transaction visible; credit score adjusted if applicable

---

## Notifications

### TC023: Notifications - View and Manage User Notifications ✅

**Type:** Positive | **Category:** System  
**Description:** Validate viewing the notifications list for logged-in user

**Steps:**

1. Open "Notifications" page from navigation or header icon → Page loads successfully
2. Verify notifications list visible with message, date/time, status (read/unread) → All notifications appear correctly
3. Click unread notification item → Related detail page opens; notification status changes to read
4. Use actions "Mark all as read" or "Clear notifications" → Action applied correctly; list updates

---

## Credit Score History

### TC024: Credit Score History - View and Filter ✅

**Type:** Positive | **Category:** System  
**Description:** Validate viewing and filtering credit score history

**Steps:**

1. Open "Credit Score History" page → Page loads successfully
2. Verify list/table of credit score records visible (date, score, provider, remarks) → All records listed correctly
3. Apply date range or filter (e.g., last 6 months) → List refreshes; counts update correctly
4. Clear filters or reset view → Full history list restored

---

## Email Notifications

### TC026: Email - Librarian Assignment Alert ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that admins receive email alerts for unassigned librarian duty days

**Steps:**

1. Run scheduled command or wait for 9 AM daily execution → System checks for unassigned dates 3 days ahead
2. Verify email sent to all active admins → Email sent with subject "Alert: Unassigned Librarian Duty Days"
3. Check email content shows unassigned dates → Email lists specific dates (Mon-Sat) without assignments
4. Verify urgency tags appear (TODAY, TOMORROW, X DAYS) → Dates show correct urgency indicators
5. Click "Assign Librarians Now" button in email → Redirects to `/admin/assign-librarians` page
6. Verify critical alert for 5+ unassigned days → Critical warning box appears when >= 5 days unassigned

---

### TC027: Email - Past Dates Excluded ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that past dates are excluded from librarian assignment alerts

**Steps:**

1. Run `librarian:check-assignments` command → System processes upcoming week only
2. Verify past dates not included in email → Email only shows future dates
3. Verify no negative day counts appear → All day counts are positive integers

---

## Librarian Assignment Restrictions

### TC028: Librarian Assignment - Sunday Restriction ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that Sunday dates cannot be selected for librarian duty

**Steps:**

1. Navigate to Librarian Batches page → Assign Librarians page opens
2. Click "Edit" on a batch to assign date → Edit modal opens with date picker
3. Attempt to select a Sunday date → Sunday date disabled/cannot be selected
4. If Sunday entered manually, try to save → Server validation rejects with error: "Sundays are not allowed"
5. Verify error message displays in UI → Error alert appears showing "Invalid Date: Sundays are not allowed"

---

## QR Scanner

### TC029: QR Scanner - Error Handling ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that QR scanner shows inline errors instead of modal alerts

**Steps:**

1. Open QR scanner (Attendance or Borrow Transactions) → Scanner modal opens
2. Upload invalid image file (no QR code) → Error displays in inline panel within scanner
3. Verify no blocking modal/alert appears → Error shows in dismissible panel, scanner remains usable
4. Click dismiss (×) on error panel → Error panel closes, can retry scanning
5. Upload valid QR code image → Error clears, scan processes successfully

---

## Pagination & Loading States

### TC030: Pagination - Loading States ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that pagination shows loading overlay during page transitions

**Steps:**

1. Navigate to Manage Roles page with pagination → Page displays with paginated user list
2. Click "Next Page" or page number → Loading overlay appears immediately
3. Wait for page to load → Loading overlay disappears, new page data displays
4. Verify content doesn't flash old data → Smooth transition without showing stale data

---

### TC031: User Statistics - All Users Count ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that user statistics show all users, not just current page

**Steps:**

1. Navigate to Manage Roles page → Page displays with role statistics cards
2. Verify statistics cards at top (Students, Librarians, Admins, Super Admins) → Each card shows total count across all pages
3. Change pagination to show different page → Statistics numbers remain unchanged
4. Compare statistics count with actual database count → Statistics match total users in database

---

## Password Validation

### TC032: Password - Weak Validation ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that weak passwords are rejected with visual feedback

**Steps:**

1. Navigate to password update section → Password form visible
2. Enter weak password (e.g., "12345") → Strength bar shows "Weak" in red
3. Attempt to submit form → Validation error appears with requirements
4. Enter strong password (upper, lower, number, symbol, 8+ chars) → Strength bar shows "Strong" in green
5. Submit form → Password accepted and updated successfully

---

### TC033: Rate Limiting - Password Reset ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that password reset requests are rate-limited

**Steps:**

1. Navigate to "Forgot Password" page → Page displays email input form
2. Submit email 3 times rapidly → First requests succeed
3. Attempt 4th request within time limit → Rate limit error appears: "Too many requests"
4. Verify email verification sent → Email is sent with rate limit notice

---

## Name Formatting

### TC034: Name Capitalization - Auto Format ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that first and last names are auto-capitalized

**Steps:**

1. Enter first name in lowercase (e.g., "juan") → First letter auto-capitalizes to "Juan"
2. Enter last name in lowercase (e.g., "dela cruz") → First letters capitalize to "Dela Cruz"
3. Submit form → Names stored with proper capitalization

---

## Transaction History

### TC035: Transaction History - Sidebar Visibility ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that transaction history sidebar is properly visible

**Steps:**

1. Navigate to Borrow Logs or Transaction History → Page displays with sidebar
2. Verify sidebar not hidden or cut off → Sidebar fully visible and accessible
3. Click transaction to view details → Details load in sidebar without layout issues

---

## Academic Papers - Pagination

### TC036: Academic Papers - Pagination Fix ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that academic papers table pagination works correctly

**Steps:**

1. Navigate to Academic Papers page → Papers table displays with pagination
2. Verify pagination controls appear at bottom → Pagination controls visible and functional
3. Click page 2 or next button → Table loads next set of papers correctly
4. Verify no duplicate or missing records → All papers appear exactly once across pages

---

## Export Functionality

### TC037: Export - Attendance PDF ✅

**Type:** Positive | **Category:** System  
**Description:** Validate PDF export of attendance records

**Steps:**

1. Navigate to Attendance Logs page → Page displays attendance records
2. Apply date range filter (e.g., last 30 days) → Records filtered by date
3. Click "Export PDF" button → PDF generation starts
4. Verify PDF downloads successfully → PDF file downloads with attendance data
5. Open PDF and verify content → PDF contains correct records, proper formatting, PLV branding

---

### TC038: Export - Borrow Transactions PDF ✅

**Type:** Positive | **Category:** System  
**Description:** Validate PDF export of borrow transaction records

**Steps:**

1. Navigate to Borrow Logs page → Page displays transaction records
2. Apply filters (status, date range) → Records filtered correctly
3. Click "Export PDF" button → PDF generation starts
4. Verify PDF downloads → PDF file downloads with transaction data
5. Open PDF and check content → PDF shows transactions with status, dates, borrower info

---

## Filter Consistency

### TC039: Filters - Consistency ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that filter controls are consistent across all pages

**Steps:**

1. Navigate to Academic Papers page → Filter controls visible (Department, Type, Year, Search)
2. Navigate to Borrow Logs page → Similar filter pattern with Status, Date Range, Search
3. Navigate to Attendance Logs page → Consistent filter UI with Date Range, Status, Search
4. Verify "Clear Filters" button only appears after filtering → Button hidden by default, appears after applying any filter
5. Click "Clear Filters" → All filters reset to default, button disappears

---

## Transaction Badges

### TC040: Transaction Badge - Active/Overdue Indicator ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that active/overdue badges appear on transactions

**Steps:**

1. Navigate to Borrow Logs page → Transaction list displays
2. Locate active transaction (not returned, within due date) → Transaction shows green "Active" badge
3. Locate overdue transaction (past due date) → Transaction shows red "Overdue" badge with days overdue
4. Verify badge updates when status changes → Badge reflects current status dynamically

---

## Middleware Tests

### TC041: Librarian Middleware - Admin Access Prevention ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that librarian users cannot access admin-only features

**Steps:**

1. Librarian user logged in
2. Attempt to access `/admin/manage-roles` URL → 403 Forbidden error displayed
3. Attempt to access `/admin/assign-librarians` URL → 403 Forbidden error displayed
4. Verify sidebar only shows Librarian-accessible menus → No admin-only options visible
5. Attempt API call to admin endpoint → Request rejected with 403 status

---

### TC042: Credit Score Middleware - Access Control ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that credit score middleware enforces minimum score requirements

**Steps:**

1. Student with low credit score logged in
2. Attempt to borrow a book (credit score below threshold) → Access denied message appears
3. View error message explaining credit requirement → Message shows: "Credit score too low to borrow items"
4. Navigate to Credit Score History page → Page displays current score and history
5. Admin increases student's credit score → Score updates in database
6. Student attempts to borrow again → Access granted, borrow transaction created

---

## Email Domain Validation

### TC043: Email Domain - Validation Rule ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that only @plv.edu.ph emails are accepted during registration

**Steps:**

1. Enter email with different domain (e.g., user@gmail.com) → Validation error appears
2. Verify error message → Message shows: "Email must be from @plv.edu.ph domain"
3. Enter valid PLV email (user@plv.edu.ph) → Validation passes, field accepts input
4. Submit registration form → Account created successfully

---

## Name Input Validation

### TC044: Name Input - Special Characters Filtering ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that numbers and symbols are rejected in first/last name fields

**Steps:**

1. Enter first name with numbers (e.g., "Juan123") → Validation error or characters filtered out
2. Enter last name with symbols (e.g., "Dela@Cruz") → Validation error appears: "Only letters allowed"
3. Enter valid names (letters only) → Validation passes
4. Submit form → Names stored correctly without special characters

---

## Attendance QR Integrity

### TC045: Attendance QR - Database Integrity ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that QR code scanning maintains database integrity

**Steps:**

1. Scan student attendance QR code → Time In recorded with timestamp
2. Verify database record created → Attendance record exists with correct user_id and date
3. Scan same QR code again (same day) → Time Out recorded, same record updated
4. Verify no duplicate records created → Only one attendance record per student per day
5. Attempt to scan tampered/invalid QR code → Error displayed: "Invalid QR code"

---

## Real-time Updates

### TC046: Wire Polling - Real-time Countdown ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that time-sensitive data updates in real-time using wire polling

**Steps:**

1. Navigate to Active Users (attendance) page → Page displays users with "Time Remaining" countdown
2. Observe countdown timer for 1 minute → Timer decrements every second/minute in real-time
3. Verify countdown reaches zero → Status changes to "Timed Out" or triggers action
4. Check borrow transactions page → Overdue status updates without page refresh

---

## Date Picker Validation

### TC047: Date Picker - Prevent Past Date Selection ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that past dates cannot be selected in librarian assignment date picker

**Steps:**

1. Navigate to Assign Librarians page → Page displays with available batches
2. Click "Edit" on a batch → Edit modal opens with date picker
3. Attempt to select yesterday's date → Past dates disabled/grayed out
4. Try to manually enter past date → Validation error: "Cannot select past dates"

---

## Responsive Design

### TC048: Welcome Page - Responsive Design ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that welcome/landing page is responsive on laptop screens

**Steps:**

1. Open welcome page on 1920x1080 resolution → Page displays correctly without horizontal scroll
2. Resize browser to 1366x768 (standard laptop) → Layout adjusts, all content visible
3. Test on 1280x800 resolution → No content cut off, responsive design works
4. Verify background, images, and text remain readable → Design maintains visual hierarchy

---

## Lazy Loading

### TC049: Lazy Loading - Table Data ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that tables use lazy loading with placeholders for better UX

**Steps:**

1. Navigate to Academic Papers or Borrow Logs → Page shows loading skeleton/placeholder
2. Wait for data to load → Placeholder replaced with actual data smoothly
3. Apply filter to trigger re-load → Loading placeholder appears during filter processing
4. Verify no blank/white screen during load → Skeleton maintains page structure

---

### TC050: Lazy Loading - Filter Application ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that applying filters shows loading state before results

**Steps:**

1. Open page with filter controls → Filters visible, no loading state initially
2. Select department filter → Loading overlay/spinner appears
3. Wait for filtered results → Results update, loading state clears
4. Change multiple filters quickly → Each change shows appropriate loading feedback

---

## Super Admin Role Check

### TC051: Super Admin Check - Role Assignment ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that only Super Admin can assign Admin and Super Admin roles

**Steps:**

1. Navigate to Manage Roles page → Page displays with all users
2. Click "Change" on a student user → Role modal opens with all 4 roles visible
3. Verify "Admin" and "Super Admin" show badge: "Super Admin Only" → Badge visible on restricted roles
4. Log out and login as Admin user → Admin successfully logged in
5. Navigate to Manage Roles (if accessible) → Page not accessible or restricted roles disabled

---

## Page Title

### TC052: Web Page Title - Dynamic Updates ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that browser tab title updates based on current page

**Steps:**

1. Navigate to Dashboard → Browser tab shows "Dashboard - CEIT Library"
2. Navigate to Manage Roles → Tab title updates to "Manage Roles - CEIT Library"
3. Navigate to Borrow Logs → Tab title shows "Borrow Logs - CEIT Library"
4. Navigate to Profile → Tab title shows "Profile - CEIT Library"

---

## Email Verification

### TC053: Email Verification - Account Activation ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that new users must verify email before accessing system

**Steps:**

1. Complete registration form with @plv.edu.ph email → Account created, verification email sent
2. Attempt to login without verifying email → Login blocked with message: "Please verify your email"
3. Check email inbox for verification link → Email received with "Verify Your CEIT Library Account" subject
4. Click verification link in email → Redirected to system with success message
5. Login with verified account → Login successful, dashboard accessible

---

### TC054: Welcome Email - New User ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that welcome email is sent after email verification

**Steps:**

1. Verify email address via verification link → Email verified successfully
2. Wait 30 seconds (email has delay) → Welcome email queued and processed
3. Check email inbox → Welcome email received with subject "Welcome to CEIT Library Management System"
4. Open welcome email → Email contains PLV branding and system introduction

---

### TC055: Overdue Email - Automated Notification ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that overdue email is sent when borrowed item exceeds due date

**Steps:**

1. System runs scheduled overdue check → Overdue transactions identified
2. Verify email sent to borrower → Overdue notification email sent
3. Check email content → Email lists overdue items with return deadline
4. Verify credit score penalty applied → Credit score decreased according to policy

---

### TC056: Password Reset - Email Flow ✅

**Type:** Positive | **Category:** System  
**Description:** Validate password reset email functionality

**Steps:**

1. Navigate to "Forgot Password" page → Page displays with email input
2. Enter registered email address → Email validation passes
3. Click "Send Reset Link" → Success message appears
4. Check email inbox → Email received with subject "Reset Your CEIT Library Password"
5. Click reset link in email → Password reset page opens with token
6. Enter new password and confirm → Password updated successfully

---

## Borrow Transactions

### TC057: Borrow Transaction - Create New ✅

**Type:** Positive | **Category:** System  
**Description:** Validate creating a new borrow transaction via QR scan

**Steps:**

1. Navigate to Borrow Transactions page → Page displays with transaction list
2. Click "Scan QR Code" button → QR scanner modal opens
3. Scan student's borrow QR code → Student information displays in scanner
4. Select academic paper from available list → Paper selected, due date calculated
5. Click "Confirm Borrow" → Transaction created, success toast appears
6. Verify transaction appears in list → New transaction visible with "Active" status
7. Verify paper copy count decremented → Available copies reduced by 1

---

### TC058: Borrow Transaction - Return Item ✅

**Type:** Positive | **Category:** System  
**Description:** Validate returning a borrowed item

**Steps:**

1. Locate active transaction in Borrow Logs → Transaction visible with "Active" status
2. Click "Edit" button on transaction → Edit modal opens with transaction details
3. Change status to "Returned" → Status dropdown shows "Returned" option
4. Click "Save" → Transaction updated, success message appears
5. Verify status badge changed → Badge now shows "Returned" with green color
6. Verify paper copy count increased → Available copies increased by 1

---

### TC059: Borrow Transaction - Credit Score Block ❌

**Type:** Negative | **Category:** System  
**Description:** Validate that students with low credit score cannot borrow

**Steps:**

1. Librarian scans student QR code for borrow → Student info displays
2. Attempt to select academic paper → Error appears: "Credit score too low"
3. Verify current credit score shown → Score displayed with threshold requirement
4. Attempt to proceed anyway → Transaction blocked, cannot continue

---

## Attendance Management

### TC060: Attendance - Manual Declare Forgot Timeout ✅

**Type:** Positive | **Category:** System  
**Description:** Validate declaring forgot timeout for active attendance

**Steps:**

1. Navigate to Active Users tab in Attendance Logs → Active users list displays
2. Locate student who forgot to time out → Student visible in active users list
3. Click "Declare Forgot Timeout" action → Confirmation modal appears
4. Confirm action → Attendance marked complete, violation recorded
5. Verify student no longer in active list → Student removed from active users
6. Check violation transaction created → Violation recorded with "Forgot Timeout" type

---

## Violation Management

### TC061: Violation - Record New Violation ✅

**Type:** Positive | **Category:** System  
**Description:** Validate recording a new violation for a student

**Steps:**

1. Navigate to Violation Management page → Page displays with Violations and Transactions tabs
2. Click "Violation Transactions" tab → Transactions list displays
3. Click "Record Violation" button → Record modal opens
4. Select student from dropdown → Student selected
5. Select violation type (e.g., "Noise Disturbance") → Type selected, penalty score shows
6. Select severity and add remarks → Form completed
7. Click "Record" → Violation saved, credit score updated
8. Verify transaction appears in list → New violation visible with date and details

---

## Academic Paper Management

### TC062: Academic Paper - Create with Multiple Copies ✅

**Type:** Positive | **Category:** System  
**Description:** Validate creating academic paper with multiple copies

**Steps:**

1. Navigate to Academic Papers page → Page displays papers list
2. Click "Create Academic Paper" → Create form modal opens
3. Fill title, authors, department, type, year → Form accepts inputs
4. Set total copies to 5 → Copies field accepts number
5. Click "Save" → Paper created successfully
6. Verify paper appears in list → New paper visible with "5 available" copies
7. Create borrow transaction for this paper → Available copies decrements to 4

---

### TC063: Academic Paper - Search and Filter ✅

**Type:** Positive | **Category:** System  
**Description:** Validate search and filter functionality for academic papers

**Steps:**

1. Navigate to Academic Papers page → Papers list displays
2. Enter search term in search box (e.g., "Machine Learning") → Results filter in real-time
3. Select Department filter (e.g., "Computer Science") → List shows only CS papers
4. Select Type filter (e.g., "Thesis") → Results further filtered to CS Thesis papers
5. Select Year filter (e.g., "2024") → Only 2024 CS Thesis papers shown
6. Click "Clear Filters" → All filters reset, full list restored

---

## Dashboard Statistics

### TC064: Dashboard - Statistics Cards ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that dashboard statistics display accurate real-time data

**Steps:**

1. Navigate to Admin Dashboard → Dashboard loads with statistics cards
2. Verify "Total Users" card displays correct count → Count matches database user count
3. Verify "Academic Papers" card → Count matches total papers in system
4. Verify "Available Copies" card → Count shows total available (not borrowed) copies
5. Verify "Active Sessions" card → Shows current active attendances today
6. Create new user or paper → Dashboard statistics update automatically

---

### TC065: Dashboard - Charts Rendering ✅

**Type:** Positive | **Category:** System  
**Description:** Validate that dashboard charts render correctly with data

**Steps:**

1. Navigate to Admin Dashboard → Dashboard loads
2. Scroll to "Papers by Department" chart → Bar/pie chart displays
3. Verify chart shows all departments with data → Each department appears with correct count
4. View "Academic Papers by Category" chart → Chart renders with categories
5. Hover over chart segments → Tooltip shows exact numbers

---

## Librarian Batch Management

### TC066: Librarian Batch - Create with 5 Students ✅

**Type:** Positive | **Category:** System  
**Description:** Validate creating librarian batch requires exactly 5 students

**Steps:**

1. Navigate to Librarian Batches page → Page displays available students and batches
2. Click "Create New Batch" → Modal opens with student selection list
3. Select only 2 students → Counter shows "2/5 selected", Create button disabled
4. Select 3 more students (total 5) → Counter shows "5/5 selected", Create button enabled
5. Click "Create Batch" → Batch created successfully
6. Verify batch appears in "All Batches" table → New batch listed with status "Pending/Inactive"

---

### TC067: Librarian Batch - Assign to Date ✅

**Type:** Positive | **Category:** System  
**Description:** Validate assigning a librarian batch to specific date

**Steps:**

1. Navigate to Librarian Batches page → Page displays
2. Click "Edit" on a batch with no assigned date → Edit modal opens
3. Select a future weekday date (not Sunday) → Date picker accepts date
4. Click "Save & Assign Date" → Batch assigned to date, success message appears
5. Verify batch status changed to "Active" → Status badge updated
6. Check calendar/schedule view → Date shows assigned batch

---

## Rules and Regulations

### TC068: Rules and Regulations - View List ✅

**Type:** Positive | **Category:** System  
**Description:** Validate viewing library rules and regulations

**Steps:**

1. Navigate to Rules & Regulations page → Page displays rules organized by headers
2. Verify rule headers visible → Headers like "General Conduct", "Borrowing Policy" shown
3. Verify individual rules listed under headers → Rules displayed with descriptions
4. Scroll through all rules → All rules accessible and readable

---

## Inventory Management

### TC069: Inventory Management - View Inventory List ✅

**Type:** Positive | **Category:** System  
**Description:** Validate viewing inventory items list

**Steps:**

1. Navigate to Inventory Management page → Inventory list displays
2. Verify table shows item name, quantity, status → All columns visible with data
3. Check search functionality → Search filters inventory items
4. Verify pagination works → Pages load correctly with items

---

## Student Dashboard

### TC070: Student Dashboard - View Personal Stats ✅

**Type:** Positive | **Category:** System  
**Description:** Validate student can view their dashboard with personal statistics

**Steps:**

1. Navigate to Dashboard → Student dashboard displays
2. Verify credit score card visible → Current credit score shown
3. Verify active borrows section → Shows currently borrowed items if any
4. Verify attendance history section → Recent attendance records visible
5. Check violation history (if any) → Past violations displayed

---

### TC071: Student - View Borrowed Books History ✅

**Type:** Positive | **Category:** System  
**Description:** Validate student can view their borrow history

**Steps:**

1. Navigate to "My Borrows" or Borrow History page → Page displays personal borrow transactions
2. Verify only student's own transactions shown → No other students' data visible
3. Check status badges (Active, Returned, Overdue) → Status correctly displayed for each transaction
4. Verify due dates shown → Due date visible for active borrows

---

## QR Code Generation

### TC072: QR Code - Student Attendance QR ✅

**Type:** Positive | **Category:** System  
**Description:** Validate student attendance QR code generation

**Steps:**

1. Navigate to Profile page → Profile displays
2. Scroll to Attendance QR section → QR code visible
3. Verify QR code displays unique pattern → QR code rendered correctly
4. Scan QR with scanner → QR contains valid attendance data

---

### TC073: QR Code - Borrow QR Generation ✅

**Type:** Positive | **Category:** System  
**Description:** Validate student borrow QR code generation

**Steps:**

1. Navigate to Profile or Borrow section → Page displays
2. Locate Borrow QR code → QR code visible
3. Verify QR contains student identification → QR includes student ID and verification data
4. Librarian scans QR → Student information retrieved correctly

---

## Session Management

### TC074: Session Timeout - Auto Logout ✅

**Type:** Positive | **Category:** System  
**Description:** Validate user is logged out after session timeout

**Steps:**

1. Login and remain inactive for session duration (120 minutes) → User remains logged in during session
2. Wait for session to expire → Session expires after configured time
3. Attempt to navigate to protected page → Redirected to login page
4. Verify session cleared → Session data removed from storage

---

### TC075: Middleware - Guest Only Routes ❌

**Type:** Negative | **Category:** System  
**Description:** Validate authenticated users cannot access guest-only pages

**Steps:**

1. User logged in
2. Attempt to navigate to `/login` URL → Redirected to dashboard
3. Attempt to navigate to `/register` URL → Redirected to dashboard
4. Attempt to navigate to `/forgot-password` URL → Redirected to dashboard

---

## Author Management

### TC076: Author Management - Add New Author ✅

**Type:** Positive | **Category:** System  
**Description:** Validate adding new author to academic paper

**Steps:**

1. Open academic paper create/edit form → Form displays
2. Click "Add Author" button → Author input field appears
3. Enter author name → Name accepted
4. Add multiple authors (e.g., 3 authors) → All authors listed
5. Save paper → Authors saved with paper

---

## Department Filter

### TC077: Department Filter - All Departments ✅

**Type:** Positive | **Category:** System  
**Description:** Validate department filter shows all configured departments

**Steps:**

1. Navigate to Academic Papers → Page displays
2. Open Department filter dropdown → Dropdown shows all departments
3. Verify departments match config file → All departments from departments.php shown
4. Select a department → Papers filtered by department

---

## Search Functionality

### TC078: Search - Real-time Results ✅

**Type:** Positive | **Category:** System  
**Description:** Validate search shows real-time results as user types

**Steps:**

1. Click in search input field → Field focused, ready for input
2. Type first 3 characters (e.g., "Mac") → Results start filtering in real-time
3. Continue typing (e.g., "Machine Learning") → Results narrow down with each character
4. Clear search input → All results restored

---

## Modal Behavior

### TC079: Modal - Close on Escape Key ✅

**Type:** Positive | **Category:** System  
**Description:** Validate modals can be closed with Escape key

**Steps:**

1. Open any modal (Create, Edit, Scanner, etc.) → Modal displays
2. Press Escape key on keyboard → Modal closes
3. Verify background restored → Overlay removed, page interactive
4. Test with different modals → All modals respond to Escape key

---

### TC080: Modal - Close on Backdrop Click ✅

**Type:** Positive | **Category:** System  
**Description:** Validate modals close when clicking outside modal area

**Steps:**

1. Open any modal → Modal displays with dark backdrop
2. Click on backdrop (outside modal content) → Modal closes
3. Verify form data cleared if not saved → Unsaved changes discarded

---

## Form Validation

### TC081: Form Validation - Required Fields ❌

**Type:** Negative | **Category:** System  
**Description:** Validate required field validation on forms

**Steps:**

1. Open form modal → Form displays
2. Leave required fields empty → Fields show as empty
3. Attempt to submit form → Validation errors appear
4. Verify error messages indicate required fields → Messages like "This field is required" shown
5. Fill all required fields → Validation passes
6. Submit form → Form saves successfully

---

## Toast Notifications

### TC082: Toast Notifications - Success Messages ✅

**Type:** Positive | **Category:** System  
**Description:** Validate success toast notifications appear after actions

**Steps:**

1. Create new record (paper, user, batch, etc.) → Action completes
2. Verify success toast appears → Green toast with success message displays
3. Wait for auto-dismiss → Toast disappears after 3-5 seconds
4. Perform update operation → Success toast appears again

---

### TC083: Toast Notifications - Error Messages ✅

**Type:** Positive | **Category:** System  
**Description:** Validate error toast notifications appear on failures

**Steps:**

1. Trigger validation error (e.g., submit invalid form) → Validation fails
2. Verify error toast appears → Red toast with error message displays
3. Read error message → Message clearly states the problem
4. Dismiss toast manually if X button present → Toast closes on click

---

## Navigation

### TC084: Breadcrumb Navigation - Trail Display ✅

**Type:** Positive | **Category:** System  
**Description:** Validate breadcrumb navigation shows current path

**Steps:**

1. Navigate to nested page (e.g., Admin > Manage Roles) → Breadcrumb shows "Dashboard > Manage Roles"
2. Click breadcrumb link to go back → Navigates to parent page
3. Verify breadcrumb updates on page change → Trail reflects current location

---

### TC085: Sidebar - Active Menu Highlighting ✅

**Type:** Positive | **Category:** System  
**Description:** Validate active menu item is highlighted in sidebar

**Steps:**

1. Navigate to Dashboard → Dashboard menu item highlighted
2. Navigate to Academic Papers → Academic Papers menu item highlighted, Dashboard unhighlighted
3. Navigate to different pages → Active page always highlighted

---

### TC086: Sidebar - Collapse/Expand ✅

**Type:** Positive | **Category:** System  
**Description:** Validate sidebar can be collapsed for more screen space

**Steps:**

1. Locate sidebar toggle/hamburger button → Button visible
2. Click toggle button → Sidebar collapses, icons only visible
3. Click toggle again → Sidebar expands, full menu visible
4. Verify preference saved → State persists across page navigation

---

## Profile Management

### TC087: Profile - View User Information ✅

**Type:** Positive | **Category:** System  
**Description:** Validate user can view their profile information

**Steps:**

1. Navigate to Profile page → Profile displays
2. Verify Full Name displayed → Name shown correctly
3. Verify Email displayed → Email shown correctly
4. Verify Role badge visible → Role displayed (Student, Admin, etc.)
5. Check account status → Status shown (Active)

---

## Theme

### TC088: Dark Mode - Toggle (If Implemented) ✅

**Type:** Positive | **Category:** System  
**Description:** Validate dark mode toggle if feature exists

**Steps:**

1. Locate theme toggle (header/profile) → Toggle button visible
2. Click to enable dark mode → Interface changes to dark theme
3. Verify colors inverted appropriately → Dark backgrounds, light text
4. Toggle back to light mode → Returns to light theme

---

## Data Export

### TC089: Data Export - CSV Format ✅

**Type:** Positive | **Category:** System  
**Description:** Validate exporting data in CSV format

**Steps:**

1. Navigate to page with export option (e.g., Users, Papers) → Page displays with data
2. Click "Export CSV" button → CSV download initiates
3. Open downloaded CSV file → File opens in spreadsheet software
4. Verify data matches table → All columns and rows present

---

## Duplicate Prevention

### TC090: Duplicate Prevention - Academic Paper ❌

**Type:** Negative | **Category:** System  
**Description:** Validate system prevents duplicate academic papers

**Steps:**

1. Paper with title "AI Research 2024" exists
2. Attempt to create new paper with same title → Form accepts input
3. Submit form → Validation error appears
4. Verify error message → Message: "Paper with this title already exists"
5. Change title to unique value → Validation passes

---

## Test Status Legend

- ✅ = Positive Test Case
- ❌ = Negative Test Case

**Total Test Cases:** 90  
**Positive Tests:** 68  
**Negative Tests:** 22
