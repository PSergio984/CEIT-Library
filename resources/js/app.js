import "./bootstrap";
import { Html5Qrcode } from "html5-qrcode";
import jsQR from "jsqr";

// Import images for Vite processing
import.meta.glob([
    '../images/**',
]);

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
