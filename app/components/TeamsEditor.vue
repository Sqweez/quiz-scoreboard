<script setup lang="ts">
import { ref } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import Button from './ui/button/Button.vue'
import Card from './ui/card/Card.vue'
import CardContent from './ui/card/CardContent.vue'
import CardHeader from './ui/card/CardHeader.vue'
import CardTitle from './ui/card/CardTitle.vue'
import Input from './ui/input/Input.vue'
import { useQuizStore } from '../stores/quiz'

const quizStore = useQuizStore()
const newTeamName = ref('')
const error = ref('')

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
</script>

<template>
  <Card class="overflow-hidden">
    <CardHeader class="border-b bg-secondary/35">
      <div class="flex items-center justify-between gap-3">
        <CardTitle>Команды</CardTitle>
        <span class="rounded-full border bg-background px-2.5 py-1 text-xs font-medium text-muted-foreground">
          {{ quizStore.currentGame?.teams.length ?? 0 }}
        </span>
      </div>
    </CardHeader>
    <CardContent class="space-y-4">
      <div class="flex gap-2 rounded-md border bg-background p-2">
        <Input v-model="newTeamName" placeholder="Новая команда" @keyup.enter="addTeam" />
        <Button @click="addTeam">
          <Plus class="size-4" />
          Добавить
        </Button>
      </div>

      <div class="space-y-2">
        <div
          v-for="(team, index) in quizStore.currentGame?.teams"
          :key="team.id"
          class="flex items-center gap-2 rounded-md border bg-background p-2"
        >
          <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted text-sm font-semibold text-muted-foreground">
            {{ index + 1 }}
          </span>
          <Input
            :model-value="team.name"
            aria-label="Название команды"
            @update:model-value="renameTeam(team.id, String($event))"
          />
          <Button
            variant="ghost"
            size="icon"
            :disabled="(quizStore.currentGame?.teams.length ?? 0) <= 1"
            aria-label="Удалить команду"
            @click="quizStore.deleteTeam(team.id)"
          >
            <Trash2 class="size-4" />
          </Button>
        </div>
      </div>

      <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </CardContent>
  </Card>
</template>
