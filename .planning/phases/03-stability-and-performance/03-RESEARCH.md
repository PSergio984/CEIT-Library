# Phase 03: Stability & Performance - Research

**Researched:** 2026-06-02
**Domain:** Frontend UI/UX, Laravel Testing, Backend Stability
**Confidence:** HIGH

## Summary

This phase focuses on transforming the entry point of the application into a premium, high-conversion landing page for the "PLV CEIT Library" and resolving a significant technical debt of 50 failing tests. The redesign utilizes a "Liquid Glass" theme (translucent overlays, dynamic blurs) paired with scroll-driven narratives, extending the aesthetic to the Auth pages (Login, Register, Password Reset) for cohesion. Simultaneously, the underlying test suite must be rectified, primarily resolving `ComponentNotFoundException`s, 403 vs 302 middleware mismatch expectations, and missing routes.

**Primary recommendation:** Implement the Landing Page and Auth Pages using Laravel Blade, Tailwind CSS v4, and Alpine.js (`x-intersect`), leveraging the existing `plvbg.jpg` as a fixed parallax layer. Adopt a systematic test-fixing strategy by correcting the test assertions to match the current Livewire v4 / Volt application state rather than forcing code to fit outdated tests.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
1. **Primary Goal:** Transform the entry point into a premium, high-conversion landing page for the **PLV CEIT Library**, implement global branding changes, and resolve existing test debt.
2. **Branding & SEO (Global & Landing Page):**
*   **Target Name:** "PLV CEIT Library"
*   **Scope:** Global update across the application (Config, emails, documentations, PWA manifest, and browser tab titles).
*   **SEO:** Focus meta tags and descriptions around "PLV Ceit Library". Social sharing (Open Graph) images are omitted per user instruction, but structure will be semantic.
3. **UI/UX "Wow" Aesthetic:**
*   **Design Pattern:** Storytelling + Feature-Rich (Hero -> Features -> CTA).
*   **Style Theme:** "Liquid Glass" (translucent glassmorphism overlays, dynamic blurs, smooth 400-600ms transitions).
*   **Background Asset:** We will **retain the existing `plvbg.jpg` (school background)**.
*   **Color Palette (Adapted for CEIT):** Slate/Blue/Teal (CEIT Colors) with Liquid Glass treatment.
*   **Typography:** Satoshi / General Sans (via `DM Sans` fallback). Massive scale contrast.
*   **Auth Pages:** Login, Register, and Password Reset pages redesigned to match.
*   **Motion & Interactivity:** Scroll-driven narratives, subtle parallax. No generic templates.
4. **Technical Implementation:**
*   **Framework:** Laravel Blade + Alpine.js + Tailwind CSS v4.
*   **Branch:** `feature/premium-landing-page` (Created).
5. **Testing & Stability Debt:**
*   **Requirement:** The 50 currently failing tests *must* be resolved.
*   **Strategy:** Combine aesthetic landing page work with rigorous backend/test fixes.

### the agent's Discretion
- Implementation details of Alpine.js scrolling effects and Tailwind CSS styling logic.
- The specific fixes for the 50 failing tests (resolving routes, mock data, Livewire component names, and 302 redirects vs 403 status codes).

### Deferred Ideas (OUT OF SCOPE)
- Social sharing (Open Graph) images are omitted per user instruction.
</user_constraints>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Landing Page UI | Browser / Client | Frontend Server (SSR) | Blade renders the layout, Alpine.js handles scroll interactions (`x-intersect`) and parallax. |
| Authentication UI | Browser / Client | Frontend Server (SSR) | Blade handles the updated Liquid Glass forms; Livewire handles submission logic. |
| Global Branding | Frontend Server (SSR) | CDN / Static | `APP_NAME`, PWA Manifest, emails, layout titles rendered dynamically. |
| Test Fixes | API / Backend | Frontend Server (SSR) | Tests validate server responses (302/403), component rendering, and DB mutations. |

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Blade | v13 | SSR Views | Native to Laravel, ideal for highly-customized static landing pages. |
| Alpine.js | v3 | Interactivity | Native to Laravel ecosystem, provides `x-intersect` for smooth scroll animations. |
| Tailwind CSS | v4 | Styling | Native styling solution; handles glassmorphism and blurs elegantly without custom CSS files. |
| Livewire | v4 | Auth forms | Pre-existing for auth views, requires styling updates without logic changes. |
| PHPUnit | v12 | Test Suite | Standard framework for resolving the 50 failing tests. |

