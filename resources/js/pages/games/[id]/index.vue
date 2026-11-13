<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ArrowLeft, Check, Copy, Pencil } from 'lucide-vue-next'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle
} from '~/components/ui/alert-dialog'
import { Button } from '~/components/ui/button'
import { Spinner } from '~/components/ui/spinner'
import ScoreTable from '~/components/ScoreTable.vue'
import { useResultsClipboard } from '~/composables/useResultsClipboard'
import { useQuizStore } from '~/stores/quiz'
import { createPendingNavigation } from '~/utils/pendingNavigation'

const quizStore = useQuizStore()
const props = defineProps<{ gameId: string }>()
const titleDraft = ref('')
const titleError = ref('')
const isDeleting = ref(false)
const isConfirmingDelete = ref(false)
const isFinishing = ref(false)
const isConfirmingFinish = ref(false)

const { copyStatus, copyStatusText, copyResults } = useResultsClipboard()

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
  () => props.gameId,
  async (gameId) => {
    await quizStore.loadGame(gameId)
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

const guardNavigation = createPendingNavigation(
  () => quizStore.isSavingScores,
  () => quizStore.flushPendingScoreChanges(true),
  (visit: Parameters<typeof router.visit>[1] & { url: URL }) => router.visit(visit.url, visit)
)
const stopNavigationGuard = router.on('before', event => {
  guardNavigation(event.detail.visit, () => event.preventDefault())
})

onMounted(() => {
  window.addEventListener('pagehide', flushScoresBeforePageHide)
  document.addEventListener('visibilitychange', flushScoresWhenHidden)
})

onBeforeUnmount(() => {
  stopNavigationGuard()
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

function requestFinish(): void {
  isConfirmingFinish.value = true
}

async function confirmFinish(): Promise<void> {
  if (isFinishing.value) {
    return
  }

  isFinishing.value = true

  try {
    await quizStore.finishGame()
  } finally {
    isFinishing.value = false
    isConfirmingFinish.value = false
  }
}

function requestDelete(): void {
  isConfirmingDelete.value = true
}

async function confirmDelete(): Promise<void> {
  if (isDeleting.value) {
    return
  }

  isDeleting.value = true

  try {
    await quizStore.clearGame()

    if (!quizStore.error) {
      router.visit('/')
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
      <Link
        href="/"
        class="inline-flex items-center gap-1.5 self-start text-sm text-muted-foreground transition-colors hover:text-foreground"
      >
        <ArrowLeft class="size-3.5" />
        Все игры
      </Link>

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
            <div class="flex flex-wrap gap-2 lg:justify-end">
              <Button
                v-if="!isFinished"
                type="button"
                variant="outline"
                @click="requestFinish"
              >
                <Check class="size-4" />
                Завершить игру
              </Button>
              <Button type="button" @click="shareResults">
                <Copy class="size-4" />
                Скопировать результаты
              </Button>
            </div>
            <span
              v-if="copyStatusText"
              class="text-xs"
              :class="copyStatus === 'error' ? 'text-primary' : 'text-muted-foreground'"
            >
              {{ copyStatusText }}
            </span>
            <Link
              v-if="!isFinished"
              :href="`/games/${quizStore.currentGame.id}/edit`"
              class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
              <Pencil class="size-3.5" />
              Команды и раунды
            </Link>
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

      <AlertDialog v-model:open="isConfirmingFinish">
        <AlertDialogContent>
          <AlertDialogTitle class="font-display text-2xl font-medium">
            Завершить игру?
          </AlertDialogTitle>
          <AlertDialogDescription class="text-sm text-muted-foreground">
            После завершения очки больше нельзя будет менять.
          </AlertDialogDescription>
          <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <AlertDialogCancel as-child>
              <Button variant="outline" size="lg" :disabled="isFinishing">
                Отмена
              </Button>
            </AlertDialogCancel>
            <Button
              size="lg"
              :disabled="isFinishing || quizStore.isSavingScores"
              @click="confirmFinish"
            >
              <Spinner v-if="isFinishing" />
              Да, завершить
            </Button>
          </div>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog v-model:open="isConfirmingDelete">
        <AlertDialogContent>
          <AlertDialogTitle class="font-display text-2xl font-medium">
            Удалить игру?
          </AlertDialogTitle>
          <AlertDialogDescription class="text-sm text-muted-foreground">
            Игра и все очки будут удалены навсегда.
          </AlertDialogDescription>
          <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <AlertDialogCancel as-child>
              <Button variant="outline" size="lg" :disabled="isDeleting">
                Отмена
              </Button>
            </AlertDialogCancel>
            <Button
              variant="destructive"
              size="lg"
              :disabled="isDeleting"
              @click="confirmDelete"
            >
              <Spinner v-if="isDeleting" />
              Да, удалить
            </Button>
          </div>
        </AlertDialogContent>
      </AlertDialog>

      <footer class="flex flex-wrap items-center justify-end gap-4 border-t border-rule pt-6 text-sm">
        <button
          type="button"
          class="text-muted-foreground underline decoration-rule underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
          @click="requestDelete"
        >
          Удалить игру
        </button>
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
      <Link
        href="/"
        class="text-sm font-medium text-foreground underline decoration-rule-strong underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
      >
        Вернуться к списку
      </Link>
    </section>
  </main>
</template>
