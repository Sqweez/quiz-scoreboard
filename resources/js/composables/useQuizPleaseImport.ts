import { ref } from 'vue'
import type { GameExtra } from '~/lib/quiz'
import { useQuizPleaseLogin } from '~/composables/useQuizPleaseLogin'
import { getRequestMessage, http } from '~/utils/http'

export type ExternalGame = {
  externalId: string
  title: string
  date: string | null
  teams: Array<{ externalId: string, name: string }>
  extra: GameExtra
  existingGameId: string | null
}

type UseQuizPleaseImportOptions = {
  onImported: (game: ExternalGame) => void
}

export function useQuizPleaseImport(options: UseQuizPleaseImportOptions) {
  const gameId = ref('')
  const game = ref<ExternalGame | null>(null)
  const error = ref('')
  const isFetching = ref(false)
  const quizPleaseLogin = useQuizPleaseLogin()

  async function fetchGame(): Promise<void> {
    if (isFetching.value) {
      return
    }

    error.value = ''
    isFetching.value = true

    try {
      const result = await http<ExternalGame>(`/api/quizplease/game?id=${encodeURIComponent(gameId.value.trim())}`)
      game.value = result
      options.onImported(result)
    } catch (requestError) {
      if (quizPleaseLogin.requireLogin(requestError, fetchGame)) {
        return
      }

      error.value = getRequestMessage(requestError)
    } finally {
      isFetching.value = false
    }
  }

  function cancelLogin(): void {
    quizPleaseLogin.cancelLogin()
    error.value = 'Вход отменён. Чтобы получить игру, войдите в «Квиз, плиз!».'
  }

  return { gameId, game, error, isFetching, isLoginOpen: quizPleaseLogin.isLoginOpen, fetchGame, login: quizPleaseLogin.login, cancelLogin }
}
