# Welcome Page Design Overrides

---

## Typography Overrides

- **Primary Font:** Satoshi (Variable)
- **Secondary Font:** General Sans
- **Fallback Font:** DM Sans
- **Scale Contrast:** Massive. Use `clamp()` for fluid typography.
- **Headings:** Satoshi, bold, large scale.
- **Body:** General Sans, crisp.

## Color Palette Overrides

- **Base:** Slate / Blue / Teal
- **Primary:** `text-slate-900` / `bg-slate-900`
- **Glass:** `bg-slate-900/60` with `backdrop-blur-md`
- **Accents:** Teal and Blue for highlights and interactive elements.

## Animation & Motion

- **Scroll Engine:** Alpine.js `x-intersect`
- **Effect:** Liquid Glass (translucent panels, smooth transitions)
- **Background:** Parallax effect on `plvbg.jpg`

## Structure

- **Hero:** Massive typography, staggered reveals.
- **Features:** Scroll-driven staggering reveals.
- **Navigation:** Floating glass navbar.
