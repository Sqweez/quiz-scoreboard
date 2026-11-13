import { ref } from 'vue'
import { getRequestMessage, http, HttpError } from '~/utils/http'

export type ExternalGame = {
  externalId: string
  title: string
  date: string | null
  teams: Array<{ externalId: string, name: string }>
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
  const isLoginOpen = ref(false)

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
      if (requestError instanceof HttpError && requestError.reason === 'auth_required') {
        isLoginOpen.value = true
        return
      }

      error.value = getRequestMessage(requestError)
    } finally {
      isFetching.value = false
    }
  }

  // Throws on failure so the login dialog can show the error; on success resumes the pending fetch.
  async function login(username: string, password: string): Promise<void> {
    await http('/api/quizplease/login', { method: 'POST', body: { username, password } })
    isLoginOpen.value = false
    await fetchGame()
  }

  function cancelLogin(): void {
    isLoginOpen.value = false
    error.value = 'Вход отменён. Чтобы получить игру, войдите в «Квиз, плиз!».'
  }

  return { gameId, game, error, isFetching, isLoginOpen, fetchGame, login, cancelLogin }
}
