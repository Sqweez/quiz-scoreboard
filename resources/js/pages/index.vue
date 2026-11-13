<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ArrowLeft, ArrowRight, LogIn, LogOut, Plus, Search } from 'lucide-vue-next'
import type { GameSummary, PaginatedResourceCollection, ResourceCollection } from '~/lib/quiz'
import { Button } from '~/components/ui/button'
import { Input } from '~/components/ui/input'
import { Spinner } from '~/components/ui/spinner'
import { useQuizStore } from '~/stores/quiz'
import { getRequestMessage, http } from '~/utils/http'

const quizStore = useQuizStore()
const page = usePage()
const user = computed(() => page.props.auth.user)
const hasInitialAuthCheck = ref(false)

const activeGames = ref<GameSummary[]>([])
const finishedPage = ref<PaginatedResourceCollection<GameSummary> | null>(null)
const isLoading = ref(false)
const isFinishedPageLoading = ref(false)
const error = ref('')
const search = ref('')
// Unfiltered flag: an empty search result must not hide the section or the whole list.
const hasFinishedGames = ref(false)
const hasGames = computed(() => activeGames.value.length > 0 || hasFinishedGames.value)
const SEARCH_DEBOUNCE_MS = 300
let searchTimer: ReturnType<typeof setTimeout> | undefined
let finishedRequestId = 0
const dateFormat = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })

async function loadGames(): Promise<void> {
  isLoading.value = true
  error.value = ''

  try {
    const [active, finished] = await Promise.all([
      http<ResourceCollection<GameSummary>>('/api/games/active'),
      http<PaginatedResourceCollection<GameSummary>>('/api/games/finished')
    ])
    activeGames.value = active.data
    finishedPage.value = finished
    hasFinishedGames.value = finished.meta.total > 0
  } catch (requestError) {
    error.value = getRequestMessage(requestError)
  } finally {
    isLoading.value = false
  }
}

async function loadFinishedPage(pageNumber: number): Promise<void> {
  // Only the latest request may update the table when typing outpaces responses.
  const requestId = ++finishedRequestId
  const query = new URLSearchParams({ page: String(pageNumber) })

  if (search.value.trim()) {
    query.set('search', search.value.trim())
  }

  isFinishedPageLoading.value = true

  try {
    const result = await http<PaginatedResourceCollection<GameSummary>>(`/api/games/finished?${query}`)

    if (requestId === finishedRequestId) {
      finishedPage.value = result
    }
  } catch (requestError) {
    if (requestId === finishedRequestId) {
      error.value = getRequestMessage(requestError)
    }
  } finally {
    if (requestId === finishedRequestId) {
      isFinishedPageLoading.value = false
    }
  }
}

function clearGames(): void {
  clearTimeout(searchTimer)
  activeGames.value = []
  finishedPage.value = null
  hasFinishedGames.value = false
  search.value = ''
}

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadFinishedPage(1), SEARCH_DEBOUNCE_MS)
})

onBeforeUnmount(() => clearTimeout(searchTimer))

watch(
  user,
  async (value) => {
    if (!hasInitialAuthCheck.value) {
      return
    }

    if (value) {
      await loadGames()
      return
    }

    clearGames()
  },
  { immediate: false }
)

onMounted(async () => {
  if (user.value) {
    await loadGames()
  }

  hasInitialAuthCheck.value = true
})

