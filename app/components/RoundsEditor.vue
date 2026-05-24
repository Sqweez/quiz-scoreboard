<script setup lang="ts">
import { ref } from 'vue'
import { Plus, X } from 'lucide-vue-next'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'
import { useQuizStore } from '../stores/quiz'

const props = defineProps<{
  readOnly?: boolean
}>()

const quizStore = useQuizStore()
const newRoundTitle = ref('')
const error = ref('')
const confirmingId = ref<string | null>(null)

function addRound(): void {
  if (!newRoundTitle.value.trim()) {
    error.value = 'Введите название раунда.'
    return
  }

  quizStore.addRound(newRoundTitle.value)
  newRoundTitle.value = ''
  error.value = ''
}

function updateTitle(roundId: string, value: string): void {
  if (!value.trim()) {
    error.value = 'Название раунда не может быть пустым.'
    return
  }

  quizStore.renameRound(roundId, value)
  error.value = ''
}

function updateOptionalNumber(
  roundId: string,
  field: 'maxScore' | 'questionsCount',
  value: string
): void {
  quizStore.updateRoundSettings(roundId, {
    [field]: value === '' ? null : Math.max(0, Number(value))
  })
}

function requestDelete(roundId: string): void {
  confirmingId.value = roundId
}

function cancelDelete(): void {
  confirmingId.value = null
}

function confirmDelete(roundId: string): void {
  quizStore.deleteRound(roundId)
  confirmingId.value = null
}
</script>

<template>
  <section class="space-y-4">
    <header class="flex items-baseline justify-between gap-3 border-b border-[var(--rule-strong)] pb-2">
      <h2 class="font-display text-xl font-medium">Раунды</h2>
      <span class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
        {{ quizStore.currentGame?.rounds.length ?? 0 }}
      </span>
    </header>

    <div v-if="!props.readOnly" class="flex gap-2">
      <Input
        v-model="newRoundTitle"
        placeholder="Название нового раунда"
        @keyup.enter="addRound"
      />
      <Button type="button" variant="outline" @click="addRound">
        <Plus class="size-4" />
        Добавить
      </Button>
    </div>

    <ul class="divide-y divide-[var(--rule)]">
      <li
        v-for="(round, index) in quizStore.currentGame?.rounds"
        :key="round.id"
        class="grid gap-3 py-4 md:grid-cols-[2.5rem_minmax(0,1fr)_5rem_5rem_auto] md:items-center"
      >
        <span class="font-display text-base tabular-nums text-muted-foreground">
          {{ index + 1 }}
        </span>

        <input
          :value="round.title"
          aria-label="Название раунда"
          :disabled="props.readOnly"
          class="editable font-display text-lg leading-tight"
          @blur="updateTitle(round.id, ($event.target as HTMLInputElement).value)"
          @keydown.enter="($event.target as HTMLInputElement).blur()"
        >

        <label class="flex items-baseline gap-2 text-sm text-muted-foreground">
          <span class="text-[11px] uppercase tracking-[0.14em]">Макс</span>
          <input
            :value="round.maxScore ?? ''"
            type="number"
            min="0"
            :disabled="props.readOnly"
            class="editable w-12 text-right font-display tabular-nums"
            @blur="updateOptionalNumber(round.id, 'maxScore', ($event.target as HTMLInputElement).value)"
          >
        </label>

        <label class="flex items-baseline gap-2 text-sm text-muted-foreground">
          <span class="text-[11px] uppercase tracking-[0.14em]">Вопросы</span>
          <input
            :value="round.questionsCount ?? ''"
            type="number"
            min="0"
            :disabled="props.readOnly"
            class="editable w-12 text-right font-display tabular-nums"
            @blur="updateOptionalNumber(round.id, 'questionsCount', ($event.target as HTMLInputElement).value)"
          >
        </label>

        <div v-if="!props.readOnly" class="flex items-center justify-end gap-3 text-sm">
          <template v-if="confirmingId !== round.id">
            <button
              type="button"
              class="text-muted-foreground transition-colors hover:text-[var(--primary)] disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="(quizStore.currentGame?.rounds.length ?? 0) <= 1"
              aria-label="Удалить раунд"
              @click="requestDelete(round.id)"
            >
              Удалить
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              class="font-medium text-[var(--primary)] underline decoration-[var(--primary)]/40 underline-offset-4 hover:decoration-[var(--primary)]"
              @click="confirmDelete(round.id)"
            >
              Подтвердить
            </button>
            <button
              type="button"
              class="inline-flex items-center text-muted-foreground hover:text-foreground"
              @click="cancelDelete"
            >
              <X class="size-3.5" />
            </button>
          </template>
        </div>
      </li>
    </ul>

    <p v-if="error" class="text-sm text-[var(--primary)]">{{ error }}</p>
  </section>
</template>
