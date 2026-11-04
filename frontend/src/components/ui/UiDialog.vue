<script setup lang="ts">
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { X } from '@lucide/vue'
import { cn } from '@/lib/cn'

const props = withDefaults(defineProps<{
  open: boolean
  title: string
  description?: string
  size?: 'sm' | 'md' | 'lg'
}>(), { size: 'md' })

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const WIDTH = { sm: 'sm:max-w-md', md: 'sm:max-w-xl', lg: 'sm:max-w-3xl' }
</script>

<template>
  <DialogRoot :open="props.open" @update:open="emit('update:open', $event)">
    <DialogPortal>
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <DialogOverlay class="fixed inset-0 z-50 bg-ink/35 backdrop-blur-[2px]" />
      </Transition>
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-2 scale-[0.985]"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0 translate-y-2 scale-[0.985]"
      >
        <DialogContent
          :class="cn(
            'fixed left-1/2 top-1/2 z-50 w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2',
            'rounded-lg border border-line bg-surface shadow-2 focus:outline-none',
            WIDTH[props.size],
          )"
        >
          <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
            <div class="min-w-0">
              <DialogTitle class="text-[15px] font-semibold text-ink">{{ props.title }}</DialogTitle>
              <DialogDescription v-if="props.description" class="mt-1 text-[13px] text-ink-muted">
                {{ props.description }}
              </DialogDescription>
            </div>
            <DialogClose class="rounded-md p-1.5 text-ink-muted hover:bg-surface-2 hover:text-ink" aria-label="Đóng">
              <X class="size-4" />
            </DialogClose>
          </div>
          <div class="max-h-[70vh] overflow-auto p-5">
            <slot />
          </div>
          <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-line px-5 py-3">
            <slot name="footer" />
          </footer>
        </DialogContent>
      </Transition>
    </DialogPortal>
  </DialogRoot>
</template>
