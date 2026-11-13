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
const newTeamName = ref('')
const error = ref('')
const confirmingId = ref<string | null>(null)

function addTeam(): void {
  if (!newTeamName.value.trim()) {
    error.value = 'Введите название команды.'
    return
  }

  quizStore.addTeam(newTeamName.value)
  newTeamName.value = ''
  error.value = ''
}

function renameTeam(teamId: string, value: string): void {
  if (!value.trim()) {
    error.value = 'Название команды не может быть пустым.'
    return
  }

  quizStore.renameTeam(teamId, value)
  error.value = ''
}

function requestDelete(teamId: string): void {
  confirmingId.value = teamId
}

function cancelDelete(): void {
  confirmingId.value = null
}

function confirmDelete(teamId: string): void {
  quizStore.deleteTeam(teamId)
  confirmingId.value = null
}
</script>

<template>
  <section class="space-y-4">
    <header class="flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2">
      <h2 class="font-display text-xl font-medium">Команды</h2>
      <span class="eyebrow text-muted-foreground">
        {{ quizStore.currentGame?.teams.length ?? 0 }}
      </span>
    </header>

    <div v-if="!props.readOnly" class="flex gap-2">
      <Input
        v-model="newTeamName"
        placeholder="Название новой команды"
        @keyup.enter="addTeam"
      />
      <Button type="button" variant="outline" @click="addTeam">
        <Plus class="size-4" />
        Добавить
      </Button>
    </div>

    <ul class="divide-y divide-rule">
      <li
        v-for="(team, index) in quizStore.currentGame?.teams"
        :key="team.id"
        class="flex items-center gap-3 py-3"
      >
        <span class="w-6 font-display text-base tabular-nums text-muted-foreground">
          {{ index + 1 }}
        </span>

        <input
          :value="team.name"
          aria-label="Название команды"
          :disabled="props.readOnly"
          class="editable flex-1 font-display text-lg leading-tight"
          @blur="renameTeam(team.id, ($event.target as HTMLInputElement).value)"
          @keydown.enter="($event.target as HTMLInputElement).blur()"
        >

        <template v-if="!props.readOnly">
          <template v-if="confirmingId !== team.id">
            <button
              type="button"
              class="text-sm text-muted-foreground transition-colors hover:text-primary disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="(quizStore.currentGame?.teams.length ?? 0) <= 1"
              aria-label="Удалить команду"
              @click="requestDelete(team.id)"
            >
              Удалить
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              class="text-sm font-medium text-primary underline decoration-primary/40 underline-offset-4 hover:decoration-primary"
              @click="confirmDelete(team.id)"
            >
              Подтвердить
            </button>
            <button
              type="button"
              class="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
              @click="cancelDelete"
            >
              <X class="size-3.5" />
            </button>
          </template>
        </template>
      </li>
    </ul>

    <p v-if="error" class="text-sm text-primary">{{ error }}</p>
  </section>
</template>
