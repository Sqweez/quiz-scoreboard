<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { useGameSetupForm } from '~/composables/useGameSetupForm'
import { useQuizStore } from '~/stores/quiz'

const router = useRouter()
const quizStore = useQuizStore()
const { title, teamNames, rounds, error, addTeam, removeTeam, addRound, removeRound, createGame } = useGameSetupForm({
  createGame: quizStore.createGame,
  navigateToGame: (game) => router.push({ path: '/game', query: { gameId: game.id } })
})
</script>

<template>
  <Card class="overflow-hidden">
    <CardHeader class="border-b bg-secondary/35">
      <CardTitle>Создание игры</CardTitle>
      <CardDescription>Настройте команды и раунды перед началом подсчета.</CardDescription>
    </CardHeader>
    <CardContent>
      <form class="space-y-6" @submit.prevent="createGame">
        <div class="space-y-2">
          <Label for="game-title">Название игры</Label>
          <Input id="game-title" v-model="title" />
        </div>

        <section class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Команды</h3>
            <Button variant="outline" size="sm" @click="addTeam">
              <Plus class="size-4" />
              Добавить команду
            </Button>
          </div>
          <div class="space-y-2">
            <div v-for="(team, index) in teamNames" :key="team.id" class="flex gap-2 rounded-md border bg-background p-2">
              <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-secondary text-sm font-semibold text-muted-foreground">
                {{ index + 1 }}
              </span>
              <Input v-model="team.name" aria-label="Название команды" />
              <Button
                variant="ghost"
                size="icon"
                :disabled="teamNames.length <= 1"
                aria-label="Удалить команду"
                @click="removeTeam(team.id)"
              >
                <Trash2 class="size-4" />
              </Button>
            </div>
          </div>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Раунды</h3>
            <Button variant="outline" size="sm" @click="addRound">
              <Plus class="size-4" />
              Добавить раунд
            </Button>
          </div>
          <div class="space-y-2">
            <div
              v-for="round in rounds"
              :key="round.id"
              class="grid gap-2 rounded-md border bg-background p-3 md:grid-cols-[minmax(0,1fr)_120px_140px_auto]"
            >
              <Input v-model="round.title" aria-label="Название раунда" />
              <Input v-model="round.maxScore" type="number" min="0" placeholder="Макс." />
              <Input v-model="round.questionsCount" type="number" min="0" placeholder="Вопросы" />
              <Button
                variant="ghost"
                size="icon"
                :disabled="rounds.length <= 1"
                aria-label="Удалить раунд"
                @click="removeRound(round.id)"
              >
                <Trash2 class="size-4" />
              </Button>
            </div>
          </div>
        </section>

        <p v-if="error" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {{ error }}
        </p>

        <div class="flex justify-end border-t pt-4">
          <Button type="submit" size="lg">
            Создать игру
          </Button>
        </div>
      </form>
    </CardContent>
  </Card>
</template>
