import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/smashzone.css', 'resources/css/home.css', 'resources/js/app.js', 'resources/js/home.js', 'resources/css/courts.css', 'resources/js/courts.js', 'resources/css/booking-flow.css', 'resources/js/booking-flow.js', 'resources/css/customer-dashboard.css', 'resources/js/customer-dashboard.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
