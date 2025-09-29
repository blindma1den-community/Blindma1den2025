// @ts-check
import { defineConfig } from 'astro/config';

import tailwindcss from '@tailwindcss/vite';

// https://astro.build/config
export default defineConfig({
  site: 'https://www.blindma1den.com',
  base: '/',
  build: {
    assets: '_astro'
  },
  vite: {
    plugins: [tailwindcss()],
    build: {
      assetsInlineLimit: 4096, // Inline assets < 4KB
      rollupOptions: {
        output: {
          assetFileNames: '_astro/[name].[hash][extname]',
          chunkFileNames: '_astro/[name].[hash].js',
          entryFileNames: '_astro/[name].[hash].js'
        }
      }
    }
  },
  server: {
    port: 4321
  }
});