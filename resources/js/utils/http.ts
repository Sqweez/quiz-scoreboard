type RequestOptions = {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE'
  body?: unknown
}

// Carries the HTTP status and the optional machine-readable reason sent by the server.
export class HttpError extends Error {
  constructor(message: string, readonly status: number, readonly reason?: string) {
    super(message)
  }
}

export async function http<T = void>(url: string, options: RequestOptions = {}): Promise<T> {
  const token = document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))
  const response = await fetch(url, {
    method: options.method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token.slice('XSRF-TOKEN='.length)) } : {})
    },
    body: options.body === undefined ? undefined : JSON.stringify(options.body)
  })

  if (response.status === 204) {
    return undefined as T
  }

  const data = await response.json()

  if (!response.ok) {
    throw new HttpError(data.message, response.status, data.reason)
  }

  return data as T
}

export function getRequestMessage(error: unknown): string {
  return error instanceof Error ? error.message : 'Не удалось сохранить изменения.'
}
