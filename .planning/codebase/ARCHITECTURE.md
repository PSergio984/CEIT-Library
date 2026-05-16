<!-- refreshed: 2025-05-16 -->
# Architecture

**Analysis Date:** 2025-05-16

## System Overview

```text
┌─────────────────────────────────────────────────────────────┐
│                      Presentation Layer                      │
│        (Blade, DaisyUI, Alpine.js, Livewire Templates)       │
│                `resources/views/livewire`                    │
└────────┬──────────────────┬──────────────────┬──────────────┘
         │                  │                  │
         ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
│       (Livewire Components, Volt Components, Forms)          │
│         `app/Livewire`, `resources/views/livewire`           │
└────────┬─────────────────────────────────────┬──────────────┘
         │                                     │
         ▼                                     ▼
┌─────────────────────────────────────────────────────────────┐
│                      Domain Layer                            │
│           (Eloquent Models, Notifications, Mail)             │
│            `app/Models`, `app/Notifications`                 │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                   Infrastructure Layer                       │
│              (MySQL Database, File Storage)                  │
│                `config/database.php`                         │
└─────────────────────────────────────────────────────────────┘
```

## Component Responsibilities

| Component | Responsibility | File |
|-----------|----------------|------|
| Livewire Pages | Handle page-level state and user interaction | `app/Livewire/Pages/` |
| Volt Components | Single-file components for Auth and Layout | `resources/views/livewire/` |
| Livewire Forms | Encapsulate validation and form state | `app/Livewire/Forms/` |
| Eloquent Models | Data persistence and relationship management | `app/Models/` |
| Middleware | Request filtering and security (Roles/Auth) | `app/Http/Middleware/` |
| Gates | Granular permission control | `app/Providers/AppServiceProvider.php` |
| QR System | Encrypted QR generation and scanning logic | `app/Livewire/QrScanner.php` |

## Pattern Overview

**Overall:** Livewire-centric (MVVM-like)

**Key Characteristics:**
- **Component-Based UI:** Almost all pages are standalone Livewire components.
- **Minimal Controllers:** Controllers are used only for specialized tasks (e.g., `QrCodeDownloadController`).
- **Alpine.js Interactivity:** Used for client-side state (modals, drawers) to minimize server roundtrips.
- **Role-Based Access Control (RBAC):** Three primary roles (Student, Librarian, Super Admin) managed via Gates.

## Layers

**Presentation Layer:**
- Purpose: Renders the UI and handles client-side interactivity.
- Location: `resources/views/livewire`, `resources/views/components`
- Contains: Blade templates, DaisyUI components, Alpine.js logic.
- Depends on: Application Layer (Livewire state).
- Used by: End users.

**Application Layer:**
- Purpose: Orchestrates business logic and maintains UI state.
- Location: `app/Livewire`, `app/Livewire/Forms`, `resources/views/livewire` (Volt)
- Contains: Livewire component classes, Volt anonymous classes, Form objects.
- Depends on: Domain Layer (Models).
- Used by: Presentation Layer.

**Domain Layer:**
- Purpose: Represents the business entities and rules.
- Location: `app/Models`, `app/Notifications`, `app/Mail`
- Contains: Eloquent models, Notification classes, Mailables.
- Depends on: Infrastructure Layer.
- Used by: Application Layer.

## Data Flow

### Primary Request Path (Livewire Page)

1. **Route Match:** `routes/web.php` maps a URL to a Livewire component (e.g., `AdminAcademicPaperIndex::class`).
2. **Middleware:** Request passes through `auth`, `verified`, and custom role middleware (`librarian.or.admin`).
3. **Component Mount:** Livewire instantiates the component and calls `mount()`.
4. **Data Fetching:** Component uses Eloquent models to fetch data.
5. **Initial Render:** Blade template is rendered and sent to the browser.
6. **Subsequent Interactions:** `wire:model` or `wire:click` triggers an AJAX request to the server, updating the component's state and re-rendering only the affected part of the DOM.

### QR Scanning Flow

1. **Scanner Entry:** User navigates to `/admin/test-qr` (or production scanner page).
2. **Scan Capture:** `QrScanner.php` component handles the QR code reading.
3. **Decryption:** The scanned data is decrypted using `decryptQrData` trait.
4. **Validation:** Canonical message is validated for integrity.
5. **Transaction Update:** `BorrowTransaction` or `Attendance` record is updated based on the QR content.

**State Management:**
- **Server-side:** Managed by Livewire component properties and serialized between requests.
- **Client-side:** Managed by Alpine.js (`x-data`) for UI-only state (e.g., modal visibility).

## Key Abstractions

**Livewire Forms:**
- Purpose: Encapsulates validation rules and data properties for complex forms.
- Examples: `app/Livewire/Forms/AcademicPaperForm.php`, `app/Livewire/Forms/ViolationForm.php`
- Pattern: Livewire Form Objects.

**RBAC System:**
- Purpose: Manages user permissions across the system.
- Examples: `app/Models/User.php`, `app/Providers/AppServiceProvider.php`
- Pattern: Role-based permissions with Laravel Gates.

## Entry Points

**Web Routes:**
- Location: `routes/web.php`
- Triggers: Browser navigation.
- Responsibilities: Map URLs to Livewire components and apply middleware.

**Console Commands:**
- Location: `routes/console.php` (and `app/Console/Commands/`)
- Triggers: Scheduled tasks or CLI invocation.
- Responsibilities: Periodic tasks like checking for overdue transactions.

## Architectural Constraints

- **Threading:** Single-threaded (standard PHP execution model).
- **Global state:** Avoided; state is encapsulated within Livewire components or fetched via Eloquent.
- **Circular imports:** Managed by Laravel's service container and autoloading.
- **Volt vs Livewire:** Volt is preferred for Layout/Auth components, while standard Livewire is used for complex page components.

## Anti-Patterns

### Heavy Server Roundtrips for UI State

**What happens:** Using `wire:click` just to open a modal.
**Why it's wrong:** Causes unnecessary network lag for a purely UI action.
**Do this instead:** Use Alpine.js `x-data="{ open: false }"` to handle visibility locally.

### Manual Boolean Admin Checks

**What happens:** Using `if ($user->is_admin)` throughout the code.
**Why it's wrong:** Fragile and doesn't support granular roles (Librarian vs Super Admin).
**Do this instead:** Use Gates like `@can('manage-academic-papers')` or model methods like `$user->isSuperAdmin()`.

## Error Handling

**Strategy:** Exception-based with automatic UI feedback via Livewire.

**Patterns:**
- **Validation Errors:** Handled by Livewire's `$this->validate()` and displayed using `error` directives in Blade.
- **Exceptions:** Logged and displayed via standard Laravel exception handler (configured in `bootstrap/app.php`).

## Cross-Cutting Concerns

**Logging:** Uses Laravel's logging facade (`Log::info()`, `Log::error()`).
**Validation:** Centralized in Livewire Form objects or component `rules()` methods.
**Authentication:** Managed by Laravel Breeze (integrated with Livewire/Volt).

---

*Architecture analysis: 2025-05-16*
