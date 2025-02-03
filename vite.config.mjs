import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [react(), tailwindcss()],
    root: path.resolve(__dirname, 'resources', 'react'),
    base: '',
    build: {
        outDir: path.resolve(__dirname, 'dist'), // Your distribution folder
        emptyOutDir: true,
        rollupOptions: {
            input: path.resolve(__dirname, 'resources', 'react', 'main.jsx'),
        },
    },
    server: {
        port: 5179, // Customize as needed
        watch: { ignored: [] },
        hmr: { overlay: false },
    },
});
