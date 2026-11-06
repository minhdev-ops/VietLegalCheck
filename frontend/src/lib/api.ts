/**
 * API client: Bearer token (Sanctum), tiền tố /api/v1, lỗi thống nhất theo SRS 8.1.
 */
export interface ApiError {
  code: string
  message: string
  details?: Record<string, unknown>
  request_id?: string
}

const TOKEN_KEY = 'vlc.token'

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

export interface Paginated<T> {
  data: T[]
  total: number
  page: number
  per_page: number
  last_page: number
}

export interface ListParams {
  page?: number
  per_page?: number
  sort?: string
  q?: string
  [key: string]: unknown
}

async function request<T>(method: string, path: string, body?: unknown, params?: ListParams): Promise<T> {
  const url = new URL(`/api/v1${path}`, window.location.origin)
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value))
    }
  }

  const headers: Record<string, string> = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  if (body !== undefined) headers['Content-Type'] = 'application/json'
  let response: Response
  try {
    response = await fetch(url.toString(), {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: AbortSignal.timeout(120_000),
    })
  } catch {
    throw { code: 'NETWORK', message: 'Mất kết nối tới máy chủ. Vui lòng kiểm tra mạng và thử lại.' } as ApiError
  }

  if (response.status === 401) {
    setToken(null)
    if (!location.pathname.startsWith('/dang-nhap')) {
      location.assign('/dang-nhap?next=' + encodeURIComponent(location.pathname))
    }
  }

  if (response.status === 204) return undefined as T

  const text = await response.text()
  const payload = text ? JSON.parse(text) : null

  if (!response.ok) {
    throw (payload?.error ?? {
      code: 'HTTP_' + response.status,
      message: payload?.message ?? 'Yêu cầu không thành công.',
    }) as ApiError
  }

  return payload as T
}

export const api = {
  get: <T>(path: string, params?: ListParams) => request<T>('GET', path, undefined, params),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
  del: <T>(path: string) => request<T>('DELETE', path),
}

/** Upload multipart; backend kiểm tra loại file thật và băm (FR-DOC-06). */
export async function upload(path: string, file: File, extra?: Record<string, string>): Promise<unknown> {
  const form = new FormData()
  form.append('file', file)
  if (extra) for (const [k, v] of Object.entries(extra)) form.append(k, v)

  const headers: Record<string, string> = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(`/api/v1${path}`, { method: 'POST', headers, body: form })
  const text = await response.text()
  const payload = text ? JSON.parse(text) : null
  if (!response.ok) throw (payload?.error ?? { code: 'UPLOAD', message: 'Tải file không thành công.' })
  return payload
}

/**
 * SSE streaming cho AI (NFR-04) và tiến độ tác vụ. Trả về hàm huỷ.
 */
export function streamSse(
  path: string,
  body: unknown,
  handlers: {
    onDelta: (text: string) => void
    onFinal?: (payload: unknown) => void
    onError?: (e: ApiError) => void
  },
): () => void {
  const controller = new AbortController()
  const token = getToken()

  void (async () => {
    try {
      const response = await fetch(`/api/v1${path}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify(body),
        signal: controller.signal,
      })

      if (!response.ok || !response.body) {
        const text = await response.text().catch(() => '')
        const payload = text ? JSON.parse(text) : null
        throw payload?.error ?? { code: 'STREAM', message: 'Không mở được luồng phản hồi.' }
      }

      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''

      for (;;) {
        const { done, value } = await reader.read()
        if (done) break
        buffer += decoder.decode(value, { stream: true })
        const events = buffer.split('\n\n')
        buffer = events.pop() ?? ''
        for (const raw of events) {
          const line = raw.split('\n').find((l) => l.startsWith('data:'))
          if (!line) continue
          const json = JSON.parse(line.slice(5).trim())
          if (json.type === 'delta') handlers.onDelta(json.text ?? '')
          else if (json.type === 'final') handlers.onFinal?.(json)
        }
      }
    } catch (cause) {
      if (controller.signal.aborted) return
      handlers.onError?.((cause as ApiError) ?? { code: 'STREAM', message: 'Luồng bị ngắt.' })
    }
  })()

  return () => controller.abort()
}
