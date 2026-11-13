<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { http, getRequestMessage } from '~/utils/http'
import { Button } from '~/components/ui/button'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { Spinner } from '~/components/ui/spinner'

type AuthMode = 'signin' | 'signup'

const mode = ref<AuthMode>('signin')
const email = ref('')
const password = ref('')
const isLoading = ref(false)
const error = ref('')

const isSignup = computed(() => mode.value === 'signup')
const title = computed(() => (isSignup.value ? 'Создать аккаунт' : 'Войти'))
const description = computed(() =>
  isSignup.value
    ? 'Аккаунт хранит игры в облаке и не теряет ввод между устройствами.'
    : 'Игры останутся под рукой, даже если перезагрузить вкладку.'
)
const primaryLabel = computed(() => (isSignup.value ? 'Создать аккаунт' : 'Войти'))

async function submitAuth(): Promise<void> {
  error.value = ''

  const trimmedEmail = email.value.trim()

  if (!trimmedEmail || !password.value) {
    error.value = 'Введите email и пароль.'
    return
  }

  isLoading.value = true

  try {
    await http(isSignup.value ? '/register' : '/login', {
      method: 'POST',
      body: { email: trimmedEmail, password: password.value }
    })
    router.visit('/')
  } catch (requestError) {
    error.value = getRequestMessage(requestError)
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center gap-10 px-4 py-12">
    <header class="space-y-3 border-b border-rule-strong pb-6">
      <p class="eyebrow text-muted-foreground">
        Таблица квиза
      </p>
      <h1 class="font-display text-4xl font-medium leading-tight">
        {{ title }}
      </h1>
      <p class="text-sm text-muted-foreground">
        {{ description }}
      </p>
    </header>

    <div class="flex items-center gap-6 text-sm">
      <button
        type="button"
        class="border-b pb-1 transition-colors"
        :class="!isSignup
          ? 'border-rule-strong font-medium text-foreground'
          : 'border-transparent text-muted-foreground hover:text-foreground'"
        :aria-pressed="!isSignup"
        @click="mode = 'signin'"
      >
        Вход
      </button>
      <button
        type="button"
        class="border-b pb-1 transition-colors"
        :class="isSignup
          ? 'border-rule-strong font-medium text-foreground'
          : 'border-transparent text-muted-foreground hover:text-foreground'"
        :aria-pressed="isSignup"
        @click="mode = 'signup'"
      >
        Регистрация
      </button>
    </div>

    <form class="space-y-6" @submit.prevent="submitAuth">
      <div class="space-y-2">
        <Label for="email" class="eyebrow-tight text-muted-foreground">
          Email
        </Label>
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
        <Label for="password" class="eyebrow-tight text-muted-foreground">
          Пароль
        </Label>
        <Input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          placeholder="••••••••"
          class="h-11"
        />
      </div>

      <p v-if="error" class="border-t border-primary bg-secondary px-3 py-2 text-sm text-foreground">
        {{ error }}
      </p>

      <Button type="submit" class="h-11 w-full" :disabled="isLoading">
        <Spinner v-if="isLoading" />
        {{ isLoading ? 'Подождите' : primaryLabel }}
      </Button>
    </form>

    <p class="text-center text-sm text-muted-foreground">
      <span>{{ isSignup ? 'Уже есть аккаунт?' : 'Нет аккаунта?' }}</span>
      <button
        type="button"
        class="ml-1 font-medium text-foreground underline decoration-rule-strong underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
        @click="mode = isSignup ? 'signin' : 'signup'"
      >
        {{ isSignup ? 'Войти' : 'Создать' }}
      </button>
    </p>
  </main>
</template>
