<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { useQuizStore } from '../stores/quiz'
import type { Round, Team } from '~/lib/quiz'
import { usePinnedTeamOrder } from '../composables/usePinnedTeamOrder'

const props = defineProps<{
  readOnly?: boolean
}>()

const quizStore = useQuizStore()
const { visibleTeams, freezeCurrentOrder, releaseCurrentOrder } = usePinnedTeamOrder(
  () => quizStore.sortedTeams
)

const mobileMode = ref<'round' | 'team' | 'table'>('round')
const activeRoundIndex = ref(0)

const activeRound = computed(() => {
  const rounds = quizStore.currentGame?.rounds
  if (!rounds || rounds.length === 0) {
    return null
  }
  if (activeRoundIndex.value >= rounds.length) {
    activeRoundIndex.value = Math.max(0, rounds.length - 1)
  }
  return rounds[activeRoundIndex.value] ?? null
})

type ActiveContext = {
  teamId: string
  teamName: string
  roundIndex: number
  roundTitle: string
}

const activeContext = ref<ActiveContext | null>(null)
const scoreDrafts = ref<Record<string, string>>({})
let scoreBlurTimer: ReturnType<typeof setTimeout> | null = null
let contextClearTimer: ReturnType<typeof setTimeout> | null = null

function updateScore(team: Team, round: Round, event: Event): void {
  const target = event.target as HTMLInputElement
  scoreDrafts.value[`${team.id}:${round.id}`] = target.value
  quizStore.updateTeamScore(team.id, round.id, target.value)
}

