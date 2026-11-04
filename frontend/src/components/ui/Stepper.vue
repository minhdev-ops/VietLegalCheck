<script setup lang="ts">
import { Check } from '@lucide/vue'
import { cn } from '@/lib/cn'

interface Step {
  label: string
  short: string
}

const props = defineProps<{
  steps: Step[]
  current: number
  completed?: number
}>()

const emit = defineEmits<{ select: [index: number] }>()
</script>

<template>
  <ol class="flex flex-wrap items-center gap-y-3" role="list">
    <li
      v-for="(step, index) in props.steps"
      :key="index"
      class="flex items-center gap-3"
    >
      <button
        type="button"
        :class="cn(
          'flex items-center gap-2 rounded-pill border px-3 py-1.5 text-[13px] font-medium transition-colors',
          index < props.current
            ? 'border-ok/30 bg-ok/8 text-ok'
            : index === props.current
              ? 'border-accent bg-accent text-white'
              : 'border-line bg-surface text-ink-muted',
          index <= (props.completed ?? props.current) && 'hover:border-line-strong',
        )"
        :aria-current="index === props.current ? 'step' : undefined"
        :disabled="index > (props.completed ?? props.current)"
        @click="emit('select', index)"
      >
        <span
          :class="cn(
            'flex size-5 items-center justify-center rounded-full text-[11px] font-semibold tnum',
            index < props.current ? 'bg-ok text-white' : index === props.current ? 'bg-white/25' : 'bg-surface-3 text-ink-muted',
          )"
        >
          <Check v-if="index < props.current" class="size-3" aria-hidden="true" />
          <span v-else>{{ index + 1 }}</span>
        </span>
        <span class="hidden sm:inline">{{ step.label }}</span>
        <span class="sm:hidden">{{ step.short }}</span>
      </button>
      <span v-if="index < props.steps.length - 1" class="h-px w-5 bg-line-strong sm:w-8" aria-hidden="true" />
    </li>
  </ol>
</template>
