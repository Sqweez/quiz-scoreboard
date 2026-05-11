<script setup lang="ts">
import { ref } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import Button from './ui/button/Button.vue'
import Card from './ui/card/Card.vue'
import CardContent from './ui/card/CardContent.vue'
import CardDescription from './ui/card/CardDescription.vue'
import CardHeader from './ui/card/CardHeader.vue'
import CardTitle from './ui/card/CardTitle.vue'
import Input from './ui/input/Input.vue'
import Label from './ui/label/Label.vue'
import { useQuizStore } from '../stores/quiz'

type DraftRound = {
  id: string
  title: string
  maxScore: string
  questionsCount: string
}

const router = useRouter()
const quizStore = useQuizStore()

const title = ref('Квиз')
const teamNames = ref([
  { id: createDraftId(), name: 'Команда 1' },
  { id: createDraftId(), name: 'Команда 2' }
])
const rounds = ref<DraftRound[]>([
  { id: createDraftId(), title: 'Раунд 1', maxScore: '', questionsCount: '' },
  { id: createDraftId(), title: 'Раунд 2', maxScore: '', questionsCount: '' }
])
const error = ref('')

function addTeam(): void {
  teamNames.value.push({ id: createDraftId(), name: `Команда ${teamNames.value.length + 1}` })
}

function removeTeam(id: string): void {
  if (teamNames.value.length > 1) {
    teamNames.value = teamNames.value.filter((team) => team.id !== id)
  }
}

function addRound(): void {
  rounds.value.push({
    id: createDraftId(),
    title: `Раунд ${rounds.value.length + 1}`,
    maxScore: '',
    questionsCount: ''
  })
}

function removeRound(id: string): void {
  if (rounds.value.length > 1) {
    rounds.value = rounds.value.filter((round) => round.id !== id)
  }
}

function createGame(): void {
  const teamValues = teamNames.value.map((team) => team.name.trim()).filter(Boolean)
  const roundValues = rounds.value
    .map((round) => ({
      title: round.title.trim(),
      maxScore: parseOptionalNumber(round.maxScore),
      questionsCount: parseOptionalNumber(round.questionsCount)
    }))
    .filter((round) => round.title)

  if (!title.value.trim()) {
    error.value = 'Введите название игры.'
    return
  }

  if (teamValues.length < 1 || teamValues.length !== teamNames.value.length) {
    error.value = 'Добавьте минимум одну команду и заполните все названия команд.'
    return
  }

  if (roundValues.length < 1 || roundValues.length !== rounds.value.length) {
    error.value = 'Добавьте минимум один раунд и заполните все названия раундов.'
    return
  }

  quizStore.createGame({
    title: title.value,
    teamNames: teamValues,
    rounds: roundValues
  })
  router.push('/game')
}

function parseOptionalNumber(value: string): number | null {
  return value === '' ? null : Math.max(0, Number(value))
}

function createDraftId(): string {
  return `${Date.now()}-${Math.random().toString(36).slice(2)}`
}
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
