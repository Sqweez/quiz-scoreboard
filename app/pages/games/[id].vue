<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ArrowLeft, CheckCircle2, ListChecks, Medal, Trophy, Users } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { Spinner } from '~/components/ui/spinner'
import RoundsEditor from '~/components/RoundsEditor.vue'
import ScoreTable from '~/components/ScoreTable.vue'
import TeamsEditor from '~/components/TeamsEditor.vue'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const titleError = ref('')
const isDeleting = ref(false)
const route = useRoute()

definePageMeta({
  middleware: 'auth'
})

const leader = computed(() => quizStore.sortedTeams[0] ?? null)
const leaderTotal = computed(() => (leader.value ? quizStore.getTotalScore(leader.value) : 0))
const teamsCount = computed(() => quizStore.currentGame?.teams.length ?? 0)
const roundsCount = computed(() => quizStore.currentGame?.rounds.length ?? 0)
const isFinished = computed(() => quizStore.currentGame?.status === 'finished')

watch(
  () => route.params.id,
  async (gameId) => {
    await quizStore.loadGame(String(gameId ?? ''))
  },
  { immediate: true }
)

onBeforeRouteLeave(async () => {
  await quizStore.flushPendingScoreChanges(true)
})

function updateTitle(value: string | number | null | undefined): void {
  const title = String(value ?? '')

  if (!title.trim()) {
    titleError.value = 'Название игры не может быть пустым.'
    return
  }

  quizStore.updateGameTitle(title)
  titleError.value = ''
}

async function finishCurrentGame(): Promise<void> {
  await quizStore.finishGame()
}

async function deleteCurrentGame(): Promise<void> {
  const gameId = quizStore.currentGame?.id

  if (!gameId || isDeleting.value) {
    return
  }

  const confirmed = window.confirm('Удалить эту игру? Это действие нельзя отменить.')

  if (!confirmed) {
    return
  }

  isDeleting.value = true

  try {
    await quizStore.clearGame()

    if (!quizStore.error) {
      await navigateTo('/')
    }
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-6 px-4 py-6 lg:py-8">
    <template v-if="quizStore.currentGame">
      <header class="rounded-lg border bg-card/95 p-4 shadow-sm shadow-slate-200/70 lg:p-5">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0 flex-1 space-y-4">
            <div class="flex flex-wrap items-center gap-2">
              <NuxtLink to="/">
                <Button variant="ghost" size="sm" class="h-9 px-2">
                  <ArrowLeft class="size-4" />
                  К списку
                </Button>
              </NuxtLink>
              <span
                class="rounded-full border px-3 py-1 text-xs font-medium uppercase tracking-[0.12em]"
                :class="isFinished ? 'bg-emerald-50 text-emerald-700' : 'bg-secondary text-secondary-foreground'"
              >
                {{ quizStore.currentGame.statusLabel }}
              </span>
              <span v-if="isFinished" class="rounded-full border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                Только просмотр
              </span>
            </div>

            <div class="max-w-2xl space-y-2">
              <Label for="game-title" class="text-muted-foreground">Название игры</Label>
              <Input
                id="game-title"
                :model-value="quizStore.currentGame.title"
                class="h-13 border-0 bg-transparent px-0 text-3xl font-bold shadow-none focus-visible:ring-0"
                :disabled="isFinished"
                @update:model-value="updateTitle"
              />
              <p v-if="titleError" class="text-sm text-destructive">{{ titleError }}</p>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button
              v-if="!isFinished"
              variant="outline"
              class="shrink-0"
              :disabled="quizStore.isSavingScores"
              @click="finishCurrentGame"
            >
              <CheckCircle2 class="size-4" />
              Завершить игру
            </Button>
            <Button
              variant="destructive"
              class="shrink-0"
              :disabled="isDeleting || quizStore.isSavingScores"
              @click="deleteCurrentGame"
            >
              <Spinner v-if="isDeleting" />
              Удалить игру
            </Button>
          </div>
        </div>
      </header>

      <p v-if="quizStore.error" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
        {{ quizStore.error }}
      </p>

      <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border bg-card p-4 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-muted-foreground">Лидер</p>
            <Trophy class="size-4 text-amber-600" />
          </div>
          <div class="mt-3">
            <p class="truncate text-2xl font-bold">{{ leader?.name ?? 'Нет команд' }}</p>
            <p class="text-sm text-muted-foreground">{{ leaderTotal }} баллов</p>
          </div>
        </div>

        <div class="rounded-lg border bg-card p-4 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-muted-foreground">Команды</p>
            <Users class="size-4 text-primary" />
          </div>
          <p class="mt-3 text-2xl font-bold">{{ teamsCount }}</p>
          <p class="text-sm text-muted-foreground">участников в таблице</p>
        </div>

        <div class="rounded-lg border bg-card p-4 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-muted-foreground">Раунды</p>
            <ListChecks class="size-4 text-emerald-600" />
          </div>
          <p class="mt-3 text-2xl font-bold">{{ roundsCount }}</p>
          <p class="text-sm text-muted-foreground">этапов подсчета</p>
        </div>

        <div class="rounded-lg border bg-card p-4 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-muted-foreground">Первое место</p>
            <Medal class="size-4 text-slate-500" />
          </div>
          <p class="mt-3 truncate text-2xl font-bold">{{ leader?.name ?? '...' }}</p>
          <p class="text-sm text-muted-foreground">по текущему тай-брейку</p>
        </div>
      </section>

      <ScoreTable :read-only="isFinished" />

      <section class="grid gap-6 lg:grid-cols-2">
        <TeamsEditor :read-only="isFinished" />
        <RoundsEditor :read-only="isFinished" />
      </section>
    </template>

    <Card v-else>
      <CardHeader>
        <div class="mb-2 flex items-center gap-2 text-muted-foreground">
          <Spinner />
          <span class="text-sm">{{ quizStore.isLoading ? 'Загружаем игру' : 'Игра не найдена' }}</span>
        </div>
        <CardTitle>{{ quizStore.isLoading ? 'Загружаем игру' : 'Игра не найдена' }}</CardTitle>
        <CardDescription>
          {{ quizStore.isLoading ? 'Проверяем сохраненные данные.' : 'Вернитесь к списку игр.' }}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <NuxtLink to="/">
          <Button>К списку</Button>
        </NuxtLink>
      </CardContent>
    </Card>
  </main>
</template>
