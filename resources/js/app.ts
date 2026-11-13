import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { createPinia } from 'pinia'
import './assets/css/main.css'

const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { import: 'default' })

createInertiaApp({
  resolve: name => pages[`./pages/${name}.vue`](),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(createPinia())
      .mount(el)
  }
})
