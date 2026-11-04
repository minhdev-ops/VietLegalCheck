import { defineStore } from 'pinia'
import { api, setToken, type ApiError } from '@/lib/api'

export interface SystemUser {
  id: string
  name: string
  email: string
  system_role: 'USER' | 'LEGAL_EXPERT' | 'LEGAL_ADMIN' | 'SYS_ADMIN'
  email_verified_at?: string | null
  requires_two_factor: boolean
  has_two_factor: boolean
}

interface State {
  user: SystemUser | null
  loading: boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): State => ({ user: null, loading: false }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
    isAdmin: (state) => ['LEGAL_ADMIN', 'SYS_ADMIN'].includes(state.user?.system_role ?? 'USER'),
    isExpert: (state) => state.user?.system_role === 'LEGAL_EXPERT',
  },
  actions: {
    async load(): Promise<void> {
      if (!localStorage.getItem('vlc.token')) return
      try {
        this.user = await api.get<SystemUser>('/auth/me')
      } catch {
        this.user = null
      }
    },
    async login(email: string, password: string, twoFactorCode?: string): Promise<SystemUser> {
      const payload = await api.post<{ token: string; user: SystemUser }>('/auth/login', {
        email,
        password,
        two_factor_code: twoFactorCode,
        device_name: navigator.userAgent.slice(0, 120),
      })
      setToken(payload.token)
      this.user = payload.user
      return payload.user
    },
    async register(input: {
      name: string
      email: string
      password: string
      password_confirmation: string
      consent: boolean
    }): Promise<void> {
      await api.post('/auth/register', input)
    },
    async logout(): Promise<void> {
      try {
        await api.post('/auth/logout')
      } finally {
        setToken(null)
        this.user = null
      }
    },
    errorCode(error: unknown): string {
      return (error as ApiError)?.code ?? 'UNKNOWN'
    },
  },
})
