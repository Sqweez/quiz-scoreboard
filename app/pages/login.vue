<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Button } from '~/components/ui/button'
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
    ? 'Аккаунт хранит игры в облаке и не теряет ввод между устройствами.'
    : 'Игры останутся под рукой, даже если перезагрузить вкладку.'
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
    success.value = 'Аккаунт создан. Теперь войдите.'
    mode.value = 'signin'
    password.value = ''
    return
  }

  await navigateTo('/')
}
</script>

<template>
  <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center gap-10 px-4 py-12">
    <header class="space-y-3 border-b border-[var(--rule-strong)] pb-6">
      <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
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
          ? 'border-[var(--rule-strong)] font-medium text-foreground'
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
          ? 'border-[var(--rule-strong)] font-medium text-foreground'
          : 'border-transparent text-muted-foreground hover:text-foreground'"
        :aria-pressed="isSignup"
        @click="mode = 'signup'"
      >
        Регистрация
      </button>
    </div>

    <form class="space-y-6" @submit.prevent="submitAuth">
      <div class="space-y-2">
        <Label for="email" class="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
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
        <Label for="password" class="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
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

      <p v-if="error" class="border-t border-[var(--primary)] bg-[var(--secondary)] px-3 py-2 text-sm text-foreground">
        {{ error }}
      </p>
      <p v-if="success" class="border-t border-foreground/30 bg-[var(--secondary)] px-3 py-2 text-sm text-foreground">
        {{ success }}
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
        class="ml-1 font-medium text-foreground underline decoration-[var(--rule-strong)] underline-offset-4 transition-colors hover:text-[var(--primary)] hover:decoration-[var(--primary)]"
        @click="mode = isSignup ? 'signin' : 'signup'"
      >
        {{ isSignup ? 'Войти' : 'Создать' }}
      </button>
    </p>
  </main>
</template>
