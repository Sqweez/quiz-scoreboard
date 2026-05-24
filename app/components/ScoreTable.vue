<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { useQuizStore } from '../stores/quiz'
import type { Round, Team } from '#shared/quiz'
import { usePinnedTeamOrder } from '../composables/usePinnedTeamOrder'

const props = defineProps<{
  readOnly?: boolean
}>()

const quizStore = useQuizStore()
const { visibleTeams, freezeCurrentOrder, releaseCurrentOrder } = usePinnedTeamOrder(
  () => quizStore.sortedTeams
)

type ActiveContext = {
  teamId: string
  teamName: string
  roundIndex: number
  roundTitle: string
}

const activeContext = ref<ActiveContext | null>(null)
let scoreBlurTimer: ReturnType<typeof setTimeout> | null = null
let contextClearTimer: ReturnType<typeof setTimeout> | null = null

function updateScore(team: Team, round: Round, event: Event): void {
  const target = event.target as HTMLInputElement
  quizStore.updateTeamScore(team.id, round.id, target.value)
}

function scoreValue(team: Team, roundId: string): number {
  return team.scores[roundId] ?? 0
}

function handleScoreFocus(
  team: Team,
  round: Round,
  roundIndex: number,
  event: FocusEvent
): void {
  if (scoreBlurTimer) {
    clearTimeout(scoreBlurTimer)
    scoreBlurTimer = null
  }

  if (contextClearTimer) {
    clearTimeout(contextClearTimer)
    contextClearTimer = null
  }

  freezeCurrentOrder()
  activeContext.value = {
    teamId: team.id,
    teamName: team.name,
    roundIndex,
    roundTitle: round.title
  }

  if (event.target instanceof HTMLInputElement) {
    event.target.select()
  }
}

function handleScoreBlur(): void {
  releaseCurrentOrder()

  if (scoreBlurTimer) {
    clearTimeout(scoreBlurTimer)
  }

  scoreBlurTimer = setTimeout(() => {
    scoreBlurTimer = null
    void quizStore.flushPendingScoreChanges(true)
  }, 120)

  // Clear with delay so a quick Tab between cells doesn't make the banner flicker.
  if (contextClearTimer) {
    clearTimeout(contextClearTimer)
  }

  contextClearTimer = setTimeout(() => {
    contextClearTimer = null
    activeContext.value = null
  }, 200)
}

function handleScoreKeydown(event: KeyboardEvent): void {
  if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
    event.preventDefault()
  }

  if (event.key === 'Enter' && event.currentTarget instanceof HTMLInputElement) {
    event.currentTarget.blur()
  }
}

function preventWheelStep(event: WheelEvent): void {
  if (event.currentTarget instanceof HTMLInputElement) {
    event.currentTarget.blur()
  }
}

onBeforeUnmount(() => {
  if (scoreBlurTimer) {
    clearTimeout(scoreBlurTimer)
    scoreBlurTimer = null
  }

  if (contextClearTimer) {
    clearTimeout(contextClearTimer)
    contextClearTimer = null
  }

  void quizStore.flushPendingScoreChanges(true)
})
</script>

