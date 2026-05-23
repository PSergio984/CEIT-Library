# Phase 02: Security Hardening - Pattern Mapping

This document maps the security patterns to be applied to the files modified or created during Phase 02 (Security Hardening).

---

## 1. File Classifications and Analog Mapping

| File Path | Role | Data Flow | Closest Analog | Why This Analog? |
| :--- | :--- | :--- | :--- | :--- |
| `app/Http/Middleware/CheckAccountStatus.php` | middleware | request-response | `app/Http/Middleware/LibrarianOrAdmin.php` | Standard class-based HTTP middleware executing authentication and user checks before passing to next middleware. |
| `bootstrap/app.php` | config | transform | `bootstrap/app.php` | Self-referential config modification for registering new middleware in the web group and defining middleware aliases. |
| `app/Livewire/Pages/Student/AttendanceQr.php` | component | request-response | `app/Livewire/Pages/Student/AttendanceQr.php` | Modifying static QR code generation logic to dynamic time-based polling (polling using standard Livewire state). |
| `app/Livewire/QrScanner.php` | component | request-response | `app/Livewire/QrScanner.php` | Adding strict signature validation, clock drift checks, and nonce verification (replay mitigation). |
| `app/Livewire/Pages/Admin/AdminBorrowTransactions.php` | component | CRUD | `app/Livewire/Pages/Admin/AdminBorrowTransactions.php` | Standardizing custom validation rules (`NoHtmlTags`, `SafeText`) on search, inputs, and adding authorization guards at component levels. |

---

## 2. Pattern Blueprint Excerpts

### A. Middleware Pattern (CheckAccountStatus)
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->status !== 'Active') {
            Auth::logout();
            
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Your account has been deactivated. Please contact an administrator.');
        }

        return $next($request);
    }
}
```

### B. Global Middleware Registration Pattern (bootstrap/app.php)
```php
<?php

use App\Http\Middleware\CheckAccountStatus;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    // ...
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            CheckAccountStatus::class,
        ]);
        
        $middleware->alias([
            'librarian.or.admin' => \App\Http\Middleware\LibrarianOrAdmin::class,
        ]);
    })
    // ...
```

### C. Livewire Authorization & Validation Rule Integration Pattern (AdminBorrowTransactions)
```php
<?php

namespace App\Livewire\Pages\Admin;

use App\Rules\NoHtmlTags;
use App\Rules\SafeText;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Gate;

class AdminBorrowTransactions extends AdminComponent
{
    // Simple filter / search validations using inline attributes
    #[Validate(['nullable', 'string', 'max:100', new NoHtmlTags, new SafeText])]
    public string $search = '';

    #[Validate(['nullable', 'string', 'max:50', new NoHtmlTags, new SafeText])]
    public string $statusFilter = '';

    // Enforce Gate authorization checks in mount and action methods for defense-in-depth
    public function mount(): void
    {
        if (! Gate::allows('view-borrow-logs')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function processTransaction(int $transactionId): void
    {
        if (! Gate::allows('manage-borrow-logs')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Action execution logic...
    }
}
```

### D. Dynamic QR Code Payload Pattern with Short-Lived Timestamp (AttendanceQr)
```php
<?php

namespace App\Livewire\Pages\Student;

use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class AttendanceQr extends Component
{
    use CreatesQrCanonicalMessage;

    public string $qrCodeData = '';
    public int $generatedAt;
    public string $nonce = '';

    public function mount(): void
    {
        if (! Auth::check()) {
            abort(401);
        }
        $this->generateQr();
    }

    /**
     * Periodically called via wire:poll to refresh the QR code and limit replay attack windows
     */
    public function refreshQr(): void
    {
        $this->generateQr();
    }

    protected function generateQr(): void
    {
        $user = Auth::user();
        $this->generatedAt = time();
        $this->nonce = Str::random(16);

        $payload = [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            't' => $this->generatedAt,
            'nonce' => $this->nonce,
        ];

        // Creates a deterministic HMAC signed string or encrypted token using CreatesQrCanonicalMessage
        $this->qrCodeData = $this->encryptPayload($payload);
    }
}
```

### E. Timestamp and Nonce Verification Pattern (QrScanner)
```php
<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QrScanner
{
    public const VALIDATION_SUCCESS = 'success';
    public const VALIDATION_INVALID = 'invalid';
    public const VALIDATION_EXPIRED = 'expired';

    protected function decryptAndValidateAttendanceData(string $encryptedData): array|string
    {
        try {
            $data = $this->decryptPayload($encryptedData);

            if (! isset($data['t']) || ! isset($data['nonce'])) {
                return self::VALIDATION_INVALID;
            }

            // 1. Verify temporal validity (Short-lived timestamp: max 90s future skew for server drift / 60s past skew)
            $scanTime = time();
            $qrTime = (int) $data['t'];
            $timeDiff = $scanTime - $qrTime;

            if ($timeDiff < -60 || $timeDiff > 90) {
                return self::VALIDATION_EXPIRED;
            }

            // 2. Prevent replay attacks using a cache lock on the nonce
            $nonceKey = "qr_nonce:{$data['nonce']}";
            if (Cache::has($nonceKey)) {
                return self::VALIDATION_INVALID; // Already scanned
            }
            
            // Cache the nonce for the duration of the expiration window (e.g. 120s)
            Cache::put($nonceKey, true, 120);

            return $data;
        } catch (\Exception $e) {
            Log::error('QR scanning decryption failure: ' . $e->getMessage());
            return self::VALIDATION_INVALID;
        }
    }
}
```

---

## 3. General Architecture & Security Rules

1. **Defense-in-Depth Authorization**: Gate check must be enforced in both middleware layers and at the very beginning of Livewire `mount()` and execution methods. Do not rely solely on route-level protection.
2. **Strict Bound Validations**: Ensure every string field has clear constraints (e.g. `max:255`).
3. **No Unsanitized Inputs**: Standardize using `NoHtmlTags` and `SafeText` validation rules.
4. **Action-level Throttling**: Throttle user actions dynamically via custom back-end checks using cache/IP signatures, or use Laravel's standard RateLimiter for authentication/login components to prevent bruteforce attempts.
