<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue'

const props = defineProps<{
  page: number
  lastPage: number
  total?: number
}>()

const emit = defineEmits<{ change: [page: number] }>()

function go(next: number): void {
  const target = Math.min(Math.max(1, next), Math.max(1, props.lastPage))
  if (target !== props.page) emit('change', target)
}
</script>

<template>
  <div class="flex items-center justify-between gap-4 border-t border-line py-3 text-[13px] text-ink-muted">
    <span class="tnum">{{ props.total !== undefined ? `Tổng ${props.total} bản ghi` : '' }}</span>
    <div class="flex items-center gap-1">
      <button
        class="flex size-8 items-center justify-center rounded-md border border-line text-ink disabled:opacity-40 enabled:hover:bg-surface-2"
        :disabled="props.page <= 1"
        aria-label="Trang trước"
        @click="go(props.page - 1)"
      >
        <ChevronLeft class="size-4" />
      </button>
      <span class="tnum px-2">Trang {{ props.page }} / {{ Math.max(1, props.lastPage) }}</span>
      <button
        class="flex size-8 items-center justify-center rounded-md border border-line text-ink disabled:opacity-40 enabled:hover:bg-surface-2"
        :disabled="props.page >= props.lastPage"
        aria-label="Trang sau"
        @click="go(props.page + 1)"
      >
        <ChevronRight class="size-4" />
      </button>
    </div>
  </div>
</template>
