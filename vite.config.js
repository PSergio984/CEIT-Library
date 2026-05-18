import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import {VitePWA} from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: 'auto',
            manifest: {
                name: 'CEIT Library',
                short_name: 'CEIT Lib',
                description: 'CEIT Library Management System',
                theme_color: '#0046ad',
                icons: [
                    {
                        src: 'images/ceit-logo.png',
                        sizes: '192x192',
                        type: 'image/png'
                    },
                    {
                        src: 'images/ceit-logo.png',
                        sizes: '512x512',
                        type: 'image/png'
                    }
                ]
            }
        })
    ],
});
