<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, X } from 'lucide-vue-next'
import ConfirmDialog from '~/components/ConfirmDialog.vue'
import type { Team } from '~/lib/quiz'
import { useQuizStore } from '../stores/quiz'

const props = defineProps<{
  readOnly?: boolean
}>()

const quizStore = useQuizStore()
const newTeamName = ref('')
const error = ref('')
const teamToDelete = ref<Team | null>(null)
const isDeleting = ref(false)

const teams = computed(() => quizStore.currentGame?.teams ?? [])

const isDeleteDialogOpen = computed({
  get: () => teamToDelete.value !== null,
  set: (open) => {
    if (!open) {
      teamToDelete.value = null
    }
  }
})

function isTaken(name: string, exceptId?: string): boolean {
  const key = name.trim().toLocaleLowerCase('ru')
  return teams.value.some((team) => team.id !== exceptId && team.name.trim().toLocaleLowerCase('ru') === key)
}

async function addTeams(names: string[]): Promise<void> {
  error.value = ''

  for (const name of names) {
    if (isTaken(name)) {
      error.value = `Команда «${name}» уже есть.`
      continue
    }

    await quizStore.addTeam(name)
  }
}

async function submitNewTeam(): Promise<void> {
  const name = newTeamName.value.trim()

  if (!name) {
    return
  }

  newTeamName.value = ''
  await addTeams([name])
}

async function handleNewTeamPaste(event: ClipboardEvent): Promise<void> {
  const text = event.clipboardData?.getData('text') ?? ''

  if (!text.includes('\n')) {
    return
  }

  event.preventDefault()
  await addTeams(text.split(/\r?\n/).map((name) => name.trim()).filter(Boolean))
}

function renameTeam(team: Team, input: HTMLInputElement): void {
  const name = input.value.trim()

  if (name === team.name) {
    return
  }

  if (!name) {
    error.value = 'Название команды не может быть пустым.'
    input.value = team.name
    return
  }

  if (isTaken(name, team.id)) {
    error.value = `Команда «${name}» уже есть.`
    input.value = team.name
    return
  }

  error.value = ''
  quizStore.renameTeam(team.id, name)
}

function hasScores(team: Team): boolean {
  return Object.values(team.scores).some((score) => score !== 0)
}

function requestDelete(team: Team): void {
  if (hasScores(team)) {
    teamToDelete.value = team
    return
  }

  quizStore.deleteTeam(team.id)
}

async function confirmDelete(): Promise<void> {
  if (!teamToDelete.value) {
    return
  }

  isDeleting.value = true

  try {
    await quizStore.deleteTeam(teamToDelete.value.id)
  } finally {
    isDeleting.value = false
    teamToDelete.value = null
  }
}
</script>

<template>
  <section class="space-y-2">
    <div class="flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2">
      <h2 class="eyebrow text-muted-foreground">Команды</h2>
      <span class="eyebrow text-muted-foreground">{{ teams.length }}</span>
    </div>

    <ul class="divide-y divide-rule">
      <li
        v-for="(team, index) in teams"
        :key="team.id"
        class="flex items-center gap-3 transition-colors focus-within:bg-primary/[0.07]"
      >
        <span class="w-8 shrink-0 pl-1 font-display text-base tabular-nums text-muted-foreground">
          {{ index + 1 }}
        </span>
        <input
          :value="team.name"
          type="text"
          autocomplete="off"
          :aria-label="`Команда ${index + 1}`"
          :disabled="props.readOnly"
          class="h-14 min-w-0 flex-1 bg-transparent font-display text-xl leading-tight outline-none"
          @blur="renameTeam(team, $event.target as HTMLInputElement)"
          @keydown.enter="($event.target as HTMLInputElement).blur()"
        >
        <button
          v-if="!props.readOnly"
          type="button"
          class="inline-flex size-12 shrink-0 items-center justify-center text-muted-foreground transition-colors hover:text-primary disabled:pointer-events-none disabled:opacity-30"
          :disabled="teams.length <= 1"
          :aria-label="`Удалить команду ${team.name}`"
          @click="requestDelete(team)"
        >
          <X class="size-4" />
        </button>
      </li>
    </ul>

    <label
      v-if="!props.readOnly"
      class="flex items-center gap-3 border-t border-dashed border-rule-strong transition-colors focus-within:bg-primary/[0.07]"
    >
      <span class="flex w-8 shrink-0 justify-center text-muted-foreground">
        <Plus class="size-4" />
      </span>
      <input
        v-model="newTeamName"
        type="text"
        autocomplete="off"
        enterkeyhint="done"
        aria-label="Новая команда"
        placeholder="Добавить команду"
        class="h-14 min-w-0 flex-1 bg-transparent font-display text-xl leading-tight outline-none placeholder:text-muted-foreground/60"
        @keydown.enter.prevent="submitNewTeam"
        @paste="handleNewTeamPaste"
      >
    </label>

    <p v-if="error" class="text-sm text-primary">{{ error }}</p>
    <p v-if="!props.readOnly" class="text-xs text-muted-foreground">
      Enter — добавить. Можно вставить список, по команде на строку.
    </p>

    <ConfirmDialog
      v-model:open="isDeleteDialogOpen"
      :title="`Удалить команду «${teamToDelete?.name ?? ''}»?`"
      description="Её очки за все раунды пропадут."
      confirm-label="Да, удалить"
      :pending="isDeleting"
      @confirm="confirmDelete"
    />
  </section>
</template>
