import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/js/app.ts'], refresh: true }),
    vue(),
    tailwindcss()
  ],
  resolve: {
    alias: {
      '~': fileURLToPath(new URL('./resources/js', import.meta.url)),
      '#shared': fileURLToPath(new URL('./shared', import.meta.url))
    }
  }
})
