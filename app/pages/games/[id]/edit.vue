<script setup lang="ts">
import { computed, watch } from 'vue'
import { ArrowLeft } from 'lucide-vue-next'
import TeamsEditor from '~/components/TeamsEditor.vue'
import RoundsEditor from '~/components/RoundsEditor.vue'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const route = useRoute()

definePageMeta({
  middleware: 'auth'
})

const isFinished = computed(() => quizStore.currentGame?.status === 'finished')

watch(
  () => route.params.id,
  async (gameId) => {
    await quizStore.loadGame(String(gameId ?? ''))
  },
  { immediate: true }
)
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col gap-10 px-4 py-8 lg:px-8 lg:py-12">
    <template v-if="quizStore.currentGame">
      <NuxtLink
        :to="`/games/${quizStore.currentGame.id}`"
        class="inline-flex items-center gap-1.5 self-start text-sm text-muted-foreground transition-colors hover:text-foreground"
      >
        <ArrowLeft class="size-3.5" />
        Назад к таблице
      </NuxtLink>

      <header class="border-b border-rule-strong pb-6">
        <p class="eyebrow text-muted-foreground">
          Настройка игры
        </p>
        <h1 class="mt-3 font-display text-3xl font-medium leading-tight lg:text-4xl">
          {{ quizStore.currentGame.title }}
        </h1>
        <p v-if="isFinished" class="mt-3 text-sm text-muted-foreground">
          Игра завершена. Редактирование закрыто.
        </p>
      </header>

      <p
        v-if="quizStore.error"
        class="border-t border-primary bg-secondary px-3 py-2 text-sm text-foreground"
      >
        {{ quizStore.error }}
      </p>

      <div class="grid gap-12 lg:grid-cols-2">
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
      <NuxtLink
        to="/"
        class="text-sm font-medium text-foreground underline decoration-rule-strong underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
      >
        Вернуться к списку
      </NuxtLink>
    </section>
  </main>
</template>
