<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ArrowLeft, Check, Copy, Pencil, X } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Spinner } from '~/components/ui/spinner'
import ScoreTable from '~/components/ScoreTable.vue'
import { useResultsClipboard } from '~/composables/useResultsClipboard'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const route = useRoute()
const titleDraft = ref('')
const titleError = ref('')
const isDeleting = ref(false)
const isConfirmingDelete = ref(false)

const { copyStatus, copyStatusText, copyResults } = useResultsClipboard()

definePageMeta({
  middleware: 'auth'
})

const teamsCount = computed(() => quizStore.currentGame?.teams.length ?? 0)
const roundsCount = computed(() => quizStore.currentGame?.rounds.length ?? 0)
const isFinished = computed(() => quizStore.currentGame?.status === 'finished')

const metaLine = computed(() => {
  const parts: string[] = []
  parts.push(pluralize(teamsCount.value, 'команда', 'команды', 'команд'))
  parts.push(pluralize(roundsCount.value, 'раунд', 'раунда', 'раундов'))
  return parts.join(' · ')
})

watch(
  () => route.params.id,
  async (gameId) => {
    await quizStore.loadGame(String(gameId ?? ''))
  },
  { immediate: true }
)

watch(
  () => quizStore.currentGame?.title,
  (value) => {
    titleDraft.value = value ?? ''
  },
  { immediate: true }
)

onBeforeRouteLeave(async () => {
  await quizStore.flushPendingScoreChanges(true)
})

onMounted(() => {
  window.addEventListener('pagehide', flushScoresBeforePageHide)
  document.addEventListener('visibilitychange', flushScoresWhenHidden)
})

onBeforeUnmount(() => {
  window.removeEventListener('pagehide', flushScoresBeforePageHide)
  document.removeEventListener('visibilitychange', flushScoresWhenHidden)
})

function flushScoresBeforePageHide(): void {
  void quizStore.flushPendingScoreChanges(true)
}

function flushScoresWhenHidden(): void {
  if (document.visibilityState === 'hidden') {
    void quizStore.flushPendingScoreChanges(true)
  }
}

function commitTitle(): void {
  const next = titleDraft.value.trim()

  if (!next) {
    titleError.value = 'Название игры не может быть пустым.'
    titleDraft.value = quizStore.currentGame?.title ?? ''
    return
  }

  titleError.value = ''

  if (next === quizStore.currentGame?.title) {
    return
  }

  quizStore.updateGameTitle(next)
}

function handleTitleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && event.currentTarget instanceof HTMLInputElement) {
    event.currentTarget.blur()
  }

  if (event.key === 'Escape' && event.currentTarget instanceof HTMLInputElement) {
    titleDraft.value = quizStore.currentGame?.title ?? ''
    event.currentTarget.blur()
  }
}

async function finishCurrentGame(): Promise<void> {
  await quizStore.finishGame()
}

function requestDelete(): void {
  isConfirmingDelete.value = true
}

function cancelDelete(): void {
  isConfirmingDelete.value = false
}

async function confirmDelete(): Promise<void> {
  if (isDeleting.value) {
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
    isConfirmingDelete.value = false
  }
}

function shareResults(): void {
  if (!quizStore.currentGame) {
    return
  }

  copyResults(quizStore.sortedTeams, quizStore.currentGame.rounds)
}

