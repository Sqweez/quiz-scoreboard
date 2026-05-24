<script setup lang="ts">
import { Plus, X } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { Spinner } from '~/components/ui/spinner'
import { useGameSetupForm } from '~/composables/useGameSetupForm'
import { useQuizStore } from '~/stores/quiz'

const router = useRouter()
const quizStore = useQuizStore()
const {
  title,
  teamNames,
  rounds,
  error,
  isSubmitting,
  addTeam,
  removeTeam,
  addRound,
  removeRound,
  createGame
} = useGameSetupForm({
  createGame: quizStore.createGame,
  navigateToGame: (game) => router.push({ path: `/games/${game.id}` })
})
</script>

<template>
  <form class="space-y-12" @submit.prevent="createGame">
    <section class="space-y-3">
      <Label for="game-title" class="eyebrow-tight text-muted-foreground">
        Название игры
      </Label>
      <input
        id="game-title"
        v-model="title"
        type="text"
        class="editable font-display w-full text-2xl font-medium leading-tight lg:text-3xl"
        placeholder="Например: «Среда вечером, 7 марта»"
      >
    </section>

    <section class="space-y-4">
      <header class="flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2">
        <h2 class="font-display text-xl font-medium">Команды</h2>
        <Button type="button" variant="ghost" size="sm" class="text-sm text-muted-foreground" @click.prevent="addTeam">
          <Plus class="size-3.5" />
          Добавить
        </Button>
      </header>

      <ul class="divide-y divide-rule">
        <li
          v-for="(team, index) in teamNames"
          :key="team.id"
          class="flex items-center gap-3 py-3"
        >
          <span class="w-6 font-display text-base tabular-nums text-muted-foreground">
            {{ index + 1 }}
          </span>
          <input
            v-model="team.name"
            aria-label="Название команды"
            class="editable flex-1 font-display text-lg leading-tight"
            @keydown.enter.prevent
          >
          <button
            type="button"
            class="inline-flex items-center text-sm text-muted-foreground transition-colors hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="teamNames.length <= 1"
            aria-label="Удалить команду"
            @click.prevent="removeTeam(team.id)"
          >
            <X class="size-4" />
          </button>
        </li>
      </ul>
    </section>

    <section class="space-y-4">
      <header class="flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2">
        <h2 class="font-display text-xl font-medium">Раунды</h2>
        <Button type="button" variant="ghost" size="sm" class="text-sm text-muted-foreground" @click.prevent="addRound">
          <Plus class="size-3.5" />
          Добавить
        </Button>
      </header>

      <ul class="divide-y divide-rule">
        <li
          v-for="(round, index) in rounds"
          :key="round.id"
          class="grid gap-3 py-4 md:grid-cols-[2.5rem_minmax(0,1fr)_auto_auto_auto] md:items-center"
        >
          <span class="font-display text-base tabular-nums text-muted-foreground">
            {{ index + 1 }}
          </span>
          <input
            v-model="round.title"
            aria-label="Название раунда"
            class="editable font-display text-lg leading-tight"
            @keydown.enter.prevent
          >
          <label class="flex items-baseline gap-2 text-sm text-muted-foreground">
            <span class="eyebrow-tight">Макс</span>
            <Input v-model="round.maxScore" type="number" min="0" class="h-8 w-16" />
          </label>
          <label class="flex items-baseline gap-2 text-sm text-muted-foreground">
            <span class="eyebrow-tight">Вопросы</span>
            <Input v-model="round.questionsCount" type="number" min="0" class="h-8 w-16" />
          </label>
          <button
            type="button"
            class="inline-flex items-center text-sm text-muted-foreground transition-colors hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="rounds.length <= 1"
            aria-label="Удалить раунд"
            @click.prevent="removeRound(round.id)"
          >
            <X class="size-4" />
          </button>
        </li>
      </ul>
    </section>

    <p v-if="error" class="border-t border-primary bg-secondary px-3 py-2 text-sm text-foreground">
      {{ error }}
    </p>

    <div class="flex justify-end border-t border-rule-strong pt-6">
      <Button type="submit" size="lg" :disabled="isSubmitting">
        <Spinner v-if="isSubmitting" />
        Начать игру
      </Button>
    </div>
  </form>
</template>
