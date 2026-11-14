<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { nextTick, ref, watch } from 'vue'
import { Minus, Plus, X } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Spinner } from '~/components/ui/spinner'
import QuizPleaseImport from '~/components/QuizPleaseImport.vue'
import QuizPleaseLoginDialog from '~/components/QuizPleaseLoginDialog.vue'
import RatingSettings from '~/components/RatingSettings.vue'
import type { ExternalGame } from '~/composables/useQuizPleaseImport'
import { MAX_ROUNDS, MIN_ROUNDS, useGameSetupForm } from '~/composables/useGameSetupForm'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const {
  title,
  teams,
  roundsCount,
  externalId,
  externalIdError,
  ratingCategoryId,
  ratingIncludeScores,
  ratingPackage,
  isLoginOpen,
  login,
  cancelLogin,
  filledTeamNames,
  error,
  isSubmitting,
  showErrors,
  titleError,
  teamsError,
  duplicateTeamIds,
  addTeam,
  removeTeam,
  pasteTeams,
  applyImport,
  setRoundsCount,
  createGame
} = useGameSetupForm({
  createGame: quizStore.createGame,
  navigateToGame: (game) => router.visit(`/games/${game.id}`)
})

const source = ref<'manual' | 'external'>('manual')
const importedPackage = ref<string | null>(null)

// The two sources each own the external ID; carrying it across would link the game to an ID the user no longer sees.
watch(source, () => {
  externalId.value = ''
  importedPackage.value = null
})

function handleImported(game: ExternalGame): void {
  // An already imported game is linked from the preview instead of being prepared for a duplicate.
  if (game.existingGameId) {
    return
  }

  importedPackage.value = game.extra.packageNumber
  applyImport({ externalId: game.externalId, title: game.title, teamNames: game.teams.map((team) => team.name) })
}

async function focusTeam(id: string): Promise<void> {
  await nextTick()
  document.querySelector<HTMLInputElement>(`[data-team-id="${id}"]`)?.focus()
}

function addTeamAndFocus(afterId?: string): void {
  void focusTeam(addTeam(afterId).id)
}

function handleTeamEnter(index: number): void {
  const team = teams.value[index]
  const next = teams.value[index + 1]

  if (next) {
    void focusTeam(next.id)
    return
  }

  if (team.name.trim()) {
    addTeamAndFocus(team.id)
  }
}

function handleTeamBackspace(event: KeyboardEvent, index: number): void {
  const team = teams.value[index]

  if (team.name || teams.value.length <= 1) {
    return
  }

  event.preventDefault()
  const neighbour = teams.value[index - 1] ?? teams.value[index + 1]
  removeTeam(team.id)
  void focusTeam(neighbour.id)
}

function handleTeamPaste(event: ClipboardEvent, id: string): void {
  const text = event.clipboardData?.getData('text') ?? ''

  if (!text.includes('\n')) {
    return
  }

  event.preventDefault()
  const last = pasteTeams(id, text)

  if (last) {
    void focusTeam(last.id)
  }
}
</script>

