import { ref } from 'vue'
import { http, HttpError } from '~/utils/http'

// Shared Quiz Please login flow: an action that hits the auth reason opens the dialog and runs again after login.
// The admin API and the rating module have separate accounts, so each passes its own endpoint and reason.
export function useQuizPleaseLogin(endpoint = '/api/quizplease/login', authReason = 'auth_required') {
  const isLoginOpen = ref(false)
  let pendingAction: (() => Promise<void>) | null = null

  // Returns true when the error asked for a login and the dialog was opened.
  function requireLogin(error: unknown, retry: () => Promise<void>): boolean {
    if (!(error instanceof HttpError && error.reason === authReason)) {
      return false
    }

    pendingAction = retry
    isLoginOpen.value = true
    return true
  }

  // Throws on failure so the dialog can show the error.
  async function login(username: string, password: string): Promise<void> {
    await http(endpoint, { method: 'POST', body: { username, password } })
    const retry = pendingAction
    pendingAction = null
    isLoginOpen.value = false
    await retry?.()
  }

  function cancelLogin(): void {
    pendingAction = null
    isLoginOpen.value = false
  }

  return { isLoginOpen, requireLogin, login, cancelLogin }
}
