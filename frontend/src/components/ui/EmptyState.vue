<script setup lang="ts">
import { type Component } from 'vue'
import { cn } from '@/lib/cn'

const props = withDefaults(defineProps<{
  icon?: Component
  title: string
  body?: string
  actionLabel?: string
  secondaryLabel?: string
  class?: string
}>(), {})

const emit = defineEmits<{ action: []; secondary: [] }>()
</script>

<template>
  <div :class="cn('flex flex-col items-center gap-3 rounded-lg border border-dashed border-line-strong bg-surface-2/60 px-6 py-10 text-center', props.class)">
    <div v-if="props.icon" class="flex size-11 items-center justify-center rounded-full bg-surface-3 text-ink-muted">
      <component :is="props.icon" class="size-5" aria-hidden="true" />
    </div>
    <h4 class="text-[15px] font-semibold text-ink">{{ props.title }}</h4>
    <p v-if="props.body" class="measure text-[13.5px] text-ink-muted">{{ props.body }}</p>
    <div v-if="props.actionLabel || props.secondaryLabel" class="mt-2 flex flex-wrap items-center justify-center gap-2">
      <slot name="action">
        <button
          v-if="props.actionLabel"
          class="h-9 rounded-md bg-accent px-4 text-sm font-medium text-white transition-colors hover:bg-accent-strong"
          @click="emit('action')"
        >
          {{ props.actionLabel }}
        </button>
      </slot>
      <button
        v-if="props.secondaryLabel"
        class="h-9 rounded-md border border-line-strong px-4 text-sm font-medium text-ink transition-colors hover:bg-surface-2"
        @click="emit('secondary')"
      >
        {{ props.secondaryLabel }}
      </button>
    </div>
  </div>
</template>
