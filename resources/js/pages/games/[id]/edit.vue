<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { ArrowLeft } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Spinner } from '~/components/ui/spinner'
import type { Game, GameExtra } from '~/lib/quiz'
import TeamsEditor from '~/components/TeamsEditor.vue'
import RoundsEditor from '~/components/RoundsEditor.vue'
import QuizPleaseLoginDialog from '~/components/QuizPleaseLoginDialog.vue'
import RatingSettings from '~/components/RatingSettings.vue'
import { useQuizStore } from '~/stores/quiz'
import { useQuizPleaseLogin } from '~/composables/useQuizPleaseLogin'
import { type ExternalGame, useQuizPleaseImport } from '~/composables/useQuizPleaseImport'

const quizStore = useQuizStore()
const props = defineProps<{ gameId: string }>()

const isFinished = computed(() => quizStore.currentGame?.status === 'finished')
const isLinked = computed(() => Boolean(quizStore.currentGame?.externalId))

type GameDetails = Partial<Pick<Game, 'title' | 'externalId' | 'ratingCategoryId' | 'ratingIncludeScores' | 'ratingPackage'>>

const draft = reactive({ title: '', externalId: '', ratingCategoryId: null as number | null, ratingIncludeScores: true, ratingPackage: '' })
const detailsError = ref('')
const isSaving = ref(false)
const { isLoginOpen, requireLogin, login, cancelLogin } = useQuizPleaseLogin()

// An unlinked game is linked by fetching it first; the preview is checked and then saved with the rest of the form.
const {
  gameId: lookupId,
  game: lookupGame,
  error: lookupError,
  isFetching: isLookingUp,
  isLoginOpen: isLookupLoginOpen,
  fetchGame: lookUpGame,
  login: lookupLogin,
  cancelLogin: cancelLookupLogin
} = useQuizPleaseImport({ onImported: applyLookup })

function applyLookup(result: ExternalGame): void {
  // A game already linked elsewhere is only linked from the preview, never prepared for a duplicate.
  if (result.existingGameId) {
    return
  }

  draft.externalId = result.externalId
  draft.title = result.title
}

watch(lookupId, (value) => {
  if (!value.trim()) {
    draft.externalId = ''
  }
})

const hasUnfetchedId = computed(() => !isLinked.value && lookupId.value.trim().toLowerCase() !== draft.externalId.toLowerCase())

// The package of the linked (or just fetched) game; an empty manual package falls back to it on the server.
const autoPackage = computed(() => {
  const game = quizStore.currentGame

  if (game?.externalId) {
    return game.extra?.packageNumber ?? null
  }

  return draft.externalId ? lookupGame.value?.extra.packageNumber ?? null : null
})

// A package equal to the imported one is shown as automatic, not as a manual override.
function savedManualPackage(game: Game): string | null {
  return game.ratingPackage === (game.extra?.packageNumber ?? null) ? null : game.ratingPackage
}

function resetDraft(): void {
  const game = quizStore.currentGame

  if (!game) {
    return
  }

  draft.title = game.title
  draft.externalId = game.externalId ?? ''
  draft.ratingCategoryId = game.ratingCategoryId
  draft.ratingIncludeScores = game.ratingIncludeScores
  draft.ratingPackage = savedManualPackage(game) ?? ''
  detailsError.value = ''
  lookupId.value = ''
  lookupGame.value = null
  lookupError.value = ''
}

watch(() => quizStore.currentGame?.id, resetDraft, { immediate: true })

// Only changed fields are sent, so an untouched external id does not trigger a Quiz Please request.
const changes = computed<GameDetails>(() => {
  const game = quizStore.currentGame
  const result: GameDetails = {}

  if (!game) {
    return result
  }

  if (draft.title.trim() !== game.title) {
    result.title = draft.title.trim()
  }
  if (!game.externalId && draft.externalId) {
    result.externalId = draft.externalId
  }
  if (draft.ratingCategoryId !== game.ratingCategoryId) {
    result.ratingCategoryId = draft.ratingCategoryId
  }
  if (draft.ratingIncludeScores !== game.ratingIncludeScores) {
    result.ratingIncludeScores = draft.ratingIncludeScores
  }
  const draftPackage = draft.ratingPackage.trim() || null

  if (draftPackage !== savedManualPackage(game)) {
    result.ratingPackage = draftPackage
  }

  return result
})

