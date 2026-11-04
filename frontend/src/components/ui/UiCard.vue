<script setup lang="ts">
import { cn } from '@/lib/cn'

const props = withDefaults(defineProps<{
  title?: string
  description?: string
  padding?: 'none' | 'normal' | ' snug'
  bordered?: boolean
}>(), { padding: 'normal', bordered: true })
</script>

<template>
  <section
    :class="cn(
      'rounded-lg bg-surface',
      props.bordered && 'border border-line',
    )"
  >
    <header v-if="props.title || $slots.header || $slots.actions" class="flex items-start gap-4 px-5 pt-4">
      <div class="min-w-0 flex-1">
        <h3 v-if="props.title" class="text-[15px] font-semibold leading-tight tracking-[-0.005em] text-ink">
          {{ props.title }}
        </h3>
        <p v-if="props.description" class="mt-1 text-[13px] text-ink-muted">{{ props.description }}</p>
      </div>
      <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
        <slot name="actions" />
      </div>
    </header>
    <div v-if="$slots.header" class="px-5 pt-4">
      <slot name="header" />
    </div>
    <div :class="cn(props.padding === 'normal' && 'p-5', props.padding === 'none' && '')">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="border-t border-line px-5 py-3">
      <slot name="footer" />
    </footer>
  </section>
</template>
