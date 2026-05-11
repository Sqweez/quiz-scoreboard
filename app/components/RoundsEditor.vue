<script setup lang="ts">
import { ref } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import Button from './ui/button/Button.vue'
import Card from './ui/card/Card.vue'
import CardContent from './ui/card/CardContent.vue'
import CardHeader from './ui/card/CardHeader.vue'
import CardTitle from './ui/card/CardTitle.vue'
import Input from './ui/input/Input.vue'
import Label from './ui/label/Label.vue'
import { useQuizStore } from '../stores/quiz'

const quizStore = useQuizStore()
const newRoundTitle = ref('')
const error = ref('')

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

function updateOptionalNumber(roundId: string, field: 'maxScore' | 'questionsCount', value: string): void {
  quizStore.updateRoundSettings(roundId, {
    [field]: value === '' ? null : Math.max(0, Number(value))
  })
}
</script>

<template>
  <Card class="overflow-hidden">
    <CardHeader class="border-b bg-secondary/35">
      <div class="flex items-center justify-between gap-3">
        <CardTitle>Раунды</CardTitle>
        <span class="rounded-full border bg-background px-2.5 py-1 text-xs font-medium text-muted-foreground">
          {{ quizStore.currentGame?.rounds.length ?? 0 }}
        </span>
      </div>
    </CardHeader>
    <CardContent class="space-y-4">
      <div class="flex gap-2 rounded-md border bg-background p-2">
        <Input v-model="newRoundTitle" placeholder="Новый раунд" @keyup.enter="addRound" />
        <Button @click="addRound">
          <Plus class="size-4" />
          Добавить
        </Button>
      </div>

      <div class="space-y-3">
        <div
          v-for="round in quizStore.currentGame?.rounds"
          :key="round.id"
          class="grid gap-3 rounded-md border bg-background p-3 lg:grid-cols-[minmax(0,1fr)_120px_140px_auto]"
        >
          <div class="space-y-1.5">
            <Label>Название</Label>
            <Input
              :model-value="round.title"
              @update:model-value="updateTitle(round.id, String($event))"
            />
          </div>
          <div class="space-y-1.5">
            <Label>Макс. балл</Label>
            <Input
              :model-value="round.maxScore ?? ''"
              type="number"
              min="0"
              @update:model-value="updateOptionalNumber(round.id, 'maxScore', String($event))"
            />
          </div>
          <div class="space-y-1.5">
            <Label>Вопросы</Label>
            <Input
              :model-value="round.questionsCount ?? ''"
              type="number"
              min="0"
              @update:model-value="updateOptionalNumber(round.id, 'questionsCount', String($event))"
            />
          </div>
          <div class="flex items-end">
            <Button
              variant="ghost"
              size="icon"
              :disabled="(quizStore.currentGame?.rounds.length ?? 0) <= 1"
              aria-label="Удалить раунд"
              @click="quizStore.deleteRound(round.id)"
            >
              <Trash2 class="size-4" />
            </Button>
          </div>
        </div>
      </div>

      <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </CardContent>
  </Card>
</template>
