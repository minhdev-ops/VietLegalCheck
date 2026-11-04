<script setup lang="ts">
import { useSlots } from 'vue'
import { cn } from '@/lib/cn'

/**
 * Tiêu đề trang của khu vực Operate: câu hỏi người dùng tự trả lời được
 * (Where am I? What's here?) theo DESIGN.md — không eyebrow, không số mục.
 */
const props = withDefaults(defineProps<{
  title: string
  subtitle?: string
  actions?: boolean
  class?: string
}>(), {})

const slots = useSlots()
</script>

<template>
  <div :class="cn('mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-3', props.class)">
    <div class="min-w-0">
      <h1 class="text-[clamp(1.375rem,2.5vw,1.75rem)] font-semibold leading-tight tracking-[-0.02em] text-ink">
        {{ props.title }}
      </h1>
      <p v-if="props.subtitle" class="mt-1.5 max-w-prose text-[13.5px] text-ink-muted">{{ props.subtitle }}</p>
    </div>
    <div v-if="slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>
