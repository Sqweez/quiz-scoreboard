<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { ArrowLeft } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Spinner } from '~/components/ui/spinner'
import type { Game } from '~/lib/quiz'
import TeamsEditor from '~/components/TeamsEditor.vue'
import RoundsEditor from '~/components/RoundsEditor.vue'
import QuizPleaseLoginDialog from '~/components/QuizPleaseLoginDialog.vue'
import RatingSettings from '~/components/RatingSettings.vue'
import { useQuizStore } from '~/stores/quiz'
import { useQuizPleaseLogin } from '~/composables/useQuizPleaseLogin'

const quizStore = useQuizStore()
const props = defineProps<{ gameId: string }>()

const isFinished = computed(() => quizStore.currentGame?.status === 'finished')
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

type GameDetails = Partial<Pick<Game, 'title' | 'externalId' | 'ratingCategoryId' | 'ratingIncludeScores'>>

const draft = reactive({ title: '', externalId: '', ratingCategoryId: null as number | null, ratingIncludeScores: true })
const detailsError = ref('')
const isSaving = ref(false)
const { isLoginOpen, requireLogin, login, cancelLogin } = useQuizPleaseLogin()

function resetDraft(): void {
  const game = quizStore.currentGame

  if (!game) {
    return
  }

  draft.title = game.title
  draft.externalId = game.externalId ?? ''
  draft.ratingCategoryId = game.ratingCategoryId
  draft.ratingIncludeScores = game.ratingIncludeScores
  detailsError.value = ''
}

watch(() => quizStore.currentGame?.id, resetDraft, { immediate: true })

// Only changed fields are sent, so an untouched external id does not trigger a Quiz Please request.
const changes = computed<GameDetails>(() => {
  const game = quizStore.currentGame
  const result: GameDetails = {}

  if (!game) {
    return result
  }

  const externalId = draft.externalId.trim().toLowerCase()

  if (draft.title.trim() !== game.title) {
    result.title = draft.title.trim()
  }
  if (externalId !== (game.externalId ?? '')) {
    result.externalId = externalId || null
  }
  if (draft.ratingCategoryId !== game.ratingCategoryId) {
    result.ratingCategoryId = draft.ratingCategoryId
  }
  if (draft.ratingIncludeScores !== game.ratingIncludeScores) {
    result.ratingIncludeScores = draft.ratingIncludeScores
  }

  return result
})

const isDirty = computed(() => Object.keys(changes.value).length > 0)

async function saveDetails(): Promise<void> {
  if (isSaving.value || !isDirty.value) {
    return
  }

  const details = changes.value

  if (details.title === '') {
    detailsError.value = 'Название игры не может быть пустым.'
    return
  }
  if (details.externalId && !UUID_PATTERN.test(details.externalId)) {
    detailsError.value = 'ID игры в Квиз, плиз! должен быть UUID.'
    return
  }

  detailsError.value = ''
  isSaving.value = true

  try {
    await quizStore.updateGameDetails(details)
    resetDraft()
  } catch (requestError) {
    // Linking reads the game from Quiz Please; after a login the same changes are saved again.
    // Other errors are shown by the store and the draft is kept for correction.
    requireLogin(requestError, saveDetails)
  } finally {
    isSaving.value = false
  }
}

const externalSummary = computed(() => {
  const extra = quizStore.currentGame?.extra

  if (!extra) {
    return ''
  }

  return [
    extra.title,
    extra.gameNumber && `#${extra.gameNumber}`,
    extra.packageNumber && `пакет ${extra.packageNumber}`,
    extra.date && formatWallClock(extra.date)
  ].filter(Boolean).join(' · ')
})

// "YYYY-MM-DD HH:mm" without a time zone is shown as is, only reordered.
function formatWallClock(value: string): string {
  const [date, time] = value.split(' ')
  const [year, month, day] = date.split('-')
  return `${day}.${month}.${year} ${time}`
}

