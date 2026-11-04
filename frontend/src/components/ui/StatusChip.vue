<script setup lang="ts">
import { computed, type Component } from 'vue'
import {
  AlertTriangle,
  BadgeCheck,
  CircleHelp,
  Info,
  Minus,
  TriangleAlert,
} from '@lucide/vue'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/cn'

const chip = cva(
  'inline-flex items-center gap-1.5 rounded-pill px-2.5 py-0.5 text-[11.5px] font-semibold uppercase tracking-[0.06em] border',
  {
    variants: {
      tone: {
        neutral: 'bg-surface-2 text-ink-muted border-line',
        ok: 'bg-ok/10 text-ok border-ok/30',
        caution: 'bg-caution/15 text-caution border-caution/35',
        info: 'bg-info/10 text-info border-info/30',
        expert: 'bg-expert/10 text-expert border-expert/30',
        danger: 'bg-seal/10 text-seal border-seal/30',
      },
      plain: { true: 'border-transparent' },
    },
    defaultVariants: { tone: 'neutral' },
  },
)

type ChipVariants = VariantProps<typeof chip>

const props = withDefaults(defineProps<{
  tone?: ChipVariants['tone']
  icon?: Component | false
  label: string
  plain?: boolean
}>(), { plain: false })

const ICON_BY_TONE: Record<string, Component> = {
  neutral: Minus,
  ok: BadgeCheck,
  caution: AlertTriangle,
  info: Info,
  expert: CircleHelp,
  danger: TriangleAlert,
}

const resolvedIcon = computed<Component>(() => {
  if (props.icon === false) return Minus
  return props.icon ?? ICON_BY_TONE[(props.tone as string) ?? 'neutral'] ?? Minus
})

</script>

<template>
  <span :class="cn(chip({ tone: props.tone, plain: props.plain }))">
    <component :is="resolvedIcon" class="size-3.5" aria-hidden="true" />
    {{ label }}
  </span>
</template>
