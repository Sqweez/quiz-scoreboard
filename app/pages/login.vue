<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { Spinner } from '~/components/ui/spinner'

type AuthMode = 'signin' | 'signup'

const supabase = useSupabaseClient()
const user = useSupabaseUser()

const mode = ref<AuthMode>('signin')
const email = ref('')
const password = ref('')
const isLoading = ref(false)
const error = ref('')
const success = ref('')

const isSignup = computed(() => mode.value === 'signup')
const title = computed(() => (isSignup.value ? 'Создать аккаунт' : 'Войти'))
const description = computed(() =>
  isSignup.value
    ? 'Создай аккаунт и сразу начни работать с играми.'
    : 'Войди, чтобы открыть свои игры и продолжить работу.'
)
const primaryLabel = computed(() => (isSignup.value ? 'Создать аккаунт' : 'Войти'))

watch(
  user,
  () => {
    if (user.value) {
      navigateTo('/')
    }
  },
  { immediate: true }
)

async function submitAuth(): Promise<void> {
  error.value = ''
  success.value = ''

  const trimmedEmail = email.value.trim()

  if (!trimmedEmail || !password.value) {
    error.value = 'Введите email и пароль.'
    return
  }

  isLoading.value = true

  const result = isSignup.value
    ? await supabase.auth.signUp({
        email: trimmedEmail,
        password: password.value
      })
    : await supabase.auth.signInWithPassword({
        email: trimmedEmail,
        password: password.value
      })

  isLoading.value = false

  if (result.error) {
    error.value = result.error.message
    return
  }

  if (isSignup.value && !result.data.session) {
    success.value = 'Аккаунт создан. Теперь войди в него.'
    mode.value = 'signin'
    password.value = ''
    return
  }

  await navigateTo('/')
}
</script>

<template>
  <main class="flex min-h-dvh items-center justify-center bg-slate-50 px-4 py-10">
    <Card class="w-full max-w-md border-slate-200 shadow-sm">
      <CardHeader class="space-y-2 pb-4">
        <CardTitle class="text-2xl font-semibold tracking-tight text-slate-950">
          {{ title }}
        </CardTitle>
        <CardDescription class="text-sm leading-6 text-slate-600">
          {{ description }}
        </CardDescription>
      </CardHeader>

      <CardContent class="space-y-5">
        <div class="grid grid-cols-2 rounded-lg bg-slate-100 p-1">
          <Button
            type="button"
            variant="ghost"
            class="h-10 rounded-md text-sm font-medium shadow-none data-[state=active]:bg-white data-[state=active]:text-slate-950 data-[state=active]:shadow-sm"
            :class="!isSignup ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
            :aria-pressed="!isSignup"
            @click="mode = 'signin'"
          >
            Вход
          </Button>
          <Button
            type="button"
            variant="ghost"
            class="h-10 rounded-md text-sm font-medium shadow-none data-[state=active]:bg-white data-[state=active]:text-slate-950 data-[state=active]:shadow-sm"
            :class="isSignup ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
            :aria-pressed="isSignup"
            @click="mode = 'signup'"
          >
            Регистрация
          </Button>
        </div>

        <form class="space-y-4" @submit.prevent="submitAuth">
          <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              placeholder="name@example.com"
              class="h-11"
            />
          </div>

          <div class="space-y-2">
            <Label for="password">Пароль</Label>
            <Input
              id="password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              placeholder="••••••••"
              class="h-11"
            />
          </div>

          <p v-if="error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
          </p>
          <p v-if="success" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ success }}
          </p>

          <Button
            type="submit"
            class="h-11 w-full"
            :disabled="isLoading"
          >
            <Spinner v-if="isLoading" />
            {{ isLoading ? 'Подождите...' : primaryLabel }}
          </Button>
        </form>

        <p class="text-center text-sm text-slate-500">
          <span>{{ isSignup ? 'Уже есть аккаунт?' : 'Нет аккаунта?' }}</span>
          <button
            type="button"
            class="ml-1 font-medium text-slate-900 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-500"
            @click="mode = isSignup ? 'signin' : 'signup'"
          >
            {{ isSignup ? 'Войти' : 'Создать' }}
          </button>
        </p>
      </CardContent>
    </Card>
  </main>
</template>