## Package Legitimacy Audit
> Not applicable for this phase. No new external packages are being introduced. The stack relies entirely on pre-installed dependencies (Tailwind v4, Livewire, Alpine).

## Architecture Patterns

### Pattern 1: Liquid Glass & Parallax
**What:** Combining a fixed background image with frosted glass panels to create visual depth and a premium feel.
**When to use:** On the premium landing page and Auth pages.
**Example:**
```html
<div class="relative min-h-screen bg-fixed bg-center bg-cover" style="background-image: url('/plvbg.jpg');">
    <!-- Liquid Glass Overlay -->
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[2px]"></div>
    
    <!-- Content container -->
    <div class="relative z-10 p-10 bg-slate-800/40 backdrop-blur-xl rounded-2xl border border-white/10 shadow-2xl">
        <!-- Content -->
    </div>
</div>
```

### Pattern 2: Scroll-Driven Animations
**What:** Triggering CSS animations when elements enter the viewport.
**When to use:** For staggered reveals in the "Features" section of the landing page.
**Example:**
```html
<div x-data="{ shown: false }" x-intersect.once="show = true">
    <div :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'" class="transition-all duration-700 ease-out">
        <h3>Premium Feature</h3>
    </div>
</div>
```

### Anti-Patterns to Avoid
- **Generic Component Slop:** Do not use plain unstyled daisyUI for the landing page; use custom hand-crafted UI to achieve the Liquid Glass aesthetic.
- **Forcing Code to fit Outdated Tests:** Many tests fail because they assert an outdated design (e.g., expecting `403` instead of a redirect `302`, or expecting `password.email` route when Volt components handle it). Update the test to match the modern application flow, do not break the app to pass the test.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Scroll tracking | Hand-rolled JS `scroll` listener | Alpine.js `x-intersect` | Better performance using IntersectionObserver natively. |
| Auth logic | Custom login/registration logic | Existing Volt/Livewire logic | Phase strictly re-skins Auth pages. Auth logic is already robust and secure. |

## Runtime State Inventory

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | `CEIT Library` strings potentially cached in sessions. | Cache clear command during deployment. |
| Live service config | `APP_NAME` in `.env` | Env var update instruction (`APP_NAME="PLV CEIT Library"`) |
| OS-registered state | None verified | None |
| Secrets/env vars | Configuration in `config/app.php` | Code edit |
| Build artifacts | `public/manifest.json` (PWA) | Code edit to update application name and short name |

## Common Pitfalls

### Pitfall 1: 403 vs 302 Test Failures
**What goes wrong:** Tests fail asserting `302 is identical to 403`.
**Why it happens:** The test expects an `abort(403)` but the application's middleware cleanly redirects unauthorized users back to the dashboard or login page (`302`).
**How to avoid:** Update the test assertion from `$response->assertStatus(403)` to `$response->assertRedirect(...)` to match the application's UX decisions.

### Pitfall 2: Livewire ComponentNotFoundException in Tests
**What goes wrong:** Tests fail trying to render a component that doesn't exist.
**Why it happens:** Earlier refactors to MaryUI or Volt renamed or deleted components (e.g. `profile.update-profile-information-form`).
**How to avoid:** Investigate the target view to find the correct component name and update the test's `Livewire::test()` or `Volt::test()` target.

### Pitfall 3: Failing to await Lazy Loaded Components
**What goes wrong:** Tests assert seeing table headers (like 'Department') but fail because they see a loading spinner.
**Why it happens:** The Livewire component is using `lazy="true"`, so the initial HTTP request only returns the placeholder.
**How to avoid:** Use `Livewire::test(Component::class)` which automatically resolves the initial state, instead of making a generic HTTP `$this->get()` request to assert page content.

## Code Examples

### Testing Livewire Volt Components
```php
// Source: Laravel Volt Documentation
Volt::test('pages.auth.login')
    ->set('email', 'test@plv.edu.ph')
    ->set('password', 'password')
    ->call('login')
    ->assertRedirect('/dashboard');
```

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 12 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter {TestName}` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REQ-01 | Transform Entry Point | feature | `php artisan test --filter WelcomePageTest` | ❌ Wave 0 |
| REQ-02 | Global Branding Update | feature | `php artisan test --filter PageTitleTest` | ✅ Wave 0 |
| REQ-03 | Resolve Test Debt | unit/feature | `php artisan test` | ✅ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter {TestName}`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/WelcomePageTest.php` — Missing test to cover the new premium landing page rendering and components.
- [ ] Assertions in Auth tests (e.g. `PasswordResetTest`, `EmailVerificationTest`) need updating to accommodate the new Liquid Glass design structure and routing.
