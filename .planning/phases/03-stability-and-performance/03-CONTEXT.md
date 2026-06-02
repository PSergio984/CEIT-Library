# Phase 3 Context: Premium Landing Page & Stability

## Phase Scope & Decisions

**1. Primary Goal:** 
Transform the entry point into a premium, high-conversion landing page for the **PLV CEIT Library**, implement global branding changes, and resolve existing test debt.

**2. Branding & SEO (Global & Landing Page):**
*   **Target Name:** "PLV CEIT Library"
*   **Scope:** Global update across the application (Config, emails, documentations, PWA manifest, and browser tab titles).
*   **SEO:** Focus meta tags and descriptions around "PLV Ceit Library". Social sharing (Open Graph) images are omitted per user instruction, but structure will be semantic.

**3. UI/UX "Wow" Aesthetic (Guided by `premium-frontend-ui` & `ui-ux-pro-max`):**
*   **Design Pattern:** Storytelling + Feature-Rich (Hero -> Features -> CTA).
*   **Style Theme:** "Liquid Glass" (translucent glassmorphism overlays, dynamic blurs, smooth 400-600ms transitions).
*   **Background Asset:** We will **retain the existing `plvbg.jpg` (school background)**. Instead of removing it, we will elevate it by using it as the base layer for the parallax effect, applying subtle atmospheric overlays, and placing the frosted glass elements on top to make the school imagery look incredibly high-end.
*   **Color Palette (Adapted for CEIT):** Since the generated pink/gold doesn't fit a standard library/university brand, we will retain the core **Slate/Blue/Teal (CEIT Colors)** but apply the *Liquid Glass* treatment to them.
*   **Typography:** Satoshi / General Sans (via `DM Sans` fallback) for a clean, sophisticated, premium feel. Massive scale contrast between headlines and body copy.
*   **Auth Pages:** The **Login, Register, and Password Reset pages** will also be redesigned to match this premium Liquid Glass aesthetic to maintain a cohesive experience.
*   **Motion & Interactivity:** 
    *   Scroll-driven narratives (e.g., using Alpine.js `x-intersect` or native Scroll API) for staggered reveal animations.
    *   Subtle parallax on the background elements (floating or softly moving geometric shapes behind the frosted glass).
    *   No generic templates; customized, hand-crafted CSS/Tailwind.

**4. Technical Implementation:**
*   **Framework:** Laravel Blade + Alpine.js + Tailwind CSS v4.
*   **Branch:** `feature/premium-landing-page` (Created).

**5. Testing & Stability Debt:**
*   **Requirement:** The 50 currently failing tests *must* be resolved as part of this phase.
*   **Strategy:** Combine the aesthetic landing page work with rigorous backend/test fixes to ensure the system is both beautiful and stable.

## Locked Constraints
*   Do not use generic "AI slop" or templated UI components. Follow the `premium-frontend-ui` craftsmanship guidelines.
*   Do not omit resolving the failing tests.
*   Retain performance and accessibility guardrails (e.g., `prefers-reduced-motion` fallbacks, contrast minimums).