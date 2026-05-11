import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  css: ['~/assets/css/main.css'],
  modules: ['@pinia/nuxt', 'shadcn-nuxt'],
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  shadcn: {
    prefix: '',
    componentDir: '~/components/ui'
  },
  vite: {
    plugins: [tailwindcss()]
  }
})
