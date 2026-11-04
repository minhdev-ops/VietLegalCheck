<script setup lang="ts">
import { cn } from '@/lib/cn'

const props = withDefaults(defineProps<{
  lines?: number
  class?: string
}>(), { lines: 3 })
</script>

<template>
  <div :class="cn('flex flex-col gap-2', props.class)" aria-hidden="true">
    <div
      v-for="index in props.lines"
      :key="index"
      class="h-3.5 rounded-sm bg-surface-3"
      :style="{ width: `${[100, 88, 72, 60][Math.min(index, 4) - 1]}%` }"
    />
  </div>
</template>