const isDirty = computed(() => Object.keys(changes.value).length > 0 || hasUnfetchedId.value)

async function saveDetails(): Promise<void> {
  if (isSaving.value || !isDirty.value) {
    return
  }

  const details = changes.value

  if (details.title === '') {
    detailsError.value = 'Название игры не может быть пустым.'
    return
  }
  if (hasUnfetchedId.value) {
    detailsError.value = lookupGame.value?.existingGameId
      ? 'Эта игра «Квиз, плиз!» уже привязана к другой игре.'
      : 'Нажмите «Подтянуть», чтобы проверить игру перед сохранением.'
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

const externalSummary = computed(() => summarize(quizStore.currentGame?.extra ?? null))

function summarize(extra: GameExtra | null): string {
  if (!extra) {
    return ''
  }

  return [
    extra.title,
    extra.gameNumber && `#${extra.gameNumber}`,
    extra.packageNumber && `пакет ${extra.packageNumber}`,
    extra.date && formatWallClock(extra.date)
  ].filter(Boolean).join(' · ')
}

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
        class="-my-3 inline-flex items-center gap-1.5 self-start py-3 text-sm text-muted-foreground transition-colors hover:text-foreground"
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
            <template v-if="isLinked">
              <p class="eyebrow text-muted-foreground">
                ID игры в Квиз, плиз!
              </p>
              <p class="flex min-h-12 items-center break-all border-b border-rule font-mono text-base">
                {{ quizStore.currentGame.externalId }}
              </p>
              <p v-if="externalSummary" class="text-sm text-muted-foreground">{{ externalSummary }}</p>
              <p class="text-xs text-muted-foreground">
                Игра привязана к Квиз, плиз! — ID изменить нельзя.
              </p>
            </template>
            <template v-else>
              <label for="game-external-id" class="eyebrow block text-muted-foreground">
                ID игры в Квиз, плиз!
              </label>
              <div class="flex flex-col gap-3 sm:flex-row">
                <input
                  id="game-external-id"
                  v-model="lookupId"
                  type="text"
                  autocomplete="off"
                  spellcheck="false"
                  placeholder="01a19b42-0cc8-709f-8197-62881b0dbcdc"
                  class="h-12 min-w-0 flex-1 border-0 border-b border-rule-strong bg-transparent font-mono text-base outline-none transition-colors placeholder:text-muted-foreground/40 focus:border-foreground"
                  @keydown.enter.prevent="lookUpGame"
                >
                <Button
                  type="button"
                  variant="outline"
                  size="lg"
                  class="h-12"
                  :disabled="isLookingUp || isLookupLoginOpen || !lookupId.trim()"
                  @click="lookUpGame"
                >
                  <Spinner v-if="isLookingUp" />
                  Подтянуть
                </Button>
              </div>
              <p v-if="lookupError" class="text-sm text-primary" role="alert">{{ lookupError }}</p>
              <div v-if="lookupGame" class="space-y-1 border-l-2 border-rule-strong pl-4" aria-live="polite">
                <p v-if="lookupGame.existingGameId" class="text-sm">
                  Эта игра уже привязана к другой игре.
                  <Link :href="`/games/${lookupGame.existingGameId}`" class="text-primary underline-offset-4 hover:underline">
                    Открыть
                  </Link>
                </p>
                <template v-else>
                  <p class="font-display text-lg leading-tight">{{ summarize(lookupGame.extra) }}</p>
                  <p class="text-sm text-muted-foreground">
                    Название и пакет подставлены. Проверьте и нажмите «Сохранить» — после этого ID изменить нельзя.
                  </p>
                </template>
              </div>
              <p v-else class="text-xs text-muted-foreground">
                Введите ID и нажмите «Подтянуть»: название и пакет подставятся из Квиз, плиз!
              </p>
            </template>
          </section>

          <RatingSettings :auto-package="autoPackage" v-model:category-id="draft.ratingCategoryId" v-model:include-scores="draft.ratingIncludeScores" v-model:package="draft.ratingPackage" />

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
        <QuizPleaseLoginDialog :open="isLookupLoginOpen" :submit="lookupLogin" @cancel="cancelLookupLogin" />

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
