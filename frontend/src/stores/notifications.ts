import { defineStore } from 'pinia'
import { api } from '@/lib/api'

export interface AppNotification {
  id: string
  type: string
  title: string
  body: string
  data?: Record<string, unknown>
  read_at?: string | null
  created_at: string
}

interface State {
  items: AppNotification[]
  unread: number
  loaded: boolean
}

export const useNotificationsStore = defineStore('notifications', {
  state: (): State => ({ items: [], unread: 0, loaded: false }),
  actions: {
    async load(): Promise<void> {
      const payload = await api.get<{ data: AppNotification[]; unread: number }>('/notifications')
      this.items = payload.data
      this.unread = payload.unread
      this.loaded = true
    },
    async markRead(id: string): Promise<void> {
      await api.patch(`/notifications/${id}/read`)
      const item = this.items.find((n) => n.id === id)
      if (item && !item.read_at) {
        item.read_at = new Date().toISOString()
        this.unread = Math.max(0, this.unread - 1)
      }
    },
    async markAllRead(): Promise<void> {
      await api.post('/notifications/read-all')
      for (const item of this.items) item.read_at = item.read_at ?? new Date().toISOString()
      this.unread = 0
    },
    prepend(item: AppNotification): void {
      this.items.unshift(item)
      if (!item.read_at) this.unread += 1
    },
  },
})
