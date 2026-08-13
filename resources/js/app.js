import "./bootstrap";
import { Html5Qrcode } from "html5-qrcode";
import jsQR from "jsqr";

// Import images for Vite processing.
// Vite 8 (Rolldown) drops glob imports that are never consumed, which removes
// resources/images/* from the manifest and breaks Vite::asset() lookups (e.g.
// ceit-logo.png). Eager + ?url keeps every image in the manifest.
import.meta.glob('../images/**', { eager: true, query: '?url', import: 'default' });

// Make Html5Qrcode available globally
window.Html5Qrcode = Html5Qrcode;
// Make jsQR available globally for file-based QR scanning
window.jsQR = jsQR;

// Anti-flash/restore theme function
function applyTheme() {
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        document.documentElement.setAttribute('data-theme', 'light');
    }
}

// Apply theme on load and on Livewire dynamic navigation
applyTheme();
document.addEventListener('livewire:navigated', applyTheme);
