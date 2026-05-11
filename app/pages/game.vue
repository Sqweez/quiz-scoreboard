<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ArrowLeft, ListChecks, Medal, RotateCcw, Trophy, Users } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import RoundsEditor from '../components/RoundsEditor.vue'
import ScoreTable from '../components/ScoreTable.vue'
import TeamsEditor from '../components/TeamsEditor.vue'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const titleError = ref('')

const leader = computed(() => quizStore.sortedTeams[0] ?? null)
const leaderTotal = computed(() => (leader.value ? quizStore.getTotalScore(leader.value) : 0))
const teamsCount = computed(() => quizStore.currentGame?.teams.length ?? 0)
const roundsCount = computed(() => quizStore.currentGame?.rounds.length ?? 0)

onMounted(() => {
  quizStore.loadGame()
})

function updateTitle(value: string | number | null): void {
  const title = String(value ?? '')

  if (!title.trim()) {
    titleError.value = 'Название игры не может быть пустым.'
    return
  }

  quizStore.updateGameTitle(title)
  titleError.value = ''
}

function resetGame(): void {
  quizStore.clearGame()
  navigateTo('/')
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
                  К настройке
                </Button>
              </NuxtLink>
              <span class="rounded-full border bg-secondary px-3 py-1 text-xs font-medium text-secondary-foreground">
                Активная игра
              </span>
            </div>

            <div class="max-w-2xl space-y-2">
              <Label for="game-title" class="text-muted-foreground">Название игры</Label>
              <Input
                id="game-title"
                :model-value="quizStore.currentGame.title"
                class="h-13 border-0 bg-transparent px-0 text-3xl font-bold shadow-none focus-visible:ring-0"
                @update:model-value="updateTitle"
              />
              <p v-if="titleError" class="text-sm text-destructive">{{ titleError }}</p>
            </div>
          </div>

          <Button variant="outline" class="shrink-0" @click="resetGame">
            <RotateCcw class="size-4" />
            Сбросить игру
          </Button>
        </div>
      </header>

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

      <ScoreTable />

      <section class="grid gap-6 lg:grid-cols-2">
        <TeamsEditor />
        <RoundsEditor />
      </section>
    </template>

    <Card v-else>
      <CardHeader>
        <CardTitle>Нет активной игры</CardTitle>
        <CardDescription>Создайте игру перед открытием таблицы результатов.</CardDescription>
      </CardHeader>
      <CardContent>
        <NuxtLink to="/">
          <Button>К настройке</Button>
        </NuxtLink>
      </CardContent>
    </Card>
  </main>
</template>
