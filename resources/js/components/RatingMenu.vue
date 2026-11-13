<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuTrigger
} from 'reka-ui'
import { Check, ChevronDown, Download, SquarePlus, Trophy, Upload } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Spinner } from '~/components/ui/spinner'
import QuizPleaseLoginDialog from '~/components/QuizPleaseLoginDialog.vue'
import { useQuizPleaseLogin } from '~/composables/useQuizPleaseLogin'
import { useQuizStore } from '~/stores/quiz'
import { HttpError } from '~/utils/http'

// Rating actions: the full upload, plus creating the rating game and downloading the table
// separately, so the results can still be uploaded by hand on rating.quizplease.ru when the queue fails.
const emit = defineEmits<{
  done: [message: string]
}>()

const quizStore = useQuizStore()
const { isLoginOpen, requireLogin, login, cancelLogin } = useQuizPleaseLogin()
const isBusy = ref(false)

const game = computed(() => quizStore.currentGame)

const itemClass = 'flex cursor-pointer items-center gap-2 rounded-sm px-3 py-2.5 text-sm outline-none transition-colors data-[highlighted]:bg-secondary data-[disabled]:pointer-events-none data-[disabled]:opacity-50'

// The rating sign-in goes through the admin account: without its token the login dialog opens
// and the action runs again after it; other errors are already shown by the store.
async function run(action: () => Promise<void>, doneMessage: string): Promise<void> {
  if (isBusy.value) {
    return
  }

  isBusy.value = true

  try {
    await action()
    emit('done', doneMessage)
  } catch (requestError) {
    requireLogin(requestError, () => run(action, doneMessage))
  } finally {
    isBusy.value = false
  }
}

const uploadRating = () => run(quizStore.uploadRating, 'Результаты отправлены в рейтинг, он обработает их в очереди')
const createRatingGame = () => run(quizStore.createRatingGame, 'Игра создана в рейтинге')

async function downloadTable(): Promise<void> {
  if (!game.value || isBusy.value) {
    return
  }

  isBusy.value = true
  quizStore.error = ''

  try {
    const response = await fetch(`/api/games/${game.value.id}/rating/results`, { credentials: 'same-origin' })

    if (!response.ok) {
      const data = await response.json()
      throw new HttpError(data.message, response.status, data.reason)
    }

    const link = document.createElement('a')
    link.href = URL.createObjectURL(await response.blob())
    link.download = `${game.value.externalId}.xlsx`
    link.click()
    URL.revokeObjectURL(link.href)
    emit('done', 'Таблица скачана')
  } catch (requestError) {
    quizStore.error = requestError instanceof Error ? requestError.message : 'Не удалось скачать таблицу.'
  } finally {
    isBusy.value = false
  }
}
</script>

<template>
  <template v-if="game">
    <DropdownMenuRoot :modal="false">
      <DropdownMenuTrigger as-child>
        <Button type="button" variant="outline" :disabled="isBusy">
          <Spinner v-if="isBusy" />
          <Trophy v-else class="size-4" />
          Рейтинг
          <ChevronDown class="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuPortal>
        <DropdownMenuContent
          align="end"
          :side-offset="6"
          class="z-50 min-w-60 rounded-md border bg-background p-1 shadow-lg"
        >
          <DropdownMenuItem :class="itemClass" :disabled="Boolean(game.ratingUploadedAt)" @select="uploadRating">
            <Check v-if="game.ratingUploadedAt" class="size-4" />
            <Upload v-else class="size-4" />
            {{ game.ratingUploadedAt ? 'Результаты загружены' : 'Загрузить результаты' }}
          </DropdownMenuItem>
          <DropdownMenuItem :class="itemClass" :disabled="Boolean(game.ratingCreatedAt)" @select="createRatingGame">
            <Check v-if="game.ratingCreatedAt" class="size-4" />
            <SquarePlus v-else class="size-4" />
            {{ game.ratingCreatedAt ? 'Игра в рейтинге создана' : 'Только создать игру' }}
          </DropdownMenuItem>
          <DropdownMenuItem :class="itemClass" @select="downloadTable">
            <Download class="size-4" />
            Скачать таблицу .xlsx
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenuPortal>
    </DropdownMenuRoot>

    <QuizPleaseLoginDialog :open="isLoginOpen" :submit="login" @cancel="cancelLogin" />
  </template>
</template>