watch(
  () => props.gameId,
  async (gameId) => {
    await quizStore.loadGame(gameId)
  },
  { immediate: true }
)
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col gap-10 px-4 py-8 lg:px-8 lg:py-12">
    <template v-if="quizStore.currentGame">
      <Link
        :href="`/games/${quizStore.currentGame.id}`"
        class="inline-flex items-center gap-1.5 self-start text-sm text-muted-foreground transition-colors hover:text-foreground"
      >
        <ArrowLeft class="size-3.5" />
        Назад к таблице
      </Link>

      <header class="border-b border-rule-strong pb-6">
        <p class="eyebrow text-muted-foreground">
          Настройка игры
        </p>
        <h1 class="mt-3 font-display text-3xl font-medium leading-tight lg:text-4xl">
          {{ quizStore.currentGame.title }}
        </h1>
        <p v-if="isFinished" class="mt-3 text-sm text-muted-foreground">
          Игра завершена: команды закрыты, название, ID игры и настройки рейтинга можно изменить.
        </p>
      </header>

      <p
        v-if="quizStore.error"
        class="border-t border-primary bg-secondary px-3 py-2 text-sm text-foreground"
      >
        {{ quizStore.error }}
      </p>

      <div class="flex flex-col gap-12">
        <form class="flex flex-col gap-8" novalidate @submit.prevent="saveDetails">
          <section class="space-y-3">
            <label for="game-title" class="eyebrow block text-muted-foreground">
              Название игры
            </label>
            <input
              id="game-title"
              v-model="draft.title"
              type="text"
              autocomplete="off"
              class="h-12 w-full border-0 border-b border-rule-strong bg-transparent font-display text-xl outline-none transition-colors focus:border-foreground"
            >
          </section>

          <section class="space-y-3">
            <label for="game-external-id" class="eyebrow block text-muted-foreground">
              ID игры в Квиз, плиз!
            </label>
            <input
              id="game-external-id"
              v-model="draft.externalId"
              type="text"
              autocomplete="off"
              spellcheck="false"
              placeholder="Не указан"
              class="h-12 w-full border-0 border-b border-rule-strong bg-transparent font-mono text-base outline-none transition-colors placeholder:text-muted-foreground/40 focus:border-foreground"
            >
            <p v-if="externalSummary" class="text-sm text-muted-foreground">{{ externalSummary }}</p>
            <p class="text-xs text-muted-foreground">
              Оставьте пустым, чтобы отвязать игру. При сохранении данные игры подтянутся из Квиз, плиз!
            </p>
          </section>

          <RatingSettings v-model:category-id="draft.ratingCategoryId" v-model:include-scores="draft.ratingIncludeScores" />

          <div class="space-y-3">
            <p v-if="detailsError" class="text-sm text-primary">{{ detailsError }}</p>
            <div class="flex flex-col gap-3 sm:flex-row">
              <Button type="submit" size="lg" class="h-12" :disabled="isSaving || !isDirty">
                <Spinner v-if="isSaving" />
                Сохранить
              </Button>
              <Button v-if="isDirty" type="button" variant="ghost" size="lg" class="h-12" :disabled="isSaving" @click="resetDraft">
                Отменить изменения
              </Button>
            </div>
          </div>
        </form>

        <QuizPleaseLoginDialog :open="isLoginOpen" :submit="login" @cancel="cancelLogin" />

        <TeamsEditor :read-only="isFinished" />
        <RoundsEditor v-if="!isFinished" />
      </div>
    </template>

    <section v-else class="flex flex-col items-start gap-4 py-16">
      <p class="eyebrow text-muted-foreground">
        {{ quizStore.isLoading ? 'Загрузка' : 'Не найдено' }}
      </p>
      <h1 class="font-display text-3xl font-medium">
        {{ quizStore.isLoading ? 'Загружаем игру' : 'Игра не найдена' }}
      </h1>
      <Link
        href="/"
        class="text-sm font-medium text-foreground underline decoration-rule-strong underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
      >
        Вернуться к списку
      </Link>
    </section>
  </main>
</template>
