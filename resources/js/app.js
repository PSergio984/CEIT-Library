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