function pluralize(count: number, one: string, few: string, many: string): string {
  const mod10 = count % 10
  const mod100 = count % 100

  if (mod10 === 1 && mod100 !== 11) {
    return `${count} ${one}`
  }

  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return `${count} ${few}`
  }

  return `${count} ${many}`
}
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col gap-10 px-4 py-8 lg:px-8 lg:py-12">
    <template v-if="quizStore.currentGame">
      <NuxtLink
        to="/"
        class="inline-flex items-center gap-1.5 self-start text-sm text-muted-foreground transition-colors hover:text-foreground"
      >
        <ArrowLeft class="size-3.5" />
        Все игры
      </NuxtLink>

      <header class="border-b border-rule-strong pb-6">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 eyebrow text-muted-foreground">
          <span>{{ isFinished ? 'Завершено' : 'Идёт игра' }}</span>
          <span aria-hidden="true">·</span>
          <span>{{ metaLine }}</span>
          <span v-if="quizStore.scoreSyncText" aria-hidden="true">·</span>
          <span
            v-if="quizStore.scoreSyncText"
            class="normal-case tracking-normal"
            :class="{
              'text-foreground': quizStore.scoreSyncTone === 'saved',
              'text-primary': quizStore.scoreSyncTone === 'error',
              'text-muted-foreground': quizStore.scoreSyncTone === 'saving'
            }"
          >
            {{ quizStore.scoreSyncText }}
          </span>
        </div>

        <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
          <label class="flex-1 text-foreground">
            <span class="sr-only">Название игры</span>
            <input
              v-model="titleDraft"
              type="text"
              class="editable font-display w-full text-4xl font-medium leading-tight tracking-tight lg:text-5xl"
              :disabled="isFinished"
              :aria-invalid="Boolean(titleError)"
              @blur="commitTitle"
              @keydown="handleTitleKeydown"
            >
          </label>

          <div class="flex flex-col items-start gap-2 lg:items-end">
            <Button type="button" @click="shareResults">
              <Copy class="size-4" />
              Скопировать результаты
            </Button>
            <span
              v-if="copyStatusText"
              class="text-xs"
              :class="copyStatus === 'error' ? 'text-primary' : 'text-muted-foreground'"
            >
              {{ copyStatusText }}
            </span>
            <NuxtLink
              v-if="!isFinished"
              :to="`/games/${quizStore.currentGame.id}/edit`"
              class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
              <Pencil class="size-3.5" />
              Команды и раунды
            </NuxtLink>
          </div>
        </div>

        <p v-if="titleError" class="mt-2 text-sm text-primary">
          {{ titleError }}
        </p>
      </header>

      <p
        v-if="quizStore.error"
        class="border-l-0 border-t border-primary bg-secondary px-3 py-2 text-sm text-foreground"
      >
        {{ quizStore.error }}
      </p>

      <ScoreTable :read-only="isFinished" />

      <footer class="flex flex-wrap items-center justify-end gap-4 border-t border-rule pt-6 text-sm">
        <button
          v-if="!isFinished"
          type="button"
          class="inline-flex items-center gap-2 font-medium text-foreground transition-colors hover:text-primary disabled:opacity-60"
          :disabled="quizStore.isSavingScores"
          @click="finishCurrentGame"
        >
          <Check class="size-4" />
          Завершить игру
        </button>

        <template v-if="!isConfirmingDelete">
          <button
            type="button"
            class="text-muted-foreground underline decoration-rule underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
            @click="requestDelete"
          >
            Удалить игру
          </button>
        </template>

        <template v-else>
          <span class="text-foreground">Удалить навсегда?</span>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 font-medium text-primary underline decoration-primary/40 underline-offset-4 hover:decoration-primary disabled:opacity-60"
            :disabled="isDeleting"
            @click="confirmDelete"
          >
            <Spinner v-if="isDeleting" />
            Да, удалить
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
            :disabled="isDeleting"
            @click="cancelDelete"
          >
            <X class="size-3.5" />
            Отмена
          </button>
        </template>
      </footer>
    </template>

    <section v-else class="flex flex-col items-start gap-4 py-16">
      <p class="eyebrow text-muted-foreground">
        {{ quizStore.isLoading ? 'Загрузка' : 'Не найдено' }}
      </p>
      <h1 class="font-display text-3xl font-medium">
        {{ quizStore.isLoading ? 'Загружаем игру' : 'Игра не найдена' }}
      </h1>
      <p class="text-sm text-muted-foreground">
        {{ quizStore.isLoading ? 'Проверяем сохранённые данные.' : 'Возможно, ссылка устарела.' }}
      </p>
      <NuxtLink
        to="/"
        class="text-sm font-medium text-foreground underline decoration-rule-strong underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
      >
        Вернуться к списку
      </NuxtLink>
    </section>
  </main>
</template>
