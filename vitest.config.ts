import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '~': fileURLToPath(new URL('./resources/js', import.meta.url)),
      '#shared': fileURLToPath(new URL('./shared', import.meta.url))
    }
  }
})
