<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ArrowRight, LogIn, LogOut, Plus } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'
import { Spinner } from '~/components/ui/spinner'
import { useQuizStore } from '~/stores/quiz'

const quizStore = useQuizStore()
const supabase = useSupabaseClient()
const user = useSupabaseUser()
const hasInitialAuthCheck = ref(false)

const draftGames = computed(() =>
  quizStore.games.filter((game) => game.status !== 'finished')
)
const finishedGames = computed(() =>
  quizStore.games.filter((game) => game.status === 'finished')
)

watch(
  user,
  async (value) => {
    if (!hasInitialAuthCheck.value) {
      return
    }

    if (value) {
      await quizStore.loadGames()
      return
    }

    quizStore.games = []
  },
  { immediate: false }
)

onMounted(async () => {
  if (user.value) {
    await quizStore.loadGames()
  }

  hasInitialAuthCheck.value = true
})

async function signOut(): Promise<void> {
  await supabase.auth.signOut()
  quizStore.games = []
  quizStore.currentGame = null
}
</script>

<template>
  <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col gap-10 px-4 py-8 lg:px-8 lg:py-12">
    <header class="flex flex-wrap items-end justify-between gap-6 border-b border-[var(--rule-strong)] pb-6">
      <div class="space-y-3">
        <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
          Таблица квиза
        </p>
        <h1 class="font-display text-4xl font-medium leading-none lg:text-5xl">
          Все игры
        </h1>
      </div>

      <div class="flex flex-wrap items-center gap-3 text-sm">
        <NuxtLink to="/games/new">
          <Button>
            <Plus class="size-4" />
            Создать игру
          </Button>
        </NuxtLink>
        <button
          v-if="user"
          type="button"
          class="inline-flex items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
          @click="signOut"
        >
          <LogOut class="size-3.5" />
          Выйти
        </button>
        <NuxtLink
          v-else
          to="/login"
          class="inline-flex items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
        >
          <LogIn class="size-3.5" />
          Войти
        </NuxtLink>
      </div>
    </header>

    <section v-if="!user" class="flex flex-col items-start gap-4 py-12">
      <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
        Доступ закрыт
      </p>
      <h2 class="font-display text-3xl font-medium">Войдите, чтобы увидеть свои игры</h2>
      <NuxtLink to="/login">
        <Button class="mt-2">
          <LogIn class="size-4" />
          Войти
        </Button>
      </NuxtLink>
    </section>

    <section v-else-if="quizStore.isLoading" class="flex items-center gap-3 py-12 text-muted-foreground">
      <Spinner />
      <span class="text-sm">Загружаем игры</span>
    </section>

    <section v-else-if="quizStore.error" class="space-y-2 py-12">
      <p class="text-[11px] uppercase tracking-[0.18em] text-[var(--primary)]">
        Ошибка
      </p>
      <p class="text-foreground">{{ quizStore.error }}</p>
    </section>

    <section v-else-if="quizStore.games.length === 0" class="flex flex-col items-start gap-4 py-12">
      <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
        Чисто
      </p>
      <h2 class="font-display text-3xl font-medium">Пока нет игр</h2>
      <p class="max-w-md text-sm text-muted-foreground">
        Создайте первую: добавьте команды, опишите раунды и начните вносить баллы.
      </p>
      <NuxtLink to="/games/new">
        <Button class="mt-2">
          <Plus class="size-4" />
          Создать игру
        </Button>
      </NuxtLink>
    </section>

    <template v-else>
      <section v-if="draftGames.length > 0" class="space-y-3">
        <div class="flex items-baseline justify-between gap-3 border-b border-[var(--rule-strong)] pb-2">
          <h2 class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
            Идут сейчас
          </h2>
          <span class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
            {{ draftGames.length }}
          </span>
        </div>
        <ul class="divide-y divide-[var(--rule)]">
          <li v-for="game in draftGames" :key="game.id">
            <NuxtLink
              :to="`/games/${game.id}`"
              class="group flex items-center justify-between gap-4 py-4 transition-colors hover:bg-secondary/40"
            >
              <div class="min-w-0 flex-1">
                <p class="font-display text-2xl font-medium leading-tight">
                  {{ game.title }}
                </p>
                <p class="mt-1 text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                  {{ game.teams.length }} команд · {{ game.rounds.length }} раундов
                </p>
              </div>
              <span class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors group-hover:text-[var(--primary)]">
                {{ game.statusActionLabel }}
                <ArrowRight class="size-3.5" />
              </span>
            </NuxtLink>
          </li>
        </ul>
      </section>

      <section v-if="finishedGames.length > 0" class="space-y-3">
        <div class="flex items-baseline justify-between gap-3 border-b border-[var(--rule-strong)] pb-2">
          <h2 class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
            Завершённые
          </h2>
          <span class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">
            {{ finishedGames.length }}
          </span>
        </div>
        <ul class="divide-y divide-[var(--rule)]">
          <li v-for="game in finishedGames" :key="game.id">
            <NuxtLink
              :to="`/games/${game.id}`"
              class="group flex items-center justify-between gap-4 py-4 transition-colors hover:bg-secondary/40"
            >
              <div class="min-w-0 flex-1">
                <p class="font-display text-xl leading-tight text-foreground">
                  {{ game.title }}
                </p>
                <p class="mt-1 text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                  {{ game.teams.length }} команд · {{ game.rounds.length }} раундов
                </p>
              </div>
              <span class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors group-hover:text-[var(--primary)]">
                Открыть
                <ArrowRight class="size-3.5" />
              </span>
            </NuxtLink>
          </li>
        </ul>
      </section>
    </template>
  </main>
</template>
