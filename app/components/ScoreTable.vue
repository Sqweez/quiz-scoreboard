<script setup lang="ts">
import { Copy, Trophy } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '~/components/ui/table'
import { useQuizStore } from '../stores/quiz'
import type { Round, Team } from '#shared/quiz'
import { usePinnedTeamOrder } from '../composables/usePinnedTeamOrder'
import { useResultsClipboard } from '../composables/useResultsClipboard'

const quizStore = useQuizStore()
const { visibleTeams, freezeCurrentOrder, releaseCurrentOrder } = usePinnedTeamOrder(() => quizStore.sortedTeams)
const { copyStatus, copyStatusText, copyResults } = useResultsClipboard()

function updateScore(team: Team, round: Round, event: Event): void {
  const target = event.target as HTMLInputElement
  quizStore.updateTeamScore(team.id, round.id, target.value)
}

function scoreValue(team: Team, roundId: string): number {
  return team.scores[roundId] ?? 0
}

function preventNativeNumberStep(event: KeyboardEvent): void {
  if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
    event.preventDefault()
  }
}

function preventWheelStep(event: WheelEvent): void {
  event.currentTarget instanceof HTMLInputElement && event.currentTarget.blur()
}

function handleScoreKeydown(event: KeyboardEvent): void {
  preventNativeNumberStep(event)

  if (event.key === 'Enter' && event.currentTarget instanceof HTMLInputElement) {
    event.currentTarget.blur()
  }
}

function copyCurrentResults(): void {
  if (!quizStore.currentGame) {
    return
  }

  copyResults(quizStore.sortedTeams, quizStore.currentGame.rounds)
}
</script>

<template>
  <div class="overflow-hidden rounded-lg border bg-card shadow-sm shadow-slate-200/70">
    <div class="flex flex-col gap-3 border-b bg-secondary/35 p-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="text-lg font-semibold">Таблица результатов</h2>
        <p class="text-sm text-muted-foreground">
          Баллы, итоги и места пересчитываются сразу после ввода.
        </p>
      </div>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <p
          v-if="copyStatusText"
          class="text-sm"
          :class="copyStatus === 'success' ? 'text-emerald-700' : 'text-destructive'"
        >
          {{ copyStatusText }}
        </p>
        <Button variant="outline" size="sm" @click="copyCurrentResults">
          <Copy class="size-4" />
          Скопировать результаты
        </Button>
        <div class="rounded-full border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
          Тай-брейк: от последнего раунда к первому
        </div>
      </div>
    </div>

    <Table>
      <TableHeader class="sticky top-0 z-10 bg-card">
        <TableRow class="hover:bg-transparent">
          <TableHead class="sticky left-0 z-30 w-20 min-w-20 bg-card shadow-[1px_0_0_var(--border)]">
            Место
          </TableHead>
          <TableHead class="sticky left-20 z-30 w-56 min-w-56 bg-card shadow-[1px_0_0_var(--border)]">
            Команда
          </TableHead>
          <TableHead class="sticky left-76 z-30 w-28 min-w-28 bg-secondary text-right shadow-[1px_0_0_var(--border)]">
            Итого
          </TableHead>
          <TableHead
            v-for="round in quizStore.currentGame?.rounds"
            :key="round.id"
            class="min-w-36 border-l text-right"
          >
            <div>{{ round.title }}</div>
            <div v-if="round.maxScore !== null && round.maxScore !== undefined" class="text-xs font-normal">
              макс. {{ round.maxScore }}
            </div>
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow
          v-for="(team, index) in visibleTeams"
          :key="team.id"
          :class="index === 0 ? 'bg-amber-50/70 hover:bg-amber-50' : ''"
        >
          <TableCell
            class="sticky left-0 z-20 w-20 min-w-20 font-semibold shadow-[1px_0_0_var(--border)]"
            :class="index === 0 ? 'bg-amber-50' : 'bg-card'"
          >
            <span
              class="inline-flex size-8 items-center justify-center rounded-full text-sm"
              :class="index === 0 ? 'bg-amber-500 text-white' : 'bg-muted text-muted-foreground'"
            >
              {{ index + 1 }}
            </span>
          </TableCell>
          <TableCell
            class="sticky left-20 z-20 w-56 min-w-56 font-medium shadow-[1px_0_0_var(--border)]"
            :class="index === 0 ? 'bg-amber-50' : 'bg-card'"
          >
            <div class="flex items-center gap-2">
              <Trophy v-if="index === 0" class="size-4 shrink-0 text-amber-600" />
              <span class="truncate">{{ team.name }}</span>
            </div>
          </TableCell>
          <TableCell class="sticky left-76 z-20 w-28 min-w-28 bg-secondary text-right text-base font-bold shadow-[1px_0_0_var(--border)]">
            <span class="rounded-md bg-background px-2.5 py-1 shadow-sm">
              {{ quizStore.getTotalScore(team) }}
            </span>
          </TableCell>
          <TableCell
            v-for="round in quizStore.currentGame?.rounds"
            :key="round.id"
            class="border-l text-right"
          >
            <input
              class="h-9 w-24 rounded-md border border-input bg-background px-2 text-right text-sm font-medium shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              type="number"
              min="0"
              :max="round.maxScore ?? undefined"
              :value="scoreValue(team, round.id)"
              @focus="freezeCurrentOrder"
              @input="updateScore(team, round, $event)"
              @blur="releaseCurrentOrder"
              @keydown="handleScoreKeydown"
              @wheel="preventWheelStep"
            >
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>
</template>
