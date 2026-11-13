<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '~/components/ui/button'
import { Spinner } from '~/components/ui/spinner'
import QuizPleaseLoginDialog from '~/components/QuizPleaseLoginDialog.vue'
import { type ExternalGame, useQuizPleaseImport } from '~/composables/useQuizPleaseImport'

const emit = defineEmits<{
  imported: [game: ExternalGame]
}>()

const { gameId, game, error, isFetching, isLoginOpen, fetchGame, login, cancelLogin } = useQuizPleaseImport({
  onImported: (result) => emit('imported', result)
})

// The API date format is not documented, so unparseable values are shown as received.
const gameDate = computed(() => {
  if (!game.value?.date) {
    return ''
  }

  const date = new Date(game.value.date.replace(' ', 'T'))

  return Number.isNaN(date.getTime())
    ? game.value.date
    : date.toLocaleString('ru', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
})
</script>

<template>
  <section class="space-y-3">
    <label for="quizplease-game-id" class="eyebrow-tight block text-muted-foreground">
      ID игры в Квиз, плиз!
    </label>
    <div class="flex flex-col gap-3 sm:flex-row">
      <input
        id="quizplease-game-id"
        v-model="gameId"
        type="text"
        autocomplete="off"
        spellcheck="false"
        placeholder="01a19b42-0cc8-709f-8197-62881b0dbcdc"
        class="h-12 min-w-0 flex-1 border-0 border-b border-rule-strong bg-transparent font-mono text-base outline-none transition-colors placeholder:text-muted-foreground/40 focus:border-foreground"
        @keydown.enter.prevent="fetchGame"
      >
      <Button
        type="button"
        variant="outline"
        size="lg"
        class="h-12"
        :disabled="isFetching || isLoginOpen || !gameId.trim()"
        @click="fetchGame"
      >
        <Spinner v-if="isFetching" />
        Получить игру
      </Button>
    </div>

    <p v-if="error" class="text-sm text-primary" role="alert">
      {{ error }}
    </p>

    <div v-if="game" class="space-y-2 border-l-2 border-rule-strong pl-4" aria-live="polite">
      <p class="font-display text-xl font-medium leading-tight">{{ game.title }}</p>
      <p v-if="gameDate" class="text-sm text-muted-foreground">{{ gameDate }}</p>

      <p v-if="game.existingGameId" class="text-sm">
        Эта игра уже создана.
        <Link :href="`/games/${game.existingGameId}`" class="text-primary underline-offset-4 hover:underline">
          Открыть игру
        </Link>
      </p>
      <template v-else>
        <p v-if="game.teams.length === 0" class="text-sm text-primary">
          В игре нет подтверждённых команд. Название подставлено, команды добавьте вручную.
        </p>
        <template v-else>
          <p class="text-sm text-muted-foreground">
            Подтверждённых команд: {{ game.teams.length }}. Они подставлены в форму ниже — проверьте перед созданием.
          </p>
          <ol class="list-decimal pl-5 text-sm">
            <li v-for="team in game.teams" :key="team.externalId">{{ team.name }}</li>
          </ol>
        </template>
      </template>
    </div>

    <QuizPleaseLoginDialog :open="isLoginOpen" :submit="login" @cancel="cancelLogin" />
  </section>
</template>
