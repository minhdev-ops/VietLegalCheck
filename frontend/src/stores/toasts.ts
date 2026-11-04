import { defineStore } from 'pinia'

export type ToastTone = 'ok' | 'caution' | 'info' | 'danger' | 'expert'

export interface Toast {
  id: number
  title: string
  body?: string
  tone: ToastTone
}

let sequence = 0

export const useToastsStore = defineStore('toasts', {
  state: (): { items: Toast[] } => ({ items: [] }),
  actions: {
    push(title: string, body?: string, tone: ToastTone = 'info', timeoutMs = 6000): void {
      const id = ++sequence
      this.items.push({ id, title, body, tone })
      if (timeoutMs > 0) {
        setTimeout(() => {
          this.items = this.items.filter((item) => item.id !== id)
        }, timeoutMs)
      }
    },
    dismiss(id: number): void {
      this.items = this.items.filter((item) => item.id !== id)
    },
  },
})
