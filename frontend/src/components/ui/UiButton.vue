<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority'
import { Loader2 } from '@lucide/vue'
import { cn } from '@/lib/cn'

const button = cva(
  [
    'relative inline-flex select-none items-center justify-center gap-2 whitespace-nowrap font-medium',
    'transition-[transform,background-color,color,border-color] duration-150',
    'active:scale-[0.98]',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-paper',
    'disabled:pointer-events-none disabled:opacity-50',
  ],
  {
    variants: {
      variant: {
        primary: 'bg-accent text-white hover:bg-accent-strong rounded-md',
        secondary: 'bg-surface text-ink border border-line-strong hover:bg-surface-2 rounded-md',
        outline: 'bg-transparent text-ink border border-line-strong hover:bg-surface-2 rounded-md',
        ghost: 'bg-transparent text-ink hover:bg-surface-2 rounded-md',
        danger: 'bg-seal text-white hover:brightness-95 rounded-md',
        quiet: 'bg-transparent text-ink-muted hover:text-ink hover:bg-surface-2 rounded-md',
      },
      size: {
        sm: 'h-8 px-3 text-[13px]',
        md: 'h-9 px-4 text-sm',
        lg: 'h-10 px-5 text-sm',
        icon: 'h-9 w-9',
      },
      block: { true: 'flex w-full' },
    },
    defaultVariants: { variant: 'primary', size: 'md' },
  },
)

type ButtonVariants = VariantProps<typeof button>

const props = withDefaults(defineProps<{
  variant?: ButtonVariants['variant']
  size?: ButtonVariants['size']
  block?: boolean
  loading?: boolean
  disabled?: boolean
  type?: 'button' | 'submit' | 'reset'
}>(), { type: 'button' })
</script>

<template>
  <button
    :type="props.type"
    :disabled="props.disabled || props.loading"
    :class="cn(button({ variant: props.variant, size: props.size, block: props.block }))"
  >
    <Loader2 v-if="props.loading" class="size-4 animate-spin" aria-hidden="true" />
    <slot />
  </button>
</template>
