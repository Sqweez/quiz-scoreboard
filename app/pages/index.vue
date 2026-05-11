<script setup lang="ts">
import { onMounted } from 'vue'
import { ArrowRight, ListChecks, RotateCcw, Users } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '~/components/ui/card'
import GameSetupForm from '../components/GameSetupForm.vue'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()

onMounted(() => {
  quizStore.loadGame()
})
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
        <div class="flex gap-2 text-sm text-muted-foreground">
          <span class="inline-flex items-center gap-2 rounded-full border bg-secondary px-3 py-1">
            <Users class="size-4" />
            Команды
          </span>
          <span class="inline-flex items-center gap-2 rounded-full border bg-secondary px-3 py-1">
            <ListChecks class="size-4" />
            Раунды
          </span>
        </div>
      </div>
    </header>

    <Card v-if="quizStore.currentGame">
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
