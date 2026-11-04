<script setup lang="ts">
import { computed } from 'vue'
import { AlertTriangle, Info, ShieldAlert, Sparkles } from '@lucide/vue'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/cn'

const callout = cva(
  'flex gap-3 rounded-lg border p-4 text-sm',
  {
    variants: {
      tone: {
        neutral: 'bg-surface-2 border-line text-ink',
        info: 'bg-info/5 border-info/25 text-ink',
        caution: 'bg-caution/8 border-caution/30 text-ink',
        danger: 'bg-seal/5 border-seal/25 text-ink',
        ok: 'bg-ok/5 border-ok/25 text-ink',
        expert: 'bg-expert/5 border-expert/25 text-ink',
      },
    },
    defaultVariants: { tone: 'neutral' },
  },
)

type CalloutVariants = VariantProps<typeof callout>

const props = withDefaults(defineProps<{
  tone?: CalloutVariants['tone']
  title: string
  icon?: 'info' | 'warning' | 'ai' | 'expert' | 'none'
  actionLabel?: string
}>(), { icon: 'info' })

const emit = defineEmits<{ action: [] }>()

const ICON = { info: Info, warning: AlertTriangle, expert: ShieldAlert, ai: Sparkles, none: Info }
const iconComponent = computed(() => ICON[props.icon])
const showIcon = computed(() => props.icon !== 'none')
</script>

<template>
  <div :class="cn(callout({ tone: props.tone }))" role="note">
    <component :is="iconComponent" v-if="showIcon" class="mt-0.5 size-4 shrink-0 text-ink-muted" aria-hidden="true" />
    <div class="flex-1">
      <p class="font-semibold">{{ props.title }}</p>
      <div v-if="$slots.default" class="mt-1 text-ink-muted">
        <slot />
      </div>
    </div>
    <button
      v-if="props.actionLabel"
      @click="emit('action')"
      class="shrink-0 text-[13px] font-semibold text-accent hover:underline underline-offset-2"
    >
      {{ props.actionLabel }}
    </button>
  </div>
</template>
