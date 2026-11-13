<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { ArrowLeft } from 'lucide-vue-next'
import TeamsEditor from '~/components/TeamsEditor.vue'
import RoundsEditor from '~/components/RoundsEditor.vue'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const props = defineProps<{ gameId: string }>()

const isFinished = computed(() => quizStore.currentGame?.status === 'finished')
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
const externalIdDraft = ref('')
const externalIdError = ref('')

watch(() => quizStore.currentGame?.externalId, (externalId) => {
  externalIdDraft.value = externalId ?? ''
}, { immediate: true })

async function saveExternalId(): Promise<void> {
  const value = externalIdDraft.value.trim()

  if (value === (quizStore.currentGame?.externalId ?? '')) {
    externalIdError.value = ''
    return
  }

  if (value && !UUID_PATTERN.test(value)) {
    externalIdError.value = 'ID игры в Квиз, плиз! должен быть UUID.'
    return
  }

  externalIdError.value = ''
  await quizStore.updateGameExternalId(value || null)
  // A rejected value (e.g. already used) falls back to the saved one; the store shows the reason.
  externalIdDraft.value = quizStore.currentGame?.externalId ?? ''
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
          Игра завершена: команды и раунды закрыты, ID игры в Квиз, плиз! можно изменить.
        </p>
      </header>

      <p
        v-if="quizStore.error"
        class="border-t border-primary bg-secondary px-3 py-2 text-sm text-foreground"
      >
        {{ quizStore.error }}
      </p>

      <div class="flex flex-col gap-12">
        <section class="space-y-3">
          <label for="game-external-id" class="eyebrow block text-muted-foreground">
            ID игры в Квиз, плиз!
          </label>
          <input
            id="game-external-id"
            v-model="externalIdDraft"
            type="text"
            autocomplete="off"
            spellcheck="false"
            enterkeyhint="done"
            placeholder="Не указан"
            class="h-12 w-full border-0 border-b border-rule-strong bg-transparent font-mono text-base outline-none transition-colors placeholder:text-muted-foreground/40 focus:border-foreground"
            :class="{ 'border-primary focus:border-primary': externalIdError }"
            :aria-invalid="Boolean(externalIdError)"
            @blur="saveExternalId"
            @keydown.enter="($event.target as HTMLInputElement).blur()"
          >
          <p v-if="externalIdError" class="text-sm text-primary">{{ externalIdError }}</p>
          <p v-else class="text-xs text-muted-foreground">
            Сохраняется при выходе из поля. Оставьте пустым, чтобы отвязать игру.
          </p>
        </section>

        <TeamsEditor :read-only="isFinished" />
        <RoundsEditor :read-only="isFinished" />
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
