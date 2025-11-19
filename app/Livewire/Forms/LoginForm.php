<?php

namespace App\Livewire\Forms;

use App\Rules\PlvEmailDomain;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    /**
     * Cached validated login attempt limit.
     * Validated once per request lifecycle to avoid repeated config reads and logging.
     */
    private static ?int $validatedLoginLimit = null;

    #[Validate(['required', 'string', 'email', new PlvEmailDomain])]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            $decaySeconds = config('throttle.login.decay', 60);
            RateLimiter::hit($this->throttleKey(), $decaySeconds);

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        // Validate and cache the login limit on first access
        if (self::$validatedLoginLimit === null) {
            $raw = config('throttle.login.limit', 5);
            $maxAttempts = (int) $raw;

            if ($maxAttempts < 1) {
                Log::warning('Invalid throttle.login.limit config: ' . var_export($raw, true) . '. Falling back to 5.');
                self::$validatedLoginLimit = 5;
            } elseif ($maxAttempts > 100) {
                Log::warning('Excessive throttle.login.limit config: ' . var_export($raw, true) . '. Capping to 100.');
                self::$validatedLoginLimit = 100;
            } else {
                self::$validatedLoginLimit = $maxAttempts;
            }
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::$validatedLoginLimit)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }
}