<template>
  <div class="relative">
    <!--
      Sticky heads-up of which team is being edited. Sits above the table and
      stays at the top of the viewport while the user scrolls, so the operator
      can confirm the row without looking at the table.
    -->
    <div
      aria-live="polite"
      class="sticky top-0 z-20 -mt-px flex items-baseline gap-3 border-b border-[var(--rule-strong)] bg-background/95 py-3 backdrop-blur transition-opacity duration-150"
      :class="activeContext ? 'opacity-100' : 'pointer-events-none opacity-0'"
    >
      <span class="text-[11px] uppercase tracking-[0.18em] text-[var(--primary)]">
        Редактируем
      </span>
      <span class="font-display text-2xl font-semibold leading-none text-foreground">
        {{ activeContext?.teamName ?? ' ' }}
      </span>
      <span v-if="activeContext" class="text-sm text-muted-foreground">
        раунд {{ activeContext.roundIndex + 1 }} · {{ activeContext.roundTitle }}
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-foreground">
        <colgroup>
          <col class="w-10">
          <col class="w-auto">
          <col class="w-20">
          <col
            v-for="round in quizStore.currentGame?.rounds"
            :key="round.id"
            class="w-16"
          >
        </colgroup>

        <thead>
          <tr class="border-b border-[var(--rule-strong)] text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
            <th scope="col" class="py-3 pl-3 pr-2 text-left font-medium sm:pl-4">
              №
            </th>
            <th scope="col" class="py-3 pr-3 text-left font-medium">
              Команда
            </th>
            <th scope="col" class="border-l border-[var(--rule)] py-3 pl-3 pr-2 text-right font-medium">
              Σ
            </th>
            <th
              v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
              :key="round.id"
              scope="col"
              class="py-3 pl-2 pr-2 text-right font-medium last:pr-3 sm:last:pr-4"
              :title="round.title"
            >
              <div class="font-display text-base font-medium tracking-normal text-foreground normal-case">
                {{ roundIndex + 1 }}
              </div>
              <div
                v-if="round.maxScore !== null && round.maxScore !== undefined"
                class="mt-0.5 text-[10px] font-normal text-muted-foreground"
              >
                макс. {{ round.maxScore }}
              </div>
            </th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="(team, index) in visibleTeams"
            :key="team.id"
            class="border-b border-[var(--rule)] transition-colors hover:bg-secondary/60 focus-within:!bg-[var(--primary)]/[0.07]"
            :class="{
              'bg-secondary/35': index % 2 === 1
            }"
          >
            <td class="py-3.5 pl-3 pr-2 align-middle sm:pl-4">
              <span class="flex items-center gap-1.5 font-display text-lg tabular-nums">
                <span
                  v-if="index === 0"
                  aria-hidden="true"
                  class="inline-block size-1.5 rounded-full bg-[var(--leader)]"
                />
                <span
                  :class="index === 0
                    ? 'font-semibold text-foreground'
                    : 'font-medium text-foreground/70'"
                >
                  {{ index + 1 }}
                </span>
              </span>
            </td>

            <td class="py-3.5 pr-3 align-middle">
              <span
                class="font-display text-lg leading-tight text-foreground"
                :class="index === 0 ? 'font-semibold' : 'font-medium'"
              >
                {{ team.name }}
              </span>
            </td>

            <td class="border-l border-[var(--rule)] py-3.5 pl-3 pr-2 text-right align-middle">
              <span
                class="font-display text-3xl tabular-nums text-foreground"
                :class="index === 0 ? 'font-semibold' : 'font-medium'"
              >
                {{ quizStore.getTotalScore(team) }}
              </span>
            </td>

            <td
              v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
              :key="round.id"
              class="relative py-3.5 pl-2 pr-2 text-right align-middle last:pr-3 sm:last:pr-4"
            >
              <input
                class="peer ml-auto block h-10 w-full max-w-16 rounded-none border-0 border-b border-[var(--rule)] bg-transparent px-1 text-right font-display text-xl tabular-nums text-foreground/85 outline-none transition-colors placeholder:text-muted-foreground/40 hover:border-[var(--rule-strong)] hover:text-foreground focus:border-[var(--primary)] focus:text-foreground disabled:cursor-not-allowed disabled:opacity-60"
                type="number"
                inputmode="numeric"
                min="0"
                :max="round.maxScore ?? undefined"
                :value="scoreValue(team, round.id)"
                :disabled="props.readOnly"
                :aria-label="`Баллы команды ${team.name} в раунде ${round.title}`"
                placeholder="0"
                @focus="handleScoreFocus(team, round, roundIndex, $event)"
                @input="updateScore(team, round, $event)"
                @blur="handleScoreBlur"
                @keydown="handleScoreKeydown"
                @wheel="preventWheelStep"
              >

              <!--
                Per-cell tooltip near the focused input. Doubles up with the
                sticky banner so the operator can confirm either by glancing up
                or by looking at peripheral vision near the cursor.
              -->
              <span
                aria-hidden="true"
                class="pointer-events-none absolute -top-1 right-1 -translate-y-full whitespace-nowrap rounded-sm bg-foreground px-2 py-1 text-xs font-medium text-background opacity-0 shadow-md transition-opacity duration-150 peer-focus:opacity-100"
              >
                {{ team.name }}
              </span>
            </td>
          </tr>

          <tr v-if="visibleTeams.length === 0">
            <td
              :colspan="3 + (quizStore.currentGame?.rounds.length ?? 0)"
              class="py-12 text-center text-sm text-muted-foreground"
            >
              Добавьте команды и раунды, чтобы начать.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
