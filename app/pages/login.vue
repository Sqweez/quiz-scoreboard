<script setup lang="ts">
import { ref } from 'vue'
import { LogIn } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'

const supabase = useSupabaseClient()
const user = useSupabaseUser()
const email = ref('')
const password = ref('')
const isLoading = ref(false)
const error = ref('')

watch(user, () => {
  if (user.value) {
    navigateTo('/')
  }
}, { immediate: true })

async function signIn(): Promise<void> {
  await submitAuth('signInWithPassword')
}

async function signUp(): Promise<void> {
  await submitAuth('signUp')
}

async function submitAuth(action: 'signInWithPassword' | 'signUp'): Promise<void> {
  if (!email.value.trim() || !password.value) {
    error.value = 'Введите email и пароль.'
    return
  }

  isLoading.value = true
  error.value = ''

  const { error: authError } = await supabase.auth[action]({
    email: email.value.trim(),
    password: password.value
  })

  isLoading.value = false

  if (authError) {
    error.value = authError.message
    return
  }

  await navigateTo('/')
}
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-8">
    <Card class="overflow-hidden">
      <CardHeader class="border-b bg-secondary/35">
        <CardTitle>Вход в таблицу квиза</CardTitle>
        <CardDescription>Войдите или создайте аккаунт, чтобы сохранять игры в Supabase.</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="signIn">
          <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input id="email" v-model="email" type="email" autocomplete="email" />
          </div>

          <div class="space-y-2">
            <Label for="password">Пароль</Label>
            <Input id="password" v-model="password" type="password" autocomplete="current-password" />
          </div>

          <p v-if="error" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {{ error }}
          </p>

          <div class="flex flex-col gap-2 sm:flex-row">
            <Button type="submit" class="flex-1" :disabled="isLoading">
              <LogIn class="size-4" />
              Войти
            </Button>
            <Button type="button" variant="outline" class="flex-1" :disabled="isLoading" @click="signUp">
              Создать аккаунт
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
