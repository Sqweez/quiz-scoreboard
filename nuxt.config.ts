import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  app: {
    head: {
      title: 'Таблица квиза',
      meta: [
        { name: 'application-name', content: 'Таблица квиза' }
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Spectral:wght@400;500;700&display=swap'
        }
      ]
    }
  },
  css: ['~/assets/css/main.css'],
  modules: [
    '@pinia/nuxt',
    'shadcn-nuxt',
    '@nuxtjs/supabase'
  ],
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  runtimeConfig: {
    databaseUrl: ''
  },
  supabase: {
    redirect: false,
    types: false
  },
  shadcn: {
    prefix: '',
    componentDir: '~/components/ui'
  },
  vite: {
    plugins: [tailwindcss()]
  },
  typescript: {
    typeCheck: true,
  },
})
