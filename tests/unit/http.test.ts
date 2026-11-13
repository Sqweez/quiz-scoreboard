import { afterEach, expect, it, vi } from 'vitest'
import { getRequestMessage, http } from '../../resources/js/utils/http'

afterEach(() => vi.unstubAllGlobals())

it('sends JSON requests with the current CSRF token and session cookies', async () => {
  vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=session%2Btoken' })
  const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ ok: true })))
  vi.stubGlobal('fetch', fetchMock)
  await expect(http('/api/games', { method: 'POST', body: { title: 'Quiz' } })).resolves.toEqual({ ok: true })
  expect(fetchMock).toHaveBeenCalledWith('/api/games', expect.objectContaining({
    credentials: 'same-origin', body: '{"title":"Quiz"}',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'session+token' }
  }))
})

it('extracts Laravel validation errors and handles empty logout responses', async () => {
  vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=token' })
  vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Invalid email.', errors: { email: ['Invalid email.'] } }), { status: 422 })).mockResolvedValueOnce(new Response(null, { status: 204 })))
  const error = await http('/login').catch(error => error)
  expect(getRequestMessage(error)).toBe('Invalid email.')
  await expect(http('/logout', { method: 'POST' })).resolves.toBeUndefined()
})

it('reads the rotated CSRF cookie for each request', async () => {
  const document = { cookie: 'XSRF-TOKEN=before' }
  vi.stubGlobal('document', document)
  const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
  vi.stubGlobal('fetch', fetchMock)
  await http('/login', { method: 'POST' })
  document.cookie = 'XSRF-TOKEN=after'
  await http('/logout', { method: 'POST' })
  expect(fetchMock.mock.calls[1][1].headers['X-XSRF-TOKEN']).toBe('after')
})
