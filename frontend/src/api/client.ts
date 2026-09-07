import { getAccessToken, setAccessToken } from '../auth/tokenStore'
import type { LoginResult, WorkOrder } from './types'

type RefreshBody = LoginResult

let refreshInFlight: Promise<boolean> | null = null

async function refreshSession(): Promise<boolean> {
  if (refreshInFlight) {
    return refreshInFlight
  }

  refreshInFlight = (async () => {
    const response = await fetch('/api/token/refresh', {
      method: 'POST',
      credentials: 'include',
    })
    if (!response.ok) {
      setAccessToken(null)
      return false
    }
    const body = (await response.json()) as RefreshBody
    setAccessToken(body.token)
    return true
  })().finally(() => {
    refreshInFlight = null
  })

  return refreshInFlight
}

export async function restoreSession(): Promise<boolean> {
  return refreshSession()
}

export async function apiFetch(path: string, init: RequestInit = {}, retry = true): Promise<Response> {
  const headers = new Headers(init.headers)
  const token = getAccessToken()
  if (token) {
    headers.set('Authorization', `Bearer ${token}`)
  }
  if (init.body !== undefined && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const response = await fetch(path, {
    ...init,
    headers,
    credentials: 'include',
  })

  const skipRefresh = path === '/api/login' || path === '/api/token/refresh' || path === '/api/token/logout'
  if (response.status === 401 && retry && !skipRefresh) {
    const refreshed = await refreshSession()
    if (refreshed) {
      return apiFetch(path, init, false)
    }
  }

  return response
}

export async function login(email: string, password: string): Promise<LoginResult> {
  const response = await apiFetch('/api/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })
  const body = (await response.json()) as LoginResult & { error?: string }
  if (!response.ok) {
    throw new Error(body.error ?? 'login_failed')
  }
  setAccessToken(body.token)
  return body
}

export async function logout(): Promise<void> {
  await apiFetch('/api/token/logout', { method: 'POST' })
  setAccessToken(null)
}

export async function listWorkOrders(): Promise<WorkOrder[]> {
  const response = await apiFetch('/api/work-orders')
  if (!response.ok) {
    throw new Error('list_failed')
  }
  const body = (await response.json()) as { items: WorkOrder[] }
  return body.items
}

export async function createWorkOrder(title: string, description: string): Promise<WorkOrder> {
  const response = await apiFetch('/api/work-orders', {
    method: 'POST',
    body: JSON.stringify({ title, description }),
  })
  const body = (await response.json()) as WorkOrder & { error?: string }
  if (!response.ok) {
    throw new Error(body.error ?? 'create_failed')
  }
  return body
}

export async function patchWorkOrderStatus(id: number, status: string): Promise<WorkOrder> {
  const response = await apiFetch(`/api/work-orders/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  })
  const body = (await response.json()) as WorkOrder & { error?: string }
  if (!response.ok) {
    throw new Error(body.error ?? 'patch_failed')
  }
  return body
}
