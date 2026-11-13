<script setup lang="ts">
import { computed, ref } from 'vue'
import { Minus, Plus } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import ConfirmDialog from '~/components/ConfirmDialog.vue'
import { MAX_ROUNDS } from '~/composables/useGameSetupForm'
import { useQuizStore } from '../stores/quiz'

const props = defineProps<{
  readOnly?: boolean
}>()

const quizStore = useQuizStore()
const isConfirmingRemove = ref(false)
const isRemoving = ref(false)

const rounds = computed(() => quizStore.currentGame?.rounds ?? [])
const lastRound = computed(() => rounds.value[rounds.value.length - 1])

function lastRoundHasScores(): boolean {
  const roundId = lastRound.value.id
  return (quizStore.currentGame?.teams ?? []).some((team) => (team.scores[roundId] ?? 0) !== 0)
}

function addRound(): void {
  quizStore.addRound(`Раунд ${rounds.value.length + 1}`)
}

function requestRemove(): void {
  if (lastRoundHasScores()) {
    isConfirmingRemove.value = true
    return
  }

  quizStore.deleteRound(lastRound.value.id)
}

async function confirmRemove(): Promise<void> {
  isRemoving.value = true

  try {
    await quizStore.deleteRound(lastRound.value.id)
  } finally {
    isRemoving.value = false
    isConfirmingRemove.value = false
  }
}
</script>

<template>
  <section class="space-y-4">
    <div class="border-b border-rule-strong pb-2">
      <h2 id="rounds-label" class="eyebrow text-muted-foreground">Раунды</h2>
    </div>

    <div class="flex items-center gap-4" role="group" aria-labelledby="rounds-label">
      <Button
        v-if="!props.readOnly"
        type="button"
        variant="outline"
        class="size-12"
        :disabled="rounds.length <= 1 || quizStore.isLoading"
        aria-label="Убрать последний раунд"
        @click="requestRemove"
      >
        <Minus class="size-5" />
      </Button>
      <output class="w-16 text-center font-display text-5xl font-medium tabular-nums" aria-live="polite">
        {{ rounds.length }}
      </output>
      <Button
        v-if="!props.readOnly"
        type="button"
        variant="outline"
        class="size-12"
        :disabled="rounds.length >= MAX_ROUNDS || quizStore.isLoading"
        aria-label="Добавить раунд"
        @click="addRound"
      >
        <Plus class="size-5" />
      </Button>
    </div>

    <ConfirmDialog
      v-model:open="isConfirmingRemove"
      :title="`Удалить «${lastRound?.title ?? ''}»?`"
      description="В этом раунде уже есть очки, они пропадут."
      confirm-label="Да, удалить"
      :pending="isRemoving"
      @confirm="confirmRemove"
    />
  </section>
</template>