<template>
  <form class="flex flex-col gap-12" novalidate @submit.prevent="createGame">
    <section class="space-y-6">
      <div class="flex gap-2" role="group" aria-label="Способ создания">
        <Button
          type="button"
          :variant="source === 'manual' ? 'secondary' : 'ghost'"
          :aria-pressed="source === 'manual'"
          @click="source = 'manual'"
        >
          Вручную
        </Button>
        <Button
          type="button"
          :variant="source === 'external' ? 'secondary' : 'ghost'"
          :aria-pressed="source === 'external'"
          @click="source = 'external'"
        >
          Создать из внешней системы
        </Button>
      </div>

      <QuizPleaseImport v-show="source === 'external'" @imported="handleImported" />
    </section>

    <section class="space-y-3">
      <label for="game-title" class="eyebrow-tight block text-muted-foreground">
        Название игры
      </label>
      <input
        id="game-title"
        v-model="title"
        type="text"
        autocomplete="off"
        autofocus
        class="w-full border-0 border-b border-rule-strong bg-transparent pb-2 font-display text-3xl font-medium leading-tight tracking-tight outline-none transition-colors placeholder:text-muted-foreground/40 focus:border-foreground lg:text-4xl"
        :class="{ 'border-primary focus:border-primary': showErrors && titleError }"
        placeholder="Среда, бар «Маяк»"
        :aria-invalid="showErrors && Boolean(titleError)"
        @keydown.enter.prevent="focusTeam(teams[0].id)"
      >
      <p v-if="showErrors && titleError" class="text-sm text-primary">
        {{ titleError }}
      </p>
    </section>

    <!-- In external mode the ID comes from the import block above, so the manual field would duplicate it. -->
    <section v-if="source === 'manual'" class="space-y-3">
      <label for="game-external-id" class="eyebrow-tight block text-muted-foreground">
        ID игры в Квиз, плиз! — необязательно
      </label>
      <input
        id="game-external-id"
        v-model="externalId"
        type="text"
        autocomplete="off"
        spellcheck="false"
        placeholder="01a19b42-0cc8-709f-8197-62881b0dbcdc"
        class="h-12 w-full border-0 border-b border-rule-strong bg-transparent font-mono text-base outline-none transition-colors placeholder:text-muted-foreground/40 focus:border-foreground"
        :class="{ 'border-primary focus:border-primary': showErrors && externalIdError }"
        :aria-invalid="showErrors && Boolean(externalIdError)"
      >
      <p v-if="showErrors && externalIdError" class="text-sm text-primary">
        {{ externalIdError }}
      </p>
    </section>

    <section class="space-y-2">
      <div class="flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2">
        <h2 class="eyebrow text-muted-foreground">Команды</h2>
        <span class="eyebrow text-muted-foreground">{{ filledTeamNames.length }}</span>
      </div>

      <ul class="divide-y divide-rule">
        <li
          v-for="(team, index) in teams"
          :key="team.id"
          class="flex items-center gap-3 transition-colors focus-within:bg-primary/[0.07]"
        >
          <span class="w-8 shrink-0 pl-1 font-display text-base tabular-nums text-muted-foreground">
            {{ index + 1 }}
          </span>
          <input
            v-model="team.name"
            type="text"
            autocomplete="off"
            enterkeyhint="next"
            :data-team-id="team.id"
            :aria-label="`Команда ${index + 1}`"
            :aria-invalid="showErrors && duplicateTeamIds.has(team.id)"
            placeholder="Название команды"
            class="h-14 min-w-0 flex-1 bg-transparent font-display text-xl leading-tight outline-none placeholder:text-muted-foreground/40"
            :class="{ 'text-primary': showErrors && duplicateTeamIds.has(team.id) }"
            @keydown.enter.prevent="handleTeamEnter(index)"
            @keydown.backspace="handleTeamBackspace($event, index)"
            @paste="handleTeamPaste($event, team.id)"
          >
          <button
            type="button"
            class="inline-flex size-12 shrink-0 items-center justify-center text-muted-foreground transition-colors hover:text-primary disabled:pointer-events-none disabled:opacity-30"
            :disabled="teams.length <= 1"
            :aria-label="`Убрать команду ${index + 1}`"
            @click="removeTeam(team.id)"
          >
            <X class="size-4" />
          </button>
        </li>
      </ul>

      <button
        type="button"
        class="flex h-12 w-full items-center gap-2 border-t border-dashed border-rule-strong pl-1 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
        @click="addTeamAndFocus()"
      >
        <Plus class="size-4" />
        Добавить команду
      </button>

      <p v-if="showErrors && teamsError" class="text-sm text-primary">
        {{ teamsError }}
      </p>
      <p class="text-xs text-muted-foreground">
        Enter — следующая команда. Можно вставить список, по команде на строку. Пустые строки не учитываются.
      </p>
    </section>

    <section class="space-y-4">
      <div class="border-b border-rule-strong pb-2">
        <h2 id="rounds-label" class="eyebrow text-muted-foreground">Раунды</h2>
      </div>

      <div class="flex items-center gap-4" role="group" aria-labelledby="rounds-label">
        <Button
          type="button"
          variant="outline"
          class="size-12"
          :disabled="roundsCount <= MIN_ROUNDS"
          aria-label="Меньше раундов"
          @click="setRoundsCount(roundsCount - 1)"
        >
          <Minus class="size-5" />
        </Button>
        <output class="w-16 text-center font-display text-5xl font-medium tabular-nums" aria-live="polite">
          {{ roundsCount }}
        </output>
        <Button
          type="button"
          variant="outline"
          class="size-12"
          :disabled="roundsCount >= MAX_ROUNDS"
          aria-label="Больше раундов"
          @click="setRoundsCount(roundsCount + 1)"
        >
          <Plus class="size-5" />
        </Button>
      </div>
    </section>

    <RatingSettings :auto-package="importedPackage" v-model:category-id="ratingCategoryId" v-model:include-scores="ratingIncludeScores" v-model:package="ratingPackage" />

    <QuizPleaseLoginDialog :open="isLoginOpen" :submit="login" @cancel="cancelLogin" />

    <div class="sticky bottom-0 -mx-4 space-y-3 border-t border-rule-strong bg-background px-4 py-4 lg:-mx-8 lg:px-8">
      <p v-if="error" class="text-sm text-primary">
        {{ error }}
      </p>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-muted-foreground">
          Команд: {{ filledTeamNames.length }} · Раундов: {{ roundsCount }}
        </p>
        <Button type="submit" size="lg" class="h-12 w-full sm:w-auto" :disabled="isSubmitting">
          <Spinner v-if="isSubmitting" />
          Начать игру
        </Button>
      </div>
    </div>
  </form>
</template>
