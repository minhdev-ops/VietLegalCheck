<script setup lang="ts">
import { cn } from '@/lib/cn'

const props = defineProps<{
  modelValue?: string | null
  label?: string
  hint?: string
  error?: string
  required?: boolean
  rows?: number
  wrapperClass?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

function onInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
}
</script>

<template>
  <div :class="cn('flex flex-col gap-1.5', props.wrapperClass)">
    <label v-if="props.label" class="text-[13px] font-medium text-ink" :class="{ required: props.required }">
      {{ props.label }}
    </label>
    <textarea
      :value="props.modelValue ?? ''"
      @input="onInput"
      :rows="props.rows ?? 4"
      :required="props.required"
      :aria-invalid="props.error ? 'true' : undefined"
      :class="cn(
        'w-full rounded-md border border-line-strong bg-surface p-3 text-sm text-ink leading-relaxed',
        'placeholder:text-ink-faint',
        'focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20',
        props.error && 'border-seal',
      )"
    />
    <p v-if="props.hint && !props.error" class="text-xs text-ink-muted">{{ props.hint }}</p>
    <p v-if="props.error" class="text-xs text-seal" role="alert">{{ props.error }}</p>
  </div>
</template>

<style scoped>
label.required::after {
  content: ' *';
  color: var(--seal);
}
</style>
