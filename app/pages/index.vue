<script setup lang="ts">
import { watch } from 'vue'
import { ArrowRight, LogIn, LogOut, Plus } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '~/components/ui/card'
import { Spinner } from '~/components/ui/spinner'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const supabase = useSupabaseClient()
const user = useSupabaseUser()

watch(
  user,
  async (value) => {
    if (value) {
      await quizStore.loadGames()
      return
    }

    quizStore.games = []
  },
  { immediate: true }
)

async function signOut(): Promise<void> {
  await supabase.auth.signOut()
  quizStore.games = []
  quizStore.currentGame = null
}
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-6 px-4 py-6 lg:py-8">
    <header class="rounded-lg border bg-card/95 p-5 shadow-sm shadow-slate-200/70">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
          <p class="text-sm font-medium text-primary">Таблица квиза</p>
          <h1 class="text-3xl font-bold tracking-normal">Все игры</h1>
          <p class="max-w-2xl text-sm text-muted-foreground">
            Открывайте завершенные игры в режиме просмотра или создавайте новую отдельным потоком.
          </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm text-muted-foreground">
          <NuxtLink to="/games/new">
            <Button>
              <Plus class="size-4" />
              Создать игру
            </Button>
          </NuxtLink>
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
        <CardTitle>Войдите, чтобы видеть свои игры</CardTitle>
        <CardDescription>
          Авторизация нужна, чтобы хранить игры в Supabase и разделять черновики с завершенными партиями.
        </CardDescription>
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

    <Card v-else-if="quizStore.isLoading">
      <CardHeader>
        <div class="mb-2 flex items-center gap-2 text-muted-foreground">
          <Spinner />
          <span class="text-sm">Загружаем игры</span>
        </div>
        <CardTitle>Загружаем игры</CardTitle>
        <CardDescription>Проверяем сохраненные записи.</CardDescription>
      </CardHeader>
    </Card>

    <Card v-else-if="quizStore.error">
      <CardHeader>
        <CardTitle>Не удалось загрузить игры</CardTitle>
        <CardDescription>{{ quizStore.error }}</CardDescription>
      </CardHeader>
    </Card>

    <Card v-else-if="quizStore.games.length === 0">
      <CardHeader>
        <CardTitle>Пока нет игр</CardTitle>
        <CardDescription>Создайте первую игру, чтобы начать работу.</CardDescription>
      </CardHeader>
      <CardContent>
        <NuxtLink to="/games/new">
          <Button>
            <Plus class="size-4" />
            Создать игру
          </Button>
        </NuxtLink>
      </CardContent>
    </Card>

    <section v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="game in quizStore.games"
        :key="game.id"
        class="rounded-lg border bg-card p-5 shadow-sm shadow-slate-200/50"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-lg font-semibold text-foreground">{{ game.title }}</p>
            <p class="mt-1 text-sm text-muted-foreground">
              {{ game.teams.length }} команд · {{ game.rounds.length }} раундов
            </p>
          </div>
          <span
            class="rounded-full border px-2.5 py-1 text-[11px] font-medium uppercase tracking-[0.12em]"
            :class="game.status === 'finished' ? 'bg-emerald-50 text-emerald-700' : 'bg-secondary text-secondary-foreground'"
          >
            {{ game.statusLabel }}
          </span>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <NuxtLink :to="`/games/${game.id}`">
            <Button size="sm">
              <ArrowRight class="size-4" />
              {{ game.statusActionLabel }}
            </Button>
          </NuxtLink>
        </div>
      </article>
    </section>
  </main>
</template>
