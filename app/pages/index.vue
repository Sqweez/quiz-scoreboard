<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { ArrowRight, ListChecks, LogIn, LogOut, Users } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '~/components/ui/card'
import GameSetupForm from '../components/GameSetupForm.vue'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const supabase = useSupabaseClient()
const user = useSupabaseUser()

onMounted(async () => {
  if (user.value) {
    await quizStore.loadGames()
    await quizStore.loadGame()
  }
})

watch(user, async () => {
  if (user.value) {
    await quizStore.loadGames()
    await quizStore.loadGame()
  } else {
    quizStore.currentGame = null
    quizStore.games = []
  }
})

async function signOut(): Promise<void> {
  await supabase.auth.signOut()
  quizStore.currentGame = null
  quizStore.games = []
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

    <Card v-if="user && quizStore.currentGame" class="border-slate-200/80 bg-card/95 shadow-sm shadow-slate-200/70">
      <CardHeader>
        <CardTitle>Последняя игра</CardTitle>
        <CardDescription>
          {{ quizStore.currentGame.title }} ·
          {{ quizStore.currentGame.teams.length }} команд ·
          {{ quizStore.currentGame.rounds.length }} раундов
        </CardDescription>
      </CardHeader>
      <CardContent class="flex flex-wrap gap-3">
        <NuxtLink :to="{ path: '/game', query: { gameId: quizStore.currentGame.id } }">
          <Button>
            Открыть игру
            <ArrowRight class="size-4" />
          </Button>
        </NuxtLink>
      </CardContent>
    </Card>

    <section v-if="user" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
      <GameSetupForm />

      <Card class="h-fit">
        <CardHeader>
          <CardTitle>Все игры</CardTitle>
          <CardDescription>
            Открывайте любую сохраненную игру и продолжайте с того места, где остановились.
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="quizStore.isLoading" class="rounded-md border bg-secondary/30 p-4 text-sm text-muted-foreground">
            Загружаем список игр...
          </div>

          <div v-else-if="quizStore.games.length === 0" class="rounded-md border border-dashed bg-background p-4 text-sm text-muted-foreground">
            Пока нет сохраненных игр. Создайте первую через форму слева.
          </div>

          <div v-else class="space-y-3">
            <article
              v-for="game in quizStore.games"
              :key="game.id"
              class="rounded-lg border bg-background p-4 shadow-sm"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-base font-semibold text-foreground">{{ game.title }}</p>
                  <p class="mt-1 text-sm text-muted-foreground">
                    {{ game.teams.length }} команд · {{ game.rounds.length }} раундов
                  </p>
                </div>
                <span class="rounded-full border bg-secondary px-2.5 py-1 text-[11px] font-medium uppercase tracking-[0.12em] text-secondary-foreground">
                  {{ quizStore.currentGame?.id === game.id ? 'Открыта' : 'Сохранена' }}
                </span>
              </div>

              <div class="mt-4 flex flex-wrap gap-2">
                <NuxtLink :to="{ path: '/game', query: { gameId: game.id } }">
                  <Button size="sm">
                    <ArrowRight class="size-4" />
                    Открыть
                  </Button>
                </NuxtLink>
              </div>
            </article>
          </div>
        </CardContent>
      </Card>
    </section>
  </main>
</template>
