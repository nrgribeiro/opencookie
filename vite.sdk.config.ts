import { defineConfig } from 'vite';

/**
 * Standalone build for the public Consent SDK. Produces a single minified IIFE
 * at public/sdk/v1/cmp.js — no React, no app code. Built separately from the
 * dashboard bundle: `npm run build:sdk`.
 */
export default defineConfig({
    // Do not copy the app's public/ dir into the SDK outDir (would recurse).
    publicDir: false,
    build: {
        outDir: 'public/sdk/v1',
        emptyOutDir: false,
        // Vite 8 dropped bundled esbuild — use the default minifier (oxc on rolldown).
        minify: true,
        target: 'es2018',
        lib: {
            entry: 'resources/sdk/cmp.ts',
            formats: ['iife'],
            name: 'CMP',
            fileName: () => 'cmp.js',
        },
    },
});
