<script setup lang="ts">
import { cn } from '@/lib/cn'

interface Option {
  value: string | number
  label: string
}

const props = defineProps<{
  modelValue?: string | number | null
  options: Option[]
  label?: string
  hint?: string
  required?: boolean
  placeholder?: string
  wrapperClass?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

function onChange(event: Event): void {
  emit('update:modelValue', (event.target as HTMLSelectElement).value)
}
</script>

<template>
  <div :class="cn('flex flex-col gap-1.5', props.wrapperClass)">
    <label v-if="props.label" class="text-[13px] font-medium text-ink" :class="{ required: props.required }">
      {{ props.label }}
    </label>
    <select
      :value="props.modelValue ?? ''"
      @change="onChange"
      :required="props.required"
      :class="cn(
        'h-9 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink',
        'focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20',
      )"
    >
      <option v-if="props.placeholder" value="">{{ props.placeholder }}</option>
      <option v-for="option in props.options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
    <p v-if="props.hint" class="text-xs text-ink-muted">{{ props.hint }}</p>
  </div>
</template>

<style scoped>
label.required::after {
  content: ' *';
  color: var(--seal);
}
</style>
