import { afterEach, describe, expect, it, vi } from 'vitest'
import { useQuizPleaseLogin } from '../../resources/js/composables/useQuizPleaseLogin'
import { HttpError } from '../../resources/js/utils/http'

afterEach(() => vi.unstubAllGlobals())

describe('useQuizPleaseLogin', () => {
  it('opens only for auth_required and retries the interrupted action after login', async () => {
    vi.stubGlobal('document', { cookie: '' })
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)
    const retry = vi.fn().mockResolvedValue(undefined)
    const login = useQuizPleaseLogin()

    expect(login.requireLogin(new HttpError('Not found', 404, 'not_found'), retry)).toBe(false)
    expect(login.requireLogin(new HttpError('Login', 409, 'auth_required'), retry)).toBe(true)
    expect(login.isLoginOpen.value).toBe(true)

    await login.login('host@example.com', 'secret')

    expect(fetchMock).toHaveBeenCalledWith('/api/quizplease/login', expect.objectContaining({
      method: 'POST', body: '{"username":"host@example.com","password":"secret"}'
    }))
    expect(login.isLoginOpen.value).toBe(false)
    expect(retry).toHaveBeenCalledOnce()
  })

  it('keeps the dialog open on a failed login and drops the action on cancel', async () => {
    vi.stubGlobal('document', { cookie: '' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: 'Неверный логин или пароль «Квиз, плиз!».', reason: 'invalid_credentials' }), { status: 422 })))
    const retry = vi.fn()
    const login = useQuizPleaseLogin()

    login.requireLogin(new HttpError('Login', 409, 'auth_required'), retry)
    await expect(login.login('host@example.com', 'wrong')).rejects.toThrow('Неверный логин или пароль «Квиз, плиз!».')
    expect(login.isLoginOpen.value).toBe(true)

    login.cancelLogin()
    expect(login.isLoginOpen.value).toBe(false)
    expect(retry).not.toHaveBeenCalled()
  })
})
