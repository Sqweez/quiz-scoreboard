<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
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

type MobileMode = 'round' | 'team' | 'table'

const mobileModes: { value: MobileMode, label: string }[] = [
  { value: 'round', label: 'По раундам' },
  { value: 'team', label: 'По командам' },
  { value: 'table', label: 'Таблица' }
]
const mobileMode = ref<MobileMode>('round')
const activeRoundIndex = ref(0)
const roundsCount = computed(() => quizStore.currentGame?.rounds.length ?? 0)
const roundStrip = ref<HTMLElement | null>(null)

// Keep the selected round chip visible when navigating with the prev/next buttons.
watch(activeRoundIndex, async (index) => {
  await nextTick()
  const strip = roundStrip.value
  const chip = strip?.children[index] as HTMLElement | undefined
  if (!strip || !chip) {
    return
  }
  strip.scrollLeft = chip.offsetLeft - (strip.clientWidth - chip.offsetWidth) / 2
})

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
    // Inside an enter group (mobile round/team lists) Enter jumps to the next score, like a phone keyboard's "Next".
    const input = event.currentTarget
    const group = input.closest('[data-enter-group]')
    const inputs = group ? Array.from(group.querySelectorAll('input')) : []
    const next = inputs[inputs.indexOf(input) + 1]

    if (next) {
      event.preventDefault()
      next.focus()
    } else {
      input.blur()
    }
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
                  class="mt-0.5 whitespace-nowrap text-[10px] font-normal text-muted-foreground"
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
    <div class="block w-full min-w-0 max-w-full lg:hidden">
      <!-- Mode Switcher -->
      <div class="mb-4 grid grid-cols-3 gap-1 rounded-md border border-rule bg-secondary/60 p-1">
        <button
          v-for="mode in mobileModes"
          :key="mode.value"
          type="button"
          class="flex h-10 min-w-0 items-center justify-center whitespace-nowrap rounded-sm px-1 text-sm font-medium transition-colors"
          :class="mobileMode === mode.value
            ? 'bg-background text-foreground ring-1 ring-rule'
            : 'text-muted-foreground hover:text-foreground'"
          :aria-pressed="mobileMode === mode.value"
          @click="mobileMode = mode.value"
        >
          {{ mode.label }}
        </button>
      </div>

      <div v-if="visibleTeams.length === 0" class="py-12 text-center text-sm text-muted-foreground">
        Добавьте команды и раунды, чтобы начать.
      </div>

      <!-- Mode 1: By Round. The round strip sticks to the top so switching rounds never needs scrolling back up. -->
      <div v-else-if="mobileMode === 'round'">
        <div class="sticky top-0 z-10 -mx-4 border-b border-rule bg-background/95 px-4 py-2 backdrop-blur">
          <div ref="roundStrip" class="no-scrollbar flex gap-1.5 overflow-x-auto overscroll-x-contain scroll-smooth">
            <button
              v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
              :key="round.id"
              type="button"
              class="h-11 min-w-12 shrink-0 rounded-md border px-3 font-display text-base tabular-nums transition-colors"
              :class="activeRoundIndex === roundIndex
                ? 'border-foreground bg-foreground font-semibold text-background'
                : 'border-rule text-foreground/70 hover:border-rule-strong hover:text-foreground'"
              :aria-current="activeRoundIndex === roundIndex ? 'true' : undefined"
              :aria-label="`Раунд ${roundIndex + 1}`"
              @click="activeRoundIndex = roundIndex"
            >
              Р{{ roundIndex + 1 }}
            </button>
          </div>
        </div>

        <template v-if="activeRound">
          <div class="mt-4 flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2">
            <h3 class="font-display text-xl font-medium leading-tight">
              Раунд {{ activeRoundIndex + 1 }}
            </h3>
            <span v-if="activeRound.maxScore !== null && activeRound.maxScore !== undefined" class="eyebrow-tight text-muted-foreground">
              макс. {{ activeRound.maxScore }}
            </span>
          </div>

          <TransitionGroup tag="div" name="team-rank" data-enter-group class="w-full min-w-0 divide-y divide-rule border-b border-rule">
            <div
              v-for="(team, index) in visibleTeams"
              :key="team.id"
              class="flex items-center gap-3 py-3 transition-colors focus-within:bg-primary/[0.07]"
            >
              <span class="flex w-7 shrink-0 items-center gap-1 font-display text-base tabular-nums">
                <span
                  v-if="index === 0"
                  aria-hidden="true"
                  class="inline-block size-1.5 rounded-full bg-leader"
                />
                <span :class="index === 0 ? 'font-semibold text-foreground' : 'font-medium text-foreground/70'">
                  {{ index + 1 }}
                </span>
              </span>
              <div class="min-w-0 flex-1">
                <div
                  class="break-words font-display text-lg leading-tight text-foreground"
                  :class="index === 0 ? 'font-semibold' : 'font-medium'"
                >
                  {{ team.name }}
                </div>
                <div class="mt-0.5 text-sm tabular-nums text-muted-foreground">
                  Всего {{ quizStore.getTotalScore(team) }}
                </div>
              </div>
              <input
                type="text"
                inputmode="decimal"
                :enterkeyhint="index < visibleTeams.length - 1 ? 'next' : 'done'"
                class="h-12 w-20 shrink-0 scroll-mt-20 rounded-md border-2 border-rule bg-background px-2 text-center font-display text-2xl font-semibold tabular-nums text-foreground outline-none transition-colors hover:border-rule-strong focus:border-primary disabled:cursor-not-allowed disabled:opacity-60"
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
          </TransitionGroup>

          <div
            v-if="roundsCount > 1"
            class="mt-6 grid grid-cols-2 gap-2"
          >
            <Button
              v-if="activeRoundIndex > 0"
              type="button"
              variant="outline"
              class="h-12 justify-start"
              @click="activeRoundIndex--"
            >
              <ChevronLeft class="size-4" />
              Раунд {{ activeRoundIndex }}
            </Button>
            <span v-else />
            <Button
              v-if="activeRoundIndex < roundsCount - 1"
              type="button"
              variant="outline"
              class="h-12 justify-end"
              @click="activeRoundIndex++"
            >
              Раунд {{ activeRoundIndex + 2 }}
              <ChevronRight class="size-4" />
            </Button>
          </div>
        </template>
      </div>

      <!-- Mode 2: By Team. Rounds wrap into a grid so every round is visible without sideways scrolling. -->
      <TransitionGroup v-else-if="mobileMode === 'team'" tag="div" name="team-rank" class="w-full min-w-0 divide-y divide-rule border-y border-rule">
        <section
          v-for="(team, index) in visibleTeams"
          :key="team.id"
          class="py-4 transition-colors focus-within:bg-primary/[0.04]"
        >
          <div class="flex items-baseline justify-between gap-3">
            <div class="flex min-w-0 items-baseline gap-2">
              <span class="flex shrink-0 items-center gap-1 font-display text-base tabular-nums">
                <span
                  v-if="index === 0"
                  aria-hidden="true"
                  class="inline-block size-1.5 -translate-y-0.5 rounded-full bg-leader"
                />
                <span :class="index === 0 ? 'font-semibold text-foreground' : 'font-medium text-foreground/70'">
                  {{ index + 1 }}
                </span>
              </span>
              <h3
                class="min-w-0 break-words font-display text-lg leading-tight text-foreground"
                :class="index === 0 ? 'font-semibold' : 'font-medium'"
              >
                {{ team.name }}
              </h3>
            </div>
            <span
              class="shrink-0 font-display text-2xl tabular-nums text-foreground"
              :class="index === 0 ? 'font-semibold' : 'font-medium'"
            >
              <span class="sr-only">Всего </span>{{ quizStore.getTotalScore(team) }}
            </span>
          </div>

          <div data-enter-group class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8">
            <label
              v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
              :key="round.id"
              class="flex min-w-0 flex-col items-center gap-1"
            >
              <span class="eyebrow-tight text-muted-foreground">
                Р{{ roundIndex + 1 }}
              </span>
              <input
                type="text"
                inputmode="decimal"
                :enterkeyhint="roundIndex < roundsCount - 1 ? 'next' : 'done'"
                class="h-12 w-full min-w-0 scroll-mt-4 rounded-md border-2 border-rule bg-background px-1 text-center font-display text-xl font-semibold tabular-nums text-foreground outline-none transition-colors hover:border-rule-strong focus:border-primary disabled:cursor-not-allowed disabled:opacity-60"
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
            </label>
          </div>
        </section>
      </TransitionGroup>

      <!-- Mode 3: Only the score columns scroll; team names stay visible. -->
      <div v-else-if="mobileMode === 'table'" tabindex="0" role="region" aria-label="Таблица результатов, прокрутка по горизонтали" class="w-full max-w-full overflow-x-auto overscroll-x-contain border-y border-rule">
        <table class="w-full border-collapse text-foreground">
          <thead>
            <tr class="border-b border-rule-strong eyebrow-tight text-muted-foreground">
              <th scope="col" class="sticky left-0 z-10 w-10 min-w-10 max-w-10 bg-background py-3.5 pl-3 pr-2 text-left font-medium">
                №
              </th>
              <th scope="col" class="sticky left-10 z-10 w-28 min-w-28 max-w-28 border-r border-rule bg-background py-3.5 pl-2 pr-3 text-left font-medium sm:w-40 sm:min-w-40 sm:max-w-40">
                Команда
              </th>
              <th scope="col" class="min-w-14 border-r border-rule px-3 py-3.5 text-right font-medium">
                Σ
              </th>
              <th
                v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
                :key="round.id"
                scope="col"
                class="min-w-20 px-2 py-3 text-center font-medium"
              >
                <div class="font-display text-base font-medium normal-case tracking-normal text-foreground">
                  Р{{ roundIndex + 1 }}
                </div>
                <div v-if="round.maxScore !== null && round.maxScore !== undefined" class="mt-0.5 whitespace-nowrap text-[10px] font-normal text-muted-foreground">
                  макс. {{ round.maxScore }}
                </div>
              </th>
            </tr>
          </thead>
          <TransitionGroup tag="tbody" name="team-rank">
            <tr
              v-for="(team, index) in visibleTeams"
              :key="team.id"
              class="border-b border-rule"
            >
              <td class="sticky left-0 z-10 w-10 min-w-10 max-w-10 bg-background py-3 pl-3 pr-2 align-middle">
                <span class="flex items-center gap-1 font-display text-base tabular-nums">
                  <span v-if="index === 0" aria-hidden="true" class="inline-block size-1.5 rounded-full bg-leader" />
                  <span :class="index === 0 ? 'font-semibold text-foreground' : 'font-medium text-foreground/70'">
                    {{ index + 1 }}
                  </span>
                </span>
              </td>
              <td class="sticky left-10 z-10 w-28 min-w-28 max-w-28 whitespace-normal break-words hyphens-auto border-r border-rule bg-background py-3 pl-2 pr-3 align-middle sm:w-40 sm:min-w-40 sm:max-w-40">
                <span class="font-display text-base leading-tight text-foreground" :class="index === 0 ? 'font-semibold' : 'font-medium'">
                  {{ team.name }}
                </span>
              </td>
              <td class="border-r border-rule px-3 py-3 text-right align-middle">
                <span class="font-display text-xl tabular-nums text-foreground" :class="index === 0 ? 'font-semibold' : 'font-medium'">
                  {{ quizStore.getTotalScore(team) }}
                </span>
              </td>
              <td
                v-for="(round, roundIndex) in quizStore.currentGame?.rounds"
                :key="round.id"
                class="min-w-20 px-1.5 py-2 text-center align-middle"
              >
                <input
                  type="text"
                  inputmode="decimal"
                  enterkeyhint="done"
                  class="h-12 w-16 rounded-md border-2 border-rule bg-background px-1 text-center font-display text-xl font-semibold tabular-nums text-foreground outline-none transition-colors hover:border-rule-strong focus:border-primary disabled:cursor-not-allowed disabled:opacity-60"
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