async function signOut(): Promise<void> {
  if (!await quizStore.flushPendingScoreChanges(true)) {
    return
  }

  await http('/logout', { method: 'POST' })
  clearGames()
  quizStore.currentGame = null
  router.visit('/')
}
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col gap-10 px-4 py-8 lg:px-8 lg:py-12">
    <header class="flex flex-wrap items-end justify-between gap-6 border-b border-rule-strong pb-6">
      <div class="space-y-3">
        <p class="eyebrow text-muted-foreground">
          Таблица квиза
        </p>
        <h1 class="font-display text-4xl font-medium leading-none lg:text-5xl">
          Все игры
        </h1>
      </div>

      <div class="flex flex-wrap items-center gap-3 text-sm">
        <Link href="/games/new">
          <Button>
            <Plus class="size-4" />
            Создать игру
          </Button>
        </Link>
        <button
          v-if="user"
          type="button"
          class="inline-flex items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
          @click="signOut"
        >
          <LogOut class="size-3.5" />
          Выйти
        </button>
        <Link
          v-else
          href="/login"
          class="inline-flex items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
        >
          <LogIn class="size-3.5" />
          Войти
        </Link>
      </div>
    </header>

    <section v-if="!user" class="flex flex-col items-start gap-4 py-12">
      <p class="eyebrow text-muted-foreground">
        Доступ закрыт
      </p>
      <h2 class="font-display text-3xl font-medium">Войдите, чтобы увидеть свои игры</h2>
      <Link href="/login">
        <Button class="mt-2">
          <LogIn class="size-4" />
          Войти
        </Button>
      </Link>
    </section>

    <section v-else-if="isLoading" class="flex items-center gap-3 py-12 text-muted-foreground">
      <Spinner />
      <span class="text-sm">Загружаем игры</span>
    </section>

    <section v-else-if="error" class="space-y-2 py-12">
      <p class="eyebrow text-primary">
        Ошибка
      </p>
      <p class="text-foreground">{{ error }}</p>
    </section>

    <section v-else-if="!hasGames" class="flex flex-col items-start gap-4 py-12">
      <p class="eyebrow text-muted-foreground">
        Чисто
      </p>
      <h2 class="font-display text-3xl font-medium">Пока нет игр</h2>
      <p class="max-w-md text-sm text-muted-foreground">
        Создайте первую: добавьте команды, опишите раунды и начните вносить баллы.
      </p>
      <Link href="/games/new">
        <Button class="mt-2">
          <Plus class="size-4" />
          Создать игру
        </Button>
      </Link>
    </section>

    <template v-else>
      <section v-if="activeGames.length > 0" class="space-y-3">
        <div class="flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2">
          <h2 class="eyebrow text-muted-foreground">
            Идут сейчас
          </h2>
          <span class="eyebrow text-muted-foreground">
            {{ activeGames.length }}
          </span>
        </div>
        <ul class="divide-y divide-rule">
          <li v-for="game in activeGames" :key="game.id">
            <Link
              :href="`/games/${game.id}`"
              class="group flex items-center justify-between gap-4 py-4 transition-colors hover:bg-secondary/40"
            >
              <div class="min-w-0 flex-1">
                <p class="font-display text-2xl font-medium leading-tight">
                  {{ game.title }}
                </p>
                <p class="mt-1 eyebrow-tight text-muted-foreground">
                  {{ game.teamsCount }} команд · {{ game.roundsCount }} раундов
                </p>
              </div>
              <span class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors group-hover:text-primary">
                Открыть
                <ArrowRight class="size-3.5" />
              </span>
            </Link>
          </li>
        </ul>
      </section>

      <section v-if="finishedPage && hasFinishedGames" class="space-y-3">
        <div class="flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2">
          <h2 class="eyebrow text-muted-foreground">
            Завершённые
          </h2>
          <span class="eyebrow text-muted-foreground">
            {{ finishedPage.meta.total }}
          </span>
        </div>
        <label class="relative block max-w-sm">
          <span class="sr-only">Поиск по названию</span>
          <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input v-model="search" type="search" placeholder="Поиск по названию" class="pl-8" />
        </label>
        <p v-if="finishedPage.meta.total === 0" class="py-6 text-sm text-muted-foreground">
          Ничего не найдено
        </p>
        <div v-else class="overflow-x-auto" :class="{ 'opacity-60': isFinishedPageLoading }">
          <table class="w-full border-collapse text-foreground">
            <thead>
              <tr class="border-b border-rule eyebrow-tight text-muted-foreground">
                <th scope="col" class="py-3 pr-3 text-left font-medium">Игра</th>
                <th scope="col" class="px-3 py-3 text-right font-medium">Команд</th>
                <th scope="col" class="px-3 py-3 text-right font-medium">Раундов</th>
                <th scope="col" class="py-3 pl-3 text-right font-medium">Дата</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-rule">
              <tr
                v-for="game in finishedPage.data"
                :key="game.id"
                class="cursor-pointer transition-colors hover:bg-secondary/40"
                @click="router.visit(`/games/${game.id}`)"
              >
                <td class="py-3.5 pr-3 align-middle">
                  <Link :href="`/games/${game.id}`" class="font-display text-xl leading-tight" @click.stop>
                    {{ game.title }}
                  </Link>
                </td>
                <td class="px-3 py-3.5 text-right align-middle text-sm text-muted-foreground">
                  {{ game.teamsCount }}
                </td>
                <td class="px-3 py-3.5 text-right align-middle text-sm text-muted-foreground">
                  {{ game.roundsCount }}
                </td>
                <td class="whitespace-nowrap py-3.5 pl-3 text-right align-middle text-sm text-muted-foreground">
                  {{ dateFormat.format(new Date(game.finishedAt!)) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <nav
          v-if="finishedPage.meta.last_page > 1"
          class="flex items-center justify-between gap-3 border-t border-rule-strong pt-3 text-sm"
        >
          <Button
            variant="ghost"
            size="sm"
            :disabled="finishedPage.meta.current_page <= 1 || isFinishedPageLoading"
            @click="loadFinishedPage(finishedPage.meta.current_page - 1)"
          >
            <ArrowLeft class="size-3.5" />
            Назад
          </Button>
          <span class="eyebrow-tight text-muted-foreground">
            {{ finishedPage.meta.current_page }} из {{ finishedPage.meta.last_page }}
          </span>
          <Button
            variant="ghost"
            size="sm"
            :disabled="finishedPage.meta.current_page >= finishedPage.meta.last_page || isFinishedPageLoading"
            @click="loadFinishedPage(finishedPage.meta.current_page + 1)"
          >
            Вперёд
            <ArrowRight class="size-3.5" />
          </Button>
        </nav>
      </section>
    </template>
  </main>
</template>
