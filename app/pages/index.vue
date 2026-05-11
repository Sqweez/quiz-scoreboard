<script setup lang="ts">
import { onMounted } from 'vue'
import { ArrowRight, ListChecks, LogIn, LogOut, RotateCcw, Users } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '~/components/ui/card'
import GameSetupForm from '../components/GameSetupForm.vue'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const supabase = useSupabaseClient()
const user = useSupabaseUser()

onMounted(async () => {
  if (user.value) {
    await quizStore.loadGame()
  }
})

watch(user, async () => {
  if (user.value) {
    await quizStore.loadGame()
  } else {
    quizStore.currentGame = null
  }
})

async function signOut(): Promise<void> {
  await supabase.auth.signOut()
  quizStore.currentGame = null
}
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-6 px-4 py-6 lg:py-8">
    <header class="rounded-lg border bg-card/95 p-5 shadow-sm shadow-slate-200/70">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
          <p class="text-sm font-medium text-primary">Таблица квиза</p>
          <h1 class="text-3xl font-bold tracking-normal">Настройка игры</h1>
          <p class="max-w-2xl text-sm text-muted-foreground">
            Создайте команды, задайте раунды и сразу переходите к подсчету результатов.
          </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm text-muted-foreground">
          <span class="inline-flex items-center gap-2 rounded-full border bg-secondary px-3 py-1">
            <Users class="size-4" />
            Команды
          </span>
          <span class="inline-flex items-center gap-2 rounded-full border bg-secondary px-3 py-1">
            <ListChecks class="size-4" />
            Раунды
          </span>
          <Button v-if="user" variant="outline" size="sm" @click="signOut">
            <LogOut class="size-4" />
            Выйти
          </Button>
          <NuxtLink v-else to="/login">
            <Button size="sm">
              <LogIn class="size-4" />
              Войти
            </Button>
          </NuxtLink>
        </div>
      </div>
    </header>

    <Card v-if="!user">
      <CardHeader>
        <CardTitle>Войдите, чтобы сохранять игры</CardTitle>
        <CardDescription>Авторизация нужна для хранения таблиц в Supabase и доступа только к своим играм.</CardDescription>
      </CardHeader>
      <CardContent>
        <NuxtLink to="/login">
          <Button>
            <LogIn class="size-4" />
            Войти
          </Button>
        </NuxtLink>
      </CardContent>
    </Card>

    <p v-else-if="quizStore.error" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
      {{ quizStore.error }}
    </p>

    <Card v-else-if="quizStore.currentGame">
      <CardHeader>
        <CardTitle>{{ quizStore.currentGame.title }}</CardTitle>
        <CardDescription>
          Команд: {{ quizStore.currentGame.teams.length }},
          раундов: {{ quizStore.currentGame.rounds.length }}
        </CardDescription>
      </CardHeader>
      <CardContent class="flex flex-wrap gap-3">
        <NuxtLink to="/game">
          <Button>
            Открыть игру
            <ArrowRight class="size-4" />
          </Button>
        </NuxtLink>
        <Button variant="outline" @click="quizStore.clearGame">
          <RotateCcw class="size-4" />
          Сбросить игру
        </Button>
      </CardContent>
    </Card>

    <GameSetupForm v-else />
  </main>
</template>
