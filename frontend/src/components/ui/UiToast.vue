<script setup lang="ts">
import { CheckCircle2, Info, TriangleAlert, X, XCircle } from '@lucide/vue'
import { useToastsStore, type ToastTone } from '@/stores/toasts'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/cn'

const toast = cva('rounded-md border p-3.5 shadow-2', {
  variants: {
    tone: {
      ok: 'bg-ok/8 border-ok/30',
      caution: 'bg-caution/10 border-caution/35',
      info: 'bg-info/8 border-info/25',
      danger: 'bg-seal/8 border-seal/30',
      expert: 'bg-expert/8 border-expert/25',
    },
  },
})

type ToastVariants = VariantProps<typeof toast>

const ICON: Record<ToastTone, typeof Info> = {
  ok: CheckCircle2,
  caution: TriangleAlert,
  info: Info,
  danger: XCircle,
  expert: Info,
}

const store = useToastsStore()
</script>

<template>
  <Teleport to="body">
    <div
      v-if="store.items.length"
      class="fixed bottom-5 right-5 z-[60] flex w-[min(390px,calc(100vw-2.5rem))] flex-col gap-2"
      role="region"
      aria-live="polite"
      aria-label="Thông báo"
    >
      <div
        v-for="item in store.items"
        :key="item.id"
        :class="cn(toast({ tone: item.tone as ToastVariants['tone'] }))"
      >
        <div class="flex items-start gap-3">
          <component :is="ICON[item.tone]" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <div class="min-w-0 flex-1">
            <p class="text-[13.5px] font-semibold text-ink">{{ item.title }}</p>
            <p v-if="item.body" class="mt-0.5 text-[12.5px] text-ink-muted">{{ item.body }}</p>
          </div>
          <button
            class="shrink-0 rounded p-0.5 text-ink-faint hover:text-ink"
            aria-label="Bỏ qua"
            @click="store.dismiss(item.id)"
          >
            <X class="size-3.5" />
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
