<script setup lang="ts">
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import { cn } from '@/lib/cn'

const props = withDefaults(defineProps<{
  text: string
  side?: 'top' | 'bottom' | 'left' | 'right'
}>(), { side: 'top' })
</script>

<template>
  <TooltipRoot>
    <TooltipTrigger as-child>
      <slot />
    </TooltipTrigger>
    <TooltipPortal>
      <TooltipContent
        :side="props.side"
        :class="cn(
          'z-[70] max-w-xs rounded-md border border-line-strong bg-ink px-2.5 py-1.5 text-[12px] text-paper shadow-2',
          'data-[state=delayed-open]:animate-in data-[state=delayed-open]:fade-in-0',
        )"
      >
        {{ props.text }}
        <TooltipArrow class="fill-ink" />
      </TooltipContent>
    </TooltipPortal>
  </TooltipRoot>
</template>