function scoreValue(team: Team, roundId: string): string | number {
  return scoreDrafts.value[`${team.id}:${roundId}`] ?? team.scores[roundId] ?? 0
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

function handleScoreBlur(team: Team, round: Round): void {
  delete scoreDrafts.value[`${team.id}:${round.id}`]
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
  <div class="relative w-full min-w-0 max-w-full">
    <!-- Desktop View (>= 1024px) -->
    <div class="hidden lg:block">
      <!--
        Sticky heads-up of which team is being edited. Sits above the table and
        stays at the top of the viewport while the user scrolls, so the operator
        can confirm the row without looking at the table.
      -->
      <div
        aria-live="polite"
        class="sticky top-0 z-20 -mt-px flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-rule-strong bg-background/95 py-3 backdrop-blur transition-opacity duration-150"
        :class="activeContext ? 'opacity-100' : 'pointer-events-none opacity-0'"
      >
        <span class="eyebrow text-primary">
          Редактируем
        </span>
        <span class="min-w-0 break-words font-display text-2xl font-semibold leading-tight text-foreground">
          {{ activeContext?.teamName ?? ' ' }}
        </span>
        <span v-if="activeContext" class="text-sm text-muted-foreground">
          раунд {{ activeContext.roundIndex + 1 }} · {{ activeContext.roundTitle }}
        </span>
      </div>

      <div tabindex="0" role="region" aria-label="Таблица результатов" class="max-w-full overflow-x-auto overscroll-x-contain focus-visible:outline-2 focus-visible:outline-primary">
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
            <tr class="border-b border-rule-strong eyebrow-tight text-muted-foreground">
              <th scope="col" class="py-3 pl-3 pr-2 text-left font-medium sm:pl-4">
                №
              </th>
              <th scope="col" class="py-3 pr-3 text-left font-medium">
                Команда
              </th>
              <th scope="col" class="border-l border-rule py-3 pl-3 pr-2 text-right font-medium">
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

          <TransitionGroup tag="tbody" name="team-rank">
            <tr
              v-for="(team, index) in visibleTeams"
              :key="team.id"
              class="border-b border-rule transition-colors hover:bg-secondary/60 focus-within:!bg-primary/[0.07]"
              :class="{
                'bg-secondary/35': index % 2 === 1
              }"
            >
              <td class="py-3.5 pl-3 pr-2 align-middle sm:pl-4">
                <span class="flex items-center gap-1.5 font-display text-lg tabular-nums">
                  <span
                    v-if="index === 0"
                    aria-hidden="true"
                    class="inline-block size-1.5 rounded-full bg-leader"
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

              <td class="border-l border-rule py-3.5 pl-3 pr-2 text-right align-middle">
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
                  class="peer ml-auto block h-10 w-full max-w-16 rounded-none border-0 border-b border-rule bg-transparent px-1 text-right font-display text-xl tabular-nums text-foreground/85 outline-none transition-colors placeholder:text-muted-foreground/40 hover:border-rule-strong hover:text-foreground focus:border-primary focus:text-foreground disabled:cursor-not-allowed disabled:opacity-60"
                  type="text"
                  inputmode="decimal"
                  :value="scoreValue(team, round.id)"
                  :disabled="props.readOnly"
                  :aria-label="`Баллы команды ${team.name} в раунде ${round.title}`"
                  placeholder="0"
                  @focus="handleScoreFocus(team, round, roundIndex, $event)"
                  @input="updateScore(team, round, $event)"
                  @blur="handleScoreBlur(team, round)"
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

            <tr v-if="visibleTeams.length === 0" key="empty">
              <td
                :colspan="3 + (quizStore.currentGame?.rounds.length ?? 0)"
                class="py-12 text-center text-sm text-muted-foreground"
              >
                Добавьте команды и раунды, чтобы начать.
              </td>
            </tr>
          </TransitionGroup>
        </table>
      </div>
    </div>

    <!-- Mobile / Tablet View (< 1024px) -->
    <div class="block lg:hidden w-full min-w-0 max-w-full">
      <!-- Mode Switcher -->
      <div class="mb-4 grid grid-cols-3 items-center rounded-md border border-rule bg-secondary/60 p-1">
        <button
          type="button"
          class="flex-1 flex items-center justify-center h-11 min-w-0 px-1 rounded-sm text-xs sm:text-sm font-medium transition-all whitespace-nowrap text-center"
          :class="mobileMode === 'round'
            ? 'bg-background font-bold text-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground active:scale-95'"
          @click="mobileMode = 'round'"
        >
          По раундам
        </button>
        <button
          type="button"
          class="flex-1 flex items-center justify-center h-11 min-w-0 px-1 rounded-sm text-xs sm:text-sm font-medium transition-all whitespace-nowrap text-center"
          :class="mobileMode === 'team'
            ? 'bg-background font-bold text-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground active:scale-95'"
          @click="mobileMode = 'team'"
        >
          По командам
        </button>
        <button
          type="button"
          class="flex-1 flex items-center justify-center h-11 min-w-0 px-1 rounded-sm text-xs sm:text-sm font-medium transition-all whitespace-nowrap text-center"
          :class="mobileMode === 'table'
            ? 'bg-background font-bold text-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground active:scale-95'"
          @click="mobileMode = 'table'"
        >
          Таблица
        </button>
      </div>

      <div v-if="visibleTeams.length === 0" class="py-12 text-center text-sm text-muted-foreground">
        Добавьте команды и раунды, чтобы начать.
      </div>

      <!-- Mode 1: By Round -->
      <div v-else-if="mobileMode === 'round'">
        <!-- Scrollable round tabs -->
        <div class="flex items-center gap-2 overflow-x-auto overscroll-x-contain pb-2 w-full max-w-full">
          <button
            v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
            :key="round.id"
            type="button"
            class="flex h-11 shrink-0 items-center gap-1.5 rounded-lg border px-4 text-sm font-medium transition-all active:scale-95"
            :class="activeRoundIndex === roundIndex
              ? 'border-foreground bg-foreground font-bold text-background shadow-sm'
              : 'border-rule bg-background text-muted-foreground hover:border-rule-strong hover:text-foreground'"
            @click="activeRoundIndex = roundIndex"
          >
            <span>Р{{ roundIndex + 1 }}</span>
            <span v-if="round.maxScore !== null && round.maxScore !== undefined" class="text-xs opacity-75">
              ({{ round.maxScore }})
            </span>
          </button>
        </div>

        <!-- Active round banner with quick prev/next navigation -->
        <div v-if="activeRound" class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-rule bg-secondary/40 p-3">
          <div class="min-w-0 flex-1">
            <div class="break-words font-display text-base sm:text-lg font-bold text-foreground">
              Раунд {{ activeRoundIndex + 1 }}
            </div>
            <div v-if="activeRound.maxScore !== null && activeRound.maxScore !== undefined" class="text-xs sm:text-sm text-muted-foreground">
              Максимум баллов: {{ activeRound.maxScore }}
            </div>
          </div>
          <div class="flex shrink-0 items-center gap-1.5">
            <Button
              type="button"
              variant="outline"
              class="h-10 px-3 text-sm font-semibold active:scale-95"
              :disabled="activeRoundIndex <= 0"
              @click="activeRoundIndex--"
            >
              ← Р{{ activeRoundIndex }}
            </Button>
            <Button
              type="button"
              variant="outline"
              class="h-10 px-3 text-sm font-semibold active:scale-95"
              :disabled="!quizStore.currentGame || activeRoundIndex >= quizStore.currentGame.rounds.length - 1"
              @click="activeRoundIndex++"
            >
              Р{{ activeRoundIndex + 2 }} →
            </Button>
          </div>
        </div>

        <!-- Teams list for active round -->
        <TransitionGroup v-if="activeRound" tag="div" name="team-rank" class="mt-4 divide-y divide-rule border-y border-rule w-full min-w-0">
          <div
            v-for="(team, index) in visibleTeams"
            :key="team.id"
            class="flex items-center justify-between gap-3 py-3.5 transition-colors hover:bg-secondary/40 focus-within:bg-primary/[0.07]"
            :class="{ 'bg-secondary/20': index % 2 === 1 }"
          >
            <div class="flex min-w-0 flex-1 items-center gap-2.5 sm:gap-3">
              <span class="flex w-6 shrink-0 items-center gap-1 font-display text-base tabular-nums">
                <span
                  v-if="index === 0"
                  aria-hidden="true"
                  class="inline-block size-2 rounded-full bg-leader"
                />
                <span :class="index === 0 ? 'font-bold text-foreground' : 'font-medium text-foreground/70'">
                  {{ index + 1 }}
                </span>
              </span>
              <div class="min-w-0 flex-1">
                <div
                  class="break-words font-display text-base sm:text-lg font-semibold leading-tight text-foreground"
                  :class="{ 'font-bold': index === 0 }"
                >
                  {{ team.name }}
                </div>
                <div class="text-xs sm:text-sm text-muted-foreground tabular-nums">
                  Всего: <span class="font-semibold text-foreground">{{ quizStore.getTotalScore(team) }}</span>
                </div>
              </div>
            </div>

            <div class="flex shrink-0 items-center">
              <input
                type="text"
                inputmode="decimal"
                class="h-12 w-18 sm:w-24 rounded-lg border-2 border-rule bg-background px-2 text-center font-display text-2xl font-bold tabular-nums text-foreground outline-none transition-colors hover:border-rule-strong focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                :value="scoreValue(team, activeRound.id)"
                :disabled="props.readOnly"
                :aria-label="`Баллы команды ${team.name} в раунде ${activeRound.title}`"
                placeholder="0"
                @focus="handleScoreFocus(team, activeRound, activeRoundIndex, $event)"
                @input="updateScore(team, activeRound, $event)"
                @blur="handleScoreBlur(team, activeRound)"
                @keydown="handleScoreKeydown"
                @wheel="preventWheelStep"
              >
            </div>
          </div>
        </TransitionGroup>

        <!-- Bottom prev/next round buttons -->
        <div
          v-if="activeRound && quizStore.currentGame && quizStore.currentGame.rounds.length > 1"
          class="mt-6 flex items-center justify-between gap-2 sm:gap-3"
        >
          <Button
            type="button"
            variant="outline"
            class="h-11 flex-1 px-2 text-xs sm:text-sm font-semibold active:scale-98"
            :disabled="activeRoundIndex <= 0"
            @click="activeRoundIndex--"
          >
            ← Предыдущий
          </Button>
          <Button
            type="button"
            variant="outline"
            class="h-11 flex-1 px-2 text-xs sm:text-sm font-semibold active:scale-98"
            :disabled="activeRoundIndex >= quizStore.currentGame.rounds.length - 1"
            @click="activeRoundIndex++"
          >
            Следующий →
          </Button>
        </div>
      </div>

      <!-- Mode 2: By Team -->
      <TransitionGroup v-else-if="mobileMode === 'team'" tag="div" name="team-rank" class="flex flex-col gap-3.5 w-full min-w-0">
        <div
          v-for="(team, index) in visibleTeams"
          :key="team.id"
          class="rounded-xl border border-rule bg-background p-4 transition-colors focus-within:border-primary focus-within:bg-primary/[0.03] w-full min-w-0"
          :class="{ 'bg-secondary/25': index % 2 === 1 }"
        >
          <div class="mb-3 flex flex-wrap items-center justify-between gap-x-2 gap-y-2 border-b border-rule pb-2.5">
            <div class="flex min-w-0 flex-1 basis-40 items-center gap-2">
              <span class="flex shrink-0 items-center gap-1 font-display text-sm tabular-nums">
                <span
                  v-if="index === 0"
                  aria-hidden="true"
                  class="inline-block size-2 rounded-full bg-leader"
                />
                <span :class="index === 0 ? 'font-bold text-foreground' : 'font-medium text-foreground/70'">
                  #{{ index + 1 }}
                </span>
              </span>
              <div class="break-words font-display text-base sm:text-lg font-bold leading-tight text-foreground">
                {{ team.name }}
              </div>
            </div>
            <div class="shrink-0 rounded-md bg-secondary px-2.5 py-1 font-display text-sm sm:text-base font-bold tabular-nums text-foreground">
              Σ {{ quizStore.getTotalScore(team) }}
            </div>
          </div>

          <!-- Horizontal scrollable rounds row for this team (scales up to 15+ rounds smoothly) -->
          <div class="flex gap-2.5 overflow-x-auto overscroll-x-contain pb-1.5 pt-0.5 w-full max-w-full">
            <div
              v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
              :key="round.id"
              class="flex shrink-0 flex-col items-center gap-1.5"
            >
              <span class="eyebrow-tight whitespace-nowrap text-xs font-semibold text-muted-foreground">
                Р{{ roundIndex + 1 }}
              </span>
              <input
                type="text"
                inputmode="decimal"
                class="h-12 w-16 sm:w-18 rounded-lg border-2 border-rule bg-background px-1 text-center font-display text-xl font-bold tabular-nums text-foreground outline-none transition-colors hover:border-rule-strong focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                :value="scoreValue(team, round.id)"
                :disabled="props.readOnly"
                :aria-label="`Баллы команды ${team.name} в раунде ${round.title}`"
                placeholder="0"
                @focus="handleScoreFocus(team, round, roundIndex, $event)"
                @input="updateScore(team, round, $event)"
                @blur="handleScoreBlur(team, round)"
                @keydown="handleScoreKeydown"
                @wheel="preventWheelStep"
              >
            </div>
          </div>
        </div>
      </TransitionGroup>

      <!-- Mode 3: Only the score columns scroll; team names stay visible. -->
      <div v-else-if="mobileMode === 'table'" tabindex="0" role="region" aria-label="Таблица результатов, прокрутка по горизонтали" class="w-full max-w-full overflow-x-auto overscroll-x-contain border-y border-rule">
        <table class="w-full border-collapse text-foreground">
          <thead>
            <tr class="border-b border-rule-strong eyebrow-tight text-muted-foreground">
              <th scope="col" class="sticky left-0 z-10 w-10 min-w-10 max-w-10 bg-background py-3.5 pl-3 pr-2 text-left font-semibold min-w-10">
                №
              </th>
              <th scope="col" class="sticky left-10 z-10 w-28 min-w-28 max-w-28 sm:w-40 sm:min-w-40 sm:max-w-40 border-r border-rule bg-background py-3.5 pl-2 pr-3 text-left font-semibold">
                Команда
              </th>
              <th scope="col" class="border-r border-rule py-3.5 px-3 text-right font-semibold min-w-14">
                Σ
              </th>
              <th
                v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
                :key="round.id"
                scope="col"
                class="min-w-22 py-3 px-2 text-center font-semibold"
              >
                <div class="font-display text-base font-bold tracking-normal text-foreground normal-case">
                  Р{{ roundIndex + 1 }}
                </div>
                <div v-if="round.maxScore !== null && round.maxScore !== undefined" class="text-[11px] text-muted-foreground font-normal">
                  макс. {{ round.maxScore }}
                </div>
              </th>
            </tr>
          </thead>
          <TransitionGroup tag="tbody" name="team-rank">
            <tr
              v-for="(team, index) in visibleTeams"
              :key="team.id"
              class="border-b border-rule transition-colors focus-within:!bg-primary/[0.07]"
              :class="{ 'bg-secondary/35': index % 2 === 1 }"
            >
              <td class="sticky left-0 z-10 w-10 min-w-10 max-w-10 py-3.5 pl-3 pr-2 align-middle" :class="index % 2 === 1 ? 'bg-secondary' : 'bg-background'">
                <span class="flex items-center gap-1 font-display text-sm tabular-nums">
                  <span v-if="index === 0" aria-hidden="true" class="inline-block size-1.5 rounded-full bg-leader" />
                  <span :class="index === 0 ? 'font-bold text-foreground' : 'font-medium text-foreground/70'">
                    {{ index + 1 }}
                  </span>
                </span>
              </td>
              <td
                class="sticky left-10 z-10 w-28 min-w-28 max-w-28 sm:w-40 sm:min-w-40 sm:max-w-40 break-words whitespace-normal border-r border-rule py-3.5 pl-2 pr-3 align-middle"
                :class="index % 2 === 1 ? 'bg-secondary' : 'bg-background'"
              >
                <span class="font-display text-sm sm:text-base leading-tight text-foreground" :class="index === 0 ? 'font-bold' : 'font-semibold'">
                  {{ team.name }}
                </span>
              </td>
              <td class="border-r border-rule py-3.5 px-3 text-right align-middle">
                <span class="font-display text-base sm:text-lg tabular-nums text-foreground font-bold" :class="index === 0 ? 'text-primary' : ''">
                  {{ quizStore.getTotalScore(team) }}
                </span>
              </td>
              <td
                v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
                :key="round.id"
                class="py-2.5 px-1.5 text-center align-middle min-w-22"
              >
                <input
                  type="text"
                  inputmode="decimal"
                  class="h-12 w-18 sm:w-20 rounded-lg border-2 border-rule bg-background px-1 text-center font-display text-xl font-bold tabular-nums text-foreground outline-none transition-colors hover:border-rule-strong focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                  :value="scoreValue(team, round.id)"
                  :disabled="props.readOnly"
                  :aria-label="`Баллы команды ${team.name} в раунде ${round.title}`"
                  placeholder="0"
                  @focus="handleScoreFocus(team, round, roundIndex, $event)"
                  @input="updateScore(team, round, $event)"
                  @blur="handleScoreBlur(team, round)"
                  @keydown="handleScoreKeydown"
                  @wheel="preventWheelStep"
                >
              </td>
            </tr>
          </TransitionGroup>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.team-rank-move {
  transition: transform 280ms ease-out, background-color 150ms ease-out;
}

@media (prefers-reduced-motion: reduce) {
  .team-rank-move {
    transition: none;
  }
}
</style>
