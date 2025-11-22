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
    protected static ?int $validatedLoginLimit = null;

    #[Validate(['required', 'string', 'email', new PlvEmailDomain])]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Validate and return [limit, decay] for a given throttle config.
     * Applies sensible bounds and logs warnings if invalid.
     *
     * @param mixed $limitRaw
     * @param mixed $decayRaw
     * @param string $context (e.g. 'login', 'verify_email')
     * @return array{int,int} [limit, decay]
     */
    public static function validatedThrottleConfig($limitRaw, $decayRaw, string $context = 'login'): array
    {
        static $cache = [];
        $key = $context . '|' . var_export($limitRaw, true) . '|' . var_export($decayRaw, true);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $limit = (int) $limitRaw;
        $decay = (int) $decayRaw;
        $defaultLimit = 5;
        $defaultDecay = 60;

        if ($limit < 1) {
            Log::warning("Invalid throttle.$context.limit config: " . var_export($limitRaw, true) . ". Falling back to $defaultLimit.");
            $limit = $defaultLimit;
        } elseif ($limit > 100) {
            Log::warning("Excessive throttle.$context.limit config: " . var_export($limitRaw, true) . ". Capping to 100.");
            $limit = 100;
        }

        if ($decay < 1) {
            Log::warning("Invalid throttle.$context.decay config: " . var_export($decayRaw, true) . ". Falling back to $defaultDecay.");
            $decay = $defaultDecay;
        }

        $cache[$key] = [$limit, $decay];
        return $cache[$key];
    }

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
