<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { X } from '@lucide/vue'
import { cn } from '@/lib/cn'
import { spring } from '@/lib/motion'

/**
 * Drawer về một bên (SRS 9: vào/trả cùng đường). Vuốt để đóng, spring
 * damping 0.8 / response 0.3 cho drawer (apple-design), có rubber-band ở mép.
 */
const props = withDefaults(defineProps<{
  open: boolean
  title: string
  side?: 'right' | 'bottom'
  width?: number
}>(), { side: 'right' })

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const sheet = ref<HTMLElement | null>(null)
const scrim = ref<HTMLElement | null>(null)
const offset = ref(0)
const dragging = ref(false)

let animation = spring(0, {
  duration: 0.3,
  bounce: 0.18,
  onFrame: (value) => {
    offset.value = value
    if (scrim.value) {
      scrim.value.style.opacity = String(1 - Math.abs(value) / distance())
    }
  },
})

function distance(): number {
  return props.side === 'right' ? (props.width ?? 480) : 320
}

watch(
  () => props.open,
  (open) => {
    if (open) {
      offset.value = 0
      animation.target(0, { duration: 0.3, bounce: 0.18 })
    }
  },
)

onBeforeUnmount(() => animation.stop())

function onPointerDown(event: PointerEvent): void {
  if (event.button !== 0) return
  dragging.value = true
  ;(event.target as HTMLElement).setPointerCapture(event.pointerId)
}

function onPointerMove(event: PointerEvent): void {
  if (!dragging.value || !sheet.value) return
  const bounds = sheet.value.getBoundingClientRect()
  const delta = props.side === 'right' ? event.clientX - bounds.left : event.clientY - bounds.top
  const dim = props.side === 'right' ? bounds.width : bounds.height
  // Vượt quá mép thì kháng lại dần (rubber-band), không ngắt cứng.
  offset.value = delta > 0 ? delta : (delta * dim * 0.55) / (dim + 0.55 * Math.abs(delta))
  if (scrim.value) scrim.value.style.opacity = String(1 - Math.abs(offset.value) / dim)
}

function onPointerUp(): void {
  if (!dragging.value) return
  dragging.value = false
  if (Math.abs(offset.value) > distance() * 0.4) {
    emit('update:open', false)
  } else {
    animation.target(0, { duration: 0.3, bounce: 0.18 })
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="props.open" class="fixed inset-0 z-50" role="dialog" aria-modal="true" :aria-label="props.title">
      <div
        ref="scrim"
        class="absolute inset-0 bg-ink/35"
        style="backdrop-filter: blur(2px)"
        @click="emit('update:open', false)"
      />
      <div
        ref="sheet"
        :class="cn(
          'absolute bg-surface shadow-2',
          props.side === 'right'
            ? 'inset-y-0 right-0 h-full border-l border-line'
            : 'inset-x-0 bottom-0 max-h-[85vh] border-t border-line',
        )"
        :style="props.side === 'right'
          ? { width: `min(${props.width ?? 480}px, 100vw)`, transform: `translateX(${offset}px)` }
          : { transform: `translateY(${offset}px)` }"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
      >
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
          <h2 class="text-[15px] font-semibold text-ink">{{ props.title }}</h2>
          <button
            class="rounded-md p-1.5 text-ink-muted hover:bg-surface-2 hover:text-ink"
            aria-label="Đóng"
            @click="emit('update:open', false)"
          >
            <X class="size-4" aria-hidden="true" />
          </button>
        </div>
        <div class="h-[calc(100%-61px)] overflow-auto p-5">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>
