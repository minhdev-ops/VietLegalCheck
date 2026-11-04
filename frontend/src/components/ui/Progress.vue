<script setup lang="ts">
import { cn } from '@/lib/cn'

const props = withDefaults(defineProps<{
  value: number
  max?: number
  label?: string
  class?: string
}>(), { max: 100 })
</script>

<template>
  <div :class="cn('flex flex-col gap-1.5', props.class)">
    <div v-if="props.label" class="flex justify-between text-[11.5px] font-medium text-ink-muted">
      <span>{{ props.label }}</span>
      <span class="tnum">{{ Math.round((props.value / props.max) * 100) }}%</span>
    </div>
    <div class="h-1.5 w-full overflow-hidden rounded-pill bg-surface-3" role="progressbar" :aria-valuenow="props.value" :aria-valuemax="props.max">
      <div
        class="h-full rounded-pill bg-accent transition-[width] duration-300 ease-out"
        :style="{ width: `${Math.min(100, (props.value / props.max) * 100)}%` }"
      />
    </div>
  </div>
</template>
